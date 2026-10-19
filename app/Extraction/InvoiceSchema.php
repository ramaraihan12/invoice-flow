<?php

namespace App\Extraction;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Every field is required but nullable. OpenAI's strict mode insists on that,
 * and it stops smaller models from quietly dropping keys they aren't sure about.
 */
final class InvoiceSchema
{
    public static function make(): ObjectSchema
    {
        $lineItem = new ObjectSchema(
            name: 'line_item',
            description: 'One row of the item table',
            properties: [
                new StringSchema('description', 'Item or service name as printed', nullable: true),
                new NumberSchema('quantity', 'Quantity as printed', nullable: true),
                new NumberSchema('unit_price', 'Price per unit in rupiah', nullable: true),
                new NumberSchema('amount', 'Line total in rupiah as printed', nullable: true),
            ],
            requiredFields: ['description', 'quantity', 'unit_price', 'amount'],
        );

        return new ObjectSchema(
            name: 'invoice',
            description: 'Data printed on a supplier invoice',
            properties: [
                new StringSchema('supplier_name', 'Company that issued the invoice (the seller)', nullable: true),
                new StringSchema('supplier_npwp', 'Seller NPWP tax ID, digits only', nullable: true),
                new StringSchema('invoice_number', 'Invoice number exactly as printed', nullable: true),
                new StringSchema('invoice_date', 'Invoice date, YYYY-MM-DD', nullable: true),
                new StringSchema('due_date', 'Payment due date (jatuh tempo), YYYY-MM-DD', nullable: true),
                new StringSchema('currency', 'ISO currency code, usually IDR', nullable: true),
                new ArraySchema('line_items', 'Item rows in printed order', $lineItem),
                new NumberSchema('subtotal', 'Subtotal before tax in rupiah', nullable: true),
                new NumberSchema('tax_base', 'DPP / Dasar Pengenaan Pajak in rupiah, only if printed', nullable: true),
                new NumberSchema('tax_rate', 'PPN rate in percent, e.g. 11 or 12', nullable: true),
                new NumberSchema('tax_amount', 'PPN amount in rupiah', nullable: true),
                new NumberSchema('total', 'Grand total in rupiah', nullable: true),
            ],
            requiredFields: [
                'supplier_name', 'supplier_npwp', 'invoice_number', 'invoice_date', 'due_date', 'currency',
                'line_items', 'subtotal', 'tax_base', 'tax_rate', 'tax_amount', 'total',
            ],
        );
    }
}
