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
        Schema::table('users', function (Blueprint $table) {
            $table->string('felica_idm', 16)
                ->nullable()
                ->after('stamp_password')
                ->comment('FeliCa IDm（16進数16桁）');

            $table->timestamp('felica_registered_at')
                ->nullable()
                ->after('felica_idm')
                ->comment('FeliCaカード登録日時');

            $table->unique('felica_idm', 'uk_users_felica_idm');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('uk_users_felica_idm');
            $table->dropColumn(['felica_idm', 'felica_registered_at']);
        });
    }
};
