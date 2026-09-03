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
        Schema::create('felica_stamp_attempts', function (Blueprint $table) {
            $table->bigIncrements('id')->comment('FeliCa打刻試行は高頻度のためBIGINT');
            $table->unsignedInteger('company_id')->comment('会社ID');
            $table->unsignedInteger('user_id')->nullable()->comment('打刻したユーザーID（未登録カードの場合はNULL）');
            $table->string('felica_idm', 16)->comment('FeliCa IDm（16進数16桁）');
            $table->string('status', 20)->comment('success / cooldown / unregistered / retired / error');
            $table->string('message', 255)->comment('打刻専用画面に表示する見出しメッセージ');
            $table->string('detail', 255)->nullable()->comment('打刻専用画面に表示する補足メッセージ');
            $table->unsignedBigInteger('time_record_id')->nullable()->comment('成功時に記録された打刻レコードID');
            $table->timestamp('created_at')->nullable();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('time_record_id')->references('id')->on('time_records')->nullOnDelete();
            $table->index(['company_id', 'id'], 'idx_company_id_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('felica_stamp_attempts');
    }
};
