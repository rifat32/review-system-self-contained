<?php

namespace App\Console\Commands;

use App\Services\Rule\RuleExecutionService;
use Illuminate\Console\Command;

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

    private $logHandle;

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(RuleExecutionService $executionService)
    {
        $logFile = \storage_path('logs/ai_processing.log');
        $this->logHandle = fopen($logFile, 'a');

        try {
            $startMsg = "Starting scheduled rules execution";
            $this->info($startMsg);

            $this->fileWrite("\n" . str_repeat('=', 50) . "\n");
            $this->fileWrite("Scheduled Rules Execution started at " . \now() . "\n");
            $this->fileWrite($startMsg . "\n");

            // Execute directly via service - NO QUEUE INVOLVED
            $executionService->runScheduledRules();

            $this->info('Scheduled rules executed successfully.');
            $this->fileWrite("Scheduled rules executed successfully.\n");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->fileWrite("ERROR: " . $e->getMessage() . "\n");
            return 1;
        } finally {
            if ($this->logHandle) {
                fclose($this->logHandle);
            }
        }
    }

    private function fileWrite($message)
    {
        if ($this->logHandle) {
            fwrite($this->logHandle, $message);
        }
    }
}
