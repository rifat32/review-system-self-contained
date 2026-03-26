<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\ActivityLog;
use App\Models\Branch;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

use Illuminate\Support\Facades\File;


class SetupController extends Controller
{
    use ErrorUtil;

    public function oneTimeDBOperation()
    {
        // DB::table('question_categories')->insert([
        //     'title' => 'Staff',
        //     'description' => 'Default category for staff-related questions',
        //     'is_active' => true,
        //     'is_default' => true,
        //     'business_id' => null,
        //     'parent_question_category_id' => null,
        //     'created_by' => null, // Nullable field
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);

        return response()->json([
            'success' => true,
            'message' => 'DB Operation Complete'
        ]);
    }

    public function setupPassport()
    {
        try {
            // Clear caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');

            $this->privatePassportSetup();

            return response()->json([
                'success' => true,
                'message' => 'Passport Setup Complete'
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Passport setup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function setup()
    {

        // Clear caches
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('route:clear');
        Artisan::call('view:clear');

        // Clear storage logs
        $log_path = storage_path('logs');
        if (File::exists($log_path)) {
            File::cleanDirectory($log_path); // removes all files inside logs
        }

        // Run migrations
        Artisan::call('migrate:fresh');

        $this->privatePassportSetup();

        // Generate Swagger Documentation
        Artisan::call('l5-swagger:generate');

        Artisan::call('db:seed', ['--class' => 'DatabaseSeeder']);




        return response()->json([
            'success' => true,
            'message' => 'Setup Complete'
        ]);
    }

    private function privatePassportSetup()
    {
        // Run passport migrations
        Artisan::call('migrate', [
            '--path' => 'vendor/laravel/passport/database/migrations',
            '--force' => true
        ]);

        // Install passport (creates encryption keys and oauth clients)
        Artisan::call('passport:install', [
            '--force' => true,
            '--no-interaction' => true
        ]);
    }

    public function roleRefresh(Request $request)
    {

        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run the roles and permissions seeder
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Roles and Permissions refreshed successfully'
        ], 200);
    }

    // MIGRATE
    public function migrate(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run migrations
        Artisan::call('check:migrate');

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Migrations applied successfully'
        ], 200);
    }

    // MIGRATE
    public function migrateStatus(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        // Run migrations
        Artisan::call('migrate:status');

        // RESPONSE
        return response()->json([
            'success' => true,
            'message' => 'Migrations status retrieved successfully',
            'data' => Artisan::output()
        ], 200);
    }

    // ROLLBACK MIGRATE
    public function rollbackMigration(Request $request)
    {
        try {
            $result = Artisan::call('migrate:rollback');

            return response()->json([
                'message' => 'Last Migration Rolled Back',
                'data' => $result
            ], 200);
        } catch (Exception $e) {
            // LOG ERROR MESSAGE
            // log_message([
            //     'message' => 'Migration Roll Back Failed',
            //     'data' => $e->getMessage()
            // ], 'roll_back.log');

            return response()->json([
                'message' => 'Last Migration Rolled Back',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // CLEAR CACHE
    public function clearCache(Request $request)
    {

        Artisan::call('cache:clear');
        Artisan::call('optimize:clear');
        Artisan::call('route:clear');
        Artisan::call('config:clear');
        Artisan::call('view:clear');

        return response()->json([
            'success' => true,
            'message' => 'Cache cleared successfully'
        ], 200);
    }

    public function runArtisanCommand(Request $request)
    {
        $command = $request->query('command');

        if (!$command) {
            return response()->json([
                'success' => false,
                'message' => 'Command parameter is missing'
            ], 400);
        }

        try {
            // strip 'php artisan' prefix if present
            $command = preg_replace('/^php\s+artisan\s+/', '', $command);

            // Define allowed patterns or blocked commands
            $isAllowed = str_starts_with($command, 'schedule:') ||
                str_starts_with($command, 'recommendations:') ||
                str_starts_with($command, 'rules:') ||
                str_starts_with($command, 'reviews:') ||
                str_starts_with($command, 'reports:') ||
                str_contains($command, 'review_report:') ||
                str_contains($command, 'businesses:') ||
                in_array(explode(' ', $command)[0], ['optimize:clear', 'config:clear', 'cache:clear', 'route:clear', 'view:clear', 'check:migrate', 'l5-swagger:generate', 'businesses:purge-deleted']);

            if (!$isAllowed) {
                return response()->json([
                    'success' => false,
                    'message' => 'This command is not allowed for security reasons or to prevent breaking the project.'
                ], 403);
            }

            Artisan::call($command);
            $output = Artisan::output();

            return response()->json([
                'success' => true,
                'command' => $command,
                'output' => $output
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Command execution failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function getActivityLogs(Request $request)
    {
        $activity_logs = ActivityLog::when(!empty($request->status_code), function ($query) use ($request) {
            $query->where('status_code', $request->status_code);
        })
            ->when(!empty($request->user_id), function ($query) use ($request) {
                $query->where('user_id', $request->user_id);
            })
            ->when(!empty($request->business_id), function ($query) use ($request) {
                $query->whereExists(function ($subQuery) use ($request) {
                    $subQuery->select(DB::raw(1))
                        ->from(DB::connection('mysql')->getDatabaseName() . '.users')
                        ->whereColumn('activity_logs.user_id', 'users.id')
                        ->where('users.business_id', $request->business_id);
                });
            })
            ->when(!empty($request->api_url), function ($query) use ($request) {
                $query->where('api_url', 'like', '%' . $request->api_url . '%');
            })
            ->when(!empty($request->ip_address), function ($query) use ($request) {
                $query->where('ip_address', $request->ip_address);
            })
            ->when(!empty($request->request_method), function ($query) use ($request) {
                $query->where('request_method', $request->request_method);
            })
            ->when($request->filled('is_error'), function ($query) use ($request) {
                $query->where('is_error', $request->boolean('is_error') ? 1 : 0);
            })
            ->when(!empty($request->date), function ($query) use ($request) {
                $query->whereDate('created_at', $request->date);
            })
            ->when(!empty($request->id), function ($query) use ($request) {
                $query->where('id', $request->id);
            })
            ->orderbyDesc('id')
            ->paginate(20);

        return view('user-activity-log', compact('activity_logs'));
    }
}
