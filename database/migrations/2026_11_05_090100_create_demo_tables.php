<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_quota_hits', function (Blueprint $table) {
            $table->string('bucket')->primary();
            $table->unsignedInteger('hits');
            $table->unsignedBigInteger('resets_at');
        });

        Schema::create('demo_webhook_receipts', function (Blueprint $table) {
            $table->id();
            $table->uuid('delivery_uuid')->unique();
            $table->string('event', 50);
            $table->string('signature');
            $table->unsignedBigInteger('signed_at');
            $table->longText('body');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_webhook_receipts');
        Schema::dropIfExists('demo_quota_hits');
    }
};
