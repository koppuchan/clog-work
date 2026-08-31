<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // デモ会社のIDを取得（AdminUserSeederで作成済み）
            $company = DB::table('companies')->where('name', 'デモ会社')->first();

            if (! $company) {
                $this->command->error('デモ会社が見つかりません。先にAdminUserSeederを実行してください。');

                return;
            }

            // 管理者ユーザーを2人作成
            $this->command->info('管理者ユーザーを作成中...');
            User::factory()
                ->count(2)
                ->admin()
                ->forCompany($company->id)
                ->create();

            // 責任者ユーザーを5人作成
            $this->command->info('責任者ユーザーを作成中...');
            User::factory()
                ->count(5)
                ->manager()
                ->forCompany($company->id)
                ->create();

            // 一般ユーザーを20人作成
            $this->command->info('一般ユーザーを作成中...');
            User::factory()
                ->count(20)
                ->employee()
                ->forCompany($company->id)
                ->create();

            // 退職済みユーザーを3人作成
            $this->command->info('退職済みユーザーを作成中...');
            User::factory()
                ->count(3)
                ->employee()
                ->retired()
                ->forCompany($company->id)
                ->create();

            $this->command->info('ユーザーデータを作成しました:');
            $this->command->info('  管理者: 2人');
            $this->command->info('  責任者: 5人');
            $this->command->info('  一般: 20人');
            $this->command->info('  退職済み: 3人');
            $this->command->info('  合計: 30人（+ 初期管理者1人 = 31人）');
            $this->command->info('');
            $this->command->info('全ユーザーのパスワード: password');
        });
    }
}
