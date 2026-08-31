<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\TimeRecordCorrection;
use App\Models\TimeRecordCorrectionRequest;
use App\Models\TimeRecordCorrectionRequestDetail;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * 打刻修正履歴モックデータSeeder
 *
 * time_record_corrections に申請経由・管理者直接修正の両パターンのモックデータを作成する。
 * 前提: TimeRecordSeeder または NovemberWorkDataSeeder が実行済みであること。
 */
class TimeRecordCorrectionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::first();

        if (! $company) {
            $this->command->error('会社が見つかりません。先にAdminUserSeederを実行してください。');

            return;
        }

        $users = User::whereHas('companies', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->get();

        if ($users->isEmpty()) {
            $this->command->error('ユーザーが見つかりません。先にUserSeederを実行してください。');

            return;
        }

        // 管理者/責任者を承認者・修正者として取得
        $approvers = $users->filter(function ($user) {
            return $user->isAdmin() || $user->isManager();
        });

        if ($approvers->isEmpty()) {
            $this->command->error('管理者/責任者が見つかりません。');

            return;
        }

        $this->command->info('打刻修正履歴データを作成中...');

        $requestCorrectionCount = 0;
        $adminCorrectionCount = 0;

        foreach ($users as $user) {
            // このユーザーの打刻レコードを取得（WORK_START, WORK_ENDを優先）
            $timeRecords = TimeRecord::query()
                ->where('company_id', $company->id)
                ->where('user_id', $user->id)
                ->whereIn('record_type', [1, 2, 3, 4, 5]) // 全種別
                ->orderBy('record_time', 'desc')
                ->limit(20)
                ->get();

            if ($timeRecords->count() < 4) {
                continue;
            }

            // ランダムに2〜4件選択
            $selectedRecords = $timeRecords->random(min(fake()->numberBetween(2, 4), $timeRecords->count()));
            $approver = $approvers->random();

            foreach ($selectedRecords as $record) {
                // 50%の確率で申請経由 or 管理者直接修正
                if (fake()->boolean()) {
                    $this->createRequestCorrection($company->id, $user->id, $record, $approver);
                    $requestCorrectionCount++;
                } else {
                    $this->createAdminCorrection($record, $approver);
                    $adminCorrectionCount++;
                }
            }
        }

        $this->command->info("打刻修正履歴データを作成しました（申請経由: {$requestCorrectionCount}件, 管理者修正: {$adminCorrectionCount}件）");
    }

    /**
     * 申請経由の修正履歴を作成
     */
    private function createRequestCorrection(int $companyId, int $userId, TimeRecord $record, User $approver): void
    {
        $now = CarbonImmutable::now();
        $targetDate = $record->record_time->format('Y-m-d');

        // before_time: 現在の record_time から ±15分ずらした値（修正前の打刻）
        $offsetMinutes = fake()->numberBetween(-15, 15);
        if ($offsetMinutes === 0) {
            $offsetMinutes = fake()->randomElement([-5, 5, -10, 10]);
        }
        $beforeTime = CarbonImmutable::parse($record->record_time)->addMinutes($offsetMinutes);
        $beforeRoundedTime = $this->roundTime($beforeTime);

        // 打刻修正申請ヘッダーを作成
        $correctionRequest = TimeRecordCorrectionRequest::query()->create([
            'company_id' => $companyId,
            'user_id' => $userId,
            'target_date' => $targetDate,
            'reason' => fake()->randomElement([
                '打刻忘れのため修正申請',
                '打刻時刻が間違っていました',
                'システムエラーで打刻がずれました',
                '端末不具合のため修正をお願いします',
            ]),
            'status' => RequestStatusEnum::APPROVED,
            'approver_id' => $approver->id,
            'approved_at' => $now->subDays(fake()->numberBetween(1, 7)),
        ]);

        // 申請明細を作成
        $detail = TimeRecordCorrectionRequestDetail::query()->create([
            'correction_request_id' => $correctionRequest->id,
            'time_record_id' => $record->id,
            'record_type' => $record->record_type,
            'original_record_time' => $beforeTime,
            'original_rounded_time' => $beforeRoundedTime,
            'corrected_record_time' => $record->record_time,
            'corrected_rounded_time' => $record->rounded_time,
        ]);

        // 修正履歴を作成
        TimeRecordCorrection::query()->create([
            'correction_request_detail_id' => $detail->id,
            'time_record_id' => $record->id,
            'record_type' => $record->record_type->value,
            'before_record_time' => $beforeTime,
            'before_rounded_time' => $beforeRoundedTime,
            'before_record_source' => RecordSourceEnum::AUTO->value,
            'after_record_time' => $record->record_time,
            'after_rounded_time' => $record->rounded_time,
            'after_record_source' => RecordSourceEnum::REQUEST->value,
            'corrected_by' => $approver->id,
            'correction_note' => $correctionRequest->reason,
        ]);
    }

    /**
     * 管理者直接修正の修正履歴を作成
     */
    private function createAdminCorrection(TimeRecord $record, User $approver): void
    {
        // before_time: 現在の record_time から ±30分ずらした値（修正前の打刻）
        $offsetMinutes = fake()->numberBetween(-30, 30);
        if ($offsetMinutes === 0) {
            $offsetMinutes = fake()->randomElement([-10, 10, -20, 20]);
        }
        $beforeTime = CarbonImmutable::parse($record->record_time)->addMinutes($offsetMinutes);
        $beforeRoundedTime = $this->roundTime($beforeTime);

        TimeRecordCorrection::query()->create([
            'correction_request_detail_id' => null,
            'time_record_id' => $record->id,
            'record_type' => $record->record_type->value,
            'before_record_time' => $beforeTime,
            'before_rounded_time' => $beforeRoundedTime,
            'before_record_source' => RecordSourceEnum::AUTO->value,
            'after_record_time' => $record->record_time,
            'after_rounded_time' => $record->rounded_time,
            'after_record_source' => RecordSourceEnum::MANUAL->value,
            'corrected_by' => $approver->id,
            'correction_note' => '管理者による修正',
        ]);
    }

    /**
     * 時刻を15分単位で丸める
     */
    private function roundTime(CarbonImmutable $time): CarbonImmutable
    {
        $minute = $time->minute;
        $roundedMinute = (int) (ceil($minute / 15) * 15);

        if ($roundedMinute >= 60) {
            return $time->addHour()->setMinute(0)->setSecond(0);
        }

        return $time->setMinute($roundedMinute)->setSecond(0);
    }
}
