<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddColumnsToNotificationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('business_id');
            $table->timestamp('read_at')->nullable()->after('status');
            $table->string('link')->nullable()->after('read_at');
            $table->enum('priority', ['low', 'normal', 'high',])->default('normal')->after('link');
            $table->enum("status", ['read', 'unread'])->default("unread")->nullable()->change();

            $table->unsignedBigInteger("entity_id");
            $table->json("entity_ids")->nullable();
            // 
            $table->foreign('business_id')->references('id')->on('businesses')->onDelete('cascade');
            $table->foreign('receiver_id')->references('id')->on('users')->onDelete('cascade');

            // Add indexes for better query performance
            $table->index('receiver_id');
            $table->index('business_id');
            $table->index('type');
            $table->index('status');
            $table->index('read_at');
            $table->index('entity_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop indexes first
            $table->dropIndex(['receiver_id']);
            $table->dropIndex(['business_id']);
            $table->dropIndex(['type']);
            $table->dropIndex(['status']);
            $table->dropIndex(['read_at']);
            $table->dropIndex(['entity_id']);
            // Drop columns
            $table->dropColumn([
                'type',
                'read_at',
                'link',
                'priority',
                'icon',
                'entity_id',
                'entity_ids',
            ]);
        });
    }
}
