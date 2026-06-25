<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Agregar código único a consultas
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->string('codigo_consulta', 30)
                ->unique()
                ->nullable()
                ->after('id')
                ->comment('Código único para trazar consulta con pago: CON-YYYYMMDD-XXXXX');

            $table->decimal('monto_a_pagar', 12, 2)
                ->default(0)
                ->after('total_a_pagar')
                ->comment('Monto que se debe pagar (rodaje)');

            $table->enum('estado', ['pendiente', 'pagado', 'expirado'])
                ->default('pendiente')
                ->after('monto_a_pagar')
                ->comment('Estado de la consulta');

            $table->timestamp('expira_en')
                ->nullable()
                ->after('estado')
                ->comment('La consulta expira en 24 horas');
        });

        // Vincular pago con consulta
        Schema::table('pagos', function (Blueprint $table) {
            $table->unsignedBigInteger('consulta_bancaria_id')
                ->nullable()
                ->after('api_token_id')
                ->comment('Consulta que originó este pago');

            $table->foreign('consulta_bancaria_id')
                ->references('id')
                ->on('consulta_bancarias')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropForeign(['consulta_bancaria_id']);
            $table->dropColumn('consulta_bancaria_id');
        });

        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->dropColumn(['codigo_consulta', 'monto_a_pagar', 'estado', 'expira_en']);
        });
    }
};
