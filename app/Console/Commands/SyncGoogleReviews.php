<?php

namespace App\Console\Commands;

use App\Models\GoogleBusinessLocation;
use App\Services\GoogleBusinessService;
use Illuminate\Console\Command;
use Exception;

class SyncGoogleReviews extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:sync-reviews 
                            {--location= : Sync reviews for a specific location ID}
                            {--all : Sync all active locations}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync reviews from Google Business Profile';

    protected $googleBusinessService;

    /**
     * Create a new command instance.
     */
    public function __construct(GoogleBusinessService $googleBusinessService)
    {
        parent::__construct();
        $this->googleBusinessService = $googleBusinessService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Google Business reviews sync...');

        try {
            $locationId = $this->option('location');

            if ($locationId) {
                // Sync specific location
                $this->syncLocation($locationId);
            } else {
                // Sync all active locations
                $this->syncAllLocations();
            }

            $this->info('✓ Sync completed successfully!');
            return 0;

        } catch (Exception $e) {
            $this->error('✗ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    /**
     * Sync a specific location
     */
    protected function syncLocation($locationId)
    {
        $location = GoogleBusinessLocation::findOrFail($locationId);

        $this->info("Syncing location: {$location->location_name}");

        try {
            $syncedCount = $this->googleBusinessService->syncReviews($location);
            $this->info("  → Synced {$syncedCount} reviews");
        } catch (Exception $e) {
            $this->error("  → Failed: " . $e->getMessage());
        }
    }

    /**
     * Sync all active locations
     */
    protected function syncAllLocations()
    {
        $locations = GoogleBusinessLocation::active()->get();

        if ($locations->isEmpty()) {
            $this->warn('No active locations found to sync.');
            return;
        }

        $this->info("Found {$locations->count()} active location(s) to sync.");

        $totalSynced = 0;
        $successCount = 0;
        $failCount = 0;

        foreach ($locations as $location) {
            $this->info("Syncing: {$location->location_name}");

            try {
                $syncedCount = $this->googleBusinessService->syncReviews($location);
                $totalSynced += $syncedCount;
                $successCount++;
                $this->info("  → Synced {$syncedCount} reviews");
            } catch (Exception $e) {
                $failCount++;
                $this->error("  → Failed: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("  Total locations: {$locations->count()}");
        $this->info("  Successful: {$successCount}");
        $this->info("  Failed: {$failCount}");
        $this->info("  Total reviews synced: {$totalSynced}");
    }
}
