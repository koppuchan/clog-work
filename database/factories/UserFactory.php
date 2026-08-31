<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstNames = ['タロウ', 'ハナコ', 'ジロウ', 'サクラ', 'ケンジ', 'ユキ', 'ダイスケ', 'アヤ', 'マサシ', 'ミキ'];
        $lastNames = ['ヤマダ', 'タナカ', 'サトウ', 'スズキ', 'ワタナベ', 'イトウ', 'ナカムラ', 'コバヤシ', 'カトウ', 'ヨシダ'];

        $firstName = fake()->firstName();
        $lastName = fake()->lastName();
        $firstNameKana = fake()->randomElement($firstNames);
        $lastNameKana = fake()->randomElement($lastNames);

        return [
            'name' => $lastName.' '.$firstName,
            'name_kana' => fake()->optional()->passthrough($lastNameKana.' '.$firstNameKana),
            'employee_code' => fake()->optional()->numerify('######'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_retired' => false,
            'retirement_date' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * 退職済みユーザーの状態
     */
    public function retired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_retired' => true,
            'retirement_date' => fake()->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * オーナーユーザーの状態
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_owner' => true,
        ]);
    }

    /**
     * 管理者ユーザーの状態
     */
    public function admin(): static
    {
        return $this->afterCreating(function ($user) {
            $now = now();
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * 責任者ユーザーの状態
     */
    public function manager(): static
    {
        return $this->afterCreating(function ($user) {
            $now = now();
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * 一般ユーザーの状態
     */
    public function employee(): static
    {
        return $this->afterCreating(function ($user) {
            $now = now();
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * 会社を指定
     */
    public function forCompany(int $companyId): static
    {
        return $this->afterCreating(function ($user) use ($companyId) {
            $now = now();
            DB::table('user_companies')->insert([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'is_primary' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function ($user) {
            $now = now();

            // 勤務可能曜日を作成（全曜日を取得して設定）
            $weekdays = DB::table('weekdays')->get();
            foreach ($weekdays as $weekday) {
                // 月〜金は勤務可能、土日は一部可能
                $isAvailable = match ($weekday->id) {
                    1 => fake()->boolean(30), // 日曜
                    2, 3, 4, 5, 6 => true,     // 月〜金
                    7 => fake()->boolean(50),  // 土曜
                    default => true,
                };

                DB::table('user_available_work_days')->insert([
                    'user_id' => $user->id,
                    'weekday_id' => $weekday->id,
                    'is_available' => $isAvailable,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        });
    }
}
