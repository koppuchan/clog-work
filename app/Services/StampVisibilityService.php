<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * 打刻画面表示判定サービス
 *
 * 会社・部署・ユーザーの3階層で打刻画面の非表示設定を判定する。
 * いずれか1つでも非表示が設定されていれば、打刻画面は非表示となる（OR結合）。
 */
class StampVisibilityService
{
    /**
     * 打刻画面が非表示かどうかを判定
     *
     * @param  User  $user  判定対象のユーザー
     * @return bool 非表示の場合true
     */
    public function isStampHidden(User $user): bool
    {
        // 1. ユーザー自身の設定
        if ($user->is_stamp_hidden) {
            return true;
        }

        // 2. 所属部署の設定（プライマリ部署）
        $department = $user->primaryDepartment()->first();
        if ($department && $department->is_stamp_hidden) {
            return true;
        }

        // 3. 所属会社の設定
        $company = $user->primaryCompany()->first();
        if ($company && $company->is_stamp_hidden) {
            return true;
        }

        return false;
    }
}
