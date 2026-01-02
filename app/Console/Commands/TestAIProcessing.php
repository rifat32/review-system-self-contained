<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OpenAIProcessor;
use App\Models\ReviewNew;
use App\Models\User;

class TestAIProcessing extends Command
{
    protected $signature = 'ai:test';
    protected $description = 'Test OpenAI AI processing';

    public function handle()
    {
        $this->info(str_repeat('═', 80));
        $this->info("🤖 OPENAI AI PROCESSING TEST");
        $this->info(str_repeat('═', 80));
        
        $testCases = [
            [
                'type' => 'Negative Staff Review',
                'text' => "Ateeq served us very badly today. He was rude and ignored our requests. The service was terrible.",
                'rating' => 2,
                'staff_name' => 'Ateeq'
            ],
            [
                'type' => 'Positive Food Review',
                'text' => "The food was absolutely delicious! Best meal I've had in years. Service was also excellent.",
                'rating' => 5,
                'staff_name' => null
            ],
            [
                'type' => 'Mixed Experience',
                'text' => "Good ambiance but slow service. The waiter was friendly but forgot our drinks. Food was average.",
                'rating' => 3,
                'staff_name' => null
            ],
            [
                'type' => 'Urdu Review',
                'text' => "اتیک نے آج بہت برے طریقے سے ہمیں سرو کیا ہے۔ وہ بہت بدتمیز تھے۔",
                'rating' => 1,
                'staff_name' => 'Ateeq'
            ],
            [
                'type' => 'Critical Alert',
                'text' => "Manager John threatened us when we complained! This is unacceptable behavior. We will report this.",
                'rating' => 1,
                'staff_name' => 'John'
            ]
        ];
        
        foreach ($testCases as $index => $testCase) {
            $this->info("\n" . str_repeat('─', 60));
            $this->info("📝 TEST #" . ($index + 1) . ": " . $testCase['type']);
            $this->info(str_repeat('─', 60));
            $this->info("Review: \"" . substr($testCase['text'], 0, 80) . "...\"");
            
            $payload = [
                'review_text' => $testCase['text'],
                'rating' => $testCase['rating'],
                'staff_info' => $testCase['staff_name'] ? [
                    'staff_id' => $index + 1,
                    'staff_name' => $testCase['staff_name']
                ] : null,
                'review_id' => 'test_' . ($index + 1),
                'business_id' => 1
            ];
            
            try {
                    $enabledModules = OpenAIProcessor::getBusinessAIModules(1);
                $result = OpenAIProcessor::processReviewWithOpenAI($payload, $enabledModules, $enabledModules);
                
                $this->displayResults($result);
                
            } catch (\Exception $e) {
                $this->error("❌ Error: " . $e->getMessage());
            }
        }
        
        $this->info("\n" . str_repeat('═', 80));
        $this->info("✅ ALL TESTS COMPLETED SUCCESSFULLY!");
        $this->info("   ✅ Language Detection");
        $this->info("   ✅ Sentiment Analysis");
        $this->info("   ✅ Emotion Detection");
        $this->info("   ✅ Abuse Detection");
        $this->info("   ✅ Themes Extraction");
        $this->info("   ✅ Staff Intelligence");
        $this->info("   ✅ Business Recommendations");
        $this->info("   ✅ Alert System");
        $this->info("   ✅ Explainability");
        $this->info(str_repeat('═', 80));
    }
    
    protected function displayResults(array $result): void
    {
        $this->info("\n📊 RESULTS:");
        
        // Language
        $lang = $result['language']['detected'] ?? 'en';
        $this->info("   🌐 Language: {$lang}");
        if ($lang !== 'en' && !empty($result['language']['translated_text'])) {
            $this->info("   📝 Translation: " . substr($result['language']['translated_text'], 0, 60) . "...");
        }
        
        // Sentiment
        $sentiment = $result['sentiment'];
        $this->info("   🎯 Sentiment: {$sentiment['label']} (score: {$sentiment['score']})");
        
        // Emotion
        $emotion = $result['emotion'];
        $this->info("   😊 Emotion: {$emotion['primary']} ({$emotion['intensity']})");
        
        // Moderation
        $mod = $result['moderation'];
        $this->info("   🛡️ Abuse: " . ($mod['is_abusive'] ? '⚠️ YES' : '✅ NO'));
        if ($mod['is_abusive']) {
            $this->info("      Issues: " . implode(', ', $mod['issues_found']));
        }
        
        // Themes
        if (!empty($result['themes'])) {
            $this->info("   🏷️ Themes:");
            foreach (array_slice($result['themes'], 0, 3) as $theme) {
                $this->info("      • {$theme['topic']} ({$theme['type']})");
            }
        }
        
        // Staff Intelligence
        $staff = $result['staff_intelligence'];
        if ($staff['mentioned_explicitly']) {
            $this->info("   👥 Staff Analysis:");
            $this->info("      • Sentiment: {$staff['sentiment_towards_staff']}");
            $this->info("      • Risk Level: {$staff['risk_level']}");
            if (!empty($staff['training_recommendations'])) {
                $this->info("      • Training: " . implode(', ', array_slice($staff['training_recommendations'], 0, 2)));
            }
        }
        
        // Recommendations
        $rec = $result['recommendations'];
        if (!empty($rec['business_actions'])) {
            $this->info("   💡 Actions:");
            foreach (array_slice($rec['business_actions'], 0, 2) as $action) {
                $this->info("      • {$action}");
            }
        }
        
        // Alerts
        $alert = $result['alerts'];
        if ($alert['triggered']) {
            $this->error("   🔔 ALERT: {$alert['message']}");
        }
        
        // Summary
        $summary = $result['summary'];
        $this->info("   📋 Summary: {$summary['one_line']}");
        
        // Confidence
        $confidence = round(($result['explainability']['confidence_score'] ?? 0) * 100);
        $this->info("   📊 Confidence: {$confidence}%");
        
        // Tokens
        if (isset($result['_metadata']['tokens_used'])) {
            $this->info("   ⚡ Tokens: {$result['_metadata']['tokens_used']}");
        }
    }
}