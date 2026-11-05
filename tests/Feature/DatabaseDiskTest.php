<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->disk = Storage::disk('database');
});

it('gives back exactly the bytes it was given', function () {
    $bytes = "%PDF-1.7\0\xFF\xFE binary \0 data";

    $this->disk->put('invoices/a.pdf', $bytes);

    expect($this->disk->get('invoices/a.pdf'))->toBe($bytes)
        ->and($this->disk->exists('invoices/a.pdf'))->toBeTrue()
        ->and($this->disk->size('invoices/a.pdf'))->toBe(strlen($bytes));
});

it('stores an upload the way the upload form does', function () {
    $file = new UploadedFile(base_path('samples/01-sinar-kemas-abadi.pdf'), 'a.pdf', 'application/pdf', test: true);

    $path = $file->store('invoices', 'database');

    expect($path)->toStartWith('invoices/')->toEndWith('.pdf')
        ->and($this->disk->get($path))->toBe(file_get_contents(base_path('samples/01-sinar-kemas-abadi.pdf')));
});

it('overwrites and deletes', function () {
    $this->disk->put('exports/a.csv', 'one');
    $this->disk->put('exports/a.csv', 'two');

    expect($this->disk->get('exports/a.csv'))->toBe('two');

    $this->disk->delete('exports/a.csv');

    expect($this->disk->exists('exports/a.csv'))->toBeFalse()
        ->and($this->disk->get('exports/a.csv'))->toBeNull();
});

it('serves a file as a response', function () {
    $this->disk->put('invoices/a.pdf', 'pdf bytes');

    $response = $this->disk->response('invoices/a.pdf', 'a.pdf', ['Content-Type' => 'application/pdf'], 'inline');

    ob_start();
    $response->sendContent();

    expect(ob_get_clean())->toBe('pdf bytes')
        ->and($response->headers->get('Content-Length'))->toBe('9');
});

it('lists what is in a directory, with or without what is below it', function () {
    $this->disk->put('invoices/a.pdf', 'a');
    $this->disk->put('invoices/deep/b.pdf', 'b');
    $this->disk->put('exports/c.csv', 'c');

    expect($this->disk->files('invoices'))->toBe(['invoices/a.pdf'])
        ->and($this->disk->allFiles('invoices'))->toBe(['invoices/a.pdf', 'invoices/deep/b.pdf']);
});

it('moves and copies', function () {
    $this->disk->put('a.txt', 'a');

    $this->disk->copy('a.txt', 'b.txt');
    $this->disk->move('a.txt', 'c.txt');

    expect($this->disk->exists('a.txt'))->toBeFalse()
        ->and($this->disk->get('b.txt'))->toBe('a')
        ->and($this->disk->get('c.txt'))->toBe('a');
});
