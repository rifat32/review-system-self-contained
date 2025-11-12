<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStaffIdToReviewNewsTable extends Migration
{
     public function up(): void
    {
        Schema::table('review_news', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('is_overall');

            // Optional: if staff_id references users or staff table
            // $table->foreign('staff_id')->references('id')->on('staff')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('review_news', function (Blueprint $table) {

            $table->dropColumn('staff_id');
        });
    }




}
