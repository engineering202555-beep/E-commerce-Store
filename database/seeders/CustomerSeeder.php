<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $faker = \Faker\Factory::create('ar_SA'); // عربي
        $customers = [];

        for ($i = 0; $i < 20; $i++) {

            // توليد رقم هاتف فريد
            do {
                $phone = $faker->unique()->phoneNumber;
            } while (DB::table('users')->where('phone_verified_at', $phone)->exists());

            // توليد إيميل فريد (يمكن يكون null أحياناً)
            $email = $faker->unique()->safeEmail;

            // إنشاء المستخدم
            $userId = DB::table('users')->insertGetId([
                'role' => 'customer',
                'email' => $email,
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
                'password' => Hash::make('password123'), // كلمة مرور افتراضية
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // توليد بيانات الزبون
            $customers[] = [
                'user_id' => $userId,
                'first_name' => $faker->firstName,
                'last_name' => $faker->lastName,
                'phone_number' => $phone,
                'location' => $faker->city,
                'points_payment' => rand(0, 500),
                'loyalty_points' => rand(0, 1000),
                'birthdate' => $faker->date(),
                'image' => null, // أو تضع صور افتراضية
                'no_show_count' => rand(0, 3),
                'banned_until' => null,
                'remember_token' => Str::random(10),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('customers')->insert($customers);

        $this->command->info('Customers table seeded successfully!');
    }
}
