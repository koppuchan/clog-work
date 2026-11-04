<?php

declare(strict_types=1);

namespace Tests\Feature\Controllers\Staff;

use App\Models\Company;
use App\Models\Department;
use App\Models\TimeRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * スタッフ画面（スマホ）の打刻で、位置情報を記録できること
 *
 * 位置情報は、会社が「位置情報を記録する」設定にしていて、本人が取得に同意しているときだけ保存する。
 * 位置情報が無い・おかしい場合も、打刻自体はできる。
 */
class StampLocationTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create(['is_closed_on_holidays' => false]);
        $this->staff = User::factory()->forCompany($this->company->id)->employee()->create(['is_retired' => false]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function enableLocation(bool $consented = true): void
    {
        $this->company->forceFill(['records_stamp_location' => true])->save();
        if ($consented) {
            $this->staff->forceFill(['location_consented_at' => now()])->save();
        }
    }

    /**
     * @return array<string, float|int|string>
     */
    private function location(): array
    {
        return ['latitude' => 34.7340123, 'longitude' => 135.5001234, 'accuracy' => 24.6];
    }

    private function stamp(string $action, array $payload = [])
    {
        return $this->actingAs($this->staff, 'staff')->postJson("/staff/stamp/{$action}", $payload);
    }

    /**
     * @test
     */
    public function 記録する設定で同意済みなら打刻に位置情報が保存される(): void
    {
        $this->enableLocation();

        $response = $this->stamp('clock-in', $this->location());

        $response->assertOk()->assertJsonPath('record.hasLocation', true);
        $record = TimeRecord::query()->where('user_id', $this->staff->id)->firstOrFail();
        $this->assertEqualsWithDelta(34.7340123, $record->latitude, 0.0000001);
        $this->assertEqualsWithDelta(135.5001234, $record->longitude, 0.0000001);
        $this->assertSame(25, $record->location_accuracy);
    }

    /**
     * @test
     */
    public function 出勤だけでなく退勤・休憩の打刻にも位置情報が保存される(): void
    {
        $this->enableLocation();
        CarbonImmutable::setTestNow('2026-11-04 09:00:00');
        $this->stamp('clock-in', $this->location())->assertOk();
        CarbonImmutable::setTestNow('2026-11-04 12:00:00');
        $this->stamp('break-start', $this->location())->assertOk();
        CarbonImmutable::setTestNow('2026-11-04 13:00:00');
        $this->stamp('break-end', $this->location())->assertOk();
        CarbonImmutable::setTestNow('2026-11-04 18:00:00');
        $this->stamp('clock-out', $this->location())->assertOk();

        $records = TimeRecord::query()->where('user_id', $this->staff->id)->get();

        $this->assertCount(4, $records);
        $this->assertCount(4, $records->filter(fn (TimeRecord $r) => $r->latitude !== null));
    }

    /**
     * @test
     */
    public function 会社が記録しない設定なら位置情報は保存されない(): void
    {
        $this->staff->forceFill(['location_consented_at' => now()])->save();

        $response = $this->stamp('clock-in', $this->location());

        $response->assertOk()->assertJsonPath('record.hasLocation', false);
        $this->assertNull(TimeRecord::query()->where('user_id', $this->staff->id)->firstOrFail()->latitude);
    }

    /**
     * @test
     */
    public function 同意していなければ位置情報は保存されない(): void
    {
        $this->enableLocation(consented: false);

        $response = $this->stamp('clock-in', $this->location());

        $response->assertOk()->assertJsonPath('record.hasLocation', false);
        $this->assertNull(TimeRecord::query()->where('user_id', $this->staff->id)->firstOrFail()->latitude);
    }

    /**
     * @test
     */
    public function 位置情報が無くても打刻できる(): void
    {
        $this->enableLocation();

        $response = $this->stamp('clock-in');

        $response->assertOk()->assertJsonPath('success', true)->assertJsonPath('record.hasLocation', false);
    }

    /**
     * @test
     */
    public function 位置情報の値がおかしくても打刻は止めず、その位置情報だけ使わない(): void
    {
        $this->enableLocation();

        $response = $this->stamp('clock-in', ['latitude' => 200, 'longitude' => 'abc', 'accuracy' => -5]);

        $response->assertOk()->assertJsonPath('record.hasLocation', false);
        $this->assertNull(TimeRecord::query()->where('user_id', $this->staff->id)->firstOrFail()->latitude);
    }

    /**
     * @test
     */
    public function 精度が無い位置情報も保存される(): void
    {
        $this->enableLocation();

        $this->stamp('clock-in', ['latitude' => 34.7, 'longitude' => 135.5])->assertOk();

        $record = TimeRecord::query()->where('user_id', $this->staff->id)->firstOrFail();
        $this->assertNotNull($record->latitude);
        $this->assertNull($record->location_accuracy);
    }

    /**
     * @test
     */
    public function 同意すると同意した日時が記録される(): void
    {
        $this->enableLocation(consented: false);

        $response = $this->actingAs($this->staff, 'staff')->postJson('/staff/stamp/location-consent');

        $response->assertOk()->assertJsonPath('locationRecording.consented', true);
        $this->assertNotNull($this->staff->fresh()->location_consented_at);
    }

    /**
     * @test
     */
    public function 会社が記録しない設定なら同意は記録されない(): void
    {
        $response = $this->actingAs($this->staff, 'staff')->postJson('/staff/stamp/location-consent');

        $response->assertOk()->assertJsonPath('locationRecording.enabled', false);
        $this->assertNull($this->staff->fresh()->location_consented_at);
    }

    /**
     * @test
     */
    public function 打刻画面に位置情報の記録設定と同意の状態が渡される(): void
    {
        $this->enableLocation(consented: false);

        $this->actingAs($this->staff, 'staff')->get('/staff/stamp')->assertInertia(
            fn (Assert $page) => $page
                ->component('Staff/Stamp')
                ->where('locationRecording.enabled', true)
                ->where('locationRecording.consented', false)
        );
    }

    private function attachDepartment(bool $recordsLocation): Department
    {
        $department = Department::query()->create([
            'company_id' => $this->company->id,
            'name' => '加古川店',
            'records_stamp_location' => $recordsLocation,
        ]);
        $this->staff->departments()->attach($department->id, ['is_primary' => true]);

        return $department;
    }

    /**
     * @test
     */
    public function 会社全体の設定がオフでも本人の設定がオンなら対象になる(): void
    {
        $this->staff->forceFill(['records_stamp_location' => true, 'location_consented_at' => now()])->save();

        $this->stamp('clock-in', $this->location())->assertOk()->assertJsonPath('record.hasLocation', true);
    }

    /**
     * @test
     */
    public function 会社全体の設定がオフでも所属部署の設定がオンなら対象になる(): void
    {
        $this->attachDepartment(true);
        $this->staff->forceFill(['location_consented_at' => now()])->save();

        $this->stamp('clock-in', $this->location())->assertOk()->assertJsonPath('record.hasLocation', true);
    }

    /**
     * @test
     */
    public function 会社も部署も本人もオフなら位置情報も同意の画面も出ない(): void
    {
        $this->attachDepartment(false);
        $this->staff->forceFill(['location_consented_at' => now()])->save();

        $this->stamp('clock-in', $this->location())->assertOk()->assertJsonPath('record.hasLocation', false);
        $this->actingAs($this->staff, 'staff')->get('/staff/stamp')->assertInertia(
            fn (Assert $page) => $page->where('locationRecording.enabled', false)->where('locationRecording.consented', false)
        );
    }

    /**
     * @test
     */
    public function 対象の部署に所属していないスタッフは対象にならない(): void
    {
        $this->attachDepartment(true);
        $other = User::factory()->forCompany($this->company->id)->employee()->create(['is_retired' => false]);
        $other->forceFill(['location_consented_at' => now()])->save();

        $response = $this->actingAs($other, 'staff')->postJson('/staff/stamp/clock-in', $this->location());

        $response->assertOk()->assertJsonPath('record.hasLocation', false);
    }

    /**
     * @test
     */
    public function 対象でないスタッフが同意の記録を送っても保存されない(): void
    {
        $this->actingAs($this->staff, 'staff')->postJson('/staff/stamp/location-consent')->assertOk();

        $this->assertNull($this->staff->fresh()->location_consented_at);
    }

    /**
     * @test
     */
    public function 本人の設定がオンなら打刻画面に同意の状態つきで渡される(): void
    {
        $this->staff->forceFill(['records_stamp_location' => true])->save();

        $this->actingAs($this->staff, 'staff')->get('/staff/stamp')->assertInertia(
            fn (Assert $page) => $page->where('locationRecording.enabled', true)->where('locationRecording.consented', false)
        );
    }
}
