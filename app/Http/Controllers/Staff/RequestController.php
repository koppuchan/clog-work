<?php

declare(strict_types=1);

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Http\Requests\Staff\StoreRequestRequest;
use App\Models\User;
use App\Services\RequestService;
use App\Services\TimeRecordCorrectionRequestService;
use Illuminate\Http\RedirectResponse;

/**
 * スタッフ向け申請コントローラー
 */
class RequestController extends Controller
{
    private const CLOCK_ERROR_TYPE = 'clock-error';

    public function __construct(
        private readonly RequestService $requestService,
        private readonly TimeRecordCorrectionRequestService $correctionRequestService
    ) {}

    /**
     * 申請を作成
     */
    public function store(StoreRequestRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();
        $companyId = $user->company_id;

        // 打刻間違いの場合はtime_record_correction_requestsに登録
        if ($validated['type'] === self::CLOCK_ERROR_TYPE) {
            $this->correctionRequestService->createClockErrorRequest(
                $companyId,
                $user->id,
                [
                    'target_date' => $validated['target_date'],
                    'reason' => $validated['reason'],
                    'start_time' => $validated['start_time'] ?? null,
                    'end_time' => $validated['end_time'] ?? null,
                    'break_start_time' => $validated['break_start_time'] ?? null,
                    'break_end_time' => $validated['break_end_time'] ?? null,
                ]
            );

            return redirect()->back()->with('success', '打刻修正申請が完了しました');
        }

        // 通常の申請処理
        $applicationType = $this->requestService->getApplicationTypeByCode($validated['type']);

        if (! $applicationType) {
            return redirect()->back()->withErrors(['type' => '無効な申請タイプです']);
        }

        try {
            $this->requestService->createRequest($companyId, $user->id, [
                'type' => $applicationType->id,
                'target_date' => $validated['target_date'],
                'reason' => $validated['reason'],
                'start_time' => $validated['start_time'] ?? null,
                'end_time' => $validated['end_time'] ?? null,
            ]);
        } catch (\RuntimeException $e) {
            return redirect()->back()->withErrors(['type' => $e->getMessage()]);
        }

        return redirect()->back()->with('success', '申請が完了しました');
    }
}
