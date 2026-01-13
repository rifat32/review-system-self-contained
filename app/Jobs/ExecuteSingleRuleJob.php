<?php

namespace App\Jobs;

use App\Models\{AiRule, ReviewNew};
use App\Services\Rule\RuleExecutionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;

class ExecuteSingleRuleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected AiRule $rule;
    protected ReviewNew $review;

    /**
     * Create a new job instance.
     */
    public function __construct(AiRule $rule, ReviewNew $review)
    {
        $this->rule = $rule;
        $this->review = $review;
    }

    /**
     * Execute the job.
     */
    public function handle(RuleExecutionService $executionService): void
    {
        try {
            Log::debug("Executing real-time rule", [
                'rule_id' => $this->rule->rule_id,
                'review_id' => $this->review->id
            ]);

            // Execute rule against single review
            $result = $executionService->executeRule($this->rule, collect([$this->review]));

            Log::info("Real-time rule executed", [
                'rule_id' => $this->rule->rule_id,
                'review_id' => $this->review->id,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error("Real-time rule execution failed", [
                'rule_id' => $this->rule->rule_id,
                'review_id' => $this->review->id,
                'error' => $e->getMessage()
            ]);

            throw $e; // Re-throw to trigger failed() method
        }
    }

    /**
     * The job failed to process.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ExecuteSingleRuleJob failed", [
            'rule_id' => $this->rule->rule_id,
            'review_id' => $this->review->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
