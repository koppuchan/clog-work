<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\TimeRecordCorrectionRequestDetail;
use App\Repositories\Contracts\CorrectionRequestDetailRepositoryInterface;

/**
 * 打刻修正申請明細リポジトリ実装
 */
class CorrectionRequestDetailRepository implements CorrectionRequestDetailRepositoryInterface
{
    public function __construct(
        private readonly TimeRecordCorrectionRequestDetail $correctionRequestDetail
    ) {}

    /**
     * 打刻修正申請明細を作成
     *
     * @param  array<string, mixed>  $data  明細データ
     */
    public function create(array $data): TimeRecordCorrectionRequestDetail
    {
        return $this->correctionRequestDetail->query()->create($data);
    }

    /**
     * 打刻修正申請明細を更新
     *
     * @param  int  $id  明細ID
     * @param  array<string, mixed>  $data  更新データ
     */
    public function update(int $id, array $data): TimeRecordCorrectionRequestDetail
    {
        $detail = $this->correctionRequestDetail->query()->findOrFail($id);
        $detail->update($data);

        return $detail->fresh();
    }
}
