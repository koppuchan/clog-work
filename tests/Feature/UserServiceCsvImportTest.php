<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UserServiceCsvImportTest extends TestCase
{
    use DatabaseTransactions;

    private UserService $service;

    private Company $company;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(UserService::class);

        $this->company = Company::factory()->create();

        $this->department = Department::factory()
            ->forCompany($this->company->id)
            ->create();
    }

    /**
     * 基本情報でCSV登録できる
     *
     * @test
     */
    public function import_from_csv_creates_user(): void
    {
        $csv = $this->buildCsv([
            ['100001', '山田太郎', 'ヤマダタロウ', 'taro@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('users', [
            'employee_code' => '100001',
            'name' => '山田太郎',
        ]);
    }

    /**
     * department_id が空でもCSV登録できる（nullable化の確認）
     *
     * @test
     */
    public function import_from_csv_allows_empty_department(): void
    {
        $csv = $this->buildCsv([
            ['300001', '佐藤次郎', 'サトウジロウ', 'jiro@example.com', '', '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertDatabaseHas('users', [
            'employee_code' => '300001',
            'name' => '佐藤次郎',
        ]);
    }

    /**
     * 別会社に同じ employee_code のユーザーが既にいても、自会社に登録できる
     *
     * @test
     */
    public function import_from_csv_allows_same_employee_code_across_different_companies(): void
    {
        $otherCompany = Company::factory()->create();
        User::factory()
            ->forCompany($otherCompany->id)
            ->employee()
            ->create(['employee_code' => '400001']);

        $csv = $this->buildCsv([
            ['400001', '田中一郎', 'タナカイチロウ', 'ichiro@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
    }

    /**
     * 同一会社内で重複する employee_code はエラーになる
     *
     * @test
     */
    public function import_from_csv_rejects_duplicate_employee_code_within_same_company(): void
    {
        User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['employee_code' => '500001']);

        $csv = $this->buildCsv([
            ['500001', '高橋二郎', 'タカハシジロウ', 'ni@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('個人コード', $result['errors'][0]['message']);
    }

    /**
     * 生成されるCSVテンプレートには6列のヘッダーが含まれる
     *
     * @test
     */
    public function generate_csv_template_includes_correct_columns(): void
    {
        $template = $this->service->generateCsvTemplate();
        // BOMを除去
        $template = preg_replace('/^\xEF\xBB\xBF/', '', $template);

        $this->assertStringContainsString(
            '社員番号,氏名,氏名（カナ）,メールアドレス,部署,役割',
            $template
        );
        $this->assertStringNotContainsString('月曜シフト', $template);
    }

    /**
     * 個人コードが空欄の場合、自動採番される
     *
     * @test
     */
    public function import_from_csv_auto_assigns_empty_employee_code(): void
    {
        $csv = $this->buildCsv([
            ['', '新人一郎', 'シンジンイチロウ', 'newbie@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $result['failed']);

        // 自動採番結果が追跡されている
        $this->assertCount(1, $result['auto_assigned']);
        $this->assertSame(1, $result['auto_assigned'][0]['row']);
        $this->assertSame('新人一郎', $result['auto_assigned'][0]['name']);
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $result['auto_assigned'][0]['employee_code']);

        // DB 上でも自動採番されたコードで登録されている
        $this->assertDatabaseHas('users', [
            'name' => '新人一郎',
            'employee_code' => $result['auto_assigned'][0]['employee_code'],
        ]);
    }

    /**
     * 複数行のうち一部だけ個人コードが空欄の場合、空欄のみ自動採番される
     *
     * @test
     */
    public function import_from_csv_auto_assigns_only_empty_rows(): void
    {
        $csv = $this->buildCsv([
            ['700001', '明示太郎', 'メイジタロウ', 'mei@example.com', (string) $this->department->id, '3'],
            ['', '自動花子', 'ジドウハナコ', 'auto@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(2, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        // 自動採番は2行目のみ
        $this->assertCount(1, $result['auto_assigned']);
        $this->assertSame(2, $result['auto_assigned'][0]['row']);
        $this->assertSame('自動花子', $result['auto_assigned'][0]['name']);

        // 明示指定したコードはそのまま登録される
        $this->assertDatabaseHas('users', [
            'employee_code' => '700001',
            'name' => '明示太郎',
        ]);
    }

    /**
     * 別会社で使用中の個人コードを自社で新規登録できる
     *
     * @test
     */
    public function import_from_csv_allows_same_employee_code_in_different_companies(): void
    {
        // 他社で既に `000001` を使用しているユーザーを作成
        $otherCompany = Company::factory()->create();
        User::factory()
            ->forCompany($otherCompany->id)
            ->employee()
            ->create([
                'employee_code' => '000001',
                'name' => '他社ユーザー',
            ]);

        // 自社で同じ `000001` を CSV 登録
        $csv = $this->buildCsv([
            ['000001', '自社ユーザー', 'ジシャユーザー', 'jisha@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $result['failed']);

        // 両会社に同じ employee_code のユーザーが存在する
        $this->assertDatabaseHas('users', [
            'employee_code' => '000001',
            'name' => '他社ユーザー',
        ]);
        $this->assertDatabaseHas('users', [
            'employee_code' => '000001',
            'name' => '自社ユーザー',
        ]);
    }

    /**
     * 同じ会社内で削除済みの個人コードを再利用できる
     *
     * @test
     */
    public function import_from_csv_allows_reusing_deleted_employee_code(): void
    {
        // 元ユーザーを作成して削除
        $oldUser = User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create([
                'employee_code' => '000002',
                'name' => '元ユーザー',
            ]);
        $this->service->delete($oldUser->id);

        // 同じ個人コードで CSV 再登録
        $csv = $this->buildCsv([
            ['000002', '新ユーザー', 'シンユーザー', 'shin@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('users', [
            'employee_code' => '000002',
            'name' => '新ユーザー',
        ]);
        $this->assertDatabaseMissing('users', [
            'employee_code' => '000002',
            'name' => '元ユーザー',
        ]);
    }

    /**
     * 新設会社での自動採番が他社の既存コードと衝突しない
     *
     * @test
     */
    public function import_from_csv_auto_assigns_without_cross_company_conflict(): void
    {
        // 他社で `000001` 使用中
        $otherCompany = Company::factory()->create();
        User::factory()
            ->forCompany($otherCompany->id)
            ->employee()
            ->create(['employee_code' => '000001']);

        // 自社（ユーザー0人）に空欄で CSV 登録 → 自動採番で `000001` が割り当てられるはず
        $csv = $this->buildCsv([
            ['', '初号機', 'ショゴウキ', 'shogouki@example.com', (string) $this->department->id, '3'],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors'], JSON_UNESCAPED_UNICODE));
        $this->assertSame(0, $result['failed']);
        $this->assertCount(1, $result['auto_assigned']);
        $this->assertSame('000001', $result['auto_assigned'][0]['employee_code']);

        // 両会社に同じ employee_code のユーザーが共存
        $this->assertSame(
            2,
            User::query()->where('employee_code', '000001')->count()
        );
    }

    /**
     * CSV内容を生成するヘルパー
     *
     * @param  array<int, array<int, string>>  $rows  各行6列分の文字列配列
     */
    private function buildCsv(array $rows): string
    {
        $bom = "\xEF\xBB\xBF";
        $header = '社員番号,氏名,氏名（カナ）,メールアドレス,部署,役割';
        $lines = [$header];

        foreach ($rows as $row) {
            $lines[] = implode(',', $row);
        }

        return $bom.implode("\n", $lines)."\n";
    }
}
