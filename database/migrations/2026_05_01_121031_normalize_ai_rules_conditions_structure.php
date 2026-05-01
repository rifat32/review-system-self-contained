<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Standardize all AI Rule conditions to the nested {logic, conditions} format.
     */
    public function up(): void
    {
        DB::table('ai_rules')->get()->each(function ($rule) {
            $conditions = json_decode($rule->conditions, true);
            
            // Check if it's a flat array (doesn't have the 'logic' key)
            if ($conditions && !isset($conditions['logic'])) {
                DB::table('ai_rules')
                    ->where('id', $rule->id)
                    ->update([
                        'conditions' => json_encode([
                            'logic' => 'AND',
                            'conditions' => $conditions
                        ])
                    ]);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('ai_rules')->get()->each(function ($rule) {
            $conditions = json_decode($rule->conditions, true);
            
            // If it was nested by this migration, flatten it back
            if ($conditions && isset($conditions['logic']) && isset($conditions['conditions'])) {
                DB::table('ai_rules')
                    ->where('id', $rule->id)
                    ->update([
                        'conditions' => json_encode($conditions['conditions'])
                    ]);
            }
        });
    }
};
