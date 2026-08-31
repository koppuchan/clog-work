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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_kana', 100)->nullable();
            $table->string('employee_code', 6)->nullable();
            $table->string('email', 191);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');

            // 祝日勤務フラグ
            $table->boolean('work_on_holidays')->default(false);

            // 入社日（追加）

            // 退職関連
            $table->boolean('is_retired')->default(false);
            $table->date('retirement_date')->nullable();

            $table->rememberToken();
            $table->timestamps();

            // インデックス
            $table->unique('email');
            // 個人コードは会社ごとにユニーク（アプリ層で担保）
            $table->index('employee_code');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
