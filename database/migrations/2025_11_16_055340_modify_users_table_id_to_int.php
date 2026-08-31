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
        // 既存のusersテーブルを削除して再作成
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        // INT UNSIGNEDでusersテーブルを作成
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id')->comment('ユーザーID（想定：数十万人まで）');
            $table->string('name', 100)->comment('ユーザー名');
            $table->string('name_kana', 100)->nullable()->comment('フリガナ');
            $table->string('employee_code', 6)->nullable()->comment('個人コード（6桁）');
            $table->string('email', 191)->comment('メールアドレス');
            $table->timestamp('email_verified_at')->nullable()->comment('メール認証日時');
            $table->string('password')->comment('パスワードハッシュ');
            $table->boolean('work_on_holidays')->default(false)->comment('祝日も勤務するか');
            $table->boolean('is_retired')->default(false)->comment('退職済みかどうか');
            $table->date('retirement_date')->nullable()->comment('退職日');
            $table->rememberToken()->comment('ログイン保持トークン');
            $table->timestamps();

            $table->unique('email');
            // 個人コードは会社ごとにユニーク（アプリ層で UserRepository::existsByEmployeeCodeInCompany() で担保）
            $table->index('employee_code');
            $table->index('name');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedInteger('user_id')->nullable()->index();
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
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');

        // 元のBIGINT版を復元
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('name_kana', 100)->nullable();
            $table->string('employee_code', 6)->nullable();
            $table->string('email', 191);
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('work_on_holidays')->default(false);
            $table->boolean('is_retired')->default(false);
            $table->date('retirement_date')->nullable();
            $table->rememberToken();
            $table->timestamps();

            $table->unique('email');
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
};
