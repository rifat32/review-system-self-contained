<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\ActivityLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;


class SetupController extends Controller
{
    use ErrorUtil;

    // public function oneTimeDBOperation()
    // {
    //    DB::statement("
    //     UPDATE review_news 
    //     SET rate = FLOOR(1 + RAND() * 5)
    //     WHERE rate > 5
    // ");

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'DB Operation Complete'
    //     ]);
    // }

    public function setupPassport()
    {
        try {
            // Clear caches
            Artisan::call('config:clear');
            Artisan::call('cache:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');

            // Generate Passport keys manually to avoid STDIN issues
            Artisan::call('passport:keys', ['--force' => true]);

            // Run Passport migrations if needed
            Artisan::call('migrate', ['--path' => 'vendor/laravel/passport/database/migrations']);

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

        // Run migrations
        Artisan::call('migrate:fresh');
        // Run passport migrations
        Artisan::call('migrate', ['--path' => 'vendor/laravel/passport/database/migrations']);

        // Install passport
        // Generate Passport keys manually to avoid STDIN issues
        Artisan::call('passport:keys', ['--force' => true]);

        // Run Passport migrations if needed
        Artisan::call('migrate', ['--path' => 'vendor/laravel/passport/database/migrations']);

        // Generate Swagger Documentation
        Artisan::call('l5-swagger:generate');

        // Seed Super Admin
        Artisan::call('db:seed', ['--class' => 'SuperAdminSeeder']);

        // Setup Roles and Permissions
        Artisan::call('db:seed', ['--class' => 'RolesAndPermissionsSeeder']);
        return response()->json([
            'success' => true,
            'message' => 'Setup Complete'
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

    public function roleRefreshFunc()
    {

        // ###############################
        // permissions
        // ###############################
        $permissions =  config("setup-config.permissions");

        // setup permissions
        foreach ($permissions as $permission) {
            if (!Permission::where([
                'name' => $permission,
                'guard_name' => 'api'
            ])
                ->exists()) {
                Permission::create(['guard_name' => 'api', 'name' => $permission]);
            }
        }
        // setup roles
        $roles = config("setup-config.roles");
        foreach ($roles as $role) {
            if (!Role::where([
                'name' => $role,
                'guard_name' => 'api',
                "is_system_default" => 1,
                "business_id" => NULL,
                "is_default" => 1,
            ])
                ->exists()) {
                Role::create([
                    'guard_name' => 'api',
                    'name' => $role,
                    "is_system_default" => 1,
                    "business_id" => NULL,
                    "is_default" => 1,
                    "is_default_for_business" => (in_array($role, [
                        "business_owner",
                        "business_admin",
                        "business_manager",
                        "business_employee"
                    ]) ? 1 : 0)


                ]);
            }
        }


        // setup roles and permissions
        $role_permissions = config("setup-config.roles_permission");
        foreach ($role_permissions as $role_permission) {
            $role = Role::where(["name" => $role_permission["role"]])->first();

            $permissions = $role_permission["permissions"];


            // Get current permissions associated with the role
            $currentPermissions = $role->permissions()->pluck('name')->toArray();

            // Determine permissions to remove
            $permissionsToRemove = array_diff($currentPermissions, $permissions);

            // unassign permissions not included in the configuration
            if (!empty($permissionsToRemove)) {
                foreach ($permissionsToRemove as $permission) {
                    $role->revokePermissionTo($permission);
                }
            }

            // Assign permissions from the configuration
            $role->syncPermissions($permissions);
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
