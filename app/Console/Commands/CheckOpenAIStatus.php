<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Helpers\OpenAIProcessor;

class CheckOpenAIStatus extends Command
{
    protected $signature = 'openai:status';
    protected $description = 'Check OpenAI API status';

    public function handle()
    {
        $this->info('🔍 Checking OpenAI API Status');
        $this->info(str_repeat('═', 50));
        
        $status = OpenAIProcessor::debugOpenAIStatus();
        
        if ($status['status'] === 'success') {
            $this->info("✅ " . $status['message']);
            $this->info("   Status Code: " . ($status['status_code'] ?? 'N/A'));
        } else {
            $this->error("❌ " . $status['message']);
            if (isset($status['error'])) {
                $this->error("   Error: " . $status['error']);
            }
            if (isset($status['status_code'])) {
                $this->error("   HTTP Status: " . $status['status_code']);
            }
        }
        
        $this->info(str_repeat('═', 50));
    }
}