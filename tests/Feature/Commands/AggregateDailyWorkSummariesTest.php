<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AggregateDailyWorkSummariesTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テスト用の会社とユーザーを作成
        $this->company = Company::factory()->create([
            'name' => 'Test Company',
        ]);

        $this->user = User::factory()
            ->forCompany($this->company->id)
            ->create([
                'name' => 'Test User',
                'is_retired' => false,
            ]);
    }

    /**
     * @test
     */
    public function command_aggregates_previous_day_by_default(): void
    {
        // Arrange
        $yesterday = CarbonImmutable::now()->subDay()->startOfDay();
        $dateString = $yesterday->format('Y-m-d');

        // 前日の打刻レコードを作成
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->artisan('batch:aggregate-daily-work-summaries')
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $dateString,
        ]);
    }

    /**
     * @test
     */
    public function command_accepts_date_option(): void
    {
        // Arrange
        $targetDate = '2025-01-15';

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $targetDate.' 09:00:00',
            'rounded_time' => $targetDate.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $targetDate.' 18:00:00',
            'rounded_time' => $targetDate.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->artisan('batch:aggregate-daily-work-summaries', ['--date' => $targetDate])
            ->assertExitCode(Command::SUCCESS);

        // Assert
        $this->assertDatabaseHas('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $targetDate,
        ]);
    }

    /**
     * @test
     */
    public function command_rejects_invalid_date_format(): void
    {
        // Act & Assert
        $this->artisan('batch:aggregate-daily-work-summaries', ['--date' => 'invalid-date'])
            ->assertExitCode(Command::FAILURE);
    }

    /**
     * @test
     */
    public function command_outputs_result_summary(): void
    {
        // Arrange
        $yesterday = CarbonImmutable::now()->subDay()->startOfDay();
        $dateString = $yesterday->format('Y-m-d');

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act & Assert
        $this->artisan('batch:aggregate-daily-work-summaries')
            ->expectsOutputToContain('日次勤務実績集計バッチを開始します')
            ->expectsOutputToContain('集計結果')
            ->expectsOutputToContain('処理件数: 1')
            ->expectsOutputToContain('新規作成: 1')
            ->assertExitCode(Command::SUCCESS);
    }

    /**
     * @test
     */
    public function command_dry_run_does_not_modify_database(): void
    {
        // Arrange
        $yesterday = CarbonImmutable::now()->subDay()->startOfDay();
        $dateString = $yesterday->format('Y-m-d');

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => $dateString.' 09:00:00',
            'rounded_time' => $dateString.' 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => $dateString.' 18:00:00',
            'rounded_time' => $dateString.' 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $this->artisan('batch:aggregate-daily-work-summaries', ['--dry-run' => true])
            ->expectsOutputToContain('ドライランモード')
            ->assertExitCode(Command::SUCCESS);

        // Assert - DBには何も作成されていないこと
        $this->assertDatabaseMissing('daily_work_summaries', [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => $dateString,
        ]);
    }

    /**
     * @test
     */
    public function command_succeeds_when_no_records_to_process(): void
    {
        // 打刻レコードがない状態でコマンドを実行

        // Act & Assert
        $this->artisan('batch:aggregate-daily-work-summaries')
            ->assertExitCode(Command::SUCCESS);
    }
}
