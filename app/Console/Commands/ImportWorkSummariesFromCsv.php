<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\WorkSummaryCsvImportService;
use Illuminate\Console\Command;

/**
 * 勤務実績CSV取り込みコマンド
 *
 * 旧環境からの移行に使う。旧環境はSSHで接続できずDBダンプを取得できないため、
 * 管理画面から出力した勤務実績CSVを取り込む。
 */
class ImportWorkSummariesFromCsv extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * @var string
     */
    protected $signature = 'migrate:work-summaries
                            {file : 取り込む勤務実績CSVのパス}
                            {--company= : 取り込み先の会社ID}
                            {--dry-run : 取り込まずに結果だけを表示する}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '旧環境から出力した勤務実績CSVを取り込みます';

    /**
     * コマンドを実行
     */
    public function handle(WorkSummaryCsvImportService $importService): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("ファイルを読み込めません: {$path}");

            return self::FAILURE;
        }

        $companyId = (int) $this->option('company');

        if ($companyId <= 0) {
            $this->error('--company で取り込み先の会社IDを指定してください。');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('確認のみ（取り込みは行いません）');
        }

        $result = $importService->import((string) file_get_contents($path), $companyId, $dryRun);

        $this->info("取り込み: {$result['imported']}件 / 対象外: {$result['skipped']}件");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        // 取り込めた行が1件もなければ、指定や形式の誤りとして扱う
        if ($result['imported'] === 0) {
            $this->error('取り込めた行がありませんでした。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
