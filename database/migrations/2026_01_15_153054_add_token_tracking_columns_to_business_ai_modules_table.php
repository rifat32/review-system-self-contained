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
        Schema::table('business_ai_modules', function (Blueprint $table) {
            if (!Schema::hasColumn('business_ai_modules', 'total_tokens_used')) {
                $table->unsignedBigInteger('total_tokens_used')->default(0)->after('business_id');
            }
            if (!Schema::hasColumn('business_ai_modules', 'total_cost_usd')) {
                $table->decimal('total_cost_usd', 12, 6)->default(0)->after('total_tokens_used');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_ai_modules', function (Blueprint $table) {
            $table->dropColumn(['total_tokens_used', 'total_cost_usd']);
        });
    }
};
