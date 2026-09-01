<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\RecordSourceEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\RawStampTimeService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 帳票・CSVに出力する時刻の検証。
 *
 * 労働時間の計算には丸め時刻を使うが、出力するのは実際に打刻された時刻。
 *   例: 8:58 に打刻 → 計算は 9:00、出力は 8:58
 *
 * 集計テーブルには丸め後の時刻が保存されるため、打刻レコードから
 * 引き直せていることを確認する。
 */
class RawStampTimeExportTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private const DATE = '2026-06-22';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '980001']);
        $this->user = User::factory()->create(['name' => '打刻 太郎']);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    private function stamp(TimeRecordTypeEnum $type, string $raw, string $rounded): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $type,
            'record_time' => self::DATE.' '.$raw,
            'rounded_time' => self::DATE.' '.$rounded,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    /**
     * @return array{work_start: string|null, work_end: string|null, breaks: array<int, array{start: string, end: string|null}>}
     */
    private function resolved(): array
    {
        return app(RawStampTimeService::class)
            ->mapByDate($this->company->id, $this->user->id, self::DATE, self::DATE)[self::DATE];
    }

    /**
     * @test
     */
    public function 出退勤は丸めた時刻ではなく実打刻を返す(): void
    {
        // Arrange: 櫻本さまご指摘の例
        $this->stamp(TimeRecordTypeEnum::WORK_START, '08:58:00', '09:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END, '17:03:00', '17:00:00');

        // Act
        $raw = $this->resolved();

        // Assert
        $this->assertSame('08:58', $raw['work_start']);
        $this->assertSame('17:03', $raw['work_end']);
    }

    /**
     * @test
     */
    public function 休憩も実打刻を返す(): void
    {
        // Arrange
        $this->stamp(TimeRecordTypeEnum::WORK_START, '08:58:00', '09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '12:02:00', '12:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_END, '12:57:00', '13:00:00');
        $this->stamp(TimeRecordTypeEnum::WORK_END, '17:03:00', '17:00:00');

        // Act
        $raw = $this->resolved();

        // Assert
        $this->assertSame('12:02', $raw['breaks'][0]['start']);
        $this->assertSame('12:57', $raw['breaks'][0]['end']);
    }

    /**
     * @test
     */
    public function 休憩2枠をそれぞれ返す(): void
    {
        // Arrange
        $this->stamp(TimeRecordTypeEnum::WORK_START, '08:58:00', '09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '12:02:00', '12:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_END, '12:57:00', '13:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '15:01:00', '15:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_END, '15:14:00', '15:15:00');

        // Act
        $raw = $this->resolved();

        // Assert
        $this->assertCount(2, $raw['breaks']);
        $this->assertSame('15:01', $raw['breaks'][1]['start']);
        $this->assertSame('15:14', $raw['breaks'][1]['end']);
    }

    /**
     * @test
     */
    public function 休憩終了が未打刻でも開始だけ返す(): void
    {
        // Arrange: 休憩から戻り忘れたケース
        $this->stamp(TimeRecordTypeEnum::WORK_START, '08:58:00', '09:00:00');
        $this->stamp(TimeRecordTypeEnum::BREAK_START, '12:02:00', '12:00:00');

        // Act
        $raw = $this->resolved();

        // Assert
        $this->assertSame('12:02', $raw['breaks'][0]['start']);
        $this->assertNull($raw['breaks'][0]['end']);
    }

    /**
     * @test
     */
    public function 退勤が未打刻なら出勤のみ返す(): void
    {
        // Arrange
        $this->stamp(TimeRecordTypeEnum::WORK_START, '08:58:00', '09:00:00');

        // Act
        $raw = $this->resolved();

        // Assert
        $this->assertSame('08:58', $raw['work_start']);
        $this->assertNull($raw['work_end']);
    }
}
