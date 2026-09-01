<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\Shift;
use App\Models\ShiftColor;
use App\Models\ShiftPattern;
use App\Models\UserShiftPattern;
use App\Repositories\Contracts\ShiftColorRepositoryInterface;
use App\Repositories\Contracts\ShiftPatternRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * シフトパターンサービス
 *
 * シフトパターンの取得・作成・更新・削除を行う
 */
class ShiftPatternService
{
    /**
     * 休憩モード定数
     */
    public const BREAK_MODE_DURATION = 1;  // 時間指定

    public const BREAK_MODE_RANGE = 2;     // 時間帯指定

    private const DEFAULT_BACKGROUND_COLOR = 'bg-teal-100';

    private const DEFAULT_TEXT_COLOR = 'text-teal-800';

    public function __construct(
        private readonly ShiftPatternRepositoryInterface $shiftPatternRepository,
        private readonly ShiftColorRepositoryInterface $shiftColorRepository,
    ) {}

    /**
     * 会社IDに基づいてシフトパターンを取得（シフト表示用）
     *
     * @param  int  $companyId  会社ID
     * @return Collection フロントエンド用に整形されたシフトパターンのコレクション
     */
    public function getShiftPatternsByCompanyId(int $companyId): Collection
    {
        $patterns = $this->shiftPatternRepository->findByCompanyId($companyId);

        return $patterns->map(function ($pattern) {
            $color = $pattern->colors->first();

            return [
                'id' => $pattern->id,
                'name' => $pattern->name,
                'start_time' => $pattern->start_time ? substr($pattern->start_time, 0, 5) : null, // HH:MM形式
                'end_time' => $pattern->end_time ? substr($pattern->end_time, 0, 5) : null,
                'work_minutes' => $pattern->work_minutes,
                'break_minutes' => $pattern->break_minutes,
                'background_color' => $color?->background_color ?? self::DEFAULT_BACKGROUND_COLOR,
                'text_color' => $color?->text_color ?? self::DEFAULT_TEXT_COLOR,
            ];
        });
    }

    /**
     * 会社のシフトパターン一覧を取得（設定画面用）
     *
     * @param  int  $companyId  会社ID
     * @return Collection<int, array{
     *   id: int,
     *   name: string,
     *   startTime: string|null,
     *   endTime: string|null,
     *   workMinutes: int|null,
     *   breakMode: int|null,
     *   breakMinutes: int|null,
     *   breakStart: string|null,
     *   breakEnd: string|null,
     *   isInUse: bool
     * }>
     */
    public function getShiftPatternsForSettings(int $companyId): Collection
    {
        $patterns = $this->shiftPatternRepository->findByCompanyId($companyId);

        // 使用中チェック用データを一括取得（N+1回避）
        $patternIds = $patterns->pluck('id');
        $usedInShifts = Shift::whereIn('shift_pattern_id', $patternIds)->pluck('shift_pattern_id')->unique();
        $usedInUserPatterns = UserShiftPattern::whereIn('shift_pattern_id', $patternIds)->pluck('shift_pattern_id')->unique();
        $usedAsDefault = Company::whereIn('default_shift_pattern_id', $patternIds)->pluck('default_shift_pattern_id')->unique();
        $usedIds = $usedInShifts->merge($usedInUserPatterns)->merge($usedAsDefault)->unique();

        return $patterns->map(function (ShiftPattern $pattern) use ($usedIds) {
            $formatted = $this->formatShiftPatternForSettings($pattern);
            $formatted['isInUse'] = $usedIds->contains($pattern->id);

            return $formatted;
        });
    }

    /**
     * シフトパターンを取得（設定画面用）
     *
     * @param  int  $id  シフトパターンID
     * @return array{
     *   id: int,
     *   name: string,
     *   startTime: string|null,
     *   endTime: string|null,
     *   workMinutes: int|null,
     *   breakMode: int|null,
     *   breakMinutes: int|null,
     *   breakStart: string|null,
     *   breakEnd: string|null
     * }|null
     */
    public function getShiftPatternForSettings(int $id): ?array
    {
        $pattern = $this->shiftPatternRepository->findByIdWithRelations($id);

        if (! $pattern) {
            return null;
        }

        return $this->formatShiftPatternForSettings($pattern);
    }

    /**
     * シフトパターンを作成
     *
     * @param  int  $companyId  会社ID
     * @param  array{
     *   name: string,
     *   startTime?: string|null,
     *   endTime?: string|null,
     *   workMinutes?: int|null,
     *   breakMode?: int|null,
     *   breakMinutes?: int|null,
     *   breakStart?: string|null,
     *   breakEnd?: string|null
     * }  $data  シフトパターンデータ
     * @return array{
     *   id: int,
     *   name: string,
     *   startTime: string|null,
     *   endTime: string|null,
     *   workMinutes: int|null,
     *   breakMode: int|null,
     *   breakMinutes: int|null,
     *   breakStart: string|null,
     *   breakEnd: string|null
     * }
     */
    public function createShiftPattern(int $companyId, array $data): array
    {
        $breakMinutes = $data['breakMinutes'] ?? null;
        $workMinutes = $data['workMinutes'] ?? null;
        $this->autoCalculateMinutes($data, $breakMinutes, $workMinutes);

        $pattern = $this->shiftPatternRepository->create([
            'company_id' => $companyId,
            'name' => $data['name'],
            'start_time' => $data['startTime'] ?? null,
            'end_time' => $data['endTime'] ?? null,
            'work_minutes' => $workMinutes,
            'break_mode' => $data['breakMode'] ?? null,
            'break_minutes' => $breakMinutes,
            'break_start' => $data['breakStart'] ?? null,
            'break_end' => $data['breakEnd'] ?? null,
        ]);

        if (! empty($data['colorId'])) {
            $pattern->colors()->sync([$data['colorId']]);
        }

        $pattern = $this->shiftPatternRepository->findByIdWithRelations($pattern->id);

        return $this->formatShiftPatternForSettings($pattern);
    }

    /**
     * シフトパターンを更新
     *
     * @param  int  $id  シフトパターンID
     * @param  array{
     *   name?: string,
     *   startTime?: string|null,
     *   endTime?: string|null,
     *   workMinutes?: int|null,
     *   breakMode?: int|null,
     *   breakMinutes?: int|null,
     *   breakStart?: string|null,
     *   breakEnd?: string|null
     * }  $data  更新データ
     * @return array{
     *   id: int,
     *   name: string,
     *   startTime: string|null,
     *   endTime: string|null,
     *   workMinutes: int|null,
     *   breakMode: int|null,
     *   breakMinutes: int|null,
     *   breakStart: string|null,
     *   breakEnd: string|null
     * }
     */
    public function updateShiftPattern(int $id, array $data): array
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (array_key_exists('startTime', $data)) {
            $updateData['start_time'] = $data['startTime'];
        }
        if (array_key_exists('endTime', $data)) {
            $updateData['end_time'] = $data['endTime'];
        }
        if (array_key_exists('workMinutes', $data)) {
            $updateData['work_minutes'] = $data['workMinutes'];
        }
        if (array_key_exists('breakMode', $data)) {
            $updateData['break_mode'] = $data['breakMode'];
        }
        if (array_key_exists('breakMinutes', $data)) {
            $updateData['break_minutes'] = $data['breakMinutes'];
        }
        if (array_key_exists('breakStart', $data)) {
            $updateData['break_start'] = $data['breakStart'];
        }
        if (array_key_exists('breakEnd', $data)) {
            $updateData['break_end'] = $data['breakEnd'];
        }

        // break_minutes/work_minutes が未設定の場合、自動計算
        $breakMinutes = $updateData['break_minutes'] ?? null;
        $workMinutes = $updateData['work_minutes'] ?? null;
        $calcData = [
            'startTime' => $updateData['start_time'] ?? null,
            'endTime' => $updateData['end_time'] ?? null,
            'breakStart' => $updateData['break_start'] ?? null,
            'breakEnd' => $updateData['break_end'] ?? null,
        ];

        // 更新データに含まれていないフィールドは既存のパターンから取得
        $existingPattern = $this->shiftPatternRepository->findById($id);
        if ($existingPattern) {
            $calcData['startTime'] = $calcData['startTime'] ?? (string) $existingPattern->start_time;
            $calcData['endTime'] = $calcData['endTime'] ?? (string) $existingPattern->end_time;
            $calcData['breakStart'] = $calcData['breakStart'] ?? ($existingPattern->break_start ? (string) $existingPattern->break_start : null);
            $calcData['breakEnd'] = $calcData['breakEnd'] ?? ($existingPattern->break_end ? (string) $existingPattern->break_end : null);
            $breakMinutes = $breakMinutes ?? $existingPattern->break_minutes;
            $workMinutes = $workMinutes ?? $existingPattern->work_minutes;
        }

        $this->autoCalculateMinutes($calcData, $breakMinutes, $workMinutes);
        $updateData['break_minutes'] = $breakMinutes;
        $updateData['work_minutes'] = $workMinutes;

        $pattern = $this->shiftPatternRepository->update($id, $updateData);

        if (array_key_exists('colorId', $data)) {
            if (! empty($data['colorId'])) {
                $pattern->colors()->sync([$data['colorId']]);
            } else {
                $pattern->colors()->detach();
            }
        }

        $pattern = $this->shiftPatternRepository->findByIdWithRelations($pattern->id);

        return $this->formatShiftPatternForSettings($pattern);
    }

    /**
     * シフトパターンを削除
     *
     * @param  int  $id  シフトパターンID
     * @return bool 削除成功したかどうか
     *
     * @throws \RuntimeException シフトパターンが使用中の場合
     */
    public function deleteShiftPattern(int $id): bool
    {
        if (Shift::where('shift_pattern_id', $id)->exists()) {
            throw new \RuntimeException('このシフトパターンはシフトに割り当てられているため削除できません。');
        }

        if (UserShiftPattern::where('shift_pattern_id', $id)->exists()) {
            throw new \RuntimeException('このシフトパターンはスタッフの勤務設定で使用されているため削除できません。');
        }

        if (Company::where('default_shift_pattern_id', $id)->exists()) {
            throw new \RuntimeException('このシフトパターンは会社のデフォルトシフトに設定されているため削除できません。');
        }

        return $this->shiftPatternRepository->delete($id);
    }

    /**
     * シフトパターンが会社に属しているか確認
     *
     * @param  int  $patternId  シフトパターンID
     * @param  int  $companyId  会社ID
     */
    public function belongsToCompany(int $patternId, int $companyId): bool
    {
        $pattern = $this->shiftPatternRepository->findById($patternId);

        if (! $pattern) {
            return false;
        }

        return $pattern->company_id === $companyId;
    }

    /**
     * シフトパターンを一括同期（追加・更新・削除）
     *
     * @param  int  $companyId  会社ID
     * @param  array<int, array{id?: int, name: string, startTime?: string|null, endTime?: string|null, workMinutes?: int|null, breakMode?: int|null, breakMinutes?: int|null, breakStart?: string|null, breakEnd?: string|null}>  $shiftPatterns  シフトパターンデータ配列
     * @param  array<int>  $deletedIds  削除対象のID配列
     */
    public function syncShiftPatterns(int $companyId, array $shiftPatterns, array $deletedIds = []): void
    {
        $patternsCollection = collect($shiftPatterns);
        $deletedIdsCollection = collect($deletedIds);

        // 会社所属のパターンIDを一括取得（N+1回避）
        $companyPatternIds = $this->shiftPatternRepository->findByCompanyId($companyId)->pluck('id');

        // 削除対象のシフトパターンを削除（会社所属チェック後）
        $deletedIdsCollection
            ->filter(fn (int $id) => $companyPatternIds->contains($id))
            ->each(fn (int $id) => $this->deleteShiftPattern($id));

        // 既存のシフトパターンを更新
        $patternsCollection
            ->filter(fn (array $pattern) => isset($pattern['id']) && $pattern['id'] > 0)
            ->filter(fn (array $pattern) => $companyPatternIds->contains($pattern['id']))
            ->each(fn (array $pattern) => $this->updateShiftPattern($pattern['id'], $pattern));

        // 新規シフトパターンを作成
        $patternsCollection
            ->filter(fn (array $pattern) => ! isset($pattern['id']) || $pattern['id'] <= 0)
            ->each(fn (array $pattern) => $this->createShiftPattern($companyId, $pattern));
    }

    /**
     * 設定画面用のシフトカラー一覧を取得
     *
     * @return Collection<int, array{id: int, backgroundColor: string, textColor: string}>
     */
    public function getShiftColorsForSettings(): Collection
    {
        return $this->shiftColorRepository->findAll()->map(fn (ShiftColor $color) => [
            'id' => $color->id,
            'backgroundColor' => $color->background_color,
            'textColor' => $color->text_color,
        ]);
    }

    /**
     * シフトパターンを設定画面用にフォーマット
     *
     * @return array{
     *   id: int,
     *   name: string,
     *   startTime: string|null,
     *   endTime: string|null,
     *   workMinutes: int|null,
     *   breakMode: int|null,
     *   breakMinutes: int|null,
     *   breakStart: string|null,
     *   breakEnd: string|null,
     *   colorId: int|null,
     *   backgroundColor: string|null,
     *   textColor: string|null
     * }
     */
    private function formatShiftPatternForSettings(ShiftPattern $pattern): array
    {
        $color = $pattern->colors->first();

        return [
            'id' => $pattern->id,
            'name' => $pattern->name,
            'startTime' => $this->formatTimeToHHMM($pattern->start_time),
            'endTime' => $this->formatTimeToHHMM($pattern->end_time),
            'workMinutes' => $pattern->work_minutes,
            'breakMode' => $pattern->break_mode,
            'breakMinutes' => $pattern->break_minutes,
            'breakStart' => $this->formatTimeToHHMM($pattern->break_start),
            'breakEnd' => $this->formatTimeToHHMM($pattern->break_end),
            'colorId' => $color?->id,
            'backgroundColor' => $color?->background_color ?? self::DEFAULT_BACKGROUND_COLOR,
            'textColor' => $color?->text_color ?? self::DEFAULT_TEXT_COLOR,
        ];
    }

    /**
     * 時刻をHH:MM形式にフォーマット
     */
    private function formatTimeToHHMM(?string $time): ?string
    {
        if ($time === null) {
            return null;
        }

        return substr($time, 0, 5);
    }

    /**
     * break_minutes / work_minutes を自動計算
     *
     * break_minutes が未設定で break_start/break_end がある場合、差分から算出。
     * work_minutes が未設定で start_time/end_time がある場合、(終業-始業)-休憩 で算出。
     *
     * @param  array<string, mixed>  $data  startTime, endTime, breakStart, breakEnd を含む配列
     * @param  int|null  &$breakMinutes  休憩分数（参照渡しで更新）
     * @param  int|null  &$workMinutes  所定労働分数（参照渡しで更新）
     */
    private function autoCalculateMinutes(array $data, ?int &$breakMinutes, ?int &$workMinutes): void
    {
        // break_minutes を break_start/break_end から自動算出
        if (empty($breakMinutes) && ! empty($data['breakStart']) && ! empty($data['breakEnd'])) {
            $breakStartMin = $this->timeStringToMinutes($data['breakStart']);
            $breakEndMin = $this->timeStringToMinutes($data['breakEnd']);
            if ($breakEndMin <= $breakStartMin) {
                $breakEndMin += 24 * 60;
            }
            $breakMinutes = $breakEndMin - $breakStartMin;
        }

        // work_minutes を start_time/end_time/break_minutes から自動算出
        if (empty($workMinutes) && ! empty($data['startTime']) && ! empty($data['endTime'])) {
            $startMin = $this->timeStringToMinutes($data['startTime']);
            $endMin = $this->timeStringToMinutes($data['endTime']);
            if ($endMin <= $startMin) {
                $endMin += 24 * 60;
            }
            $workMinutes = ($endMin - $startMin) - ($breakMinutes ?? 0);
        }
    }

    /**
     * 時刻文字列を0時からの分数に変換
     */
    private function timeStringToMinutes(string $time): int
    {
        $parts = explode(':', $time);

        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }
}
