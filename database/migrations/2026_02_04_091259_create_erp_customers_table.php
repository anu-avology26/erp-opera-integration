<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('erp_customers', function (Blueprint $table) {
            $table->id();
            $table->string('erp_number')->unique();
            $table->string('name');
            $table->text('payload'); // encrypted JSON
            $table->boolean('has_credit')->default(false);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->string('opera_account_number')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('erp_customers');
    }
};
