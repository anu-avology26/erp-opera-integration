<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('erp_customers', 'status')) {
                $table->string('status')->nullable()->after('erp_number');
            }
            if (! Schema::hasColumn('erp_customers', 'account_type')) {
                $table->string('account_type')->nullable()->after('name');
            }
            if (! Schema::hasColumn('erp_customers', 'active')) {
                $table->boolean('active')->default(true)->after('account_type');
            }
            if (! Schema::hasColumn('erp_customers', 'blocked')) {
                $table->boolean('blocked')->default(false)->after('active');
            }
            if (! Schema::hasColumn('erp_customers', 'ar_number')) {
                $table->string('ar_number')->nullable()->after('blocked');
            }
            if (! Schema::hasColumn('erp_customers', 'address_1')) {
                $table->string('address_1')->nullable()->after('ar_number');
            }
            if (! Schema::hasColumn('erp_customers', 'address_2')) {
                $table->string('address_2')->nullable()->after('address_1');
            }
            if (! Schema::hasColumn('erp_customers', 'vat_registration_no')) {
                $table->string('vat_registration_no')->nullable()->after('address_2');
            }
            if (! Schema::hasColumn('erp_customers', 'phone')) {
                $table->string('phone')->nullable()->after('vat_registration_no');
            }
            if (! Schema::hasColumn('erp_customers', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
            if (! Schema::hasColumn('erp_customers', 'country')) {
                $table->string('country', 10)->nullable()->after('email');
            }
            if (! Schema::hasColumn('erp_customers', 'post_code')) {
                $table->string('post_code')->nullable()->after('country');
            }
            if (! Schema::hasColumn('erp_customers', 'payment_terms_code')) {
                $table->string('payment_terms_code')->nullable()->after('post_code');
            }
            if (! Schema::hasColumn('erp_customers', 'property')) {
                $table->string('property')->nullable()->after('payment_terms_code');
            }
            if (! Schema::hasColumn('erp_customers', 'catalog_code')) {
                $table->string('catalog_code')->nullable()->after('property');
            }
            if (! Schema::hasColumn('erp_customers', 'system_modified_at')) {
                $table->timestamp('system_modified_at')->nullable()->after('catalog_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('erp_customers', function (Blueprint $table) {
            $drop = [
                'status',
                'account_type',
                'active',
                'blocked',
                'ar_number',
                'address_1',
                'address_2',
                'vat_registration_no',
                'phone',
                'email',
                'country',
                'post_code',
                'payment_terms_code',
                'property',
                'catalog_code',
                'system_modified_at',
            ];

            foreach ($drop as $column) {
                if (Schema::hasColumn('erp_customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

