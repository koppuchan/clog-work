<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Admin;

use App\Models\Company;
use App\Models\ShiftPattern;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * シフトパターン更新時の休憩・労働時間の再計算を検証する。
 *
 * 休憩開始/終了の時刻を変更して更新しても、break_minutesが古い値の
 * まま再計算されず、シフトパターン一覧の「(休憩 45分)」等の表示が
 * 変更後も変わらなかった（No.50報告）。
 */
class UpdateShiftPatternTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->admin = User::factory()
            ->forCompany($this->company->id)
            ->admin()
            ->create(['is_retired' => false]);
    }

    /**
     * @test
     */
    public function 休憩終了時刻を変更するとbreak_minutesが再計算される(): void
    {
        // Arrange: 12:00-12:45（45分休憩）のシフトパターン
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => 'テストシフト',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 495,
            'break_mode' => 2,
            'break_minutes' => 45,
            'break_start' => '12:00',
            'break_end' => '12:45',
        ]);

        // Act: 休憩終了を13:00に変更（休憩60分になるはず）
        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/shift-patterns/{$pattern->id}", [
                'name' => 'テストシフト',
                'startTime' => '09:00',
                'endTime' => '18:00',
                'workMinutes' => 495,
                'breakMode' => 2,
                'breakMinutes' => null,
                'breakStart' => '12:00',
                'breakEnd' => '13:00',
                'autoFillBreak' => false,
            ]);

        // Assert: レスポンスと保存データの両方でbreak_minutesが60に再計算される
        $response->assertOk();
        $response->assertJson(['breakMinutes' => 60]);
        $this->assertEquals(60, $pattern->fresh()->break_minutes);
    }

    /**
     * @test
     *
     * 休憩の時間帯を持たない（分数のみ指定の）パターンで、休憩と無関係な
     * フィールド（名前）だけを更新した場合は、既存のbreak_minutesが
     * 維持されるべき（再計算できる時間帯情報が無いため）。
     */
    public function 分数のみ指定の休憩は無関係なフィールド更新でも維持される(): void
    {
        // Arrange: break_mode=1（分数のみ指定）、時間帯を持たないパターン
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '旧名称',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 480,
            'break_mode' => 1,
            'break_minutes' => 45,
            'break_start' => null,
            'break_end' => null,
        ]);

        // Act: 名前だけを更新
        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/shift-patterns/{$pattern->id}", [
                'name' => '新名称',
            ]);

        // Assert: break_minutesは45のまま維持される
        $response->assertOk();
        $this->assertEquals(45, $pattern->fresh()->break_minutes);
        $this->assertEquals('新名称', $pattern->fresh()->name);
    }

    /**
     * @test
     *
     * 休憩の時刻を空にして更新しても、保存前の休憩時間で埋め戻されて
     * 「(休憩: 60分)」が残り続けていた（クライアント報告: 削除しても再度現れる）。
     */
    public function 休憩の時刻を空にして更新すると休憩なしになる(): void
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '休憩あり',
            'start_time' => '10:00',
            'end_time' => '18:00',
            'work_minutes' => 420,
            'break_mode' => 2,
            'break_minutes' => 60,
            'break_start' => '12:00',
            'break_end' => '13:00',
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/shift-patterns/{$pattern->id}", [
                'name' => '休憩あり',
                'startTime' => '10:00',
                'endTime' => '18:00',
                'workMinutes' => null,
                'breakMode' => 2,
                'breakMinutes' => null,
                'breakStart' => null,
                'breakEnd' => null,
                'autoFillBreak' => false,
            ]);

        $response->assertOk();
        $response->assertJson(['breakStart' => null, 'breakEnd' => null, 'breakMinutes' => null, 'workMinutes' => 480]);

        $fresh = $pattern->fresh();
        $this->assertNull($fresh->break_start);
        $this->assertNull($fresh->break_end);
        $this->assertNull($fresh->break_minutes);
        $this->assertSame(480, $fresh->work_minutes);
    }

    /**
     * @test
     */
    public function 休憩の時刻を空にしても分数を明示すればその休憩は残る(): void
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '分数のみ',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 495,
            'break_mode' => 1,
            'break_minutes' => 45,
            'break_start' => null,
            'break_end' => null,
        ]);

        $response = $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/shift-patterns/{$pattern->id}", [
                'name' => '分数のみ',
                'startTime' => '09:00',
                'endTime' => '18:00',
                'workMinutes' => null,
                'breakMode' => 2,
                'breakMinutes' => 45,
                'breakStart' => null,
                'breakEnd' => null,
            ]);

        $response->assertOk();
        $this->assertSame(45, $pattern->fresh()->break_minutes);
        $this->assertSame(495, $pattern->fresh()->work_minutes);
    }

    /**
     * @test
     */
    public function 休憩の時刻を入れ直すと休憩の分数も入れ直した時刻で決まる(): void
    {
        $pattern = ShiftPattern::query()->create([
            'company_id' => $this->company->id,
            'name' => '休憩なし',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'work_minutes' => 540,
            'break_mode' => 2,
            'break_minutes' => null,
            'break_start' => null,
            'break_end' => null,
        ]);

        $this->actingAs($this->admin, 'admin')
            ->putJson("/admin/settings/shift-patterns/{$pattern->id}", [
                'name' => '休憩なし',
                'startTime' => '09:00',
                'endTime' => '18:00',
                'workMinutes' => null,
                'breakMode' => 2,
                'breakMinutes' => null,
                'breakStart' => '12:00',
                'breakEnd' => '12:45',
            ])
            ->assertOk();

        $fresh = $pattern->fresh();
        $this->assertSame(45, $fresh->break_minutes);
        $this->assertSame(495, $fresh->work_minutes);
    }
}
