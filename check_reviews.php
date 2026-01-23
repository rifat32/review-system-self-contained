<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\ReviewNew;
use Illuminate\Support\Facades\DB;

$total = ReviewNew::count();
$withRawText = ReviewNew::whereNotNull('raw_text')->count();
$notProcessed = ReviewNew::where('is_ai_processed', 0)->count();
$withRawTextNotProcessed = ReviewNew::whereNotNull('raw_text')->where('is_ai_processed', 0)->count();

echo "Total reviews: $total\n";
echo "Reviews with raw_text: $withRawText\n";
echo "Reviews not processed: $notProcessed\n";
echo "Reviews with raw_text AND not processed: $withRawTextNotProcessed\n";

if ($total > 0) {
    $firstReview = ReviewNew::first();
    $business = $firstReview->business;
    if ($business) {
        echo "Business #{$business->id} Details:\n";
        echo "Expiry Date: {$business->expiry_date}\n";
        echo "Is Subscribed (Attribute): " . ($business->is_subscribed ? 'Yes' : 'No') . "\n";
        echo "Token Limit: " . var_export($business->openai_token_limit, true) . "\n";
        echo "Is Token Limit Reached: " . ($business->is_token_limit_reached ? 'Yes' : 'No') . "\n";
        echo "Subscriptions Count: " . $business->subscriptions()->count() . "\n";
        echo "Active Subscriptions Count: " . $business->subscriptions()->where('status', 'active')->count() . "\n";

        $activeSub = $business->subscriptions()->where('status', 'active')->first();
        if ($activeSub) {
            echo "Active Sub End Date: {$activeSub->end_date}\n";
        }
    } else {
        echo "First review has no business!\n";
    }
}
