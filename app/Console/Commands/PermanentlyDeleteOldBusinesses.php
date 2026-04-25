<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\User;
use App\Models\Recommendation;
use App\Models\AiRule;
use App\Models\InsightRecord;
use App\Models\OpenAITokenUsage;
use App\Models\Leaflet;
use App\Models\DailyView;
use App\Models\ReviewNew;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PermanentlyDeleteOldBusinesses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'businesses:delete-permanently';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Permanently delete businesses that were soft-deleted more than 15 days ago';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $cutoff_date = Carbon::now()->subDays(15);

        $businesses_to_delete = Business::onlyTrashed()
            ->whereDate('deleted_at', '<=', $cutoff_date)
            ->get();

        $count = $businesses_to_delete->count();

        if ($count === 0) {
            $this->info('No businesses to delete.');
            return 0;
        }

        foreach ($businesses_to_delete as $business) {
            DB::transaction(function () use ($business) {
                $businessId = $business->id;
                $ownerId = $business->OwnerID;

                // Disable foreign key checks for this transaction to handle complex dependencies
                DB::statement("SET FOREIGN_KEY_CHECKS=0;");

                // 1. Delete CHILD tables and related Review-specific data
                Recommendation::where("business_id", $businessId)->delete();
                AiRule::where("business_id", $businessId)->delete();
                DB::table("ai_rule_triggers")->where("business_id", $businessId)->delete();
                InsightRecord::where("business_id", $businessId)->delete();
                OpenAITokenUsage::where("business_id", $businessId)->delete();
                Leaflet::where("business_id", $businessId)->delete();
                ReviewNew::where("business_id", $businessId)->delete();
                DailyView::where("business_id", $businessId)->delete();
                DB::table("surveys")->where("business_id", $businessId)->delete();
                DB::table("branches")->where("business_id", $businessId)->delete();

                // 2. Delete staff and associated users
                User::where("business_id", $businessId)->delete();

                // 3. Force delete the business record
                $business->forceDelete();

                // 4. Delete the owner
                User::where("id", $ownerId)->delete();

                // Re-enable foreign key checks
                DB::statement("SET FOREIGN_KEY_CHECKS=1;");
                User::where("id", $ownerId)->delete();
        }

        $this->info("Permanently deleted $count businesses soft-deleted before {$cutoff_date->toDateString()}.");
        return 0;
    }
}
