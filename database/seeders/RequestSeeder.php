<?php

namespace Database\Seeders;

use App\Enums\RequestStatusEnum;
use App\Enums\RequestTypeEnum;
use App\Models\Company;
use App\Models\Request;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
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

        // 会社に所属するユーザーを取得
        $users = User::whereHas('companies', function ($query) use ($company) {
            $query->where('company_id', $company->id);
        })->get();

        if ($users->isEmpty()) {
            $this->command->error('ユーザーが見つかりません。先にUserSeederを実行してください。');

            return;
        }

        // 管理者/責任者を承認者として取得
        $approvers = $users->filter(function ($user) {
            return $user->isAdmin() || $user->isManager();
        });

        $now = CarbonImmutable::now();
        $requestTypes = RequestTypeEnum::cases();
        $createdCount = 0;

        // 各ユーザーに対してランダムに申請を作成
        foreach ($users as $user) {
            // 1ユーザーあたり0〜3件の申請を作成
            $requestCount = fake()->numberBetween(0, 3);

            for ($i = 0; $i < $requestCount; $i++) {
                $type = fake()->randomElement($requestTypes);
                $status = fake()->randomElement(RequestStatusEnum::cases());
                $targetDate = $now->addDays(fake()->numberBetween(-30, 30));

                $startTime = null;
                $endTime = null;

                // 時間指定が必要なタイプの場合
                if ($type->requiresTime()) {
                    $startTime = fake()->time('H:i');
                    $endTime = fake()->time('H:i');
                }

                $approver = null;
                $decidedAt = null;
                $decisionNote = null;

                // 承認済み・却下の場合は承認者情報を設定
                if ($status !== RequestStatusEnum::PENDING && $status !== RequestStatusEnum::CANCELLED) {
                    $approver = $approvers->random();
                    $decidedAt = $now->subDays(fake()->numberBetween(1, 7));
                    $decisionNote = $status === RequestStatusEnum::REJECTED
                        ? fake()->randomElement(['業務都合により却下', '人員不足のため', '日程調整をお願いします'])
                        : null;
                }

                Request::create([
                    'company_id' => $company->id,
                    'requested_by' => $user->id,
                    'type' => $type,
                    'target_date' => $targetDate,
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'reason' => $this->generateReason($type),
                    'status' => $status,
                    'approver_id' => $approver?->id,
                    'decided_at' => $decidedAt,
                    'decision_note' => $decisionNote,
                ]);

                $createdCount++;
            }
        }

        $this->command->info("申請データを{$createdCount}件作成しました。");
    }

    /**
     * 申請タイプに応じた理由を生成
     */
    private function generateReason(RequestTypeEnum $type): string
    {
        return match ($type) {
            RequestTypeEnum::LEAVE => fake()->randomElement([
                '私用のため',
                '家族の用事',
                '通院のため',
                'リフレッシュ休暇',
                '旅行のため',
            ]),
            RequestTypeEnum::LATE => fake()->randomElement([
                '電車遅延のため',
                '通院のため',
                '役所手続きのため',
                '子供の送り迎え',
            ]),
            RequestTypeEnum::EARLY_LEAVE => fake()->randomElement([
                '体調不良のため',
                '通院のため',
                '家庭の事情',
                '子供の迎え',
            ]),
            RequestTypeEnum::ABSENCE => fake()->randomElement([
                '体調不良のため',
                '家族の看病',
                '忌引き',
                '急用のため',
            ]),
            RequestTypeEnum::BREAK => fake()->randomElement([
                '通院のため外出',
                '銀行手続き',
                '役所手続き',
            ]),
            RequestTypeEnum::OTHER => fake()->randomElement([
                '在宅勤務希望',
                '勤務時間変更希望',
                'その他の理由',
            ]),
        };
    }
}
