<?php

declare(strict_types=1);

namespace Tests\Feature\Services;

use App\Models\Company;
use App\Models\User;
use App\Services\StampService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 退勤し忘れた勤務セッションの打ち切りの検証。
 *
 * 出勤打刻に対応する退勤打刻がないと、そのユーザーはいつまでも「勤務中」と
 * 判定される。この状態で数日後に打刻すると、古い出勤に対する退勤として
 * 記録され、その間ずっと出勤打刻もできなくなる。
 *
 * 夜勤の日跨ぎは吸収しつつ、打刻漏れは引きずらないよう24時間で打ち切る。
 */
class WorkSessionExpiryTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    private StampService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config(['attendance.work_session_max_hours' => 24]);

        $this->company = Company::factory()->create(['company_code' => '990001']);
        $this->user = User::factory()->create(['name' => '夜勤 太郎']);
        $this->user->companies()->attach($this->company->id, ['is_primary' => true]);
        $this->service = app(StampService::class);
    }

    private function isWorking(): bool
    {
        return $this->service->getCurrentStatus($this->company->id, $this->user->id)['isWorking'];
    }

    /**
     * @test
     */
    public function 出勤直後は勤務中と判定される(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-06-05 22:00'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Assert
        $this->assertTrue($this->isWorking());
    }

    /**
     * @test
     */
    public function 夜勤の日跨ぎは勤務中のまま扱われる(): void
    {
        // Arrange: 22時出勤、翌朝6時に判定
        $this->travelTo(Carbon::parse('2026-06-05 22:00'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-06 06:00'));

        // Assert: 8時間経過。日跨ぎ勤務の続きとして退勤できる必要がある
        $this->assertTrue($this->isWorking());
    }

    /**
     * @test
     */
    public function 二十四時間直前はまだ勤務中と扱われる(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-06-05 22:00'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-06 21:00'));

        // Assert
        $this->assertTrue($this->isWorking());
    }

    /**
     * @test
     */
    public function 二十四時間を超えたセッションは打ち切られる(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-06-05 22:00'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-06 23:00'));

        // Assert
        $this->assertFalse($this->isWorking());
    }

    /**
     * @test
     */
    public function 退勤し忘れた翌週でも出勤打刻できる(): void
    {
        // Arrange: 6/5 に出勤して退勤を忘れた状態
        $this->travelTo(Carbon::parse('2026-06-05 16:56'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Act: 7日後にカードをかざす
        $this->travelTo(Carbon::parse('2026-06-12 09:00'));

        // Assert: 古い出勤に対する退勤ではなく、新しい出勤として受け付ける
        $this->assertFalse($this->isWorking());

        $record = $this->service->clockIn($this->company->id, $this->user->id);
        $this->assertTrue($record->record_type->isWorkStart());
    }

    /**
     * @test
     */
    public function 退勤済みなら経過時間に関わらず勤務中ではない(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-06-05 09:00'));
        $this->service->clockIn($this->company->id, $this->user->id);
        $this->travelTo(Carbon::parse('2026-06-05 18:00'));
        $this->service->clockOut($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-06 09:00'));

        // Assert
        $this->assertFalse($this->isWorking());
    }

    /**
     * @test
     */
    public function 休憩に入ったまま二十四時間を超えても打ち切られる(): void
    {
        // Arrange
        $this->travelTo(Carbon::parse('2026-06-05 09:00'));
        $this->service->clockIn($this->company->id, $this->user->id);
        $this->travelTo(Carbon::parse('2026-06-05 12:00'));
        $this->service->breakStart($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-07 09:00'));
        $status = $this->service->getCurrentStatus($this->company->id, $this->user->id);

        // Assert
        $this->assertFalse($status['isWorking']);
        $this->assertFalse($status['isOnBreak']);
    }

    /**
     * @test
     */
    public function 上限を0にすると打ち切らない(): void
    {
        // Arrange: 従来どおりの挙動に戻せることの確認
        config(['attendance.work_session_max_hours' => 0]);

        $this->travelTo(Carbon::parse('2026-06-05 09:00'));
        $this->service->clockIn($this->company->id, $this->user->id);

        // Act
        $this->travelTo(Carbon::parse('2026-06-12 09:00'));

        // Assert
        $this->assertTrue($this->isWorking());
    }
}
