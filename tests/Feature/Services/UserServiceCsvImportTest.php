<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

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
     * @test
     */
    public function imports_basic_user(): void
    {
        $csv = $this->createCsvContent([
            ['100001', '基本太郎', 'キホンタロウ', 'kihon@example.com', $this->department->id, 3],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('users', [
            'employee_code' => '100001',
            'name' => '基本太郎',
        ]);
    }

    /**
     * @test
     */
    public function imports_user_without_department(): void
    {
        $csv = $this->createCsvContent([
            ['100003', '部署無し花子', 'ブショナシハナコ', 'nodept@example.com', '', 3],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors']));
        $this->assertDatabaseHas('users', [
            'employee_code' => '100003',
            'name' => '部署無し花子',
        ]);
    }

    /**
     * @test
     */
    public function allows_same_employee_code_in_different_company(): void
    {
        // 別会社に同じ個人コードのユーザーを作成
        $otherCompany = Company::factory()->create();
        User::factory()
            ->forCompany($otherCompany->id)
            ->employee()
            ->create(['employee_code' => '100004']);

        $csv = $this->createCsvContent([
            ['100004', '別会社太郎', 'ベツカイシャタロウ', 'other@example.com', $this->department->id, 3],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(1, $result['success'], 'errors: '.json_encode($result['errors']));
        $this->assertSame(2, User::query()->where('employee_code', '100004')->count());
    }

    /**
     * @test
     */
    public function rejects_duplicate_employee_code_in_same_company(): void
    {
        // 同じ会社に既に存在
        User::factory()
            ->forCompany($this->company->id)
            ->employee()
            ->create(['employee_code' => '100005']);

        $csv = $this->createCsvContent([
            ['100005', '重複太郎', 'ジュウフクタロウ', 'dup@example.com', $this->department->id, 3],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(0, $result['success']);
        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('既に使用されています', $result['errors'][0]['message']);
    }

    /**
     * @test
     */
    public function imports_multiple_users_with_empty_email(): void
    {
        $csv = $this->createCsvContent([
            ['100010', '空メール太郎', 'カラメールタロウ', '', $this->department->id, 3],
            ['100011', '空メール次郎', 'カラメールジロウ', '', $this->department->id, 3],
            ['100012', '空メール三郎', 'カラメールサブロウ', '', $this->department->id, 3],
        ]);
        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $result = $this->service->importFromCsv($file, $this->company->id);

        $this->assertSame(3, $result['success'], 'errors: '.json_encode($result['errors']));
        $this->assertSame(0, $result['failed']);
        $this->assertDatabaseHas('users', [
            'employee_code' => '100010',
            'email' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'employee_code' => '100011',
            'email' => null,
        ]);
        $this->assertDatabaseHas('users', [
            'employee_code' => '100012',
            'email' => null,
        ]);
    }

    /**
     * @test
     */
    public function generate_csv_template_returns_six_column_headers(): void
    {
        $csv = $this->service->generateCsvTemplate();

        // BOM除去
        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
        $firstLine = strtok($csv, "\n");

        $expectedHeader = '社員番号,氏名,氏名（カナ）,メールアドレス,部署,役割';
        $this->assertSame($expectedHeader, $firstLine);
    }

    /**
     * CSV内容を生成するヘルパー
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function createCsvContent(array $rows): string
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
