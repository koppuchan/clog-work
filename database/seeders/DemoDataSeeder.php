<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AlertLevelEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Models\CompanyRegularHoliday;
use App\Models\CompanyShiftDisplaySetting;
use App\Models\CompanyShiftRoundingSetting;
use App\Models\DailyWorkSummary;
use App\Models\Department;
use App\Models\Shift;
use App\Models\ShiftColor;
use App\Models\ShiftPattern;
use App\Models\ShiftPatternColor;
use App\Models\TimeRecord;
use App\Models\User;
use App\Models\UserAvailableWorkDay;
use App\Models\UserPermission;
use App\Models\UserShiftPattern;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 検証環境確認用Seeder
 *
 * 会社、部署、シフトパターン、ユーザー4人とその関連データ、12月分の勤務実績を一括作成
 */
class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            // ========================================
            // 1. 会社を作成
            // ========================================
            $maxCode = Company::query()->max('company_code') ?? '100000';
            $newCode = str_pad((string) ((int) $maxCode + 1), 6, '0', STR_PAD_LEFT);

            $company = Company::create([
                'company_code' => $newCode,
                'name' => 'デモ株式会社',
                'is_closed_on_holidays' => true,
                'payroll_closing_day' => 25,
                'paid_leave_half_day' => true,
                'paid_leave_hourly' => false,
            ]);

            $this->command->info("会社を作成しました: デモ株式会社 ({$newCode})");

            // ========================================
            // 2. 会社定休日を作成（土日）
            // ========================================
            foreach ([6, 7] as $weekdayId) {
                CompanyRegularHoliday::firstOrCreate([
                    'company_id' => $company->id,
                    'weekday_id' => $weekdayId,
                ]);
            }

            $this->command->info('会社定休日を設定しました: 土曜・日曜');

            // ========================================
            // 3. 会社打刻丸め設定（15分単位）
            // ========================================
            CompanyShiftRoundingSetting::firstOrCreate(
                ['company_id' => $company->id],
                ['rounding_unit_id' => 4]
            );

            $this->command->info('打刻丸め設定: 15分単位');

            // ========================================
            // 4. 会社シフト表示設定（月初〜月末）
            // ========================================
            CompanyShiftDisplaySetting::firstOrCreate(
                ['company_id' => $company->id],
                ['shift_period_id' => 1]
            );

            // ========================================
            // 5. 部署を作成
            // ========================================
            $departments = [];
            foreach (['総務部', '営業部', '開発部'] as $name) {
                $departments[$name] = Department::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $name]
                );
            }

            $this->command->info('部署を作成しました: 総務部、営業部、開発部');

            // ========================================
            // 6. シフトカラーを作成（存在しない場合のみ）
            // ========================================
            $colors = [
                ['bg' => 'bg-blue-100', 'text' => 'text-blue-800'],
                ['bg' => 'bg-green-100', 'text' => 'text-green-800'],
                ['bg' => 'bg-orange-100', 'text' => 'text-orange-800'],
            ];

            $shiftColors = [];
            foreach ($colors as $color) {
                $shiftColors[] = ShiftColor::firstOrCreate([
                    'background_color' => $color['bg'],
                    'text_color' => $color['text'],
                ]);
            }

            // ========================================
            // 7. シフトパターンを作成
            // ========================================
            $shiftPatternDefs = [
                [
                    'name' => '日勤',
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'work_minutes' => 480,
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'color_index' => 0,
                ],
                [
                    'name' => '早番',
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                    'work_minutes' => 480,
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'color_index' => 1,
                ],
                [
                    'name' => '遅番',
                    'start_time' => '10:00:00',
                    'end_time' => '19:00:00',
                    'work_minutes' => 480,
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '13:00:00',
                    'break_end' => '14:00:00',
                    'color_index' => 2,
                ],
            ];

            $shiftPatternData = [];
            foreach ($shiftPatternDefs as $def) {
                $colorIndex = $def['color_index'];
                unset($def['color_index']);

                $pattern = ShiftPattern::firstOrCreate(
                    ['company_id' => $company->id, 'name' => $def['name']],
                    [
                        'start_time' => $def['start_time'],
                        'end_time' => $def['end_time'],
                        'work_minutes' => $def['work_minutes'],
                        'break_mode' => $def['break_mode'],
                        'break_minutes' => $def['break_minutes'],
                        'break_start' => $def['break_start'],
                        'break_end' => $def['break_end'],
                    ]
                );

                $shiftPatternData[$def['name']] = [
                    'id' => $pattern->id,
                    'start_time' => $def['start_time'],
                    'end_time' => $def['end_time'],
                    'break_minutes' => $def['break_minutes'],
                ];

                ShiftPatternColor::firstOrCreate([
                    'shift_pattern_id' => $pattern->id,
                    'shift_color_id' => $shiftColors[$colorIndex]->id,
                ]);
            }

            $this->command->info('シフトパターンを作成しました: 日勤、早番、遅番');

            // ========================================
            // 8. 労務アラート設定
            // ========================================
            CompanyLaborAlertSetting::firstOrCreate(
                ['company_id' => $company->id, 'alert_level_id' => AlertLevelEnum::CAUTION],
                ['overtime_threshold_hours' => 30, 'is_enabled' => true]
            );
            CompanyLaborAlertSetting::firstOrCreate(
                ['company_id' => $company->id, 'alert_level_id' => AlertLevelEnum::WARNING],
                ['overtime_threshold_hours' => 45, 'is_enabled' => true]
            );

            CompanyAlertMessage::firstOrCreate(
                ['company_id' => $company->id, 'alert_level_id' => AlertLevelEnum::CAUTION],
                [
                    'title' => '残業時間注意',
                    'message' => '{user_name}さんの今月の残業時間が{hours}時間に達しました。（閾値: {threshold}時間）',
                ]
            );
            CompanyAlertMessage::firstOrCreate(
                ['company_id' => $company->id, 'alert_level_id' => AlertLevelEnum::WARNING],
                [
                    'title' => '残業時間警告',
                    'message' => '{user_name}さんの今月の残業時間が{hours}時間を超えました！（閾値: {threshold}時間）業務調整を検討してください。',
                ]
            );

            $this->command->info('労務アラート設定を作成しました');

            // ========================================
            // 9. ユーザーを作成（4人）
            // ========================================
            // 会社コードをベースにユニークなemailとemployee_codeを生成
            $companySuffix = ltrim($newCode, '0');
            $employeeCodePrefix = substr($newCode, -4);

            $users = [
                [
                    'name' => '山田 太郎',
                    'name_kana' => 'ヤマダ タロウ',
                    'email_local' => 'yamada',
                    'role_id' => 1,
                    'department' => '総務部',
                    'shift_pattern' => '日勤',
                ],
                [
                    'name' => '佐藤 花子',
                    'name_kana' => 'サトウ ハナコ',
                    'email_local' => 'sato',
                    'role_id' => 2,
                    'department' => '営業部',
                    'shift_pattern' => '早番',
                ],
                [
                    'name' => '鈴木 一郎',
                    'name_kana' => 'スズキ イチロウ',
                    'email_local' => 'suzuki',
                    'role_id' => 3,
                    'department' => '営業部',
                    'shift_pattern' => '日勤',
                ],
                [
                    'name' => '田中 美咲',
                    'name_kana' => 'タナカ ミサキ',
                    'email_local' => 'tanaka',
                    'role_id' => 3,
                    'department' => '開発部',
                    'shift_pattern' => '遅番',
                ],
            ];

            $userRecords = [];
            $createdUsers = [];
            foreach ($users as $index => $userData) {
                $employeeCode = $employeeCodePrefix.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
                $email = "{$userData['email_local']}+{$companySuffix}@demo.com";

                $user = User::create([
                    'name' => $userData['name'],
                    'name_kana' => $userData['name_kana'],
                    'employee_code' => $employeeCode,
                    'email' => $email,
                    'email_verified_at' => CarbonImmutable::now(),
                    'password' => 'password',
                    'must_change_password' => false,
                    'is_retired' => false,
                    'retirement_date' => null,
                ]);

                $userRecords[] = [
                    'user' => $user,
                    'shift_pattern' => $userData['shift_pattern'],
                ];

                $createdUsers[] = [
                    'role_id' => $userData['role_id'],
                    'email' => $email,
                    'employee_code' => $employeeCode,
                ];

                // user_companies
                $user->companies()->attach($company->id, ['is_primary' => true]);

                // user_roles
                $user->roles()->attach($userData['role_id']);

                // user_departments
                $departmentId = $departments[$userData['department']]->id;
                $user->departments()->attach($departmentId, ['is_primary' => true]);

                // user_available_work_days
                for ($weekdayId = 1; $weekdayId <= 7; $weekdayId++) {
                    UserAvailableWorkDay::create([
                        'user_id' => $user->id,
                        'weekday_id' => $weekdayId,
                        'is_available' => $weekdayId <= 5,
                    ]);
                }

                // user_shift_patterns
                $patternId = $shiftPatternData[$userData['shift_pattern']]['id'];
                for ($weekdayId = 1; $weekdayId <= 7; $weekdayId++) {
                    UserShiftPattern::create([
                        'user_id' => $user->id,
                        'weekday_id' => $weekdayId,
                        'shift_pattern_id' => $weekdayId <= 5 ? $patternId : null,
                    ]);
                }

                // user_permissions
                if ($userData['role_id'] !== 1) {
                    UserPermission::create([
                        'user_id' => $user->id,
                        'resource_id' => 1,
                        'scope_id' => 2,
                    ]);

                    UserPermission::create([
                        'user_id' => $user->id,
                        'resource_id' => 2,
                        'scope_id' => $userData['role_id'] === 2 ? 2 : 1,
                    ]);

                    if ($userData['role_id'] === 2) {
                        UserPermission::create([
                            'user_id' => $user->id,
                            'resource_id' => 3,
                            'scope_id' => 2,
                        ]);
                        UserPermission::create([
                            'user_id' => $user->id,
                            'resource_id' => 4,
                            'scope_id' => 2,
                        ]);
                    }
                }

                $roleName = match ($userData['role_id']) {
                    1 => '管理者',
                    2 => '責任者',
                    3 => '一般',
                };
                $this->command->info("  ユーザー作成: {$userData['name']} ({$roleName}) - {$email} / {$employeeCode}");
            }

            // ========================================
            // 10. 12月分のシフト・勤務実績を作成
            // ========================================
            $this->command->info('');
            $this->command->info('12月分の勤務実績を作成中...');

            $december2024 = CarbonImmutable::create(2024, 12, 1);
            $totalWorkMinutes = [];

            foreach ($userRecords as $userInfo) {
                $user = $userInfo['user'];
                $shiftPatternName = $userInfo['shift_pattern'];
                $pattern = $shiftPatternData[$shiftPatternName];
                $totalWorkMinutes[$user->id] = 0;

                // 12月1日〜31日
                for ($day = 1; $day <= 31; $day++) {
                    $date = $december2024->setDay($day);
                    $dayOfWeek = $date->dayOfWeek; // 0=日曜, 1=月曜, ..., 6=土曜

                    // 土日はスキップ
                    if ($dayOfWeek === 0 || $dayOfWeek === 6) {
                        continue;
                    }

                    // シフトを作成
                    Shift::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'user_id' => $user->id,
                            'shift_date' => $date->format('Y-m-d'),
                        ],
                        [
                            'shift_pattern_id' => $pattern['id'],
                            'note' => null,
                        ]
                    );

                    // 打刻レコードを作成（少しランダムな変動を加える）
                    $startVariation = rand(-5, 10); // -5分〜+10分の変動
                    $endVariation = rand(-10, 30); // -10分〜+30分の変動（残業含む）

                    $scheduledStart = CarbonImmutable::parse($date->format('Y-m-d').' '.$pattern['start_time']);
                    $scheduledEnd = CarbonImmutable::parse($date->format('Y-m-d').' '.$pattern['end_time']);

                    $actualStart = $scheduledStart->addMinutes($startVariation);
                    $actualEnd = $scheduledEnd->addMinutes($endVariation);

                    // 出勤打刻
                    TimeRecord::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'user_id' => $user->id,
                            'record_type' => TimeRecordTypeEnum::WORK_START,
                            'record_time' => $actualStart,
                        ],
                        [
                            'rounded_time' => $actualStart->startOfMinute(),
                            'record_source' => RecordSourceEnum::AUTO,
                            'note' => null,
                        ]
                    );

                    // 退勤打刻
                    TimeRecord::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'user_id' => $user->id,
                            'record_type' => TimeRecordTypeEnum::WORK_END,
                            'record_time' => $actualEnd,
                        ],
                        [
                            'rounded_time' => $actualEnd->startOfMinute(),
                            'record_source' => RecordSourceEnum::AUTO,
                            'note' => null,
                        ]
                    );

                    // 勤務時間計算
                    $workMinutes = (int) $actualStart->diffInMinutes($actualEnd, false);
                    $breakMinutes = $pattern['break_minutes'];
                    $netWorkMinutes = $workMinutes - $breakMinutes;
                    $scheduledWorkMinutes = (int) $scheduledStart->diffInMinutes($scheduledEnd, false) - $breakMinutes;
                    $overtimeMinutes = max(0, $netWorkMinutes - $scheduledWorkMinutes);

                    $totalWorkMinutes[$user->id] += $netWorkMinutes;

                    // 勤務日実績を作成
                    DailyWorkSummary::firstOrCreate(
                        [
                            'company_id' => $company->id,
                            'user_id' => $user->id,
                            'work_date' => $date->format('Y-m-d'),
                        ],
                        [
                            'scheduled_start_time' => $pattern['start_time'],
                            'scheduled_end_time' => $pattern['end_time'],
                            'work_start' => $actualStart,
                            'work_end' => $actualEnd,
                            'work_minutes' => $workMinutes,
                            'break_minutes' => $breakMinutes,
                            'net_work_minutes' => $netWorkMinutes,
                            'night_minutes' => 0,
                            'holiday_minutes' => 0,
                            'overtime_minutes' => $overtimeMinutes,
                            'late_minutes' => max(0, $startVariation),
                            'early_leave_minutes' => 0,
                            'is_cross_day' => false,
                            'record_source' => RecordSourceEnum::AUTO,
                            'note' => null,
                        ]
                    );
                }
            }

            $this->command->info('12月分の勤務実績を作成しました（平日22日分）');

            $this->command->info('');
            $this->command->info('========================================');
            $this->command->info('検証用データの作成が完了しました！');
            $this->command->info('========================================');
            $this->command->info('');
            $this->command->info('【ログイン情報】');
            $this->command->info("会社コード: {$newCode}");
            $this->command->info('');
            foreach ($createdUsers as $cu) {
                $roleName = match ($cu['role_id']) {
                    1 => '管理者',
                    2 => '責任者',
                    3 => '一般',
                };
                $this->command->info("{$roleName}: {$cu['email']} / password (個人コード: {$cu['employee_code']})");
            }
        });
    }
}
