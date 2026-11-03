<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin role if it doesn't exist
        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        // Create permissions for admin
        $adminPermissions = [
            'manage-chefs',
            'manage-waiters',
            'manage-customers',
            'manage-menu',
            'manage-inventory',
            'manage-tables',
            'view-reports',
        ];

        foreach ($adminPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to admin role
        $adminRole->syncPermissions($adminPermissions);

        // Create admin user
        $user = User::create([
            'email' => 'admin@restaurant.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        // Assign role to user
        $user->assignRole('admin');

        // Create admin profile
        Admin::create([
            'user_id' => $user->id,
            'name' => 'Admin',
        ]);
    }
}
