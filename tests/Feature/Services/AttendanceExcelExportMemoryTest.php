<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\AttendanceExcelExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 全従業員Excel出力（WorkSummaryController::exportExcel, scope=all）の
 * メモリリーク回帰テスト。
 *
 * クライアント報告: 「エクセルの全員出力するとエラーになる」。
 * 本番ログでは maennchen/zipstream-php（PhpSpreadsheetのXlsxライターが
 * 内部で使用）でメモリ上限（128M）に達するFatalErrorだった。
 * 原因はSpreadsheetオブジェクトがWorksheetとの間に循環参照を持ち、
 * ループでIOFactory::load()→save()を繰り返すたびに未解放のまま
 * 積み上がっていたこと（1人あたり約4MBずつ増加し、本番の65名中
 * 17〜18人目でクラッシュすることを実際に確認済み）。
 * saveToTempFile()末尾でdisconnectWorksheets()を呼ぶことで、
 * ループを回してもメモリが単調増加し続けないようにした。
 */
class AttendanceExcelExportMemoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * @test
     */
    public function 連続生成してもメモリ使用量が増え続けない(): void
    {
        $company = Company::factory()->create(['company_code' => '970099']);
        $users = User::factory()->count(8)->create();
        foreach ($users as $user) {
            $user->companies()->attach($company->id, ['is_primary' => true]);
        }

        $service = app(AttendanceExcelExportService::class);
        $usageAfterEach = [];

        foreach ($users as $user) {
            $path = $service->generate($company->id, $user, '2026-06-21', '2026-07-20');
            unlink($path);

            gc_collect_cycles();
            $usageAfterEach[] = memory_get_usage(true);
        }

        // 最初の数回はオートロード等のウォームアップで増えうるため、
        // 後半同士（3回目以降）の増分だけを見る。disconnectWorksheets()が
        // 無いと1回あたり数MBずつ単調増加し続けていた。
        $growth = end($usageAfterEach) - $usageAfterEach[2];

        $this->assertLessThan(
            5 * 1024 * 1024,
            $growth,
            '生成を繰り返すたびにメモリ使用量が増え続けています（Spreadsheetの循環参照が解放されていない疑い）'
        );
    }
}
