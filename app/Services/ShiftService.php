<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ErrorCodeEnum;
use App\Exceptions\BusinessException;
use App\Exceptions\NotFoundException;
use App\Models\Shift;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Traits\HasLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * シフトサービス
 */
class ShiftService
{
    use HasLogService;

    private const USER_CHUNK_SIZE = 20;

    public function __construct(
        private readonly ShiftRepositoryInterface $shiftRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {}

    /**
     * IDでシフトを取得
     *
     * @param  int  $id  シフトID
     *
     * @throws NotFoundException シフトが見つからない場合
     */
    public function findById(int $id): Shift
    {
        $shift = $this->shiftRepository->findById($id);

        if (! $shift) {
            throw new NotFoundException(ErrorCodeEnum::SHIFT_NOT_FOUND);
        }

        return $shift;
    }

    /**
     * ユーザーの日付範囲のシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  ユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function getShiftsByUserIdAndDateRange(int $companyId, int $userId, string $startDate, string $endDate): Collection
    {
        return $this->shiftRepository->findByUserIdAndDateRange($companyId, $userId, $startDate, $endDate);
    }

    /**
     * 会社全体の日付範囲のシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function getShiftsByCompanyIdAndDateRange(int $companyId, string $startDate, string $endDate): Collection
    {
        return $this->shiftRepository->findByCompanyIdAndDateRange($companyId, $startDate, $endDate);
    }

    /**
     * 部署の日付範囲のシフトを取得
     *
     * @param  int  $companyId  会社ID
     * @param  int  $departmentId  部署ID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @return Collection<int, Shift>
     */
    public function getShiftsByDepartmentIdAndDateRange(int $companyId, int $departmentId, string $startDate, string $endDate): Collection
    {
        return $this->shiftRepository->findByDepartmentIdAndDateRange($companyId, $departmentId, $startDate, $endDate);
    }

    /**
     * シフトを作成
     *
     * @param  array<string, mixed>  $data  シフトデータ
     * @return Shift 作成されたシフト
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function create(array $data): Shift
    {
        try {
            return DB::transaction(function () use ($data): Shift {
                return $this->shiftRepository->create($data);
            });
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::SHIFT_CREATE_FAILED, [
                'data' => $data,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::SHIFT_CREATE_FAILED);
        }
    }

    /**
     * シフトを更新
     *
     * @param  int  $id  シフトID
     * @param  array<string, mixed>  $data  更新データ
     * @return Shift 更新されたシフト
     *
     * @throws NotFoundException シフトが見つからない場合
     * @throws BusinessException ビジネスルール違反時
     */
    public function update(int $id, array $data): Shift
    {
        try {
            // シフトが存在するか確認
            $this->findById($id);

            return DB::transaction(function () use ($id, $data): Shift {
                return $this->shiftRepository->update($id, $data);
            });
        } catch (NotFoundException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::SHIFT_UPDATE_FAILED, [
                'shift_id' => $id,
                'data' => $data,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::SHIFT_UPDATE_FAILED);
        }
    }

    /**
     * シフトを削除
     *
     * @param  int  $id  シフトID
     *
     * @throws NotFoundException シフトが見つからない場合
     * @throws BusinessException 削除できない状態の場合
     */
    public function delete(int $id): void
    {
        try {
            // シフトが存在するか確認
            $this->findById($id);

            $deleted = $this->shiftRepository->delete($id);

            if (! $deleted) {
                throw new BusinessException(ErrorCodeEnum::SHIFT_DELETE_FAILED);
            }
        } catch (NotFoundException $e) {
            throw $e;
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::SHIFT_DELETE_FAILED, [
                'shift_id' => $id,
            ], $e);

            throw new BusinessException(ErrorCodeEnum::SHIFT_DELETE_FAILED);
        }
    }

    /**
     * 複数のシフトを一括作成・更新
     *
     * @param  int  $companyId  会社ID
     * @param  array<int, array<string, mixed>>  $shifts  シフトデータの配列
     *
     * @throws BusinessException ビジネスルール違反時
     */
    public function upsertMany(int $companyId, array $shifts): void
    {
        try {
            DB::transaction(function () use ($companyId, $shifts): void {
                $this->shiftRepository->upsertMany($companyId, $shifts);
            });
        } catch (\Throwable $e) {
            $this->logError(ErrorCodeEnum::SHIFT_BULK_UPSERT_FAILED, [
                'company_id' => $companyId,
                'shifts_count' => count($shifts),
            ], $e);

            throw new BusinessException(ErrorCodeEnum::SHIFT_BULK_UPSERT_FAILED);
        }
    }

    /**
     * ユーザーの曜日別デフォルトシフトパターンを取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, int|null> 曜日名をキーとしたシフトパターンIDの配列
     */
    public function getUserShiftPatterns(int $userId): array
    {
        return $this->userRepository->getShiftPatterns($userId);
    }

    /**
     * ユーザーの勤務可能曜日を取得
     *
     * @param  int  $userId  ユーザーID
     * @return array<string, bool> 曜日名をキーとした配列
     */
    public function getUserAvailableWorkDays(int $userId): array
    {
        return $this->userRepository->getAvailableWorkDays($userId);
    }

    /**
     * スタッフ画面用のシフトデータを取得
     *
     * 権限スコープに基づいてシフトを取得する
     * - self: 自分のシフトのみ
     * - department: 自分の所属部署のシフト
     * - company: 全社員のシフト
     *
     * @param  int  $companyId  会社ID
     * @param  int  $userId  閲覧者のユーザーID
     * @param  string  $startDate  開始日（Y-m-d形式）
     * @param  string  $endDate  終了日（Y-m-d形式）
     * @param  string  $scope  権限スコープ（self, department, company）
     * @param  int|null  $departmentId  部署ID（scopeがdepartmentの場合に使用）
     * @return array{users: array<int, array{id: int, name: string, is_self: bool}>, shifts: array<string, array<int, array{id: int, shift_pattern_id: int|null, shift_pattern_name: string|null, start_time: string|null, end_time: string|null, color: string|null, note: string|null}>>, scope: string}
     */
    public function getStaffShiftViewData(
        int $companyId,
        int $userId,
        string $startDate,
        string $endDate,
        string $scope = 'self',
        ?int $departmentId = null
    ): array {
        // スコープに応じたユーザー取得
        $users = match ($scope) {
            'company' => $this->userRepository->findByCompanyId($companyId),
            'department' => $departmentId
                ? $this->userRepository->findByDepartmentId($companyId, $departmentId)
                : collect([$this->userRepository->findById($userId)]),
            default => collect([$this->userRepository->findById($userId)]),
        };

        // ユーザーが取得できない場合は空を返す
        if ($users->isEmpty() || $users->first() === null) {
            return [
                'users' => [],
                'shifts' => [],
                'scope' => $scope,
            ];
        }

        // 取得したユーザーIDリスト
        $userIds = $users->pluck('id')->toArray();

        // 期間内のシフトを取得（取得したユーザーのみ）
        $shifts = $this->shiftRepository->findByCompanyIdAndDateRange($companyId, $startDate, $endDate)
            ->filter(fn ($shift) => in_array($shift->user_id, $userIds, true));

        // ユーザー情報をフォーマット（自分かどうかのフラグ付き）
        // 部署ごとにまとめて表示できるよう部署名を持たせ、個人コード順に並べる
        $usersFormatted = $users
            ->filter(fn ($user) => $user !== null)
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->employee_code,
                'department_name' => $user->primaryDepartment()?->first()?->name,
                'is_self' => $user->id === $userId,
            ])
            ->sortBy([
                fn (array $a, array $b) => ($a['department_name'] ?? '') <=> ($b['department_name'] ?? ''),
                fn (array $a, array $b) => ($a['employee_code'] ?? '') <=> ($b['employee_code'] ?? ''),
            ])
            ->values()
            ->toArray();

        // シフトを日付・ユーザーでインデックス化
        $shiftsIndexed = $shifts->groupBy(fn ($shift) => $shift->shift_date->format('Y-m-d'))
            ->map(fn ($dateShifts) => $dateShifts->keyBy('user_id')
                ->map(fn ($shift) => [
                    'id' => $shift->id,
                    'shift_pattern_id' => $shift->shift_pattern_id,
                    'shift_pattern_name' => $shift->shiftPattern?->name,
                    'start_time' => $shift->shiftPattern?->start_time,
                    'end_time' => $shift->shiftPattern?->end_time,
                    'color' => $shift->shiftPattern?->colors->first()?->background_color,
                    'note' => $shift->note,
                ])
                ->toArray()
            )
            ->toArray();

        return [
            'users' => $usersFormatted,
            'shifts' => $shiftsIndexed,
            'scope' => $scope,
        ];
    }

    /**
     * 複数ユーザーのシフトパターンを一括取得
     *
     * @param  array<int>  $userIds  ユーザーIDの配列
     * @return array<int, array{shift_patterns: array<string, int|null>}> ユーザーIDをキーとした配列
     */
    public function getBulkUsersShiftInfo(array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }

        $weekdayIdToName = CompanyService::WEEKDAY_MAP;

        // 初期値テンプレート
        $emptyShiftPatterns = [
            'sunday' => null,
            'monday' => null,
            'tuesday' => null,
            'wednesday' => null,
            'thursday' => null,
            'friday' => null,
            'saturday' => null,
        ];

        $allShiftPatterns = collect();

        collect($userIds)->chunk(self::USER_CHUNK_SIZE)->each(function ($chunk) use (&$allShiftPatterns) {
            $allShiftPatterns = $allShiftPatterns->merge(
                $this->userRepository->getBulkShiftPatterns($chunk->toArray())
            );
        });

        // ユーザーID別にグループ化
        $shiftPatternsGrouped = $allShiftPatterns->groupBy('user_id');

        // 結果を構築
        return collect($userIds)->mapWithKeys(function ($userId) use ($shiftPatternsGrouped, $weekdayIdToName, $emptyShiftPatterns) {
            // シフトパターンを変換
            $userShiftPatterns = $shiftPatternsGrouped->get($userId, collect())
                ->mapWithKeys(fn ($pattern) => [$weekdayIdToName[$pattern->weekday_id] ?? '' => $pattern->shift_pattern_id])
                ->toArray();

            return [
                $userId => [
                    'shift_patterns' => array_merge($emptyShiftPatterns, $userShiftPatterns),
                ],
            ];
        })->toArray();
    }
}
