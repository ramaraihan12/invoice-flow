<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->index();

            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 100);
            $table->longText('source_text')->nullable();

            $table->string('supplier_name')->nullable();
            $table->string('supplier_npwp', 16)->nullable();
            $table->string('invoice_number')->nullable();
            $table->date('invoice_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('IDR');

            // Rupiah has no minor unit in practice, so amounts are whole numbers.
            // json rather than jsonb: nothing queries inside these columns, and
            // jsonb would reorder the keys of every line item in the webhook payload.
            $table->json('line_items')->nullable();
            $table->bigInteger('subtotal')->nullable();
            $table->bigInteger('tax_base')->nullable();
            $table->decimal('tax_rate', 5, 2)->nullable();
            $table->bigInteger('tax_amount')->nullable();
            $table->bigInteger('total')->nullable();

            $table->json('flags')->nullable();
            $table->json('raw_extraction')->nullable();
            $table->string('extracted_with')->nullable();
            $table->text('error')->nullable();

            $table->timestamp('extracted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('exported_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_npwp', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
