<?php

namespace App\Extraction;

use Prism\Prism\ValueObjects\Media\Image;

/**
 * What we hand to the model: the text layer of a PDF, or a photo.
 */
final readonly class InvoiceDocument
{
    private function __construct(
        public ?string $text,
        public ?Image $image,
    ) {}

    public static function fromText(string $text): self
    {
        return new self($text, null);
    }

    public static function fromImage(Image $image): self
    {
        return new self(null, $image);
    }
}
