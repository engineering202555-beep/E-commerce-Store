<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Created 2 roles:
        $customerRole = Role::firstOrCreate(['name' => 'customer', 'guard_name' => 'web']);
        $waiterRole   = Role::firstOrCreate(['name' => 'waiter', 'guard_name' => 'web']);
        $chefRole     = Role::firstOrCreate(['name' => 'chef', 'guard_name' => 'web']);
        $adminRole    = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        //The permissions for waiter and customer:
        $permissions=[
            //For Customer:
            'sign-up','verify-otp','login','logout','recover-password',

            'browse-menu','filter-menu','filter-by-type','filter-by-price','filter-by-most-ordered',
            'view-meal-details','view-offers',

            'add-to-cart','edit-cart','order-cart','cancel-cart','add-to-favorites',

            'make-reservation','view-reservations','cancel-reservation','edit-reservation',

            'receive-notifications',
            //For Waiter:
            'show-tables','show-table-details', 'show-reservation-details','confirm-reservation',

            'open-table','close-table',

            'add-meal-notes','cancel-order','track-order-status',

            'submit-reports'
        ];
        foreach($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);        }
        //give permissions to the roles:
        $customerRole->syncPermissions(['sign-up','verify-otp','login','logout','recover-password'
        ,'browse-menu','filter-menu','filter-by-type','filter-by-price','filter-by-most-ordered','view-meal-details','view-offers',
            'add-to-cart','edit-cart','order-cart','cancel-cart','add-to-favorites',
            'make-reservation','view-reservations','cancel-reservation','edit-reservation',
            'receive-notifications', ]);
        $waiterRole->syncPermissions(['sign-up','login','logout',
            'show-tables','show-table-details', 'show-reservation-details','confirm-reservation',
            'browse-menu','add-to-cart','add-meal-notes','edit-cart','cancel-order','order-cart','track-order-status',
            'open-table','close-table',
            'submit-reports'
            ]);
        $chefRole->syncPermissions(['sign-up','login','logout',]);
        $adminRole->syncPermissions(['sign-up','login','logout',]);

    }
}
