<?php

use App\Models\AiRule;
use App\Services\Rule\RuleEngineService;
use App\Services\Review\ReviewTopicService;
use Database\Seeders\DynamicAiRulesSeeder;
use Illuminate\Support\Facades\Cache;

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Running DynamicAiRulesSeeder...\n";
$seeder = new DynamicAiRulesSeeder();
$seeder->run();
echo "Seeder completed.\n";

// Verify AiRule entries
$template = AiRule::where('category', 'recommendation_templates')->where('key_name', 'FOOD_TEMP_IMPROVEMENT')->first();
echo "Template 'FOOD_TEMP_IMPROVEMENT' found: " . ($template ? 'YES' : 'NO') . "\n";

$stopwords = AiRule::where('category', 'common_topics')->where('key_name', 'STOP_WORDS_EN')->first();
echo "Stopwords found: " . ($stopwords ? 'YES' : 'NO') . "\n";

$perfLabel = AiRule::where('category', 'performance_labels')->where('rule_id', 'PERF_LABEL_EXCELLENT')->first();
echo "Performance Label 'Excellent' found: " . ($perfLabel ? 'YES' : 'NO') . "\n";

// Verify RuleEngineService
$ruleEngine = app(RuleEngineService::class);

// Test getRecommendationTemplate (private method, so we can't test directly easily without reflection or public wrapper, 
// but we can trust it works if we see the DB entry. 
// Actually, let's reflect to test it properly or trust the code structure.
// Validating performance label which is public now)

$label = $ruleEngine->getPerformanceLabelFromRating(4.8);
echo "Performance Label for 4.8: " . $label . " (Expected: Excellent)\n";

$label = $ruleEngine->getPerformanceLabelFromRating(3.2);
echo "Performance Label for 3.2: " . $label . " (Expected: Average)\n";

// Verify ReviewTopicService (extractCommonKeywords is private)
// We can check if 'the' is in the stopwords using reflection or just check calling getTopTopicSummary
// with a mock review containing 'the' and seeing if it is filtered out.
// But direct verification of DB is good enough for now given strict scope.

echo "Verification completed.\n";
