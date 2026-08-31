<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 初期管理者ユーザーを作成
        $this->call([
            AdminUserSeeder::class,
            UserSeeder::class,
            ShiftPatternSeeder::class,
            RequestSeeder::class,
        ]);
    }
}
