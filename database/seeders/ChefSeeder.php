<?php

namespace Database\Seeders;

use App\Models\Chef;
use App\Models\MenuCategory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ChefSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        $chefRole = Role::firstOrCreate(['name' => 'chef']);

        $chefPermissions = [
            'view-orders',
            'update-orders',
            'create-reports',
        ];

        foreach ($chefPermissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // Assign permissions to chef role
        $chefRole->syncPermissions($chefPermissions);


        $menuCategories = MenuCategory::all();

        if ($menuCategories->isEmpty()) {
            $this->command->error('No menu categories found! Please seed MenuCategory first.');
            return;
        }

        foreach ($menuCategories as $category) {
            for ($i = 1; $i <= 3; $i++) {

                $email = 'chef_' . $category->id . '_' . $i . '@example.com';

                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'password' => Hash::make('password123'),
                        'role' => 'chef'
                    ]
                );

                if (!$user->hasRole('chef')) {
                    $user->assignRole($chefRole);
                }

                Chef::firstOrCreate([
                    'user_id' => $user->id,
                    'username' => 'Chef-' . $category->id . '-' . $i,
                    'menu_category_id' => $category->id,
                ]);

                $token = Str::random(40);
                PersonalAccessToken::create([
                    'tokenable_type' => User::class,
                    'tokenable_id' => $user->id,
                    'name' => 'ChefSeederToken',
                    'token' => hash('sha256', $token),
                    'abilities' => ['*'],
                ]);

                $this->command->info("ChefSeeder completed successfully!");
            }
        }
    }
}
