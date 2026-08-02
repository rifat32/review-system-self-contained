<?php

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\BusinessSubscription;
use Illuminate\Console\Command;

class ProcessSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:process';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process subscription expirations and activate stacked plans for businesses';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // 1. Expire old subscriptions
        $expiredCount = BusinessSubscription::where('status', 'active')
            ->where('end_date', '<=', now())
            ->update(['status' => 'expired']);

        $this->info("Expired {$expiredCount} old subscriptions.");

        // 2. Synchronize Business limits based on currently valid plans
        $businesses = Business::all();
        $activatedCount = 0;
        $downgradedCount = 0;

        foreach ($businesses as $business) {
            // Find the active subscription that is valid RIGHT NOW
            $activeSub = BusinessSubscription::where('business_id', $business->id)
                ->where('status', 'active')
                ->where('start_date', '<=', now())
                ->where('end_date', '>', now())
                ->orderBy('start_date', 'desc')
                ->first();

            if ($activeSub) {
                // They have a valid plan today. Ensure the business table matches this exact plan.
                $business->update([
                    'service_plan_id' => $activeSub->service_plan_id,
                    'openai_token_limit' => $activeSub->openai_token_limit,
                    'start_date' => $activeSub->start_date,
                ]);
                $activatedCount++;
            } else {
                // They have NO valid plan today. Downgrade them.
                if ($business->service_plan_id !== null) {
                    $business->update([
                        'service_plan_id' => null,
                        'openai_token_limit' => 0,
                        'start_date' => null,
                    ]);
                    $downgradedCount++;
                }
            }
        }

        $this->info("Activated/Synced {$activatedCount} businesses.");
        $this->info("Downgraded {$downgradedCount} businesses.");

        return Command::SUCCESS;
    }
}
