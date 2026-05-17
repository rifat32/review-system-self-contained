<?php

namespace App\Console\Commands;

use App\Models\AiRule;
use App\Services\Rule\RuleExecutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BackfillRuleOutcomes extends Command
{
    /**
     * Backfill review_rule_outcomes for already existing AI-processed reviews.
     *
     * Usage examples:
     *   php artisan rules:backfill-outcomes --business_id=94 --business_id=95
     *   php artisan rules:backfill-outcomes --all
     *   php artisan rules:backfill-outcomes --business_id=94 --dry-run
     */
    protected $signature = 'rules:backfill-outcomes
        {--business_id=* : Business ID(s) to backfill. Can be passed multiple times.}
        {--all : Backfill all businesses that have enabled default AI rules.}
        {--dry-run : Show what would run without writing outcomes.}';

    protected $description = 'Backfill AI rule outcomes for existing reviews without requiring OpenAI to run again.';

    public function handle(RuleExecutionService $executionService): int
    {
        $businessIds = collect($this->option('business_id'))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($this->option('all')) {
            $businessIds = AiRule::query()
                ->where('enabled', true)
                ->where('is_default', true)
                ->whereNotNull('business_id')
                ->distinct()
                ->pluck('business_id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        if ($businessIds->isEmpty()) {
            $this->error('Provide at least one --business_id value, or use --all.');
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $totalRules = 0;
        $totalReviews = 0;

        foreach ($businessIds as $businessId) {
            $rules = AiRule::query()
                ->where('business_id', $businessId)
                ->where('enabled', true)
                ->where('is_default', true)
                ->orderBy('id')
                ->get();

            if ($rules->isEmpty()) {
                $this->warn("Business {$businessId}: no enabled default AI rules found.");
                continue;
            }

            $this->info("Business {$businessId}: found {$rules->count()} default rules.");

            foreach ($rules as $rule) {
                /**
                 * Important: do not permanently change applies_to to all_reviews.
                 * We only override the in-memory model attributes so getReviewsForRule()
                 * selects historical reviews for this one backfill run.
                 */
                $originalAppliesTo = $rule->applies_to;
                $originalLastRunAt = $rule->last_run_at;

                $rule->setAttribute('applies_to', 'all_reviews');
                $rule->setAttribute('last_run_at', null);

                $reviews = $executionService->getReviewsForRule($rule);
                $reviewCount = $reviews->count();

                $this->line("  - {$rule->rule_id}: {$reviewCount} review(s) eligible");

                $rule->setAttribute('applies_to', $originalAppliesTo);
                $rule->setAttribute('last_run_at', $originalLastRunAt);

                if ($dryRun || $reviewCount === 0) {
                    continue;
                }

                DB::transaction(function () use ($executionService, $rule, $reviews) {
                    $executionService->executeRule($rule, $reviews);

                    $rule->update([
                        'last_run_at' => now(),
                        'next_run_at' => $executionService->calculateNextRun($rule),
                    ]);
                });

                $totalRules++;
                $totalReviews += $reviewCount;
            }
        }

        $message = $dryRun
            ? 'Dry run completed. No outcomes were written.'
            : "Backfill completed. Executed {$totalRules} rule(s) against {$totalReviews} review selection(s).";

        $this->info($message);
        Log::info('AI rule outcome backfill completed', [
            'business_ids' => $businessIds->all(),
            'dry_run' => $dryRun,
            'rules_executed' => $totalRules,
            'review_selections' => $totalReviews,
        ]);

        return self::SUCCESS;
    }
}
