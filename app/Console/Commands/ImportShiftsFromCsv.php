<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ShiftCsvImportService;
use Illuminate\Console\Command;

/**
 * シフトCSV取り込みコマンド
 *
 * 旧環境の管理画面から出力したシフトCSVを新環境へ取り込む。
 */
class ImportShiftsFromCsv extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * @var string
     */
    protected $signature = 'migrate:shifts
                            {file : 取り込むシフトCSVのパス}
                            {--company= : 取り込み先の会社ID}
                            {--dry-run : 取り込まずに結果だけを表示する}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '旧環境から出力したシフトCSVを取り込みます';

    /**
     * コマンドを実行
     */
    public function handle(ShiftCsvImportService $importService): int
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

        if ($result['imported'] === 0) {
            $this->error('取り込めた行がありませんでした。');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
