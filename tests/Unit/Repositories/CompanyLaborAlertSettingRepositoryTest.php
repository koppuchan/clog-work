<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\AlertLevelEnum;
use App\Models\Company;
use App\Models\CompanyAlertMessage;
use App\Models\CompanyLaborAlertSetting;
use App\Repositories\CompanyLaborAlertSettingRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * php artisan test tests/Unit/Repositories/CompanyLaborAlertSettingRepositoryTest.php
 */
class CompanyLaborAlertSettingRepositoryTest extends TestCase
{
    use DatabaseTransactions;

    private CompanyLaborAlertSettingRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CompanyLaborAlertSettingRepository(
            new CompanyLaborAlertSetting,
            new CompanyAlertMessage
        );
    }

    // ========================================
    // findSettingsByCompanyId
    // ========================================

    public function test_find_settings_by_company_idで会社の全設定が返される(): void
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
            'is_enabled' => true,
        ]);

        // Act
        $result = $this->repository->findSettingsByCompanyId($company->id);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->first()->alert_level_id);
    }

    public function test_find_settings_by_company_idで設定がない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findSettingsByCompanyId($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    public function test_find_settings_by_company_idでalert_level_id順にソートされる(): void
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
        $result = $this->repository->findSettingsByCompanyId($company->id);

        // Assert
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->first()->alert_level_id);
        $this->assertEquals(AlertLevelEnum::WARNING, $result->last()->alert_level_id);
    }

    // ========================================
    // findMessagesByCompanyId
    // ========================================

    public function test_find_messages_by_company_idで会社の全メッセージが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'title' => '注意アラート',
            'message' => '残業時間が{threshold}時間を超えています',
        ]);
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'title' => '警告アラート',
            'message' => '残業時間が{threshold}時間を大幅に超えています',
        ]);

        // Act
        $result = $this->repository->findMessagesByCompanyId($company->id);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_find_messages_by_company_idでメッセージがない場合_空コレクションが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findMessagesByCompanyId($company->id);

        // Assert
        $this->assertCount(0, $result);
    }

    // ========================================
    // findSettingByLevel
    // ========================================

    public function test_find_setting_by_levelで指定レベルの設定が返される(): void
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
        $result = $this->repository->findSettingByLevel($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals(30, $result->overtime_threshold_hours);
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->alert_level_id);
    }

    public function test_find_setting_by_levelで該当レベルがない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findSettingByLevel($company->id, AlertLevelEnum::WARNING);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // findMessageByLevel
    // ========================================

    public function test_find_message_by_levelで指定レベルのメッセージが返される(): void
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
        $result = $this->repository->findMessageByLevel($company->id, AlertLevelEnum::WARNING);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('警告タイトル', $result->title);
        $this->assertEquals('警告メッセージ', $result->message);
    }

    public function test_find_message_by_levelで該当レベルがない場合_nullが返される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->findMessageByLevel($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertNull($result);
    }

    // ========================================
    // saveSetting
    // ========================================

    public function test_save_settingで新規設定が作成される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->saveSetting($company->id, AlertLevelEnum::CAUTION, [
            'overtime_threshold_hours' => 30,
            'is_enabled' => true,
        ]);

        // Assert
        $this->assertInstanceOf(CompanyLaborAlertSetting::class, $result);
        $this->assertEquals($company->id, $result->company_id);
        $this->assertEquals(AlertLevelEnum::CAUTION, $result->alert_level_id);
        $this->assertEquals(30, $result->overtime_threshold_hours);
        $this->assertTrue($result->is_enabled);
        $this->assertDatabaseHas('company_labor_alert_settings', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'overtime_threshold_hours' => 30,
        ]);
    }

    public function test_save_settingで既存設定が更新される(): void
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
        $result = $this->repository->saveSetting($company->id, AlertLevelEnum::CAUTION, [
            'overtime_threshold_hours' => 40,
            'is_enabled' => false,
        ]);

        // Assert
        $this->assertEquals(40, $result->overtime_threshold_hours);
        $this->assertFalse($result->is_enabled);

        // 重複レコードが作成されていないことを確認
        $count = CompanyLaborAlertSetting::query()
            ->where('company_id', $company->id)
            ->where('alert_level_id', AlertLevelEnum::CAUTION->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ========================================
    // saveMessage
    // ========================================

    public function test_save_messageで新規メッセージが作成される(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act
        $result = $this->repository->saveMessage($company->id, AlertLevelEnum::WARNING, [
            'title' => '新規警告タイトル',
            'message' => '新規警告メッセージ',
        ]);

        // Assert
        $this->assertInstanceOf(CompanyAlertMessage::class, $result);
        $this->assertEquals($company->id, $result->company_id);
        $this->assertEquals(AlertLevelEnum::WARNING, $result->alert_level_id);
        $this->assertEquals('新規警告タイトル', $result->title);
        $this->assertEquals('新規警告メッセージ', $result->message);
        $this->assertDatabaseHas('company_alert_messages', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'title' => '新規警告タイトル',
        ]);
    }

    public function test_save_messageで既存メッセージが更新される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'title' => '旧タイトル',
            'message' => '旧メッセージ',
        ]);

        // Act
        $result = $this->repository->saveMessage($company->id, AlertLevelEnum::WARNING, [
            'title' => '新タイトル',
            'message' => '新メッセージ',
        ]);

        // Assert
        $this->assertEquals('新タイトル', $result->title);
        $this->assertEquals('新メッセージ', $result->message);

        // 重複レコードが作成されていないことを確認
        $count = CompanyAlertMessage::query()
            ->where('company_id', $company->id)
            ->where('alert_level_id', AlertLevelEnum::WARNING->value)
            ->count();
        $this->assertEquals(1, $count);
    }

    // ========================================
    // deleteSetting
    // ========================================

    public function test_delete_settingで指定レベルの設定が削除される(): void
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
            'is_enabled' => true,
        ]);

        // Act
        $this->repository->deleteSetting($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertDatabaseMissing('company_labor_alert_settings', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
        ]);
        $this->assertDatabaseHas('company_labor_alert_settings', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
        ]);
    }

    public function test_delete_settingで該当設定がない場合_エラーにならない(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act & Assert (例外が発生しないことを確認)
        $this->repository->deleteSetting($company->id, AlertLevelEnum::CAUTION);
        $this->assertTrue(true);
    }

    // ========================================
    // deleteMessage
    // ========================================

    public function test_delete_messageで指定レベルのメッセージが削除される(): void
    {
        // Arrange
        $company = Company::factory()->create();
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
            'title' => '注意タイトル',
            'message' => '注意メッセージ',
        ]);
        CompanyAlertMessage::query()->create([
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
            'title' => '警告タイトル',
            'message' => '警告メッセージ',
        ]);

        // Act
        $this->repository->deleteMessage($company->id, AlertLevelEnum::CAUTION);

        // Assert
        $this->assertDatabaseMissing('company_alert_messages', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::CAUTION->value,
        ]);
        $this->assertDatabaseHas('company_alert_messages', [
            'company_id' => $company->id,
            'alert_level_id' => AlertLevelEnum::WARNING->value,
        ]);
    }

    public function test_delete_messageで該当メッセージがない場合_エラーにならない(): void
    {
        // Arrange
        $company = Company::factory()->create();

        // Act & Assert (例外が発生しないことを確認)
        $this->repository->deleteMessage($company->id, AlertLevelEnum::WARNING);
        $this->assertTrue(true);
    }
}
