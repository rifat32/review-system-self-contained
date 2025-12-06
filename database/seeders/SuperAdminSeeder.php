<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $superadmin_data = [
            'email' => "asjadtariq@gmail.com",
            'password' => Hash::make('12345678@We'),
            'first_Name' => 'Asjad',
            'phone' => null,
            'last_Name' => 'Tariq',
            "type" => "superadmin",
            'remember_token' => Str::random(10),
            'email_verified_at' => now(),
        ];

        $admin_exists = User::where('email', $superadmin_data['email'])->exists();

        if (!$admin_exists) {
            $user = User::create($superadmin_data);

            // Create superadmin role if it doesn't exist
            if (!Role::where('name', 'superadmin')->exists()) {
                Role::create(['name' => 'superadmin', 'guard_name' => 'api']);
            }

            // Assign superadmin role to the user
            $user->assignRole('superadmin');
        }
    }
}
