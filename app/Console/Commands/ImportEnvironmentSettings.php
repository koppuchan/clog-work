<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Department;
use App\Models\ShiftPattern;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 環境設定の取り込みコマンド
 *
 * 旧環境の設定内容をJSONで受け取り、新環境に再現する。
 * 部門・シフトパターンは名前で照合し、あれば更新、なければ作成する。
 * 繰り返し実行しても重複しない。
 */
class ImportEnvironmentSettings extends Command
{
    /**
     * コマンド名とシグネチャ
     *
     * @var string
     */
    protected $signature = 'migrate:settings
                            {file : 設定内容を書いたJSONのパス}
                            {--company= : 取り込み先の会社ID}
                            {--dry-run : 取り込まずに結果だけを表示する}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = '旧環境の設定内容を新環境に再現します';

    /**
     * コマンドを実行
     */
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_readable($path)) {
            $this->error("ファイルを読み込めません: {$path}");

            return self::FAILURE;
        }

        $companyId = (int) $this->option('company');
        $company = Company::query()->find($companyId);

        if (! $company) {
            $this->error('--company で有効な会社IDを指定してください。');

            return self::FAILURE;
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            $this->error('JSONを読み取れませんでした。');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->info('確認のみ（取り込みは行いません）');
        }

        DB::transaction(function () use ($company, $data, $dryRun) {
            $this->applyCompany($company, $data, $dryRun);
            $this->applyDepartments($company->id, $data['departments'] ?? [], $dryRun);
            $this->applyShiftPatterns($company, $data['shift_patterns'] ?? [], $dryRun);

            if ($dryRun) {
                // 反映しないため巻き戻す
                DB::rollBack();
            }
        });

        $this->info('完了しました。');

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyCompany(Company $company, array $data, bool $dryRun): void
    {
        $settings = $data['company'] ?? [];

        if ($settings === []) {
            return;
        }

        foreach ($settings as $key => $value) {
            $this->line("  会社設定 {$key}: {$company->{$key}} → {$value}");
        }

        if (! $dryRun) {
            $company->fill($settings)->save();
        }
    }

    /**
     * @param  array<int, string>  $names
     */
    private function applyDepartments(int $companyId, array $names, bool $dryRun): void
    {
        foreach ($names as $name) {
            $exists = Department::query()
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->exists();

            $this->line('  部門 '.$name.($exists ? '（既存）' : '（作成）'));

            if (! $dryRun && ! $exists) {
                Department::query()->create([
                    'company_id' => $companyId,
                    'name' => $name,
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $patterns
     */
    private function applyShiftPatterns(Company $company, array $patterns, bool $dryRun): void
    {
        foreach ($patterns as $pattern) {
            $name = (string) ($pattern['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $isDefault = (bool) ($pattern['is_default'] ?? false);
            unset($pattern['is_default']);

            $existing = ShiftPattern::query()
                ->where('company_id', $company->id)
                ->where('name', $name)
                ->first();

            $this->line('  シフトパターン '.$name.($existing ? '（更新）' : '（作成）'));

            if ($dryRun) {
                continue;
            }

            $saved = $existing
                ? tap($existing)->update($pattern)
                : ShiftPattern::query()->create(array_merge($pattern, ['company_id' => $company->id]));

            if ($isDefault) {
                $company->update(['default_shift_pattern_id' => $saved->id]);
            }
        }
    }
}
