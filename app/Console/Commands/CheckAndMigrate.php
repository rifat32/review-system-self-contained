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
        // Get all migration files in the database/migrations directory
        $migrationFiles = glob(database_path('migrations/*.php'));

        // Open log file
        $logFile = storage_path('logs/migration.log');
        $logHandle = fopen($logFile, 'a');
        fwrite($logHandle, "Migration started at " . now() . "\n");

        foreach ($migrationFiles as $file) {
            // Get the migration file name (without the path)
            $migrationName = basename($file);

            // Check if this migration has already been run by checking the migrations table
            $migrationAlreadyRun = DB::table('migrations')->where('migration', pathinfo($migrationName, PATHINFO_FILENAME))->exists();

            if ($migrationAlreadyRun) {
                $message = "Migration {$migrationName} already exists in the migrations table. Skipping...\n";
                $this->info($message);
                fwrite($logHandle, $message);
            } else {
                try {
                    // Run the specific migration
                    Artisan::call('migrate', [
                        '--path' => str_replace(base_path(), '', $file),
                    ]);

                    // Log the successful migration
                    $message = "Migrated {$migrationName} successfully.\n";
                    fwrite($logHandle, $message);
                } catch (\Exception $e) {
                    // Log the error message
                    $errorMessage = "Migration failed for {$migrationName}. Error: " . $e->getMessage() . "\n";
                    $this->error($errorMessage);
                    fwrite($logHandle, $errorMessage);
                    throw new Exception($errorMessage,400);
                }
            }
        }

        // Close log file
        fwrite($logHandle, "Migration finished at " . now() . "\n\n");
        fclose($logHandle);
    }


}
