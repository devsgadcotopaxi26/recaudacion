<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('refresh_token', 80)->nullable()->unique()->after('token_expira_en');
            $table->timestamp('refresh_token_expira_en')->nullable()->after('refresh_token');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn(['refresh_token', 'refresh_token_expira_en']);
        });
    }
};
