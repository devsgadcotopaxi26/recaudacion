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
        Schema::create('consulta_bancarias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('placa', 20)->index();
            $table->integer('anio_fiscal')->index();
            $table->enum('metodo_sri', ['deuda', 'historial'])->default('deuda');
            $table->decimal('valor_matricula', 12, 2)->default(0);
            $table->decimal('total_rodaje', 12, 2)->default(0);
            $table->decimal('total_mora', 12, 2)->default(0);
            $table->decimal('total_a_pagar', 12, 2)->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('consulta_bancarias');
    }
};
