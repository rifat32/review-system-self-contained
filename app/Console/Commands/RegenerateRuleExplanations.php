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

    private $logHandle;

    public function handle()
    {
        $logFile = storage_path('logs/ai_processing.log');
        $this->logHandle = fopen($logFile, 'a');

        try {
            $this->fileWrite("\n" . str_repeat('=', 50) . "\n");
            $this->fileWrite("Regenerate Rule Explanations started at " . now() . "\n");

            $this->info('Starting rule explanation regeneration...');
            $this->fileWrite("Starting rule explanation regeneration...\n");

            $rules = $this->getRulesToProcess();

            if ($rules->isEmpty()) {
                // Check if table is empty at all
                if (AiRule::count() === 0) {
                    $this->info('No rules found in database. Seeding default rules...');
                    $this->call('db:seed', ['--class' => 'AiRuleSeeder']);
                    $rules = $this->getRulesToProcess();
                } else {
                    $this->info('No rules found needing regeneration (use --all to force).');
                    $this->fileWrite("No rules found to process.\n");
                    return 0;
                }
            }

            $this->info("Found {$rules->count()} rule(s) to process");
            $this->fileWrite("Found {$rules->count()} rule(s) to process\n");

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
                            'explanation_generated_at' => now(),
                            // Also sync proposal columns
                            'ai_explanation_title' => $explanations['short_explanation'],
                            'ai_plain_explanation' => $explanations['detailed_explanation'],
                            'ai_why_it_matters' => $explanations['why_it_matters'],
                            'ai_when_it_triggers' => $explanations['when_it_triggers'],
                            'ai_generated_at' => now()
                        ]);

                        $results['success']++;
                        $this->fileWrite("✓ Rule {$rule->rule_id}: Updated explanations\n");
                    } else {
                        $results['failed']++;
                        $this->newLine();
                        $this->warn("Failed: {$rule->rule_id}");
                        $this->fileWrite("✗ Failed: {$rule->rule_id}\n");
                    }
                } catch (\Exception $e) {
                    $results['failed']++;
                    $this->newLine();
                    $this->error("Error: {$rule->rule_id} - {$e->getMessage()}");
                    $this->fileWrite("Error: {$rule->rule_id} - {$e->getMessage()}\n");
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
                $this->fileWrite("Some explanations failed to regenerate.\n");
                return 1;
            }

            $this->info('Explanation regeneration completed successfully!');
            $this->fileWrite("Explanation regeneration completed successfully!\n");

            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            $this->fileWrite("FATAL ERROR: " . $e->getMessage() . "\n");
            return 1;
        } finally {
            if ($this->logHandle) {
                fclose($this->logHandle);
            }
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

    private function fileWrite($message)
    {
        if ($this->logHandle) {
            fwrite($this->logHandle, $message);
        }
    }
}
