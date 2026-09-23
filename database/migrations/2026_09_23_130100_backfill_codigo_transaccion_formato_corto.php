<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Regenera codigo_transaccion con el formato corto nuevo (TRX-XXXXXX) para
 * todas las filas existentes, que todavía tienen el valor largo
 * (Str::random(32)) generado antes de este cambio — nadie lo consume
 * todavía (confirmado en auditoría), así que no hay nada externo que se
 * rompa al reemplazarlo.
 *
 * No reutiliza TransaccionPago::creating() (eso solo corre en INSERT)
 * — genera el valor con la misma lógica en SQL directo, fila por fila,
 * verificando unicidad contra lo ya asignado en esta misma corrida.
 */
return new class extends Migration
{
    private const ALFABETO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function up(): void
    {
        $usados = DB::table('transacciones_pago')->pluck('codigo_transaccion', 'id');
        $yaAsignados = [];

        foreach ($usados->keys() as $id) {
            do {
                $codigo = $this->generar();
            } while (in_array($codigo, $yaAsignados, true));

            $yaAsignados[] = $codigo;

            DB::table('transacciones_pago')->where('id', $id)->update([
                'codigo_transaccion' => $codigo,
            ]);
        }
    }

    public function down(): void
    {
        // Irreversible a propósito: no hay forma de recuperar los valores
        // largos originales (Str::random(32)) una vez sobreescritos, y
        // como nadie los consumía todavía (confirmado en auditoría), no
        // hace falta — el down() de la migración de rename (columna) sigue
        // siendo reversible por separado.
    }

    private function generar(): string
    {
        $alfabeto = self::ALFABETO;
        $largo = strlen($alfabeto);
        $codigo = 'TRX-';

        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, $largo - 1)];
        }

        return $codigo;
    }
};
