<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Waiter;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class WaiterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create waiter role if it doesn't exist
        if (!Role::where('name', 'waiter')->exists()) {
            $waiterRole = Role::create(['name' => 'waiter']);
            $waiterPermissions = ['view-orders', 'create-orders'];

            foreach ($waiterPermissions as $permission) {
                Permission::firstOrCreate(['name' => $permission]);
            }

            $waiterRole->syncPermissions($waiterPermissions);
        }

        $waiters = [
            [
                'email' => 'waiter1@restaurant.com',
                'password' => 'waiter123',
                'username' => 'Waiter1',
            ],
            [
                'email' => 'waiter2@restaurant.com',
                'password' => 'waiter123',
                'username' => 'Waiter2',
            ],
        ];

        foreach ($waiters as $waiterData) {
            $user = User::create([
                'email' => $waiterData['email'],
                'password' => Hash::make($waiterData['password']),
                'role' => 'waiter',
            ]);

            $user->assignRole('waiter');

            Waiter::create([
                'user_id' => $user->id,
                'username' => $waiterData['username'],
            ]);
        }
    }
}
