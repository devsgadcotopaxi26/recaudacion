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
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->foreignId('api_token_id')->nullable()->after('user_id')->constrained('api_tokens')->onDelete('set null');
            $table->string('entidad_nombre')->nullable()->after('api_token_id');
        });
    }

    public function down(): void
    {
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->dropForeign(['api_token_id']);
            $table->dropColumn(['api_token_id', 'entidad_nombre']);
        });
    }
};
