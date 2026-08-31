<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\AlertLevelEnum;
use App\Enums\RecordSourceEnum;
use App\Models\Company;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Models\DailyWorkSummary;
use App\Models\LaborAlertHistory;
use App\Models\User;
use App\Repositories\LaborAlertRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/LaborAlertRepositoryTest.php
 */
class LaborAlertRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private LaborAlertRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new LaborAlertRepository(
            new DailyWorkSummary,
            new CompanyLaborAlertSetting,
            new CompanyAlertMessage,
            new LaborAlertHistory
        );
    }

    // ========================================
    // findAlertSettingsByCompanyId
    // ========================================

    public function test_find_alert_settings_by_company_idで有効な設定のみが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'overtime_threshold_hours' => 30,
            'is_enabled' => true,
        ]);
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'overtime_threshold_hours' => 45,
            'is_enabled' => false,
        ]);

        // Act
        $result = $this->repository->findAlertSettingsByCompanyId($company->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->first()->alert_level_id);
    }

    public function test_find_alert_settings_by_company_idで設定がない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findAlertSettingsByCompanyId($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_alert_settings_by_company_idでalert_level_id順にソートされる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'overtime_threshold_hours' => 45,
            'is_enabled' => true,
        ]);
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'overtime_threshold_hours' => 30,
            'is_enabled' => true,
        ]);

        // Act
        $result = $this->repository->findAlertSettingsByCompanyId($company->id);

        // Assert
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->first()->alert_level_id);
        $this->assertEquals(AlertLevelEnum::WARNING, $result->last()->alert_level_id);
    }

    // ========================================
    // findAlertMessagesByCompanyId
    // ========================================

    public function test_find_alert_messages_by_company_idで会社のメッセージが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'title' => '注意タイトル',
            'message' => '注意メッセージ',
        ]);

        // Act
        $result = $this->repository->findAlertMessagesByCompanyId($company->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('注意タイトル', $result->first()->title);
    }

    public function test_find_alert_messages_by_company_idでメッセージがない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findAlertMessagesByCompanyId($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // findAlertSettingByLevel
    // ========================================

    public function test_find_alert_setting_by_levelで有効な設定が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'overtime_threshold_hours' => 30,
            'is_enabled' => true,
        ]);

        // Act
        $result = $this->repository->findAlertSettingByLevel($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(30, $result->overtime_threshold_hours);
    }

    public function test_find_alert_setting_by_levelで無効な設定の場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyLaborAlertSetting::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'overtime_threshold_hours' => 30,
            'is_enabled' => false,
        ]);

        // Act
        $result = $this->repository->findAlertSettingByLevel($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertNull($result);
    }

    public function test_find_alert_setting_by_levelで該当レベルがない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findAlertSettingByLevel($company->id, AlertLevelEnum::WARNING);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // findAlertMessageByLevel
    // ========================================

    public function test_find_alert_message_by_levelで指定レベルのメッセージが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'title' => '警告タイトル',
            'message' => '警告メッセージ',
        ]);

        // Act
        $result = $this->repository->findAlertMessageByLevel($company->id, AlertLevelEnum::WARNING);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('警告タイトル', $result->title);
    }

    public function test_find_alert_message_by_levelで該当レベルがない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findAlertMessageByLevel($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // findMonthlyWorkSummaries
    // ========================================

    public function test_find_monthly_work_summariesで指定月のサマリーが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->forCompany($company->id)->create(['is_retired' => false]);

        // 3月の日次勤務実績を複数日作成
        DailyWorkSummary::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => '2026-03-10',
            'overtime_minutes' => 60,
            'record_source' => RecordSourceEnum::AUTO,
        ]);
        DailyWorkSummary::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'work_date' => '2026-03-15',
            'overtime_minutes' => 30,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findMonthlyWorkSummaries($company->id, 2026, 3);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->user_id);
        $this->assertEquals(90, $result->first()->overtime_minutes); // 60 + 30
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_monthly_work_summariesで該当月にデータがない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findMonthlyWorkSummaries($company->id, 2026, 12);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_monthly_work_summariesで別会社のデータは含まれない(): void
    {
        // Arrange
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user = User::factory()->forCompany($company1->id)->create(['is_retired' => false]);

        DailyWorkSummary::query()->create([
            'company_id' => $company1->id,
            'user_id' => $user->id,
            'work_date' => '2026-03-10',
            'overtime_minutes' => 60,
            'record_source' => RecordSourceEnum::AUTO,
        ]);

        // Act
        $result = $this->repository->findMonthlyWorkSummaries($company2->id, 2026, 3);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // saveAlertHistory
    // ========================================

    public function test_save_alert_historyで新規履歴が作成される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        // Act
        $result = $this->repository->saveAlertHistory([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => 'テストアラート',
            'message' => 'テストメッセージ',
            'is_read' => false,
        ]);

        // Assert
        $this->assertInstanceOf(LaborAlertHistory::class, $result);
        $this->assertEquals($company->id, $result->company_id);
        $this->assertEquals($user->id, $result->user_id);
        $this->assertEquals(50, $result->alert_value);
        $this->assertDatabaseHas('labor_alert_histories', [
            'company_id' => $company->id,
            'user_id' => $user->id,
            'target_year' => 2026,
            'target_month' => 3,
        ]);
    }

    public function test_save_alert_historyで同一ユーザー同一月同一レベルの場合_更新される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 40,
            'threshold_value' => 30,
            'title' => '旧アラート',
            'message' => '旧メッセージ',
            'is_read' => false,
        ]);

        // Act
        $result = $this->repository->saveAlertHistory([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 55,
            'threshold_value' => 30,
            'title' => '新アラート',
            'message' => '新メッセージ',
            'is_read' => false,
        ]);

        // Assert
        $this->assertEquals(55, $result->alert_value);
        $this->assertEquals('新アラート', $result->title);

        // 重複レコードが作成されていないことを確認
        $count = LaborAlertHistory::query()
            ->where('user_id', $user->id)
            ->where('alert_level_id', AlertLevelEnum::CAUTION->value)
            ->where('target_year', 2026)
            ->where('target_month', 3)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ========================================
    // findUnreadAlertHistories
    // ========================================

    public function test_find_unread_alert_historiesで未読のアラートのみが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '未読アラート',
            'message' => '未読メッセージ',
            'is_read' => false,
        ]);
        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'target_year' => 2026,
            'target_month' => 2,
            'alert_value' => 60,
            'threshold_value' => 45,
            'title' => '既読アラート',
            'message' => '既読メッセージ',
            'is_read' => true,
            'read_at' => now(),
        ]);

        // Act
        $result = $this->repository->findUnreadAlertHistories($company->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('未読アラート', $result->first()->title);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_unread_alert_historiesでlimit指定時_件数が制限される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            LaborAlertHistory::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'alert_level_id' => AlertLevelEnum::CAUTION->value,
                'target_year' => 2026,
                'target_month' => $i + 1,
                'alert_value' => 50,
                'threshold_value' => 45,
                'title' => "アラート{$i}",
                'message' => "メッセージ{$i}",
                'is_read' => false,
            ]);
        }

        // Act
        $result = $this->repository->findUnreadAlertHistories($company->id, 3);

        // Assert
        $this->assertCount(3, $result);
    }

    public function test_find_unread_alert_historiesでlimitがnullの場合_全件返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            LaborAlertHistory::query()->create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'alert_level_id' => AlertLevelEnum::CAUTION->value,
                'target_year' => 2026,
                'target_month' => $i + 1,
                'alert_value' => 50,
                'threshold_value' => 45,
                'title' => "アラート{$i}",
                'message' => "メッセージ{$i}",
                'is_read' => false,
            ]);
        }

        // Act
        $result = $this->repository->findUnreadAlertHistories($company->id, null);

        // Assert
        $this->assertGreaterThanOrEqual(3, $result->count());
    }

    public function test_find_unread_alert_historiesで未読がない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findUnreadAlertHistories($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // findAlertHistoriesByMonth
    // ========================================

    public function test_find_alert_histories_by_monthで指定月の履歴が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '3月アラート',
            'message' => '3月メッセージ',
            'is_read' => false,
        ]);
        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 2,
            'alert_value' => 40,
            'threshold_value' => 30,
            'title' => '2月アラート',
            'message' => '2月メッセージ',
            'is_read' => false,
        ]);

        // Act
        $result = $this->repository->findAlertHistoriesByMonth($company->id, 2026, 3);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('3月アラート', $result->first()->title);
        $this->assertTrue($result->first()->relationLoaded('user'));
    }

    public function test_find_alert_histories_by_monthで該当月のデータがない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findAlertHistoriesByMonth($company->id, 2026, 12);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // findUserAlertHistory
    // ========================================

    public function test_find_user_alert_historyでユーザーの月次履歴が返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => 'ユーザーアラート',
            'message' => 'ユーザーメッセージ',
            'is_read' => false,
        ]);

        // Act
        $result = $this->repository->findUserAlertHistory($user->id, 2026, 3);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('ユーザーアラート', $result->title);
    }

    public function test_find_user_alert_historyでアラートレベル指定で絞り込みされる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '注意アラート',
            'message' => '注意メッセージ',
            'is_read' => false,
        ]);
        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 60,
            'threshold_value' => 45,
            'title' => '警告アラート',
            'message' => '警告メッセージ',
            'is_read' => false,
        ]);

        // Act
        $result = $this->repository->findUserAlertHistory($user->id, 2026, 3, AlertLevelEnum::WARNING);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('警告アラート', $result->title);
        $this->assertEquals(AlertLevelEnum::WARNING, $result->alert_level_id);
    }

    public function test_find_user_alert_historyで該当履歴がない場合_nullが返される(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $result = $this->repository->findUserAlertHistory($user->id, 2026, 12);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // markAlertHistoryAsRead
    // ========================================

    public function test_mark_alert_history_as_readで履歴が既読になる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user = User::factory()->create();

        $history = LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '未読アラート',
            'message' => '未読メッセージ',
            'is_read' => false,
        ]);

        // Act
        $this->repository->markAlertHistoryAsRead($history->id);

        // Assert
        $history->refresh();
        $this->assertTrue($history->is_read);
        $this->assertNotNull($history->read_at);
    }

    public function test_mark_alert_history_as_readで存在しない_i_dの場合_エラーにならない(): void
    {
        // Act & Assert (例外が発生しないことを確認)
        $this->repository->markAlertHistoryAsRead(99999);
        $this->assertTrue(true);
    }

    // ========================================
    // markAllAlertHistoriesAsRead
    // ========================================

    public function test_mark_all_alert_histories_as_readで会社の全未読が既読になる(): void
    {
        // Arrange
        $company = Company::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user1->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => 'アラート1',
            'message' => 'メッセージ1',
            'is_read' => false,
        ]);
        LaborAlertHistory::query()->create([
            'company_id' => $company->id,
            'user_id' => $user2->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 60,
            'threshold_value' => 45,
            'title' => 'アラート2',
            'message' => 'メッセージ2',
            'is_read' => false,
        ]);

        // Act
        $this->repository->markAllAlertHistoriesAsRead($company->id);

        // Assert
        $unread = LaborAlertHistory::query()
            ->where('company_id', $company->id)
            ->where('is_read', false)
            ->count();
        $this->assertEquals(0, $unread);

        $readRecords = LaborAlertHistory::query()
            ->where('company_id', $company->id)
            ->where('is_read', true)
            ->get();
        $this->assertCount(2, $readRecords);
        foreach ($readRecords as $record) {
            $this->assertNotNull($record->read_at);
        }
    }

    public function test_mark_all_alert_histories_as_readで別会社の未読は既読にならない(): void
    {
        // Arrange
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        LaborAlertHistory::query()->create([
            'company_id' => $company1->id,
            'user_id' => $user1->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '会社1アラート',
            'message' => '会社1メッセージ',
            'is_read' => false,
        ]);
        LaborAlertHistory::query()->create([
            'company_id' => $company2->id,
            'user_id' => $user2->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'target_year' => 2026,
            'target_month' => 3,
            'alert_value' => 50,
            'threshold_value' => 45,
            'title' => '会社2アラート',
            'message' => '会社2メッセージ',
            'is_read' => false,
        ]);

        // Act
        $this->repository->markAllAlertHistoriesAsRead($company1->id);

        // Assert
        $this->assertDatabaseHas('labor_alert_histories', [
            'company_id' => $company2->id,
            'user_id' => $user2->id,
            'is_read' => false,
        ]);
    }

    public function test_mark_all_alert_histories_as_readで未読がない場合_エラーにならない(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act & Assert (例外が発生しないことを確認)
        $this->repository->markAllAlertHistoriesAsRead($company->id);
        $this->assertTrue(true);
    }
}
