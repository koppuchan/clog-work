<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\DepartmentRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

/**
 * スマホ打刻のときに位置情報を記録する対象かどうかを判定するサービス
 *
 * 会社・部署・スタッフの3階層で設定でき、いずれか1つでもオンになっていれば、
 * そのスタッフは位置情報を記録する対象になる（OR結合。打刻画面の非表示設定と同じ考え方）。
 * 3つともオフ（初期状態）なら、位置情報の取得や同意の画面は出ない。
 */
class StampLocationRecordingService
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companyRepository,
        private readonly DepartmentRepositoryInterface $departmentRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {}

    /**
     * スタッフが、位置情報を記録する対象かどうか
     *
     * @param  int  $companyId  会社ID
     * @param  User  $user  判定対象のスタッフ
     */
    public function isEnabled(int $companyId, User $user): bool
    {
        if ($user->records_stamp_location) {
            return true;
        }

        $department = $user->primaryDepartment()->first();
        if ($department && $department->records_stamp_location) {
            return true;
        }

        return (bool) $this->companyRepository->findById($companyId)?->records_stamp_location;
    }

    /**
     * 会社の中に、位置情報を記録する対象が1人でもいるか
     *
     * 勤務実績のCSVに位置の列を付けるかどうかの判定に使う。人によって列が違うと、
     * 同じCSVの中で列がそろわなくなるため、会社の中に対象がいれば全員分に列を付ける。
     */
    public function isUsedInCompany(int $companyId): bool
    {
        return (bool) $this->companyRepository->findById($companyId)?->records_stamp_location
            || $this->departmentRepository->existsWithLocationRecording($companyId)
            || $this->userRepository->existsWithLocationRecording($companyId);
    }
}
