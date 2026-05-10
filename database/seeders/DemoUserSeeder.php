<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'operator', 'guard_name' => 'web']);

        $user = User::firstOrCreate(
            ['email' => 'demo@localhost'],
            [
                'name'     => 'Demo',
                'password' => Hash::make('demo'),
            ]
        );

        $user->syncRoles(['operator']);

        $this->command->info("Demo user ready: demo@localhost / demo (operator)");
    }
}
