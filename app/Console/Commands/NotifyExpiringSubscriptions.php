<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Business;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use App\Mail\SubscriptionExpiringAdminMail;

class NotifyExpiringSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:notify-expiring';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify admins about businesses whose subscriptions are expiring in exactly 15 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $targetDate = Carbon::now()->addDays(15)->toDateString();
        
        // Find businesses where the trial_end_date (which acts as subscription expiration) is exactly 15 days away
        $businesses = Business::whereDate('trial_end_date', $targetDate)->get();
        
        if ($businesses->isEmpty()) {
            $this->info("No subscriptions expiring on {$targetDate}.");
            return Command::SUCCESS;
        }

        $adminEmails = ['asjadtariq@gmail.com', 'rony.mia7800@gmail.com'];

        foreach ($businesses as $business) {
            $owner = \App\Models\User::find($business->OwnerID);
            
            try {
                Mail::to($adminEmails)->send(new SubscriptionExpiringAdminMail($business, $owner));
                $this->info("Notification sent for business: {$business->name} (ID: {$business->id})");
            } catch (\Exception $e) {
                $this->error("Failed to send notification for business ID {$business->id}: " . $e->getMessage());
            }
        }

        $this->info("Completed sending expiration notifications.");
        return Command::SUCCESS;
    }
}
