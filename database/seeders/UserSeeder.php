<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin account (test only, remove before deployment)
        User::updateOrCreate(
            ['email' => 'admin@vnr.test'],
            [
                'name' => 'Test Admin',
                'password' => Hash::make('password123'),
                'role' => 'admin',
            ]
        );

        // Guest accounts
        User::updateOrCreate(
            ['email' => 'guest1@vnr.test'],
            [
                'name' => 'Test Guest One',
                'password' => Hash::make('password123'),
                'role' => 'guest',
            ]
        );

        User::updateOrCreate(
            ['email' => 'guest2@vnr.test'],
            [
                'name' => 'Test Guest Two',
                'password' => Hash::make('password123'),
                'role' => 'guest',
            ]
        );
    }
}
