<?php

namespace App\Console\Commands;

use App\Services\Rule\RuleExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ExecuteScheduledRules extends Command
{

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rules:execute-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute scheduled AI rules based on their frequency';



    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(RuleExecutionService $executionService)
    {
        try {
            $startMsg = "Starting scheduled rules execution";
            $this->info($startMsg);

            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            Log::channel('daily')->info("Scheduled Rules Execution started at " . \now());
            Log::channel('daily')->info($startMsg);

            // Execute directly via service - NO QUEUE INVOLVED
            $executionService->runScheduledRules();

            $this->info('Scheduled rules executed successfully.');
            Log::channel('daily')->info("Scheduled rules executed successfully.");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("ERROR: " . $e->getMessage());
            return 1;
        }
    }
}
