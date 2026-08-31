<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class UserServiceEmployeeCodePaddingTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();

        $this->department = Department::factory()
            ->forCompany($this->company->id)
            ->create();

        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create([
                'is_retired' => false,
            ]);
    }

    /**
     * CSVの個人コードが6桁の場合、そのまま登録される
     *
     * @test
     */
    public function csv_import_preserves_six_digit_employee_code(): void
    {
        $csv = $this->createCsvContent([
            ['000100', 'テスト太郎', 'テストタロウ', 'taro@example.com', $this->department->id, 3],
        ]);

        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.csv-import'), ['csv_file' => $file]);

        $this->assertDatabaseHas('users', [
            'employee_code' => '000100',
        ]);
    }

    /**
     * CSVの個人コードの先頭ゼロが消えた場合（Excel保存など）、6桁ゼロ埋めで復元される
     *
     * @test
     */
    public function csv_import_pads_employee_code_with_leading_zeros(): void
    {
        $csv = $this->createCsvContent([
            ['100', 'テスト花子', 'テストハナコ', 'hanako@example.com', $this->department->id, 3],
        ]);

        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.csv-import'), ['csv_file' => $file]);

        $this->assertDatabaseHas('users', [
            'employee_code' => '000100',
            'name' => 'テスト花子',
        ]);
    }

    /**
     * CSVの個人コードが1桁の場合でも6桁ゼロ埋めされる
     *
     * @test
     */
    public function csv_import_pads_single_digit_employee_code(): void
    {
        $csv = $this->createCsvContent([
            ['1', 'テスト次郎', 'テストジロウ', 'jiro@example.com', $this->department->id, 3],
        ]);

        $file = UploadedFile::fake()->createWithContent('users.csv', $csv);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.csv-import'), ['csv_file' => $file]);

        $this->assertDatabaseHas('users', [
            'employee_code' => '000001',
            'name' => 'テスト次郎',
        ]);
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
