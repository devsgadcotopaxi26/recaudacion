<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identificador de verificación de bajo privilegio, separado de
 * `certificado_token` (acceso al certificado completo, incluye PII vía
 * datos_facturacion) y de `comprobante` (secuencial, calculado del id,
 * nunca debe usarse como credencial — ver auditoría de seguridad).
 *
 * Uso: caso "verificar autenticidad de un comprobante impreso/QR"
 * (GET /verificar/{referencia}) — respuesta mínima, sin PII. Aleatorio
 * (Str::random(32), mismo patrón que certificado_token), no secuencial,
 * no basado en timestamp.
 *
 * nullable=true a propósito: las filas existentes no tienen valor todavía.
 * Se backfillea con `php artisan pagos:backfill-token-verificacion` (ver
 * app/Console/Commands/BackfillTokenVerificacion.php). Pasar a NOT NULL
 * queda para una migración futura, una vez confirmado que el backfill
 * corrió en todos los ambientes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->string('token_verificacion', 32)->nullable()->unique()->after('certificado_token');
        });
    }

    public function down(): void
    {
        Schema::table('transacciones_pago', function (Blueprint $table) {
            $table->dropColumn('token_verificacion');
        });
    }
};
