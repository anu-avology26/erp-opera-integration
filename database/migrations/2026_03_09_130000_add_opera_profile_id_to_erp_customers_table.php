<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('erp_customers', function (Blueprint $table) {
            if (! Schema::hasColumn('erp_customers', 'opera_profile_id')) {
                $table->string('opera_profile_id')->nullable()->after('credit_limit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('erp_customers', function (Blueprint $table) {
            if (Schema::hasColumn('erp_customers', 'opera_profile_id')) {
                $table->dropColumn('opera_profile_id');
            }
        });
    }
};
