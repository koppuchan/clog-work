<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\TimeRecordCorrectionRequestDetail;

/**
 * 打刻修正申請明細リポジトリインターフェース
 */
interface CorrectionRequestDetailRepositoryInterface
{
    /**
     * 打刻修正申請明細を作成
     *
     * @param  array<string, mixed>  $data  明細データ
     */
    public function create(array $data): TimeRecordCorrectionRequestDetail;

    /**
     * 打刻修正申請明細を更新
     *
     * @param  int  $id  明細ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): TimeRecordCorrectionRequestDetail;
}
