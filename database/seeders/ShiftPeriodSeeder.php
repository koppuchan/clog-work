<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ShiftPeriodSeeder extends Seeder
{
    /**
     * シフト表示期間マスターデータを追加
     */
    public function run(): void
    {
        DB::table('shift_periods')->insertOrIgnore([
            'id' => 2,
            'name' => '締め日翌日スタート',
            'description' => '給与締め日の翌日から翌月締め日まで',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
