<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Services\WorkTypeAndNoteService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * 勤務区分・備考欄の判定ロジック（WorkTypeAndNoteService）の検証。
 *
 * 帳票（Excel: AttendanceExcelExportService）とCSV
 * （DailyWorkSummaryService::generateCsvAll）は、このクラスを共通で使って
 * いる。以前は同じ内容のロジックを2箇所に別々に書いており、片方だけ直して
 * 他方に反映し忘れる不具合（#70の「欠勤は実打刻があれば優先しない」という
 * 例外を、#72で備考欄のロジックを追加した際に入れ忘れ、出勤している全員の
 * 備考欄に「欠勤」が表示される不具合になった）が起きていた。判定を一本化
 * した今、ここが両方の出力の唯一のテスト対象になる
 * （帳票・CSV側の出力形式そのものは AttendanceExcelExportTest /
 * DailyWorkSummaryCsvExportColumnsTest で別途確認している）。
 */
class WorkTypeAndNoteServiceTest extends TestCase
{
    private WorkTypeAndNoteService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkTypeAndNoteService::class);
    }

    // ========================================
    // resolveWorkType
    // ========================================

    /**
     * @test
     */
    public function シフトあり_出退勤ありは出勤になる(): void
    {
        $summary = (object) ['leave_type' => null, 'scheduled_start_time' => '09:00'];

        $this->assertSame('出勤', $this->service->resolveWorkType($summary, '09:00'));
    }

    /**
     * @test
     */
    public function シフトあり_出退勤なしは欠勤になる(): void
    {
        $summary = (object) ['leave_type' => null, 'scheduled_start_time' => '09:00'];

        $this->assertSame('欠勤', $this->service->resolveWorkType($summary, null));
    }

    /**
     * @test
     */
    public function シフトなし_出退勤ありは休出になる(): void
    {
        $summary = (object) ['leave_type' => null, 'scheduled_start_time' => null];

        $this->assertSame('休出', $this->service->resolveWorkType($summary, '09:00'));
    }

    /**
     * @test
     */
    public function シフトなし_出退勤なしは休日になる(): void
    {
        $summary = (object) ['leave_type' => null, 'scheduled_start_time' => null];

        $this->assertSame('休日', $this->service->resolveWorkType($summary, null));
    }

    /**
     * @test
     */
    public function 勤務実績そのものが無い日は休日になる(): void
    {
        $this->assertSame('休日', $this->service->resolveWorkType(null, null));
    }

    /**
     * @test
     */
    public function 休暇種別があればシフト_出退勤の有無に関わらずそれを優先する(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::PAID_LEAVE, 'scheduled_start_time' => '09:00'];

        $this->assertSame('有給休暇', $this->service->resolveWorkType($summary, '09:00'));
    }

    /**
     * @test
     *
     * 「欠勤」の休暇種別だけは例外で、実打刻があれば優先しない
     * （入江さまの9/18のケース: 欠勤の承認済み申請が残ったまま実際は出勤していた）。
     */
    public function 欠勤の休暇種別があっても実打刻があれば出勤になる(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::ABSENCE, 'scheduled_start_time' => '09:00'];

        $this->assertSame('出勤', $this->service->resolveWorkType($summary, '08:51'));
    }

    /**
     * @test
     */
    public function 欠勤の休暇種別は実打刻が無ければ優先される(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::ABSENCE, 'scheduled_start_time' => null];

        $this->assertSame('欠勤', $this->service->resolveWorkType($summary, null));
    }

    // ========================================
    // buildNoteEntries
    // ========================================

    /**
     * @test
     */
    public function 申請が無くleave_typeも無ければ空になる(): void
    {
        $summary = (object) ['leave_type' => null];

        $this->assertSame([], $this->service->buildNoteEntries($summary, collect(), 480));
    }

    /**
     * @test
     *
     * 申請を経由しないleave_type（CSV移行データ等）も備考欄に補う（#72）。
     */
    public function 申請を経由しないleave_typeを補う(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::PAID_LEAVE, 'leave_minutes' => null];

        $entries = $this->service->buildNoteEntries($summary, collect(), 480);

        $this->assertSame([['label' => '有給休暇', 'value' => '1.0']], $entries);
    }

    /**
     * @test
     *
     * 「欠勤」は実打刻があれば補わない（#72の回帰対応）。
     */
    public function 欠勤のleave_typeは実打刻があれば補わない(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::ABSENCE, 'leave_minutes' => null];

        $entries = $this->service->buildNoteEntries($summary, collect(), 480, '08:51');

        $this->assertSame([], $entries);
    }

    /**
     * @test
     *
     * 対応する休暇系の申請が既にある場合は、leave_typeからの補完で
     * 二重にならない。
     */
    public function 対応する申請があればleave_typeで二重にしない(): void
    {
        $summary = (object) ['leave_type' => LeaveTypeEnum::PAID_LEAVE, 'leave_minutes' => null];
        $request = (object) [
            'type' => 1,
            'applicationType' => (object) ['name' => '有給休暇'],
            'start_time' => null,
            'end_time' => null,
        ];

        $entries = $this->service->buildNoteEntries($summary, collect([$request]), 480);

        $this->assertCount(1, $entries);
        $this->assertSame('有給休暇', $entries[0]['label']);
        $this->assertSame('1.0', $entries[0]['value']);
    }

    /**
     * @test
     *
     * 申請種別のマスタ参照が引けない（ラベルが空）場合は、その申請を
     * 出さない（データ不整合時に、値だけありラベルの無い項目が出てしまう
     * のを防ぐ）。
     */
    public function ラベルが引けない申請は出さない(): void
    {
        $summary = (object) ['leave_type' => null];
        $request = (object) [
            'type' => 1,
            'applicationType' => null,
            'start_time' => null,
            'end_time' => null,
        ];

        $entries = $this->service->buildNoteEntries($summary, collect([$request]), 480);

        $this->assertSame([], $entries);
    }

    /**
     * @test
     *
     * 残業申請は、summaryのovertime_minutesではなく申請自体の開始・終了
     * 時刻から時間数を算出する。
     */
    public function 残業申請は申請の時間帯から時間数を算出する(): void
    {
        $summary = (object) ['leave_type' => null, 'overtime_minutes' => 0];
        $request = (object) [
            'type' => 7,
            'applicationType' => (object) ['name' => '残業申請'],
            'start_time' => '18:00',
            'end_time' => '20:00',
        ];

        $entries = $this->service->buildNoteEntries($summary, collect([$request]), 480);

        $this->assertSame([['label' => '残業申請', 'value' => '2.0']], $entries);
    }

    /**
     * @test
     *
     * 1日に複数の申請がある場合は、それぞれ別の項目として返す。
     */
    public function 複数の申請はそれぞれ項目になる(): void
    {
        $summary = (object) ['leave_type' => null, 'late_minutes' => 15];
        $lateRequest = (object) [
            'type' => 3,
            'applicationType' => (object) ['name' => '遅刻'],
            'start_time' => null,
            'end_time' => null,
        ];
        $paidLeaveRequest = (object) [
            'type' => 9, // 半日有給
            'applicationType' => (object) ['name' => '有給休暇'],
            'start_time' => null,
            'end_time' => null,
        ];

        $entries = $this->service->buildNoteEntries(
            $summary,
            new Collection([$lateRequest, $paidLeaveRequest]),
            480
        );

        $this->assertSame([
            ['label' => '遅刻', 'value' => '0.25'],
            ['label' => '有給休暇', 'value' => '0.5'],
        ], $entries);
    }
}
