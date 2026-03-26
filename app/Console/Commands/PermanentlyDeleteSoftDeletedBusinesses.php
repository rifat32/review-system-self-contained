<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use App\Models\User;
use App\Models\Recommendation;
use App\Models\AiRule;
use App\Models\InsightRecord;
use App\Models\OpenAITokenUsage;
use App\Models\Leaflet;
use App\Models\DailyView;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PermanentlyDeleteSoftDeletedBusinesses extends Command
{
    protected $signature = 'businesses:purge-deleted';

    protected $description = 'Permanently delete soft-deleted businesses and their related data after 30 days';

    public function handle()
    {
        try {
            // Log start
            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            log_message([
                'message' => str_repeat('=', 50),
                'path' => __FILE__,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');

            Log::channel('daily')->info("Purge Deleted Businesses started at " . now());
            log_message([
                'message' => "Purge Deleted Businesses started at " . now(),
                'path' => __FILE__,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');

            // Query soft-deleted businesses (30+ days ago)
            $cutoffDate = Carbon::now()->subDays(30);
            $businesses = Business::onlyTrashed()
                ->where('deleted_at', '<=', $cutoffDate)
                ->get();

            $count = $businesses->count();
            $msg = "Found {$count} businesses to permanently delete (soft-deleted 30+ days ago)";
            $this->info($msg);
            Log::channel('daily')->info($msg);
            log_message([
                'message' => $msg,
                'path' => __FILE__,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');

            // Early return if nothing to delete
            if ($count === 0) {
                $this->info('No businesses to delete.');
                Log::channel('daily')->info('No businesses to delete.');
                log_message([
                    'message' => 'No businesses to delete.',
                    'path' => __FILE__,
                    'other information' => 'Business Cleanup Logging'
                ], 'business_cleanup.log');
                return 0;
            }

            // Process each business with progress bar
            $progressBar = $this->output->createProgressBar($count);
            $progressBar->start();
            $this->newLine();

            $stats = [
                'businesses' => 0,
                'owners' => 0,
                'staff' => 0,
                'errors' => 0
            ];

            foreach ($businesses as $business) {
                try {
                    DB::transaction(function () use ($business, &$stats) {
                        $this->deleteBusiness($business, $stats);
                    });
                    $stats['businesses']++;
                } catch (\Exception $e) {
                    $errorMsg = "Error deleting business {$business->id}: " . $e->getMessage();
                    $this->error($errorMsg);
                    Log::channel('daily')->error($errorMsg);
                    log_message([
                        'message' => $errorMsg,
                        'path' => __FILE__,
                        'business_id' => $business->id,
                        'other information' => 'Business Cleanup Logging - ERROR'
                    ], 'business_cleanup.log');
                    $stats['errors']++;
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            // Log summary
            $summary = "Purge completed. Deleted: {$stats['businesses']} businesses, {$stats['owners']} owners, {$stats['staff']} staff members. Errors: {$stats['errors']}";
            $this->info($summary);
            Log::channel('daily')->info($summary);
            log_message([
                'message' => $summary,
                'path' => __FILE__,
                'stats' => $stats,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');

            Log::channel('daily')->info("Purge Deleted Businesses completed at " . now());
            log_message([
                'message' => "Purge Deleted Businesses completed at " . now(),
                'path' => __FILE__,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->error("FATAL ERROR: " . $e->getMessage());
            log_message([
                'message' => "FATAL ERROR: " . $e->getMessage(),
                'path' => __FILE__,
                'trace' => $e->getTraceAsString(),
                'other information' => 'Business Cleanup Logging - FATAL'
            ], 'business_cleanup.log');
            return 1;
        }
    }

    /**
     * Delete a business and all its related data
     *
     * @param Business $business
     * @param array $stats
     * @return void
     */
    private function deleteBusiness(Business $business, array &$stats)
    {
        $businessId = $business->id;
        $ownerId = $business->OwnerID;

        // Log business details
        Log::channel('daily')->info("Deleting business {$businessId}: {$business->Name}");
        log_message([
            'message' => "Deleting business: {$business->Name}",
            'business_id' => $businessId,
            'business_name' => $business->Name,
            'owner_id' => $ownerId,
            'deleted_at' => $business->deleted_at,
            'path' => __FILE__,
            'other information' => 'Business Cleanup Logging'
        ], 'business_cleanup.log');

        // 1. Collect staff user IDs
        $staffIds = User::where('business_id', $businessId)->pluck('id')->toArray();

        // 2. Delete non-CASCADE tables (in dependency order)
        $deleteCounts = [
            'recommendations' => Recommendation::where('business_id', $businessId)->delete(),
            'ai_rules' => AiRule::where('business_id', $businessId)->delete(),
            'insight_records' => InsightRecord::where('business_id', $businessId)->delete(),
            'token_usage' => OpenAITokenUsage::where('business_id', $businessId)->delete(),
            'leaflets' => Leaflet::where('business_id', $businessId)->delete(),
            'daily_views' => DailyView::where('business_id', $businessId)->delete()
        ];

        Log::channel('daily')->info("Deleted related records: " . json_encode($deleteCounts));
        log_message([
            'message' => 'Deleted related records',
            'business_id' => $businessId,
            'delete_counts' => $deleteCounts,
            'path' => __FILE__,
            'other information' => 'Business Cleanup Logging'
        ], 'business_cleanup.log');

        // 3. Force delete business (triggers CASCADE deletes + file cleanup via boot event)
        $business->forceDelete();

        // 4. Delete staff users
        if (!empty($staffIds)) {
            User::whereIn('id', $staffIds)->forceDelete();
            $stats['staff'] += count($staffIds);
            Log::channel('daily')->info("Deleted " . count($staffIds) . " staff members");
            log_message([
                'message' => "Deleted staff members",
                'business_id' => $businessId,
                'staff_count' => count($staffIds),
                'staff_ids' => $staffIds,
                'path' => __FILE__,
                'other information' => 'Business Cleanup Logging'
            ], 'business_cleanup.log');
        }

        // 5. Delete owner user
        User::where('id', $ownerId)->forceDelete();
        $stats['owners']++;

        Log::channel('daily')->info("Successfully deleted business {$businessId} with owner and staff");
        log_message([
            'message' => "Successfully deleted business with owner and staff",
            'business_id' => $businessId,
            'owner_id' => $ownerId,
            'path' => __FILE__,
            'other information' => 'Business Cleanup Logging'
        ], 'business_cleanup.log');
    }
}
