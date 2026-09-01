<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shift_patterns', function (Blueprint $table) {
            $table->boolean('auto_fill_break')
                ->default(false)
                ->after('break_end')
                ->comment('休憩打刻がない日にシフトの休憩時刻を自動入力する場合TRUE');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shift_patterns', function (Blueprint $table) {
            $table->dropColumn('auto_fill_break');
        });
    }
};
