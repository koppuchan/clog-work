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
        Schema::table('time_records', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)
                ->nullable()
                ->after('note')
                ->comment('打刻したときの緯度（スマホ打刻で位置情報を記録する設定の会社のみ）');
            $table->decimal('longitude', 10, 7)
                ->nullable()
                ->after('latitude')
                ->comment('打刻したときの経度');
            $table->unsignedInteger('location_accuracy')
                ->nullable()
                ->after('longitude')
                ->comment('位置情報の精度（メートル）');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('time_records', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'location_accuracy']);
        });
    }
};
