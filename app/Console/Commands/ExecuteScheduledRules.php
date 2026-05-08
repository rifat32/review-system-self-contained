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
            log_message([
                'message' => str_repeat('=', 50),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info("Scheduled Rules Execution started at " . \now());
            log_message([
                'message' => "Scheduled Rules Execution started at " . \now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info($startMsg);
            log_message([
                'message' => $startMsg,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            // Execute directly via service
            $rules = $executionService->getRulesToExecute();

            if ($rules->isEmpty()) {
                $this->info('No rules to execute.');
                Log::channel('daily')->info("No rules to execute.");
                log_message([
                    'message' => 'No rules to execute.',
                    'path' => __FILE__,
                    'other information' => 'AI Process Logging'
                ], 'ai_process.log');
                return 0;
            }

            $progressBar = $this->output->createProgressBar($rules->count());
            $progressBar->start();
            $this->newLine();

            foreach ($rules as $rule) {
                try {
                    $reviews = $executionService->getReviewsForRule($rule);

                    if ($reviews->isEmpty()) {
                        $rule->update([
                            'last_run_at' => \now(),
                            'next_run_at' => $executionService->calculateNextRun($rule)
                        ]);
                    } else {
                        $executionService->executeRule($rule, $reviews);
                        $rule->update([
                            'last_run_at' => \now(),
                            'next_run_at' => $executionService->calculateNextRun($rule)
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error("Rule failed: " . $rule->rule_id . " - " . $e->getMessage());
                    // Only move next_run_at so the scheduler can retry later.
                    // DO NOT update last_run_at here as it would skip reviews in next run.
                    $rule->update([
                        'next_run_at' => $executionService->calculateNextRun($rule)
                    ]);
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            $this->info('Scheduled rules executed successfully.');
            Log::channel('daily')->info("Scheduled rules executed successfully.");
            log_message([
                'message' => 'Scheduled rules executed successfully.',
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("ERROR: " . $e->getMessage());
            log_message([
                'message' => "ERROR: " . $e->getMessage(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            return 1;
        }
    }
}
