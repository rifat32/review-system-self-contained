<?php

namespace App\Http\Controllers;

use App\Http\Utils\ErrorUtil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class SetupController extends Controller
{
    use ErrorUtil;


    public function setup()
    {

        Artisan::call('optimize:clear');
        Artisan::call('migrate:fresh');
        Artisan::call('migrate', ['--path' => 'vendor/laravel/passport/database/migrations']);
        Artisan::call('passport:install');
        Artisan::call('l5-swagger:generate');

        $superadmin_data = [
            'email' => "asjadtariq@gmail.com",
            'password' => '12345678@We',
            'first_Name' => 'Asjaz',
            'phone' => 'nullable',
            'last_Name' => 'Tariq',
            "type" => "superadmin",
        ];

        $superadmin_data['password'] = Hash::make($superadmin_data['password']);
        $superadmin_data['remember_token'] = Str::random(10);
        $superadmin_data['email_verified_at']  = now();

        $admin_exists = User::where([
            "email" => $superadmin_data["email"]
        ])
            ->exists();

        if (!$admin_exists) {
            $user =  User::create($superadmin_data);
            $token = $user->createToken('Laravel Password Grant Client')->accessToken;
            $data["user"] = $user;
            if (!Role::where(['name' => 'superadmin'])->exists()) {
                Role::create(['name' => 'superadmin']);
            }
            $user->assignRole('superadmin');
        }

        return "ok";
    }

    public function roleRefresh(Request $request)
    {

        $this->storeActivity($request, "DUMMY activity", "DUMMY description");

        $this->roleRefreshFunc();


        return "You are done with setup";
    }

    public function migrate(Request $request)
    {
        $this->storeActivity($request, "DUMMY activity", "DUMMY description");
        Artisan::call('check:migrate');
        return "migrated";
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

            // Deassign permissions not included in the configuration
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
