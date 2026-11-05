<?php

namespace App\Filesystem;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * A disk that keeps files in a table, for hosts where the local disk is gone by
 * the next request. Meant for small files: the whole file goes through memory,
 * and base64 in a text column costs a third more space but avoids the stream
 * handling PDO needs for bytea.
 */
class DatabaseAdapter implements FilesystemAdapter
{
    private const TABLE = 'stored_files';

    public function fileExists(string $path): bool
    {
        return DB::table(self::TABLE)->where('path', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return false;
    }

    public function write(string $path, string $contents, Config $config): void
    {
        DB::table(self::TABLE)->updateOrInsert(['path' => $path], [
            'contents' => base64_encode($contents),
            'size' => strlen($contents),
            'created_at' => now(),
        ]);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, (string) stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        $encoded = DB::table(self::TABLE)->where('path', $path)->value('contents');

        if ($encoded === null) {
            throw UnableToReadFile::fromLocation($path, 'No such file.');
        }

        return (string) base64_decode($encoded);
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        DB::table(self::TABLE)->where('path', $path)->delete();
    }

    public function deleteDirectory(string $path): void
    {
        DB::table(self::TABLE)->where('path', 'like', rtrim($path, '/').'/%')->delete();
    }

    /**
     * Directories only exist as part of a path here.
     */
    public function createDirectory(string $path, Config $config): void {}

    public function setVisibility(string $path, string $visibility): void {}

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        $mimeType = (new FinfoMimeTypeDetector)->detectMimeType($path, $this->read($path));

        return new FileAttributes($path, mimeType: $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, lastModified: Carbon::parse($this->row($path)->created_at)->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, fileSize: (int) $this->row($path)->size);
    }

    /**
     * @return iterable<FileAttributes|DirectoryAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $path === '' ? '' : rtrim($path, '/').'/';

        $rows = DB::table(self::TABLE)->where('path', 'like', $prefix.'%')->orderBy('path')->get(['path', 'size']);

        foreach ($rows as $row) {
            if ($deep || ! str_contains(substr($row->path, strlen($prefix)), '/')) {
                yield new FileAttributes($row->path, (int) $row->size);
            }
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }

    private function row(string $path): object
    {
        return DB::table(self::TABLE)->where('path', $path)->first(['size', 'created_at'])
            ?? throw UnableToRetrieveMetadata::create($path, 'metadata', 'No such file.');
    }
}
