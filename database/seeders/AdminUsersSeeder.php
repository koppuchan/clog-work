<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminUsersSeeder extends Seeder
{
    /**
     * 管理者ユーザーを3人作成する。
     */
    public function run(): void
    {
        $now = CarbonImmutable::now();

        $companyId = DB::table('companies')->where('name', 'デモ株式会社')->value('id');

        if (! $companyId) {
            $this->command->error('会社が存在しません。先に AdminUserSeeder を実行してください。');

            return;
        }

        $maxCode = (int) User::max('employee_code');

        DB::transaction(function () use ($companyId, $maxCode, $now) {
            $admins = [
                ['name' => '管理者A', 'name_kana' => 'カンリシャエー'],
                ['name' => '管理者B', 'name_kana' => 'カンリシャビー'],
                ['name' => '管理者C', 'name_kana' => 'カンリシャシー'],
            ];

            foreach ($admins as $i => $admin) {
                $code = str_pad((string) ($maxCode + $i + 1), 6, '0', STR_PAD_LEFT);

                $user = User::create([
                    'name' => $admin['name'],
                    'name_kana' => $admin['name_kana'],
                    'employee_code' => $code,
                    'email' => 'admin'.$code.'@example.com',
                    'email_verified_at' => $now,
                    'password' => Hash::make('password'),
                    'is_retired' => false,
                    'retirement_date' => null,
                ]);

                // 管理者ロールを付与
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 会社に所属
                DB::table('user_companies')->insert([
                    'user_id' => $user->id,
                    'company_id' => $companyId,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // 勤務可能曜日を作成（月〜金を勤務可能に設定）
                $weekdays = DB::table('weekdays')->get();
                foreach ($weekdays as $weekday) {
                    $isAvailable = in_array($weekday->id, [2, 3, 4, 5, 6]);

                    DB::table('user_available_work_days')->insert([
                        'user_id' => $user->id,
                        'weekday_id' => $weekday->id,
                        'is_available' => $isAvailable,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->command->info("  管理者作成: {$admin['name']} (社員番号: {$code})");
            }

            $this->command->info('管理者3人を作成しました。パスワード: password');
        });
    }
}
