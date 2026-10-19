<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Processing = 'processing';
    case NeedsReview = 'needs_review';
    case Approved = 'approved';
    case Exported = 'exported';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Processing => 'Processing',
            self::NeedsReview => 'Needs review',
            self::Approved => 'Approved',
            self::Exported => 'Exported',
            self::Failed => 'Failed',
        };
    }
}
