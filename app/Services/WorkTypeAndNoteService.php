<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LeaveTypeEnum;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * 勤務区分・備考欄（休暇・申請）の判定
 *
 * 勤務実績Excel（AttendanceExcelExportService）とCSV
 * （DailyWorkSummaryService::generateCsvAll）は同じ基準で勤務区分・備考欄を
 * 出力する必要があるが、以前はこの判定ロジックを2箇所に別々に書いており、
 * 片方だけ直して他方に反映し忘れる事故が起きていた（task#70で入れた
 * 「欠勤（ABSENCE）は実打刻があれば優先しない」という例外を、task#72で
 * 備考欄のロジックを追加した際に入れ忘れ、出勤している全員の備考欄に
 * 「欠勤」が表示される不具合になった）。今後同じ種類の事故が起きないよう、
 * 判定ロジックをこのクラスに一本化し、Excel・CSVの両方がここを参照する。
 */
class WorkTypeAndNoteService
{
    /**
     * 勤務区分を判定する
     *
     * 櫻本さまに確定いただいた基準（task#71）。曜日（土日かどうか）は
     * 判定に使わない。以前は土日かどうかで別ロジックを使っていたが、
     * その結果シフトが割り当てられている土日に出退勤がないと「休日」に
     * なってしまい（本来は欠勤と同じ扱いのはず）、逆に平日でシフトも
     * 打刻も無い日は空欄になるなど、曜日で結果が変わってしまっていた。
     *
     * - 休暇種別が設定されている日はそれを優先する（有給休暇・特別休暇など）
     * - シフト時間あり + 出退勤あり → 出勤
     * - シフト時間あり + 出退勤なし → 欠勤
     * - シフト時間なし + 出退勤あり → 休出
     * - シフト時間なし + 出退勤なし → 休日
     *
     * ただし「欠勤」の休暇種別（ABSENCE）だけは例外で、実打刻があれば
     * 優先しない。欠勤申請が承認された後に実際は出勤していた場合
     * （入江さまの9/18のケース: 打刻間違いの修正申請とは別に、欠勤の
     * 承認済み申請が残っていた）、申請どおり欠勤のまま表示され続けて
     * しまい、実際の出退勤があるのに欠勤と出力される不具合の一因になって
     * いた。有給休暇・特別休暇は半日勤務などと両立しうる区分のため、
     * 実打刻の有無に関わらずこれまでどおり優先する。
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  string|null  $effectiveWorkStart  実打刻優先の出勤時刻（H:i形式）。
     *                                           summaryのwork_startだけで判定すると、実打刻はあるのに集計バッチが
     *                                           まだ反映していない日を「打刻なし」と誤判定してしまうため、
     *                                           呼び出し側で実打刻を優先した値を渡す。
     */
    public function resolveWorkType($summary, ?string $effectiveWorkStart): string
    {
        $hasClockTimes = $effectiveWorkStart !== null;

        if ($summary?->leave_type !== null
            && ! ($summary->leave_type === LeaveTypeEnum::ABSENCE && $hasClockTimes)) {
            return $summary->leave_type->label();
        }

        $hasShiftTime = $summary?->scheduled_start_time !== null;

        return match (true) {
            $hasShiftTime && $hasClockTimes => '出勤',
            // 集計欄の欠勤日数は =COUNTIFS(B7:B37,"欠勤") でこの表記を数えている。
            $hasShiftTime && ! $hasClockTimes => '欠勤',
            ! $hasShiftTime && $hasClockTimes => '休出',
            default => '休日',
        };
    }

    /**
     * 備考/申請欄に出す項目を [{'label'=>種別名, 'value'=>数値文字列}, ...] の
     * 配列で返す
     *
     * - 時間系申請（遅刻・早退・残業）: 小数の時間数（例: "1.75"）
     * - 日数系申請（有給・特別休暇・欠勤等）: leave_minutes ÷ 1日所定分 で小数表示
     * - 1日に複数申請がある場合は複数件返す（呼び出し側で改行等で連結する）
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  Collection  $dayRequests  当日の承認済み申請コレクション
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @param  string|null  $effectiveWorkStart  実打刻優先の出勤時刻（H:i形式）。resolveWorkTypeに渡すものと同じ
     * @return array<int, array{label: string, value: string}>
     */
    public function buildNoteEntries($summary, Collection $dayRequests, int $dailyWorkingMinutes, ?string $effectiveWorkStart = null): array
    {
        $entries = [];

        foreach ($dayRequests as $request) {
            $typeName = $request->applicationType?->name ?? '';

            // 申請種別のマスタ参照が引けない場合はラベルの無い項目になって
            // しまうため、その申請は出さない（applicationTypeリレーションの
            // 読み込み漏れ等、データ不整合時の保険）。
            if ($typeName === '') {
                continue;
            }

            $typeId = $request->type;

            $valueStr = match ($typeId) {
                // 遅刻・早退: 申請自体に時間の指定はないため、打刻ベースの自動計算値を使う
                3 => $this->formatHourlyRequestValue($summary?->late_minutes ?? 0),
                4 => $this->formatHourlyRequestValue($summary?->early_leave_minutes ?? 0),
                // 残業申請: 承認しても daily_work_summaries の overtime_minutes は
                // 書き換えない設計（打刻ベースの自動計算値を維持するため）なので、
                // ここで summary の overtime_minutes を参照すると常に空欄/実態と
                // ずれた値になってしまう。申請自体の開始・終了時刻から算出する。
                7 => $this->formatHourlyRequestValue($this->requestRangeMinutes($request)),
                // 日数系: 申請種別に応じた日数計算
                default => $this->calculateLeaveDays($typeId, $request, $dailyWorkingMinutes),
            };

            $entries[] = ['label' => $typeName, 'value' => $valueStr];
        }

        // daily_work_summaries.leave_type は、承認された申請（requestsテーブル）経由
        // だけでなく、旧システムからのCSV移行データのように申請なしで直接設定されて
        // いることがある。そのケースは上のループでは拾えず備考欄に出ないだけでなく、
        // 集計欄の有給日数・欠勤日数（=SUMPRODUCTでこの列のラベルを数えている）からも
        // 漏れてしまう（クライアント報告 #72: 有給休暇が反映されているのに備考欄に
        // 出力されない）。対応する休暇系の申請が無い場合はここで補う。
        //
        // ただし「欠勤」（ABSENCE）は、resolveWorkTypeと同じ理由で実打刻があれば
        // 対象外にする。欠勤の申請承認後に実際は出勤していた日にまで備考欄へ
        // 「欠勤 1.0」を出してしまい、実際に出勤している全員の備考欄に欠勤が
        // 表示される不具合になっていた（クライアント報告: 全員に欠勤の表示が
        // 出るようになった。#72対応時にresolveWorkTypeと同じ例外を入れ忘れていた）。
        $hasLeaveRequest = $dayRequests->contains(
            fn ($request) => LeaveTypeEnum::isLeaveApplication($request->type)
        );
        $isStaleAbsence = $summary?->leave_type === LeaveTypeEnum::ABSENCE && $effectiveWorkStart !== null;
        if ($summary?->leave_type !== null && ! $hasLeaveRequest && ! $isStaleAbsence) {
            $entries[] = [
                'label' => $summary->leave_type->label(),
                'value' => $this->formatSummaryLeaveDays($summary, $dailyWorkingMinutes),
            ];
        }

        return $entries;
    }

    /**
     * 申請を経由しないleave_type（CSV移行データ等）の日数を計算する
     *
     * CSV移行では休暇の時間内訳（leave_minutes）を保存していないため、
     * 基本的に全日（"1.0"）になる。将来leave_minutesが入る経路ができても
     * 対応できるよう、入っていれば所定時間に対する割合で計算する。
     *
     * @param  mixed  $summary  daily_work_summaries レコード
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     */
    private function formatSummaryLeaveDays($summary, int $dailyWorkingMinutes): string
    {
        $leaveMinutes = $summary->leave_minutes ?? null;

        if ($leaveMinutes === null || $dailyWorkingMinutes <= 0 || $leaveMinutes >= $dailyWorkingMinutes) {
            return '1.0';
        }

        $days = round($leaveMinutes / $dailyWorkingMinutes, 4);

        return rtrim(rtrim(number_format($days, 4), '0'), '.');
    }

    /**
     * 時間系申請（遅刻・早退・残業）の表示値を生成する
     *
     * @param  int  $minutes  時間（分）
     * @return string 小数の時間数（例: 1時間45分 → "1.75"）、0以下の場合は空文字
     */
    private function formatHourlyRequestValue(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        $hours = round($minutes / 60, 2);
        $formatted = rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');

        return str_contains($formatted, '.') ? $formatted : $formatted.'.0';
    }

    /**
     * 申請自体の開始・終了時刻から時間数（分）を算出する
     *
     * @param  mixed  $request  申請レコード
     */
    private function requestRangeMinutes($request): int
    {
        if (! $request->start_time || ! $request->end_time) {
            return 0;
        }

        $start = CarbonImmutable::parse($request->start_time);
        $end = CarbonImmutable::parse($request->end_time);

        return max(0, (int) $start->diffInMinutes($end));
    }

    /**
     * 休暇申請の日数を計算
     *
     * buildNoteEntries以外に、AttendanceExcelExportService::calculateMonthlySummary
     * （月間の有給・特休・欠勤日数の集計）からも呼ばれるためpublicにしている。
     *
     * @param  int  $typeId  申請種別ID
     * @param  mixed  $request  申請レコード
     * @param  int  $dailyWorkingMinutes  1日所定勤務時間（分）
     * @return string 日数文字列（例: "1.0", "0.5", "0.125"）
     */
    public function calculateLeaveDays(int $typeId, $request, int $dailyWorkingMinutes): string
    {
        // 半日有給（type=9）: 常に0.5日
        if ($typeId === 9) {
            return '0.5';
        }

        // 時間有給（type=10）: start_time/end_time から時間数を算出し、1日所定時間で割る
        if ($typeId === 10 && $request->start_time && $request->end_time) {
            $start = CarbonImmutable::parse($request->start_time);
            $end = CarbonImmutable::parse($request->end_time);
            $leaveMinutes = (int) $start->diffInMinutes($end);

            if ($dailyWorkingMinutes > 0 && $leaveMinutes > 0) {
                $days = round($leaveMinutes / $dailyWorkingMinutes, 4);

                return rtrim(rtrim(number_format($days, 4), '0'), '.');
            }

            return '0';
        }

        // 全日有給（type=1）/ 特別休暇（type=5）/ 欠勤（type=6）/ その他: 1.0日
        return '1.0';
    }
}
