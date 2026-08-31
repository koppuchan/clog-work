<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DailyWorkSummaryBatchService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * 日次勤務実績集計バッチコマンド
 *
 * 毎日24時（0時）に実行し、前日の打刻レコードから勤務実績を自動集計する
 */
class AggregateDailyWorkSummaries extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * @var string
     */
    protected $signature = 'batch:aggregate-daily-work-summaries
                            {--date= : 対象日（Y-m-d形式）。省略時は前日}
                            {--dry-run : 実行結果をシミュレートのみ（DBには反映しない）}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '打刻レコードから日次勤務実績を自動集計します';

    /**
     * コマンドを実行
     */
    public function handle(DailyWorkSummaryBatchService $batchService): int
    {
        $this->log('日次勤務実績集計バッチを開始します...');

        // 対象日を決定（オプション指定がなければ前日）
        $dateOption = $this->option('date');
        if ($dateOption) {
            $targetDate = CarbonImmutable::createFromFormat('Y-m-d', $dateOption);
            if (! $targetDate) {
                $this->logError('日付の形式が不正です。Y-m-d形式で指定してください。');

                return Command::FAILURE;
            }
        } else {
            $targetDate = CarbonImmutable::now()->subDay()->startOfDay();
        }

        $this->log("対象日: {$targetDate->format('Y-m-d')}");

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->logWarn('ドライランモードで実行します（DBへの変更は行いません）');
            // ドライランの場合はここで終了
            $this->log('ドライランモードのため、実際の処理はスキップされました');

            return Command::SUCCESS;
        }

        try {
            $result = $batchService->aggregateAllUsers($targetDate);

            $this->newLine();
            $this->log('===== 集計結果 =====');
            $this->log("処理件数: {$result['processed']}");
            $this->log("新規作成: {$result['created']}");
            $this->log("更新: {$result['updated']}");
            $this->log("スキップ: {$result['skipped']}");

            if ($result['errors'] > 0) {
                $this->logError("エラー: {$result['errors']}");
            }

            $this->newLine();
            $this->log('日次勤務実績集計バッチが完了しました');

            return $result['errors'] > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logError('バッチ処理中にエラーが発生しました: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * 日時付きでinfoログを出力
     */
    private function log(string $message): void
    {
        $timestamp = CarbonImmutable::now()->format('Y-m-d H:i:s');
        $this->info("[{$timestamp}] {$message}");
    }

    /**
     * 日時付きでwarnログを出力
     */
    private function logWarn(string $message): void
    {
        $timestamp = CarbonImmutable::now()->format('Y-m-d H:i:s');
        $this->warn("[{$timestamp}] {$message}");
    }

    /**
     * 日時付きでerrorログを出力
     */
    private function logError(string $message): void
    {
        $timestamp = CarbonImmutable::now()->format('Y-m-d H:i:s');
        $this->error("[{$timestamp}] {$message}");
    }
}
