<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\ShiftPattern;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class ShiftSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 既存のシフトを削除
        Shift::query()->delete();

        // ユーザーとシフトパターンを取得
        $users = User::with('primaryCompany')->get();
        $shiftPatterns = ShiftPattern::all();

        if ($users->isEmpty() || $shiftPatterns->isEmpty()) {
            $this->command->warn('Users or ShiftPatterns not found. Skipping ShiftSeeder.');

            return;
        }

        // 今月と来月のシフトを生成
        $startDate = CarbonImmutable::now()->startOfMonth();
        $endDate = CarbonImmutable::now()->addMonth()->endOfMonth();

        $shifts = [];
        $now = CarbonImmutable::now();

        foreach ($users as $user) {
            $companyId = $user->company_id;

            if (! $companyId) {
                continue;
            }

            // 同じ会社のシフトパターンを取得
            $companyPatterns = $shiftPatterns->where('company_id', $companyId);

            if ($companyPatterns->isEmpty()) {
                // 会社のパターンがない場合はスキップ
                continue;
            }

            $patternArray = $companyPatterns->values()->toArray();
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // ランダムにシフトパターンを選択（一定確率で休み）
                if (fake()->boolean(85)) { // 85%の確率でシフトあり
                    $pattern = fake()->randomElement($patternArray);

                    $shifts[] = [
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'shift_date' => $currentDate->format('Y-m-d'),
                        'shift_pattern_id' => $pattern['id'],
                        'note' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $currentDate = $currentDate->addDay();
            }
        }

        // バルクインサート
        if (! empty($shifts)) {
            collect($shifts)->chunk(500)->each(function ($chunk) {
                Shift::insert($chunk->toArray());
            });

            $this->command->info('Created '.count($shifts).' shifts for '.count($users).' users.');
        }
    }
}
