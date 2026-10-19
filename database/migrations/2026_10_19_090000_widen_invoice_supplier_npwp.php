<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The model sometimes returns more than an NPWP (the 22-digit NITKU printed
// next to it, for one). Keep what it said so the checker can flag it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('supplier_npwp', 32)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('supplier_npwp', 16)->nullable()->change();
        });
    }
};
