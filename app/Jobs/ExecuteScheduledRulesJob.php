<?php

namespace App\Jobs;

use App\Models\AiRule;
use App\Services\Rule\RuleExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

class ExecuteScheduledRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $frequency;

    /**
     * Create a new job instance.
     */
    public function __construct(string $frequency = 'all')
    {
        $this->frequency = $frequency;
    }

    /**
     * Execute the job.
     */
    public function handle(RuleExecutionService $executionService): void
    {
        Log::info("Starting scheduled rule execution", [
            'frequency' => $this->frequency,
            'time' => now()->toDateTimeString()
        ]);

        // Get rules that need to run
        $rules = $this->getRulesToExecute();

        $summary = [
            'total_rules' => $rules->count(),
            'executed' => 0,
            'failed' => 0,
            'results' => []
        ];

        foreach ($rules as $rule) {
            try {
                // Get reviews for this rule
                $reviews = $executionService->getReviewsForRule($rule);

                if ($reviews->isEmpty()) {
                    Log::debug("No reviews to evaluate for rule", [
                        'rule_id' => $rule->rule_id
                    ]);
                    continue;
                }

                // Execute the rule
                $result = $executionService->executeRule($rule, $reviews);

                $summary['executed']++;
                $summary['results'][] = $result;

                Log::info("Rule executed successfully", [
                    'rule_id' => $rule->rule_id,
                    'result' => $result
                ]);

            } catch (\Exception $e) {
                $summary['failed']++;

                Log::error("Rule execution failed", [
                    'rule_id' => $rule->rule_id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        Log::info("Scheduled rule execution completed", $summary);
    }

    /**
     * Get rules that should be executed based on frequency
     */
    private function getRulesToExecute()
    {
        $query = AiRule::where('enabled', true)
            ->where('run_frequency', '!=', 'real_time');

        // Filter by frequency if specified
        if ($this->frequency !== 'all') {
            $query->where('run_frequency', $this->frequency);
        }

        // Only get rules that are due to run
        $query->where(function ($q) {
            $q->whereNull('next_run_at')
                ->orWhere('next_run_at', '<=', now());
        });

        return $query->get();
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExecuteScheduledRulesJob failed", [
            'frequency' => $this->frequency,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
