<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Super Admin
        User::updateOrCreate(
            ['email' => 'admin@megawifi.com'],
            [
                'name' => 'مدير النظام',
                'password' => Hash::make('MegaWifi2026@'),
                'role' => 'super_admin',
            ]
        );

        // Create demo admin
        User::updateOrCreate(
            ['email' => 'demo@megawifi.com'],
            [
                'name' => 'حساب تجريبي',
                'password' => Hash::make('demo123'),
                'role' => 'admin',
            ]
        );
    }
}
