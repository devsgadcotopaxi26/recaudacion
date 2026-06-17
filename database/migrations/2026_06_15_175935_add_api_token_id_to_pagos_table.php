<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('api_token_id')
                ->nullable()
                ->after('placa')
                ->comment('Entidad bancaria que registró el pago via API');

            $table->foreign('api_token_id')
                ->references('id')
                ->on('api_tokens')
                ->nullOnDelete();

            $table->index(['api_token_id', 'fecha_pago'], 'pagos_entidad_fecha_idx');
            $table->index(['placa', 'anio_fiscal', 'estado'], 'pagos_placa_anio_estado_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['api_token_id']);
            $table->dropIndex('pagos_entidad_fecha_idx');
            $table->dropIndex('pagos_placa_anio_estado_idx');
            $table->dropColumn('api_token_id');
        });
    }
};
