<?php
// app/Console/Commands/RegenerateRuleExplanations.php - Simplified version

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiRule;
use App\Services\Rule\RuleExplanationService;
use Illuminate\Support\Facades\Log;

class RegenerateRuleExplanations extends Command
{
    protected $signature = 'rules:regenerate-explanations 
                           {--business= : Specific business ID}
                           {--rule= : Specific rule ID}
                           {--all : Regenerate all rules}
                           {--missing-only : Only rules without explanations}
                           {--outdated-only : Only rules with outdated explanations}
                           {--limit=50 : Maximum rules to process}';

    protected $description = 'Regenerate AI explanations for rules';



    public function handle()
    {
        try {
            Log::channel('daily')->info("\n" . str_repeat('=', 50));
            log_message([
                'message' => str_repeat('=', 50),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            Log::channel('daily')->info("Regenerate Rule Explanations started at " . now());
            log_message([
                'message' => "Regenerate Rule Explanations started at " . now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $this->info('Starting rule explanation regeneration...');
            Log::channel('daily')->info("Starting documentation regeneration...");
            log_message([
                'message' => 'Starting documentation regeneration...',
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $rules = $this->getRulesToProcess();

            if ($rules->isEmpty()) {
                $this->info('No rules found needing regeneration (use --all to force).');
                Log::channel('daily')->info("No rules found to process.");
                log_message([
                    'message' => 'No rules found to process.',
                    'path' => __FILE__,
                    'other information' => 'AI Process Logging'
                ], 'ai_process.log');
                return 0;
            }

            $this->info("Found {$rules->count()} rule(s) to process");
            Log::channel('daily')->info("Found {$rules->count()} rule(s) to process");
            log_message([
                'message' => "Found {$rules->count()} rule(s) to process",
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $results = [
                'success' => 0,
                'failed' => 0,
                'skipped' => 0
            ];

            $progressBar = $this->output->createProgressBar($rules->count());
            $progressBar->start();

            foreach ($rules as $rule) {
                try {
                    // Check if regeneration is needed
                    if (!$this->shouldRegenerate($rule)) {
                        $results['skipped']++;
                        $progressBar->advance();
                        continue;
                    }

                    // Generate explanations using helper
                    $explanations = RuleExplanationService::regenerateForRule($rule);

                    if ($explanations) {
                        $rule->update([
                            'short_explanation' => $explanations['short_explanation'],
                            'detailed_explanation' => $explanations['detailed_explanation'],
                            'why_it_matters' => $explanations['why_it_matters'],
                            'explanation_generated_at' => now()
                        ]);

                        $results['success']++;
                        Log::channel('daily')->info("✓ Rule {$rule->rule_id}: Updated explanations");
                        log_message([
                            'message' => "Rule {$rule->rule_id}: Updated explanations",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                    } else {
                        $results['failed']++;
                        $this->newLine();
                        $this->warn("Failed: {$rule->rule_id}");
                        Log::channel('daily')->info("✗ Failed: {$rule->rule_id}");
                        log_message([
                            'message' => "Failed: {$rule->rule_id}",
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $this->newLine();
                    $this->error("Error: {$rule->rule_id} - {$e->getMessage()}");
                    Log::channel('daily')->info("Error: {$rule->rule_id} - {$e->getMessage()}");
                    log_message([
                        'message' => "Error: {$rule->rule_id} - {$e->getMessage()}",
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                }

                $progressBar->advance();

                // Small delay to avoid API rate limits
                usleep(500000); // 0.5 second delay
            }

            $progressBar->finish();
            $this->newLine(2);

            // Display results
            $this->table(
                ['Status', 'Count'],
                [
                    ['Success', $results['success']],
                    ['Failed', $results['failed']],
                    ['Skipped', $results['skipped']],
                    ['Total', $rules->count()]
                ]
            );

            if ($results['failed'] > 0) {
                $this->error("Some explanations failed to regenerate. Check logs for details.");
                Log::channel('daily')->info("Some explanations failed to regenerate.");
                log_message([
                    'message' => 'Some explanations failed to regenerate.',
                    'path' => __FILE__,
                    'other information' => 'AI Process Logging'
                ], 'ai_process.log');
                return 1;
            }

            $this->info('Explanation regeneration completed successfully!');
            Log::channel('daily')->info("Explanation regeneration completed successfully!");
            log_message([
                'message' => 'Explanation regeneration completed successfully!',
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            Log::channel('daily')->info("FATAL ERROR: " . $e->getMessage());
            log_message([
                'message' => "FATAL ERROR: " . $e->getMessage(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            return 1;
        }
    }

    private function getRulesToProcess()
    {
        $query = AiRule::query();

        if ($this->option('business')) {
            $query->where('business_id', $this->option('business'));
        }

        if ($this->option('rule')) {
            $query->where('rule_id', $this->option('rule'));
            return $query->get();
        }

        if ($this->option('missing-only')) {
            $query->where(function ($q) {
                $q->whereNull('short_explanation')
                    ->orWhereNull('detailed_explanation')
                    ->orWhereNull('why_it_matters');
            });
        }

        if ($this->option('outdated-only')) {
            $query->whereNotNull('short_explanation')
                ->whereNotNull('explanation_generated_at')
                ->whereColumn('updated_at', '>', 'explanation_generated_at');
        }

        if ($this->option('all') && !$this->option('missing-only') && !$this->option('outdated-only')) {
            // Get all rules
        } elseif (!$this->option('all') && !$this->option('missing-only') && !$this->option('outdated-only')) {
            // Default: get rules without explanations or outdated
            $query->where(function ($q) {
                $q->whereNull('short_explanation')
                    ->orWhereNull('detailed_explanation')
                    ->orWhereNull('why_it_matters')
                    ->orWhere(function ($sub) {
                        $sub->whereNotNull('explanation_generated_at')
                            ->whereColumn('updated_at', '>', 'explanation_generated_at');
                    });
            });
        }

        $limit = $this->option('limit') ?? 50;
        $query->limit($limit);

        return $query->get();
    }

    private function shouldRegenerate($rule): bool
    {
        if (!$rule->hasExplanations()) {
            return true;
        }

        if ($rule->explanationsOutdated()) {
            return true;
        }

        if ($this->option('all')) {
            return true;
        }

        return false;
    }
}
