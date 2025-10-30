<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class InsertDefaultStars extends Migration
{
     /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert values 1 to 5 if they don't already exist
        for ($i = 1; $i <= 5; $i++) {
            $exists = DB::table('stars')->where('value', $i)->exists();

            if (!$exists) {
                DB::table('stars')->insert([
                    'value' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('stars')->whereIn('value', [1, 2, 3, 4, 5])->delete();
    }
}
