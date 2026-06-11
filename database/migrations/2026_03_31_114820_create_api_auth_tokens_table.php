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
        Schema::create('api_auth_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained('api_tokens')->onDelete('cascade');
            $table->string('access_token', 80)->unique();
            $table->string('refresh_token', 80)->unique();
            $table->timestamp('token_expira_en')->nullable();
            $table->timestamp('refresh_token_expira_en')->nullable();
            $table->timestamp('ultimo_uso')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_auth_tokens');
    }
};
