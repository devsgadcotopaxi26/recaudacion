<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elimina consulta_bancarias.monto_a_pagar — auditoría confirmó que es
 * exactamente el mismo valor que total_a_pagar, asignado desde la misma
 * variable PHP ($totalAPagar) en el mismo ConsultaBancaria::create()
 * (BancaController.php), nunca dos cálculos distintos. Grep exhaustivo
 * confirmó además que NINGUNA de las dos columnas se lee de vuelta en
 * ningún lugar — ni siquiera la validación de tolerancia de ±$1.00 en
 * registrarPago() las usa (recalcula el monto esperado en vivo desde el
 * SRI en cada llamada, nunca desde esta tabla). Se conserva
 * total_a_pagar (nombre más consistente con el resto del esquema:
 * total_rodaje, total_mora, total_a_pagar) y se elimina el duplicado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->dropColumn('monto_a_pagar');
        });
    }

    public function down(): void
    {
        Schema::table('consulta_bancarias', function (Blueprint $table) {
            $table->decimal('monto_a_pagar', 12, 2)->default(0)->after('total_a_pagar');
        });
    }
};
