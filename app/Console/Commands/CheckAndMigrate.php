<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckAndMigrate extends Command
{
    protected $signature = 'check:migrate';
    protected $description = 'Check if tables exist before running migrations';



    public function handle()
    {
        try {
            // Get all migration files in the database/migrations directory
            $migrationFiles = glob(database_path('migrations/*.php'));

            Log::channel('daily')->info("Migration started at " . now());
            log_message([
                'message' => "Migration started at " . now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');

            $progressBar = $this->output->createProgressBar(count($migrationFiles));
            $progressBar->start();
            $this->newLine();

            foreach ($migrationFiles as $file) {
                // Get the migration file name (without the path)
                $migrationName = basename($file);

                // Check if this migration has already been run by checking the migrations table
                $migrationAlreadyRun = DB::table('migrations')->where('migration', pathinfo($migrationName, PATHINFO_FILENAME))->exists();

                if ($migrationAlreadyRun) {
                    $message = "Migration {$migrationName} already exists in the migrations table. Skipping...";
                    $this->info($message);
                    Log::channel('daily')->info($message);
                    log_message([
                        'message' => $message,
                        'path' => __FILE__,
                        'other information' => 'AI Process Logging'
                    ], 'ai_process.log');
                } else {
                    try {
                        // Run the specific migration
                        Artisan::call('migrate', [
                            '--path' => str_replace(base_path(), '', $file),
                        ]);

                        // Log the successful migration
                        $message = "Migrated {$migrationName} successfully.";
                        Log::channel('daily')->info($message);
                        log_message([
                            'message' => $message,
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                    } catch (\Exception $e) {
                        // Log the error message
                        $errorMessage = "Migration failed for {$migrationName}. Error: " . $e->getMessage();
                        $this->error($errorMessage);
                        Log::channel('daily')->info($errorMessage);
                        log_message([
                            'message' => $errorMessage,
                            'path' => __FILE__,
                            'other information' => 'AI Process Logging'
                        ], 'ai_process.log');
                        throw new Exception($errorMessage, 400);
                    }
                }
                $progressBar->advance();
            }

            $progressBar->finish();
            $this->newLine();

            Log::channel('daily')->info("Migration finished at " . now() . "\n");
            log_message([
                'message' => "Migration finished at " . now(),
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
        } catch (\Exception $e) {
            $errorMessage = "FATAL ERROR: " . $e->getMessage();
            $this->error($errorMessage);
            Log::channel('daily')->info($errorMessage);
            log_message([
                'message' => $errorMessage,
                'path' => __FILE__,
                'other information' => 'AI Process Logging'
            ], 'ai_process.log');
            return 1;
        }
    }
}
