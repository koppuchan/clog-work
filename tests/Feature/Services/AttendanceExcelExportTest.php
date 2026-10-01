<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Enums\LeaveTypeEnum;
use App\Enums\RecordSourceEnum;
use App\Enums\RequestStatusEnum;
use App\Enums\TimeRecordTypeEnum;
use App\Models\Company;
use App\Models\DailyWorkSummary;
use App\Models\Request;
use App\Models\TimeRecord;
use App\Models\User;
use App\Services\AttendanceExcelExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * 勤務実績Excelの出力検証。
 *
 * 集計欄と合計行はシート側の数式で算出される。出力処理がそこへ値を書くと
 * 数式が失われ、給与計算に使えなくなる。出力後も数式が残ることを固定する。
 */
class AttendanceExcelExportTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    private Company $company;

    private string $generatedPath = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['company_code' => '970001']);
        $this->user = User::factory()->create([
            'name' => '出力 太郎',
            'employee_code' => '000123',
        ]);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
    }

    protected function tearDown(): void
    {
        if ($this->generatedPath !== '' && file_exists($this->generatedPath)) {
            unlink($this->generatedPath);
        }

        parent::tearDown();
    }

    private function generatedSheet(): \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
    {
        $this->generatedPath = app(AttendanceExcelExportService::class)->generate(
            $this->company->id,
            $this->user,
            '2026-06-21',
            '2026-07-20',
        );

        return IOFactory::load($this->generatedPath)->getActiveSheet();
    }

    /**
     * @test
     */
    public function 出力後も集計欄の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['G4', 'H4', 'I4', 'J4', 'K4', 'L4', 'M4', 'N4', 'O4', 'P4', 'R4', 'S4', 'T4', 'U4'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     *
     * 有給日数（H4）はテンプレート側のnumFmtIdがGeneralのため、計算結果が
     * 3.000のような小数でも末尾のゼロが省略されて「3」と表示されてしまって
     * いた（クライアント報告 #74: 有給の桁数が0.000の小数点以下3桁で
     * 反映されていない）。数式はそのまま、表示形式だけ3桁固定にすること。
     */
    public function 有給日数の表示形式が小数点以下3桁に固定されている(): void
    {
        $sheet = $this->generatedSheet();

        $this->assertSame('0.000', $sheet->getStyle('H4')->getNumberFormat()->getFormatCode());
        // 数式自体は書き換えていないこと（上のテストと合わせて二重に確認）
        $this->assertStringStartsWith('=', (string) $sheet->getCell('H4')->getValue());
    }

    /**
     * @test
     */
    public function 出力後も合計行の数式が残っている(): void
    {
        $sheet = $this->generatedSheet();

        foreach (['M38', 'N38', 'O38', 'P38', 'Q38'] as $cell) {
            $this->assertStringStartsWith(
                '=',
                (string) $sheet->getCell($cell)->getValue(),
                "{$cell} の数式が出力時に失われています",
            );
        }
    }

    /**
     * @test
     */
    public function スタッフの識別情報が出力される(): void
    {
        $sheet = $this->generatedSheet();

        $this->assertSame('000123', (string) $sheet->getCell('A4')->getValue());
        $this->assertSame('出力 太郎', (string) $sheet->getCell('B4')->getValue());
    }

    /**
     * @test
     */
    public function 日付欄が締め期間どおりに並ぶ(): void
    {
        $sheet = $this->generatedSheet();

        // 6/21〜7/20 は30日間。7行目から36行目までが埋まる
        $this->assertStringStartsWith('6/21', (string) $sheet->getCell('A7')->getValue());
        $this->assertStringStartsWith('7/20', (string) $sheet->getCell('A36')->getValue());

        // 31日ある月に備えて37行目まで用意されているが、今回は空のまま
        $this->assertSame('', (string) $sheet->getCell('A37')->getValue());
    }

    /**
     * @test
     *
     * 残業申請は承認しても daily_work_summaries.overtime_minutes を書き換えない設計
     * (OvertimeApplicationService::applyOvertimeToWorkSummary が無効化されている)。
     * そのため overtime_minutes が0のままでも、申請自体の開始・終了時刻から
     * 残業時間を算出して備考/申請列(R・S列)に出力する。
     */
    public function 残業申請の時間数が備考申請列に出力される(): void
    {
        // 6/21始まりの期間で6/24は10行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7, // 残業申請
            'target_date' => '2026-06-24',
            'start_time' => '18:00',
            'end_time' => '20:00',
            'reason' => '月次締め作業のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('残業申請', (string) $sheet->getCell('R10')->getValue());
        $this->assertSame('2.0', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 端数のある残業時間は「2H」のように整数へ丸めず、1時間45分なら
     * 「1.75」のように小数の時間数で出力する。
     */
    public function 端数のある残業申請の時間数は小数で出力される(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 7, // 残業申請
            'target_date' => '2026-06-24',
            'start_time' => '18:00',
            'end_time' => '19:45',
            'reason' => '月次締め作業のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('1.75', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 明細行の労働時間・時間外・休日・深夜・遅刻早退は、文字列ではなく
     * 実際の数値（時刻の端数）として書き込まれ、合計行の数式が正しく
     * 計算できること（クライアント報告 #70: 合計値が計算されていない）。
     */
    public function 明細行の時間は合計可能な数値として出力され合計行が計算される(): void
    {
        // 6/21始まりの期間で6/24は10行目、6/25は11行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 30,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-25',
            'work_start' => '2026-06-25 09:00:00',
            'work_end' => '2026-06-25 19:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 540,
            'overtime_minutes' => 90,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        // M10・M11は文字列ではなく数値（時刻の端数）で入っていること
        $this->assertIsNumeric($sheet->getCell('M10')->getValue());
        $this->assertEqualsWithDelta(480 / 1440, (float) $sheet->getCell('M10')->getValue(), 0.0001);
        $this->assertEqualsWithDelta(540 / 1440, (float) $sheet->getCell('M11')->getValue(), 0.0001);

        // 合計行の数式(=SUM(M7:M37))が、書き込んだ数値を正しく合算できること
        $totalMinutes = (int) round(((float) $sheet->getCell('M38')->getCalculatedValue()) * 1440);
        $this->assertSame(480 + 540, $totalMinutes, 'M38=SUM(M7:M37) が実際の労働時間の合計を計算できていない');

        $totalOvertimeMinutes = (int) round(((float) $sheet->getCell('N38')->getCalculatedValue()) * 1440);
        $this->assertSame(30 + 90, $totalOvertimeMinutes);
    }

    /**
     * @test
     *
     * 0分の項目は、これまでどおり空欄のまま（文字列"0:00"等にはしない）。
     */
    public function 時間が0の項目は空欄のまま(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => '2026-06-24 09:00:00',
            'work_end' => '2026-06-24 18:00:00',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 480,
            'overtime_minutes' => 0,
            'holiday_minutes' => 0,
            'night_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('', (string) $sheet->getCell('N10')->getValue());
        $this->assertSame('', (string) $sheet->getCell('O10')->getValue());
        $this->assertSame('', (string) $sheet->getCell('P10')->getValue());
    }

    /**
     * @test
     *
     * シフトの所定時刻と実際の出退勤があるのに「欠勤」と出力されていた
     * 不具合（クライアント報告 #70）。日次集計バッチがまだ反映しておらず
     * summary.work_startがnullのままでも、実打刻（time_records）があれば
     * 「出勤」と判定されること。
     */
    public function シフトと実打刻があれば集計未反映でも出勤と出力される(): void
    {
        // 6/24は10行目。summaryは作るがwork_startは未反映のまま（バッチ未実行を再現）
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-06-24 08:58:00',
            'rounded_time' => '2026-06-24 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('出勤', (string) $sheet->getCell('B10')->getValue());
        $this->assertSame('08:58', (string) $sheet->getCell('G10')->getValue(), '出勤時刻は実打刻を優先する');
    }

    /**
     * @test
     *
     * シフトが割り当てられている土曜・日曜に出退勤がなければ、平日と同じ
     * 基準で「欠勤」と出力されること（以前は曜日で別ロジックを使っており
     * 「休日」になってしまっていた）。
     */
    public function シフトのある土日に出退勤がなければ欠勤と出力される(): void
    {
        // 6/27(土)は13行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-27',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('欠勤', (string) $sheet->getCell('B13')->getValue());
    }

    /**
     * @test
     *
     * 入江さまの9/18のケース（クライアント報告 #70）の再現。欠勤の申請が
     * 承認された後、実際には出勤して打刻していた場合、欠勤の申請が
     * 残っているせいで出勤時刻が表示されているのに欠勤のまま出力されて
     * いた。実打刻があれば、欠勤の休暇種別が設定されていても出勤として
     * 出力されること。
     */
    public function 欠勤申請が承認済みでも実打刻があれば出勤と出力される(): void
    {
        // 6/24は10行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-06-24 08:51:00',
            'rounded_time' => '2026-06-24 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('出勤', (string) $sheet->getCell('B10')->getValue());
    }

    /**
     * @test
     *
     * 東部さまの8/28のケース（クライアント報告 #72）。旧システムからの
     * CSV移行データのように、daily_work_summaries.leave_type が承認された
     * 申請（requestsテーブル）を経由せず直接設定されている日は、備考欄
     * （R・S列）に何も出力されず、集計欄の有給日数のカウント
     * （=SUMPRODUCTでR列のラベルを数えている）からも漏れてしまっていた。
     * 対応する申請が無くても、勤務区分（B列）と同じ休暇種別を備考欄にも
     * 出力すること。
     */
    public function 申請を経由しない有給休暇でも備考欄に出力される(): void
    {
        // 6/24は10行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::MANUAL,
            'note' => '有給休暇',
        ]);
        // この日に対応するRequestは意図的に作らない（CSV移行データの再現）

        $sheet = $this->generatedSheet();

        $this->assertSame('有給休暇', (string) $sheet->getCell('B10')->getValue());
        $this->assertSame('有給休暇', (string) $sheet->getCell('R10')->getValue());
        $this->assertSame('1.000', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 申請（requestsテーブル）経由で有給休暇が設定されている、これまでどおりの
     * 正常系では、備考欄が申請の値のまま出力され、leave_typeからの補完で
     * 二重に表示されないこと。
     */
    public function 申請経由の有給休暇は備考欄が二重にならない(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 有給休暇
            'target_date' => '2026-06-24',
            'reason' => '私用のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('有給休暇', (string) $sheet->getCell('R10')->getValue());
        $this->assertSame('1.000', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * #72対応で追加した「申請なしのleave_typeを備考欄に補う」処理が、
     * getWorkTypeと同じ「欠勤（ABSENCE）は実打刻があれば対象外」の例外を
     * 入れ忘れていたため、実際に出勤している日にまで備考欄へ「欠勤 1.0」が
     * 出てしまっていた（クライアント報告: 確認したら全員に欠勤の表示が
     * 出るようになっていた）。勤務区分（B列）は正しく「出勤」なのに、
     * 備考欄だけ「欠勤」が出るのは矛盾するため、出さないこと。
     */
    public function 欠勤の休暇種別が残っていても実打刻があれば備考欄に出ない(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 480,
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-06-24 08:51:00',
            'rounded_time' => '2026-06-24 09:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_END,
            'record_time' => '2026-06-24 18:00:00',
            'rounded_time' => '2026-06-24 18:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('出勤', (string) $sheet->getCell('B10')->getValue(), '勤務区分は出勤のはず');
        $this->assertSame('', (string) $sheet->getCell('R10')->getValue(), '実打刻がある日に備考欄へ欠勤を出してはいけない');
        $this->assertSame('', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * 上記と対になる確認。実打刻が無ければ、これまでどおり欠勤が
     * 備考欄に出ること（本当の欠勤日まで消してしまわないように）。
     */
    public function 欠勤の休暇種別は実打刻が無ければ備考欄に出る(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::ABSENCE,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('欠勤', (string) $sheet->getCell('B10')->getValue());
        $this->assertSame('欠勤', (string) $sheet->getCell('R10')->getValue());
        $this->assertSame('1.000', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * task#75: 半日・時間有給（leave_minutesが設定されている）は、勤務区分
     * には「有給休暇」を出さず、実際の出退勤状況（出勤）を表示すること。
     * 備考欄（R・S列）には、これまでどおり「有給休暇」とその日数を出す
     * （表示するのをやめるのは勤務区分だけ）。
     */
    public function 半日有給は勤務区分に出さず備考欄には出す(): void
    {
        // 6/24は10行目
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 240,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => 240,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 9, // 半日有給
            'target_date' => '2026-06-24',
            'reason' => '私用のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        TimeRecord::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'record_type' => TimeRecordTypeEnum::WORK_START,
            'record_time' => '2026-06-24 13:00:00',
            'rounded_time' => '2026-06-24 13:00:00',
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('出勤', (string) $sheet->getCell('B10')->getValue(), '半日有給の日は勤務区分に有給休暇を出さない');
        $this->assertSame('有給休暇', (string) $sheet->getCell('R10')->getValue(), '備考欄には引き続き出す');
        $this->assertSame('0.500', (string) $sheet->getCell('S10')->getValue());
    }

    /**
     * @test
     *
     * task#75: 全日有給（leave_minutesがnull）は、これまでどおり勤務区分に
     * 「有給休暇」が出ること（半日・時間有給だけを対象外にする例外が、
     * 全日有給まで巻き込んでいないことの確認）。
     */
    public function 全日有給は勤務区分に出す(): void
    {
        DailyWorkSummary::query()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'work_date' => '2026-06-24',
            'work_start' => null,
            'work_end' => null,
            'scheduled_start_time' => '09:00:00',
            'scheduled_end_time' => '18:00:00',
            'net_work_minutes' => 0,
            'leave_type' => LeaveTypeEnum::PAID_LEAVE,
            'leave_minutes' => null,
            'record_source' => RecordSourceEnum::REQUEST,
        ]);

        Request::query()->create([
            'company_id' => $this->company->id,
            'requested_by' => $this->user->id,
            'type' => 1, // 全日有給
            'target_date' => '2026-06-24',
            'reason' => '私用のため',
            'status' => RequestStatusEnum::APPROVED,
        ]);

        $sheet = $this->generatedSheet();

        $this->assertSame('有給休暇', (string) $sheet->getCell('B10')->getValue());
    }
}
