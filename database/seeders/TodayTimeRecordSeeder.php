<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\TimeRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 本日の打刻データ（退勤済み・休憩2回）を作成するSeeder
 *
 * 山田太郎以外の全ユーザー対象
 * 朝9:00出勤〜18:00退勤、休憩2回
 */
class TodayTimeRecordSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command->error('会社が見つかりません。');

            return;
        }

        $today = CarbonImmutable::now()->startOfDay();

        // 山田太郎（ID:8）以外のユーザーを取得
        $users = User::whereHas('companies', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->where('id', '!=', 8)->get();

        if ($users->isEmpty()) {
            $this->command->error('対象ユーザーが見つかりません。');

            return;
        }

        // 本日の既存データを削除
        $userIds = $users->pluck('id')->toArray();
        TimeRecord::query()
            ->where('company_id', $company->id)
            ->whereIn('user_id', $userIds)
            ->whereDate('record_time', $today->format('Y-m-d'))
            ->delete();

        DailyWorkSummary::query()
            ->where('company_id', $company->id)
            ->whereIn('user_id', $userIds)
            ->where('work_date', $today->format('Y-m-d'))
            ->delete();

        $this->command->info("本日（{$today->format('Y-m-d')}）の打刻データを作成中...");

        // ユーザーごとに異なるパターンを散りばめる
        $patterns = [
            // [出勤変動(分), 1回目休憩開始時, 1回目休憩時間(分), 2回目休憩開始時, 2回目休憩時間(分), 退勤変動(分)]
            ['start' => -5, 'break1_hour' => 12, 'break1_min' => 0, 'break1_dur' => 60, 'break2_hour' => 15, 'break2_min' => 0, 'break2_dur' => 15, 'end' => 10],
            ['start' => 3, 'break1_hour' => 11, 'break1_min' => 45, 'break1_dur' => 45, 'break2_hour' => 15, 'break2_min' => 30, 'break2_dur' => 10, 'end' => -5],
            ['start' => -10, 'break1_hour' => 12, 'break1_min' => 15, 'break1_dur' => 50, 'break2_hour' => 16, 'break2_min' => 0, 'break2_dur' => 10, 'end' => 30],
            ['start' => 0, 'break1_hour' => 12, 'break1_min' => 30, 'break1_dur' => 60, 'break2_hour' => 15, 'break2_min' => 15, 'break2_dur' => 15, 'end' => 0],
        ];

        $createdCount = 0;

        foreach ($users as $index => $user) {
            $pattern = $patterns[$index % count($patterns)];

            // 出勤 9:00 ± 変動
            $workStart = $today->setTime(9, 0)->addMinutes($pattern['start']);

            // 1回目の休憩
            $break1Start = $today->setTime($pattern['break1_hour'], $pattern['break1_min']);
            $break1End = $break1Start->addMinutes($pattern['break1_dur']);

            // 2回目の休憩
            $break2Start = $today->setTime($pattern['break2_hour'], $pattern['break2_min']);
            $break2End = $break2Start->addMinutes($pattern['break2_dur']);

            // 退勤 18:00 ± 変動
            $workEnd = $today->setTime(18, 0)->addMinutes($pattern['end']);

            // 打刻レコード作成
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::WORK_START, $workStart);
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::BREAK_START, $break1Start);
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::BREAK_END, $break1End);
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::BREAK_START, $break2Start);
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::BREAK_END, $break2End);
            $this->createRecord($company->id, $user->id, TimeRecordTypeEnum::WORK_END, $workEnd);

            // 勤務実績を集計
            $batchService = app(\App\Services\DailyWorkSummaryBatchService::class);
            $batchService->aggregateByUser($company, $user, $today);

            $totalBreak = $pattern['break1_dur'] + $pattern['break2_dur'];
            $this->command->info(sprintf(
                '  %s: 出勤%s 休憩1(%s〜%s) 休憩2(%s〜%s) 退勤%s 休憩計%d分',
                $user->name,
                $workStart->format('H:i'),
                $break1Start->format('H:i'),
                $break1End->format('H:i'),
                $break2Start->format('H:i'),
                $break2End->format('H:i'),
                $workEnd->format('H:i'),
                $totalBreak
            ));

            $createdCount++;
        }

        $this->command->info("完了: {$createdCount}人分の打刻データを作成しました。");
    }

    private function createRecord(
        int $companyId,
        int $userId,
        TimeRecordTypeEnum $recordType,
        CarbonImmutable $recordTime
    ): void {
        $roundedTime = $this->roundTime($recordTime, $recordType);

        TimeRecord::create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'record_type' => $recordType,
            'record_time' => $recordTime,
            'rounded_time' => $roundedTime,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    /**
     * 労働者不利方式で15分単位に丸める
     */
    private function roundTime(CarbonImmutable $time, TimeRecordTypeEnum $type): CarbonImmutable
    {
        $minute = $time->minute;

        if ($type->shouldRoundUp()) {
            // 切り上げ（出勤・休憩終了）
            $rounded = (int) (ceil($minute / 15) * 15);
        } else {
            // 切り捨て（退勤・休憩開始）
            $rounded = (int) (floor($minute / 15) * 15);
        }

        if ($rounded >= 60) {
            return $time->addHour()->setMinute(0)->setSecond(0);
        }

        return $time->setMinute($rounded)->setSecond(0);
    }
}
