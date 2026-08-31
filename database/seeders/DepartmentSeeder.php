<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // デモ会社のIDを取得
            $company = DB::table('companies')->where('name', 'デモ会社')->first();

            if (! $company) {
                $this->command->error('デモ会社が見つかりません。先にAdminUserSeederを実行してください。');

                return;
            }

            // 部署を作成
            $this->command->info('部署を作成中...');

            $departmentNames = [
                '総務部',
                '人事部',
                '営業部',
                '開発部',
                '企画部',
                'マーケティング部',
                '経理部',
            ];

            foreach ($departmentNames as $name) {
                Department::factory()
                    ->forCompany($company->id)
                    ->create([
                        'name' => $name,
                    ]);
            }

            $this->command->info('部署データを作成しました:');
            $this->command->info('  作成数: '.count($departmentNames).'部署');
        });
    }
}
