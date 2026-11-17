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

    private function stampAt(TimeRecordTypeEnum $type, string $dateTime): void
    {
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => $type,
            'record_time' => $dateTime,
            'rounded_time' => $dateTime,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
    }

    /**
     * @return array<string, array{work_start: string|null, work_end: string|null, breaks: array<int, array{start: string, end: string|null}>}>
     */
    private function resolvedRange(string $startDate, string $endDate): array
    {
        return app(RawStampTimeService::class)
            ->mapByDate($this->company->id, $this->user->id, $startDate, $endDate);
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

    /**
     * @test
     */
    public function 連続した夜勤でも退勤は出勤した日の行に返す(): void
    {
        // Arrange: 10/21〜10/22 の夜勤のあと、10/22 は休み、10/23 から2連続の夜勤（クライアント報告の形）
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-21 16:49:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-22 05:18:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-23 16:55:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-24 04:59:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-24 16:53:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-25 04:44:00');

        // Act
        $raw = $this->resolvedRange('2026-10-21', '2026-10-25');

        // Assert: 出勤した日にだけ、その勤務の退勤が入る。休みの日（10/22・10/25）の行は無い
        $this->assertSame('16:49', $raw['2026-10-21']['work_start']);
        $this->assertSame('05:18', $raw['2026-10-21']['work_end']);
        $this->assertArrayNotHasKey('2026-10-22', $raw);
        $this->assertSame('16:55', $raw['2026-10-23']['work_start']);
        $this->assertSame('04:59', $raw['2026-10-23']['work_end']);
        $this->assertSame('16:53', $raw['2026-10-24']['work_start']);
        $this->assertSame('04:44', $raw['2026-10-24']['work_end']);
        $this->assertArrayNotHasKey('2026-10-25', $raw);
    }

    /**
     * @test
     */
    public function 夜勤の日付をまたいだ休憩は出勤した日の勤務に返す(): void
    {
        // Arrange: 11/10 の夜勤で 3:00〜4:30 に休憩し、11/11 も夜勤（21:00 に休憩）
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-11-10 21:52:00');
        $this->stampAt(TimeRecordTypeEnum::BREAK_START, '2026-11-11 03:00:00');
        $this->stampAt(TimeRecordTypeEnum::BREAK_END, '2026-11-11 04:30:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-11 05:11:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-11-11 17:00:00');
        $this->stampAt(TimeRecordTypeEnum::BREAK_START, '2026-11-11 21:00:00');
        $this->stampAt(TimeRecordTypeEnum::BREAK_END, '2026-11-11 21:30:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-11-12 05:14:00');

        // Act
        $raw = $this->resolvedRange('2026-11-10', '2026-11-11');

        // Assert
        $this->assertSame('05:11', $raw['2026-11-10']['work_end']);
        $this->assertSame([['start' => '03:00', 'end' => '04:30']], $raw['2026-11-10']['breaks']);
        $this->assertSame('17:00', $raw['2026-11-11']['work_start']);
        $this->assertSame('05:14', $raw['2026-11-11']['work_end']);
        $this->assertSame([['start' => '21:00', 'end' => '21:30']], $raw['2026-11-11']['breaks']);
    }

    /**
     * @test
     */
    public function 期間の最終日に出勤した夜勤の退勤は最終日の行に返す(): void
    {
        // Arrange: 期間は 10/17 だけ。退勤は期間の翌日（10/18）に打刻される
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-17 16:51:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-18 03:37:00');

        // Act
        $raw = $this->resolvedRange('2026-10-17', '2026-10-17');

        // Assert
        $this->assertSame('16:51', $raw['2026-10-17']['work_start']);
        $this->assertSame('03:37', $raw['2026-10-17']['work_end']);
    }

    /**
     * @test
     */
    public function 期間の初日の前夜の退勤は初日の行に出さない(): void
    {
        // Arrange: 期間は 10/18 から。10/17 に出勤した夜勤の退勤が 10/18 に打刻されている
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-17 16:51:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-18 03:37:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-18 16:57:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-19 05:03:00');

        // Act
        $raw = $this->resolvedRange('2026-10-18', '2026-10-19');

        // Assert
        $this->assertSame('16:57', $raw['2026-10-18']['work_start']);
        $this->assertSame('05:03', $raw['2026-10-18']['work_end']);
        $this->assertArrayNotHasKey('2026-10-19', $raw);
    }

    /**
     * @test
     */
    public function 同じ日の中で終わる勤務の退勤はこれまでどおり返す(): void
    {
        // Arrange: 日勤の翌日に夜勤。日付越え退勤が無い日の打刻は寄せない
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-20 09:00:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END, '2026-10-20 18:00:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_START, '2026-10-21 17:00:00');
        $this->stampAt(TimeRecordTypeEnum::WORK_END_NEXT_DAY, '2026-10-22 05:00:00');

        // Act
        $raw = $this->resolvedRange('2026-10-20', '2026-10-22');

        // Assert
        $this->assertSame('18:00', $raw['2026-10-20']['work_end']);
        $this->assertSame('05:00', $raw['2026-10-21']['work_end']);
        $this->assertArrayNotHasKey('2026-10-22', $raw);
    }
}
