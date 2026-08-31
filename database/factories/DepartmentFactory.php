<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Department>
 */
class DepartmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departments = [
            '総務部',
            '人事部',
            '営業部',
            '開発部',
            '企画部',
            'マーケティング部',
            '経理部',
            '法務部',
            '情報システム部',
            'カスタマーサポート部',
            '製造部',
            '品質管理部',
            '物流部',
            '購買部',
            '広報部',
        ];

        return [
            'name' => fake()->unique()->randomElement($departments),
        ];
    }

    /**
     * 会社を指定
     */
    public function forCompany(int $companyId): static
    {
        return $this->state([
            'company_id' => $companyId,
        ]);
    }
}
