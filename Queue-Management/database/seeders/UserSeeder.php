<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // DEMO ONLY accounts. The shared password comes from config/demo.php.
        // role is not mass-assignable, so it is set explicitly after creating the model.
        $accounts = [
            ['Demo Student', 'student@utg.test', '2020001', 'student'],
            ['Demo Driver', 'driver@utg.test', null, 'driver'],
            ['Demo Driver Two', 'driver2@utg.test', null, 'driver'],
            ['Demo Operator', 'operator@utg.test', null, 'operator'],
            ['Demo Admin', 'admin@utg.test', null, 'admin'],
        ];

        foreach ($accounts as [$name, $email, $studentId, $role]) {
            $user = User::firstOrNew(['email' => $email]);
            $user->fill([
                'name' => $name,
                'student_id' => $studentId,
                'password' => config('demo.password'),
                'is_active' => true,
            ]);
            $user->role = $role;
            $user->save();
        }
    }
}
