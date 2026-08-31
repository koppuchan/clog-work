<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ShiftPattern;
use App\Repositories\Contracts\ShiftPatternRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

/**
 * シフトパターンリポジトリ実装
 */
class ShiftPatternRepository implements ShiftPatternRepositoryInterface
{
    public function __construct(
        private readonly ShiftPattern $shiftPattern
    ) {}

    /**
     * {@inheritDoc}
     */
    public function findByCompanyId(int $companyId): Collection
    {
        return $this->shiftPattern->query()
            ->where('company_id', $companyId)
            ->with('colors')
            ->orderBy('id')
            ->get();
    }

    /**
     * {@inheritDoc}
     */
    public function findById(int $id): ?ShiftPattern
    {
        return $this->shiftPattern->query()->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function findByIdWithRelations(int $id): ?ShiftPattern
    {
        return $this->shiftPattern->query()
            ->with('colors')
            ->find($id);
    }

    /**
     * {@inheritDoc}
     */
    public function create(array $data): ShiftPattern
    {
        return $this->shiftPattern->query()->create($data);
    }

    /**
     * {@inheritDoc}
     */
    public function update(int $id, array $data): ShiftPattern
    {
        $shiftPattern = $this->findById($id);

        if (! $shiftPattern) {
            throw new \RuntimeException("ShiftPattern with ID {$id} not found");
        }

        $shiftPattern->update($data);

        return $shiftPattern->fresh();
    }

    /**
     * {@inheritDoc}
     */
    public function delete(int $id): bool
    {
        $shiftPattern = $this->findById($id);

        if (! $shiftPattern) {
            return false;
        }

        return $shiftPattern->delete();
    }
}
