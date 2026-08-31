<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now();

        DB::transaction(function () use ($now) {
            // 初期会社を作成
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'デモ会社',
                'is_closed_on_holidays' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // 初期管理者ユーザーを作成
            $admin = User::factory()
                ->admin()
                ->forCompany($companyId)
                ->create([
                    'name' => '管理者',
                    'name_kana' => 'カンリシャ',
                    'employee_code' => '000001',
                    'email' => 'admin@example.com',
                    'email_verified_at' => $now,
                    'password' => Hash::make('password'),
                    'is_retired' => false,
                    'retirement_date' => null,
                ]);

            $this->command->info('初期会社とデータを作成しました:');
            $this->command->info('  会社名: デモ会社');
            $this->command->info('  管理者: admin@example.com / password');
        });
    }
}
