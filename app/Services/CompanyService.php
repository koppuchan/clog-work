<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * 会社サービス
 */
class CompanyService
{
    /**
     * 曜日IDから曜日名へのマッピング
     */
    public const WEEKDAY_MAP = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday',
    ];

    /** 最初の会社に割り当てる会社コード */
    private const INITIAL_COMPANY_CODE = '000001';

    /** 会社コードの桁数 */
    private const COMPANY_CODE_DIGITS = 6;

    private const MIN_PAYROLL_CLOSING_DAY = 1;

    private const MAX_PAYROLL_CLOSING_DAY = 31;

    private const MAX_DAILY_WORKING_HOURS = 24;

    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository
    ) {}

    /**
     * IDで会社を取得
     *
     * @param  int  $id  会社ID
     *
     * @throws NotFoundException 会社が見つからない場合
     */
    public function findById(int $id): Company
    {
        $company = $this->companyRepository->findById($id);

        if (! $company) {
            throw new NotFoundException('会社が見つかりません。');
        }

        return $company;
    }

    /**
     * IDで会社をリレーションと共に取得
     *
     * @param  int  $id  会社ID
     *
     * @throws NotFoundException 会社が見つからない場合
     */
    public function findByIdWithRelations(int $id): Company
    {
        $company = $this->companyRepository->findByIdWithRelations($id);

        if (! $company) {
            throw new NotFoundException('会社が見つかりません。');
        }

        return $company;
    }

    /**
     * 会社設定を更新
     *
     * @param  int  $id  会社ID
     * @param  array<string, mixed>  $data  更新データ
     * @return Company 更新された会社
     *
     * @throws NotFoundException 会社が見つからない場合
     * @throws BusinessException ビジネスルール違反時
     */
    public function updateSettings(int $id, array $data): Company
    {
        // 会社が存在するか確認
        // Todo: throwする例外の内容を充実させる（例：どの会社IDで見つからなかったのかなど）
        $company = $this->findById($id);

        // バリデーション: 給与締め日
        if (isset($data['payroll_closing_day'])) {
            $this->validatePayrollClosingDay($data['payroll_closing_day']);

            // 保存先は数値のため、末締めは31日として扱う（画面の表記と揃える）
            if ($data['payroll_closing_day'] === 'end') {
                $data['payroll_closing_day'] = self::MAX_PAYROLL_CLOSING_DAY;
            }
        }

        // バリデーション: 所定労働時間
        if (isset($data['daily_working_hours'])) {
            $this->validateDailyWorkingHours($data['daily_working_hours']);
        }

        // トランザクション内で更新
        return DB::transaction(function () use ($id, $data) {
            return $this->companyRepository->update($id, $data);
        });
    }

    /**
     * 会社を作成
     *
     * @param  array<string, mixed>  $data  会社データ
     * @return Company 作成された会社
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function create(array $data): Company
    {
        // 会社コードは必須かつ会社ごとに一意のため、指定がなければ採番する
        if (empty($data['name'])) {
            throw new BusinessException('会社名は必須です。');
        }

        return DB::transaction(function () use ($data) {
            if (empty($data['company_code'])) {
                $data['company_code'] = $this->generateCompanyCode();
            }

            return $this->companyRepository->create($data);
        });
    }

    /**
     * 会社コードを採番する
     *
     * 既存の最大値に1を足した6桁の数字を割り当てる。
     */
    private function generateCompanyCode(): string
    {
        $maxCode = $this->companyRepository->getMaxCompanyCode();

        if ($maxCode === null) {
            return self::INITIAL_COMPANY_CODE;
        }

        $nextNumber = (int) $maxCode + 1;

        if ($nextNumber > 999999) {
            throw new BusinessException('利用可能な会社コードがありません。');
        }

        return str_pad((string) $nextNumber, self::COMPANY_CODE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * 会社を削除
     *
     * @param  int  $id  会社ID
     *
     * @throws NotFoundException 会社が見つからない場合
     * @throws BusinessException 削除できない状態の場合
     */
    public function delete(int $id): void
    {
        // Todo: throw する例外の内容を充実させる（例：どの会社IDで見つからなかったのかなど）
        $company = $this->findById($id);

        // 削除可能かチェック（例：ユーザーが紐づいている場合は削除不可など）
        // 今回は単純に削除を許可
        $deleted = $this->companyRepository->delete($id);

        if (! $deleted) {
            throw new BusinessException('会社の削除に失敗しました。');
        }
    }

    /**
     * 会社の定休日を取得（フロントエンド用）
     *
     * @param  int  $companyId  会社ID
     * @return array<string, bool> 曜日名をキーとした定休日情報
     */
    public function getRegularHolidays(int $companyId): array
    {
        $weekdayIds = $this->companyRepository->getRegularHolidayWeekdayIds($companyId);

        $regularHolidays = array_fill_keys(array_values(self::WEEKDAY_MAP), false);

        foreach ($weekdayIds as $weekdayId) {
            if (isset(self::WEEKDAY_MAP[$weekdayId])) {
                $regularHolidays[self::WEEKDAY_MAP[$weekdayId]] = true;
            }
        }

        return $regularHolidays;
    }

    /**
     * 給与締め日をバリデーション
     *
     * @param  string  $payrollClosingDay  給与締め日
     *
     * @throws BusinessException バリデーションエラー時
     */
    private function validatePayrollClosingDay(string $payrollClosingDay): void
    {
        if ($payrollClosingDay === 'end') {
            return;
        }

        $day = (int) $payrollClosingDay;
        if ($day < self::MIN_PAYROLL_CLOSING_DAY || $day > self::MAX_PAYROLL_CLOSING_DAY) {
            throw new BusinessException('給与締め日は1〜31または"end"を指定してください。');
        }
    }

    /**
     * 所定労働時間をバリデーション
     *
     * @param  string  $dailyWorkingHours  所定労働時間
     *
     * @throws BusinessException バリデーションエラー時
     */
    private function validateDailyWorkingHours(string $dailyWorkingHours): void
    {
        $hours = (float) $dailyWorkingHours;
        if ($hours <= 0 || $hours > self::MAX_DAILY_WORKING_HOURS) {
            throw new BusinessException('所定労働時間は0より大きく24時間以内で指定してください。');
        }
    }
}
