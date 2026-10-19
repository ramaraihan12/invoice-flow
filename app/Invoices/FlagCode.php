<?php

namespace App\Invoices;

enum FlagCode: string
{
    case MissingInvoiceNumber = 'missing_invoice_number';
    case DuplicateInvoiceNumber = 'duplicate_invoice_number';
    case InvalidNpwp = 'invalid_npwp';
    case LineItemsMismatch = 'line_items_mismatch';
    case TaxMismatch = 'tax_mismatch';
    case TotalMismatch = 'total_mismatch';
}
