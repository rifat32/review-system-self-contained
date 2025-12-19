<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OpenAIProcessor;
use Illuminate\Support\Facades\Http;

class DebugOpenAIRequest extends Command
{
    protected $signature = 'debug:openai-request';
    protected $description = 'Debug OpenAI request format';

    public function handle()
    {
        $this->info('🔍 Debugging OpenAI Request Format');
        $this->info(str_repeat('═', 50));
        
        // Test the actual request that's failing
        $apiKey = config('services.openai.api_key');
        
        if (empty($apiKey)) {
            $this->error('API key not set');
            return;
        }
        
        $this->info("API Key: " . substr($apiKey, 0, 12) . "...");
        
        // Create a test request
        $testPayload = [
            'model' => 'gpt-4o-mini',
            'temperature' => 0.2,
            'max_tokens' => 100,
            'response_format' => ['type' => 'json_object'],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a test assistant. Return JSON: {"test": "success"}'
                ],
                [
                    'role' => 'user',
                    'content' => 'Test message'
                ]
            ]
        ];
        
        $this->info("\n📦 Test Request Payload:");
        $this->line(json_encode($testPayload, JSON_PRETTY_PRINT));
        
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])
            ->timeout(10)
            ->post('https://api.openai.com/v1/chat/completions', $testPayload);
            
            $this->info("\n✅ Response Status: " . $response->status());
            
            if ($response->successful()) {
                $data = $response->json();
                $this->info("✅ Response Successful!");
                $this->info("Content: " . json_encode($data['choices'][0]['message']['content'] ?? 'No content'));
            } else {
                $this->error("❌ Response Failed!");
                $this->error("Body: " . $response->body());
                
                // Try without response_format
                $this->info("\n🔄 Trying without response_format...");
                unset($testPayload['response_format']);
                
                $response2 = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->timeout(10)
                ->post('https://api.openai.com/v1/chat/completions', $testPayload);
                
                $this->info("Status without response_format: " . $response2->status());
                if ($response2->successful()) {
                    $this->info("✅ Works without response_format!");
                    $data = $response2->json();
                    $this->info("Content: " . ($data['choices'][0]['message']['content'] ?? 'No content'));
                } else {
                    $this->error("❌ Still failing: " . $response2->body());
                }
            }
            
        } catch (\Exception $e) {
            $this->error("Exception: " . $e->getMessage());
        }
        
        $this->info(str_repeat('═', 50));
    }
}