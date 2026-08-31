<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\ErrorCodeEnum;
use App\Enums\RequestStatusEnum;
use App\Http\Controllers\Controller;
use App\Services\PermissionService;
use App\Services\RequestService;
use App\Services\TimeRecordCorrectionRequestService;
use App\Traits\HasLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request as HttpRequest;
use Inertia\Inertia;
use Inertia\Response;

/**
 * 管理者向け申請管理コントローラー
 */
class RequestController extends Controller
{
    use HasLogService;

    public function __construct(
        private readonly RequestService $requestService,
        private readonly TimeRecordCorrectionRequestService $correctionRequestService,
        private readonly PermissionService $permissionService
    ) {}

    private const PER_PAGE = 10;

    /**
     * 申請一覧を表示
     *
     * requestsテーブルとtime_record_correction_requestsテーブルの両方を統合して表示
     */
    public function index(HttpRequest $httpRequest): Response
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;
        $status = $httpRequest->query('status');
        $page = (int) $httpRequest->query('page', 1);
        $nameQuery = $httpRequest->query('name');
        $targetMonth = $httpRequest->query('target_month');
        $approverId = $httpRequest->query('approver_id');
        $typeFilter = $httpRequest->query('type');

        // status パラメータ未指定の場合はデフォルトで「申請中」を選択
        // 明示的に「すべて」を選んだ場合は status=all が渡ってくる
        if (! $httpRequest->has('status')) {
            $statusFilter = RequestStatusEnum::PENDING->value;
        } elseif ($status === 'all' || $status === '' || $status === null) {
            $statusFilter = null;
        } else {
            $statusFilter = (int) $status;
        }

        $nameFilter = $nameQuery !== null && $nameQuery !== '' ? (string) $nameQuery : null;
        $targetMonthFilter = $targetMonth !== null && $targetMonth !== '' ? (string) $targetMonth : null;
        $approverFilter = $approverId !== null && $approverId !== '' ? (int) $approverId : null;
        $typeFilterValue = $typeFilter !== null && $typeFilter !== '' ? (string) $typeFilter : null;

        // スコープに基づいてアクセス可能なユーザーIDを取得
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        // 通常申請を取得
        $normalRequests = $this->requestService->getRequestsByCompanyId($companyId);

        // 打刻修正申請を取得
        $correctionRequests = $this->correctionRequestService->getCorrectionRequestsByCompanyId($companyId);

        // 両方のデータを統合
        $allRequests = $this->mergeRequests($normalRequests, $correctionRequests);

        // スコープに基づいてフィルタリング
        $allRequests = $allRequests->filter(
            fn ($request) => in_array($request['requested_by']['id'], $accessibleUserIds, true)
        );

        // フィルター適用
        $filteredRequests = $allRequests;

        if ($statusFilter !== null) {
            $filteredRequests = $filteredRequests->filter(
                fn ($request) => $request['status'] === $statusFilter
            );
        }

        if ($nameFilter !== null) {
            $filteredRequests = $filteredRequests->filter(
                fn ($request) => mb_stripos($request['requested_by']['name'], $nameFilter) !== false
            );
        }

        if ($targetMonthFilter !== null) {
            $filteredRequests = $filteredRequests->filter(
                fn ($request) => str_starts_with($request['target_date'], $targetMonthFilter)
            );
        }

        if ($approverFilter !== null) {
            $filteredRequests = $filteredRequests->filter(
                fn ($request) => $request['approver'] !== null && $request['approver']['id'] === $approverFilter
            );
        }

        if ($typeFilterValue !== null) {
            $filteredRequests = $filteredRequests->filter(
                fn ($request) => (string) $request['type'] === $typeFilterValue || $request['type_label'] === $typeFilterValue
            );
        }

        // ページネーション
        $total = $filteredRequests->count();
        $lastPage = (int) ceil($total / self::PER_PAGE);
        $page = max(1, min($page, $lastPage ?: 1));
        $offset = ($page - 1) * self::PER_PAGE;

        $paginatedRequests = $filteredRequests
            ->sortByDesc('created_at')
            ->slice($offset, self::PER_PAGE)
            ->values();

        $pagination = [
            'current_page' => $page,
            'last_page' => $lastPage,
            'per_page' => self::PER_PAGE,
            'total' => $total,
            'from' => $total > 0 ? $offset + 1 : 0,
            'to' => min($offset + self::PER_PAGE, $total),
        ];

        // 統計用カウント（フィルター結果と連動）
        $stats = [
            'total' => $filteredRequests->count(),
            'pending' => $filteredRequests->where('status', RequestStatusEnum::PENDING->value)->count(),
            'approved' => $filteredRequests->where('status', RequestStatusEnum::APPROVED->value)->count(),
            'rejected' => $filteredRequests->where('status', RequestStatusEnum::REJECTED->value)->count(),
        ];

        // ステータスのオプション
        $statusOptions = collect(RequestStatusEnum::cases())->map(fn ($status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        // 承認者一覧を取得（プルダウン用）
        $approvers = $allRequests
            ->filter(fn ($request) => $request['approver'] !== null)
            ->pluck('approver')
            ->unique(fn ($approver) => $approver['id'])
            ->sortBy('name')
            ->values();

        // 申請タイプ一覧を取得
        $applicationTypes = $this->requestService->getAllActiveApplicationTypes()
            ->map(fn ($type) => [
                'value' => (string) $type->id,
                'label' => $type->name,
            ]);

        return Inertia::render('Admin/Applications', [
            'requests' => $paginatedRequests,
            'pagination' => $pagination,
            'stats' => $stats,
            'statusOptions' => $statusOptions,
            'approvers' => $approvers,
            'applicationTypes' => $applicationTypes,
            'currentStatus' => $statusFilter,
            'currentName' => $nameFilter,
            'currentTargetMonth' => $targetMonthFilter,
            'currentApprover' => $approverFilter,
            'currentType' => $typeFilterValue,
        ]);
    }

    /**
     * 通常申請と打刻修正申請を統合
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\Request>  $normalRequests
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\TimeRecordCorrectionRequest>  $correctionRequests
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function mergeRequests($normalRequests, $correctionRequests): \Illuminate\Support\Collection
    {
        // 通常申請を変換
        $transformedNormal = $normalRequests->map(fn ($request) => [
            'id' => $request->id,
            'request_type' => 'normal',
            'type' => $request->type,
            'type_label' => $request->applicationType?->name ?? 'その他',
            'target_date' => $request->target_date->format('Y-m-d'),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_css_class' => $request->status->cssClass(),
            'requested_by' => [
                'id' => $request->requestedBy->id,
                'name' => $request->requestedBy->name,
            ],
            'approver' => $request->approver ? [
                'id' => $request->approver->id,
                'name' => $request->approver->name,
            ] : null,
            'decided_at' => $request->decided_at?->format('Y-m-d H:i'),
            'decision_note' => $request->decision_note,
            'created_at' => $request->created_at->format('Y-m-d H:i'),
        ]);

        // 打刻修正申請を変換
        $transformedCorrection = $correctionRequests->map(fn ($request) => [
            'id' => $request->id,
            'request_type' => 'correction',
            'type' => 'clock-error',
            'type_label' => '打刻間違い',
            'target_date' => $request->target_date->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_css_class' => $request->status->cssClass(),
            'requested_by' => [
                'id' => $request->user->id,
                'name' => $request->user->name,
            ],
            'approver' => $request->approver ? [
                'id' => $request->approver->id,
                'name' => $request->approver->name,
            ] : null,
            'decided_at' => $request->approved_at?->format('Y-m-d H:i'),
            'decision_note' => $request->rejection_reason,
            'created_at' => $request->created_at->format('Y-m-d H:i'),
            'correction_details' => $request->details->map(fn ($detail) => [
                'record_type_label' => $detail->record_type->label(),
                'original_record_time' => $detail->original_record_time?->format('H:i'),
                'corrected_record_time' => $detail->corrected_record_time?->format('H:i'),
            ])->values()->all(),
        ]);

        return $transformedNormal->toBase()->merge($transformedCorrection);
    }

    /**
     * 申請詳細を表示
     */
    public function show(HttpRequest $httpRequest, int $id): Response
    {
        $companyId = auth()->user()->company_id;
        $requestType = $httpRequest->query('type', 'normal');

        if ($requestType === 'correction') {
            return $this->showCorrectionRequest($companyId, $id);
        }

        return $this->showNormalRequest($companyId, $id);
    }

    /**
     * 通常申請の詳細を表示
     */
    private function showNormalRequest(int $companyId, int $id): Response
    {
        $request = $this->requestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいてアクセス権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->requested_by, $accessibleUserIds, true)) {
            abort(403, 'この申請を閲覧する権限がありません。');
        }

        $requestData = [
            'id' => $request->id,
            'request_type' => 'normal',
            'type' => $request->type,
            'type_label' => $request->applicationType?->name ?? 'その他',
            'target_date' => $request->target_date->format('Y-m-d'),
            'start_time' => $request->start_time,
            'end_time' => $request->end_time,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_css_class' => $request->status->cssClass(),
            'can_approve' => $request->status->canApprove(),
            'can_reject' => $request->status->canReject(),
            'requested_by' => [
                'id' => $request->requestedBy->id,
                'name' => $request->requestedBy->name,
                'employee_code' => $request->requestedBy->employee_code,
            ],
            'approver' => $request->approver ? [
                'id' => $request->approver->id,
                'name' => $request->approver->name,
            ] : null,
            'decided_at' => $request->decided_at?->format('Y-m-d H:i'),
            'decision_note' => $request->decision_note,
            'created_at' => $request->created_at->format('Y-m-d H:i'),
            'correction_details' => null,
        ];

        return Inertia::render('Admin/RequestDetail', [
            'request' => $requestData,
        ]);
    }

    /**
     * 打刻修正申請の詳細を表示
     */
    private function showCorrectionRequest(int $companyId, int $id): Response
    {
        $request = $this->correctionRequestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいてアクセス権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->user_id, $accessibleUserIds, true)) {
            abort(403, 'この申請を閲覧する権限がありません。');
        }

        // 明細を取得
        $request->load('details');

        $requestData = [
            'id' => $request->id,
            'request_type' => 'correction',
            'type' => 'clock-error',
            'type_label' => '打刻間違い',
            'target_date' => $request->target_date->format('Y-m-d'),
            'start_time' => null,
            'end_time' => null,
            'reason' => $request->reason,
            'status' => $request->status->value,
            'status_label' => $request->status->label(),
            'status_css_class' => $request->status->cssClass(),
            'can_approve' => $request->status->canApprove(),
            'can_reject' => $request->status->canReject(),
            'requested_by' => [
                'id' => $request->user->id,
                'name' => $request->user->name,
                'employee_code' => $request->user->employee_code,
            ],
            'approver' => $request->approver ? [
                'id' => $request->approver->id,
                'name' => $request->approver->name,
            ] : null,
            'decided_at' => $request->approved_at?->format('Y-m-d H:i'),
            'decision_note' => $request->rejection_reason,
            'created_at' => $request->created_at->format('Y-m-d H:i'),
            'correction_details' => $request->details->map(fn ($detail) => [
                'id' => $detail->id,
                'record_type' => $detail->record_type->value,
                'record_type_label' => $detail->record_type->label(),
                'original_record_time' => $detail->original_record_time?->format('H:i'),
                'corrected_record_time' => $detail->corrected_record_time?->format('H:i'),
            ]),
        ];

        return Inertia::render('Admin/RequestDetail', [
            'request' => $requestData,
        ]);
    }

    /**
     * 申請を承認
     */
    public function approve(HttpRequest $httpRequest, int $id): RedirectResponse
    {
        try {
            $companyId = auth()->user()->company_id;
            $approverId = auth()->user()->id;
            $requestType = $httpRequest->input('request_type', 'normal');

            if ($requestType === 'correction') {
                return $this->approveCorrectionRequest($companyId, $approverId, $id);
            }

            return $this->approveNormalRequest($companyId, $approverId, $id, $httpRequest->input('decision_note'));
        } catch (\Exception $e) {
            $this->logError(ErrorCodeEnum::REQUEST_APPROVE_FAILED, [], $e);

            return redirect()
                ->back()
                ->with('error', '申請の承認に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * 通常申請を承認
     */
    private function approveNormalRequest(int $companyId, int $approverId, int $id, ?string $decisionNote): RedirectResponse
    {
        $request = $this->requestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいて承認権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->requested_by, $accessibleUserIds, true)) {
            abort(403, 'この申請を承認する権限がありません。');
        }

        if (! $request->status->canApprove()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は承認できません。');
        }

        $this->requestService->approveRequest($id, $approverId, $decisionNote);

        return redirect()
            ->route('admin.applications')
            ->with('success', '申請を承認しました。');
    }

    /**
     * 打刻修正申請を承認
     */
    private function approveCorrectionRequest(int $companyId, int $approverId, int $id): RedirectResponse
    {
        $request = $this->correctionRequestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいて承認権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->user_id, $accessibleUserIds, true)) {
            abort(403, 'この申請を承認する権限がありません。');
        }

        if (! $request->status->canApprove()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は承認できません。');
        }

        $this->correctionRequestService->approveCorrectionRequest($id, $approverId);

        return redirect()
            ->route('admin.applications')
            ->with('success', '打刻修正申請を承認し、打刻データを更新しました。');
    }

    /**
     * 申請を却下
     */
    public function reject(HttpRequest $httpRequest, int $id): RedirectResponse
    {
        try {
            $companyId = auth()->user()->company_id;
            $approverId = auth()->user()->id;
            $requestType = $httpRequest->input('request_type', 'normal');
            $decisionNote = $httpRequest->input('decision_note');

            if ($requestType === 'correction') {
                return $this->rejectCorrectionRequest($companyId, $approverId, $id, $decisionNote ?? '');
            }

            return $this->rejectNormalRequest($companyId, $approverId, $id, $decisionNote);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', '申請の却下に失敗しました: '.$e->getMessage());
        }
    }

    /**
     * 通常申請を却下
     */
    private function rejectNormalRequest(int $companyId, int $approverId, int $id, ?string $decisionNote): RedirectResponse
    {
        $request = $this->requestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいて却下権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->requested_by, $accessibleUserIds, true)) {
            abort(403, 'この申請を却下する権限がありません。');
        }

        if (! $request->status->canReject()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は却下できません。');
        }

        $this->requestService->rejectRequest($id, $approverId, $decisionNote);

        return redirect()
            ->route('admin.applications')
            ->with('success', '申請を却下しました。');
    }

    /**
     * 打刻修正申請を却下
     */
    private function rejectCorrectionRequest(int $companyId, int $approverId, int $id, string $rejectionReason): RedirectResponse
    {
        $request = $this->correctionRequestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        // スコープに基づいて却下権限をチェック
        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->user_id, $accessibleUserIds, true)) {
            abort(403, 'この申請を却下する権限がありません。');
        }

        if (! $request->status->canReject()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は却下できません。');
        }

        $this->correctionRequestService->rejectCorrectionRequest($id, $approverId, $rejectionReason);

        return redirect()
            ->route('admin.applications')
            ->with('success', '打刻修正申請を却下しました。');
    }

    /**
     * 承認済み申請を差し戻し（申請中に戻す）
     */
    public function revert(HttpRequest $httpRequest, int $id): RedirectResponse
    {
        try {
            $companyId = auth()->user()->company_id;
            $requestType = $httpRequest->input('request_type', 'normal');

            if ($requestType === 'correction') {
                return $this->revertCorrectionRequest($companyId, $id);
            }

            return $this->revertNormalRequest($companyId, $id);
        } catch (\Exception $e) {
            $this->logError(ErrorCodeEnum::REQUEST_APPROVE_FAILED, [], $e);

            return redirect()
                ->back()
                ->with('error', '申請の差し戻しに失敗しました: '.$e->getMessage());
        }
    }

    /**
     * 通常申請を差し戻し
     */
    private function revertNormalRequest(int $companyId, int $id): RedirectResponse
    {
        $request = $this->requestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->requested_by, $accessibleUserIds, true)) {
            abort(403, 'この申請を差し戻す権限がありません。');
        }

        if (! $request->status->canRevert()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は差し戻しできません。');
        }

        $this->requestService->revertRequest($id);

        return redirect()
            ->back()
            ->with('success', '申請を差し戻しました。');
    }

    /**
     * 打刻修正申請を差し戻し
     */
    private function revertCorrectionRequest(int $companyId, int $id): RedirectResponse
    {
        $request = $this->correctionRequestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->user_id, $accessibleUserIds, true)) {
            abort(403, 'この申請を差し戻す権限がありません。');
        }

        if (! $request->status->canRevert()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は差し戻しできません。');
        }

        $this->correctionRequestService->revertCorrectionRequest($id);

        return redirect()
            ->back()
            ->with('success', '打刻修正申請を差し戻しました。');
    }

    /**
     * 承認済み申請を取り消し
     */
    public function cancel(HttpRequest $httpRequest, int $id): RedirectResponse
    {
        try {
            $companyId = auth()->user()->company_id;
            $requestType = $httpRequest->input('request_type', 'normal');

            if ($requestType === 'correction') {
                return $this->cancelCorrectionRequest($companyId, $id);
            }

            return $this->cancelNormalRequest($companyId, $id);
        } catch (\Exception $e) {
            $this->logError(ErrorCodeEnum::REQUEST_APPROVE_FAILED, [], $e);

            return redirect()
                ->back()
                ->with('error', '申請の取り消しに失敗しました: '.$e->getMessage());
        }
    }

    /**
     * 通常申請を取り消し
     */
    private function cancelNormalRequest(int $companyId, int $id): RedirectResponse
    {
        $request = $this->requestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->requested_by, $accessibleUserIds, true)) {
            abort(403, 'この申請を取り消す権限がありません。');
        }

        if (! $request->status->canCancel()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は取り消しできません。');
        }

        $this->requestService->cancelApprovedRequest($id);

        return redirect()
            ->back()
            ->with('success', '申請を取り消しました。');
    }

    /**
     * 打刻修正申請を取り消し
     */
    private function cancelCorrectionRequest(int $companyId, int $id): RedirectResponse
    {
        $request = $this->correctionRequestService->findById($id);

        if (! $request || $request->company_id !== $companyId) {
            abort(404, '申請が見つかりません。');
        }

        $authUser = auth()->user();
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        if (! in_array($request->user_id, $accessibleUserIds, true)) {
            abort(403, 'この申請を取り消す権限がありません。');
        }

        if (! $request->status->canCancel()) {
            return redirect()
                ->back()
                ->with('error', 'この申請は取り消しできません。');
        }

        $this->correctionRequestService->cancelCorrectionRequest($id);

        return redirect()
            ->back()
            ->with('success', '打刻修正申請を取り消しました。');
    }

    /**
     * 承認待ち申請一覧を表示
     */
    public function pending(): Response
    {
        $authUser = auth()->user();
        $companyId = $authUser->company_id;
        $approverId = $authUser->id;

        // スコープに基づいてアクセス可能なユーザーIDを取得
        $accessibleUserIds = $this->permissionService->getAccessibleUserIds(
            $authUser,
            PermissionService::RESOURCE_REQUEST_APPROVAL
        );

        // 通常申請の承認待ち
        $normalRequests = $this->requestService->getPendingRequestsByApproverId($companyId, $approverId);

        // 打刻修正申請の承認待ち
        $correctionRequests = $this->correctionRequestService->getPendingCorrectionRequestsByApproverId($companyId, $approverId);

        // 両方を統合
        $allRequests = $this->mergeRequests($normalRequests, $correctionRequests);

        // スコープに基づいてフィルタリング + 承認待ちのみ
        $pendingRequests = $allRequests
            ->filter(fn ($request) => in_array($request['requested_by']['id'], $accessibleUserIds, true))
            ->filter(fn ($request) => $request['status'] === RequestStatusEnum::PENDING->value)
            ->sortByDesc('created_at')
            ->values();

        return Inertia::render('Admin/RequestsPending', [
            'requests' => $pendingRequests,
        ]);
    }
}
