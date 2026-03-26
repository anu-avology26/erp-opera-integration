<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payload_audit', function (Blueprint $table) {
            $table->id();
            $table->string('direction', 32);
            $table->string('entity_ref', 128)->nullable();
            $table->string('status', 32)->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->text('payload_encrypted')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sync_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sync_log_id')->nullable()->constrained('sync_logs')->nullOnDelete();
            $table->string('erp_number', 64);
            $table->string('opera_account_number', 64)->nullable();
            $table->text('error_message');
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('retried_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_failures');
        Schema::dropIfExists('payload_audit');
    }
};
