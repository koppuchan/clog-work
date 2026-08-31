<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftPatternSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = CarbonImmutable::now();

        // 会社IDを取得（最初の会社を使用）
        $companyId = DB::table('companies')->first()?->id;

        if (! $companyId) {
            $this->command->error('会社が見つかりません。先にCompanySeederを実行してください。');

            return;
        }

        DB::transaction(function () use ($now, $companyId) {
            // シフトカラーを作成（存在しない場合のみ）
            $colors = [
                ['background_color' => 'bg-blue-100', 'text_color' => 'text-blue-800'],
                ['background_color' => 'bg-green-100', 'text_color' => 'text-green-800'],
                ['background_color' => 'bg-purple-100', 'text_color' => 'text-purple-800'],
                ['background_color' => 'bg-orange-100', 'text_color' => 'text-orange-800'],
                ['background_color' => 'bg-yellow-100', 'text_color' => 'text-yellow-800'],
            ];

            $colorIds = [];
            foreach ($colors as $color) {
                // 既存のカラーをチェック
                $existingColor = DB::table('shift_colors')
                    ->where('background_color', $color['background_color'])
                    ->where('text_color', $color['text_color'])
                    ->first();

                if ($existingColor) {
                    $colorIds[] = $existingColor->id;
                } else {
                    // 存在しない場合のみ作成
                    $colorId = DB::table('shift_colors')->insertGetId([
                        'background_color' => $color['background_color'],
                        'text_color' => $color['text_color'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    $colorIds[] = $colorId;
                }
            }

            // シフトパターンを作成
            $shiftPatterns = [
                [
                    'name' => '早番',
                    'start_time' => '06:00:00',
                    'end_time' => '15:00:00',
                    'work_minutes' => 480, // 8時間
                    'break_mode' => 1, // 固定休憩
                    'break_minutes' => 60, // 1時間
                    'break_start' => '10:00:00',
                    'break_end' => '11:00:00',
                    'color_id' => $colorIds[0],
                ],
                [
                    'name' => '遅番',
                    'start_time' => '13:00:00',
                    'end_time' => '22:00:00',
                    'work_minutes' => 480, // 8時間
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '17:00:00',
                    'break_end' => '18:00:00',
                    'color_id' => $colorIds[1],
                ],
                [
                    'name' => '夜勤',
                    'start_time' => '22:00:00',
                    'end_time' => '06:00:00',
                    'work_minutes' => 480, // 8時間
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '02:00:00',
                    'break_end' => '03:00:00',
                    'color_id' => $colorIds[2],
                ],
                [
                    'name' => '中番',
                    'start_time' => '09:00:00',
                    'end_time' => '18:00:00',
                    'work_minutes' => 480, // 8時間
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'color_id' => $colorIds[3],
                ],
                [
                    'name' => '長時間',
                    'start_time' => '08:00:00',
                    'end_time' => '20:00:00',
                    'work_minutes' => 660, // 11時間
                    'break_mode' => 1,
                    'break_minutes' => 60,
                    'break_start' => '12:00:00',
                    'break_end' => '13:00:00',
                    'color_id' => $colorIds[4],
                ],
            ];

            foreach ($shiftPatterns as $pattern) {
                $colorId = $pattern['color_id'];
                unset($pattern['color_id']);

                // シフトパターンを作成
                $patternId = DB::table('shift_patterns')->insertGetId([
                    'company_id' => $companyId,
                    'name' => $pattern['name'],
                    'start_time' => $pattern['start_time'],
                    'end_time' => $pattern['end_time'],
                    'work_minutes' => $pattern['work_minutes'],
                    'break_mode' => $pattern['break_mode'],
                    'break_minutes' => $pattern['break_minutes'],
                    'break_start' => $pattern['break_start'],
                    'break_end' => $pattern['break_end'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // シフトパターンとカラーを紐付け
                DB::table('shift_pattern_colors')->insert([
                    'shift_pattern_id' => $patternId,
                    'shift_color_id' => $colorId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->command->info('シフトパターンを作成しました:');
            $this->command->info('  - 早番 (06:00-15:00)');
            $this->command->info('  - 遅番 (13:00-22:00)');
            $this->command->info('  - 夜勤 (22:00-06:00)');
            $this->command->info('  - 中番 (09:00-18:00)');
            $this->command->info('  - 長時間 (08:00-20:00)');
        });
    }
}
