<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migra las filas reales de `pagos_legacy` (antes `pagos`) a
 * transacciones_pago + pago_detalles. Cada fila vieja ya era "un año, una
 * referencia", así que cada una se convierte en 1 transacción con 1 solo
 * detalle — sin pérdida de información.
 *
 * EXCLUIDAS explícitamente (quedan en pagos_legacy, no se migran) — 7
 * filas de datos de prueba/sintéticos, confirmadas con el usuario:
 *  - id 2,3,4,5,6,7: coinciden línea por línea con test_seed.php (ya
 *    eliminado del repo — ver commit de esta reestructuración).
 *  - id 1: sin api_token_id ni consulta_bancaria_id (ninguna fila real
 *    creada por registrarPago() queda así); origen desconocido, no tiene
 *    el patrón de un pago real vía API.
 */
return new class extends Migration {
    private const IDS_EXCLUIDOS = [1, 2, 3, 4, 5, 6, 7];

    public function up(): void
    {
        $filas = DB::table('pagos_legacy')
            ->whereNotIn('id', self::IDS_EXCLUIDOS)
            ->orderBy('id')
            ->get();

        foreach ($filas as $fila) {
            $datosAdicionales = $fila->datos_adicionales ? json_decode($fila->datos_adicionales, true) : [];

            $transaccionId = DB::table('transacciones_pago')->insertGetId([
                'placa' => $fila->placa,
                'referencia_externa' => $fila->referencia_pago,
                'codigo_consulta' => $datosAdicionales['codigo_consulta'] ?? null,
                'consulta_bancaria_id' => $fila->consulta_bancaria_id,
                'api_token_id' => $fila->api_token_id,
                'entidad_recaudadora' => $datosAdicionales['entidad_recaudadora'] ?? null,
                'monto_total' => $fila->monto_total,
                'estado' => $fila->estado,
                'fecha_pago' => $fila->fecha_pago,
                'certificado_token' => $fila->certificado_token,
                'link_pago' => $fila->link_pago,
                'datos_facturacion' => $fila->datos_facturacion,
                'datos_adicionales' => json_encode([
                    'metodo_pago' => $datosAdicionales['metodo_pago'] ?? null,
                    'vehiculo' => $datosAdicionales['vehiculo'] ?? null,
                ]),
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at,
            ]);

            DB::table('pago_detalles')->insert([
                'transaccion_pago_id' => $transaccionId,
                'placa' => $fila->placa,
                'estado' => $fila->estado,
                'anio_fiscal' => $fila->anio_fiscal,
                'monto_impuesto' => $fila->monto_impuesto,
                'monto_mora' => 0, // no existía como campo separado en pagos_legacy
                'monto_total' => $fila->monto_total,
                'created_at' => $fila->created_at,
                'updated_at' => $fila->updated_at,
            ]);
        }
    }

    public function down(): void
    {
        // Tablas creadas y pobladas exclusivamente por esta reestructuración
        // en este punto — vaciarlas es seguro y correcto para deshacer esta
        // migración de datos (pagos_legacy no se toca, permanece intacta).
        DB::table('pago_detalles')->truncate();
        DB::table('transacciones_pago')->truncate();
    }
};
