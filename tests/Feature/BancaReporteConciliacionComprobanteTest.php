<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancaReporteConciliacionComprobanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_comprobante_no_aparece_en_el_detalle_de_reporte_conciliacion(): void
    {
        $token = ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_test',
            'token' => 'token_estatico_prueba',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);

        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'api_token_id' => $token->id,
            'codigo_consulta' => 'CON-20260101-00001',
            'referencia_externa' => 'TXN-CONCILIACION-0001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);

        $transaccion->detalles()->create([
            'placa' => 'ABC1234',
            'estado' => 'pagado',
            'anio_fiscal' => 2026,
            'monto_impuesto' => 10.00,
            'monto_total' => 10.00,
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/reporte-conciliacion', [
                'fecha_desde' => now()->subDay()->format('Y-m-d'),
                'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, count($response->json('data.detalle_pagos')));

        // 'comprobante' es información interna/contable — ya no se expone
        // al banco. 'pago_id' se mantiene sin renombrar (ver auditoría:
        // ya forma parte del contrato público documentado) — pero su VALOR
        // ya no es el id crudo autoincremental (mismo riesgo de
        // enumeración que comprobante/transaccion_id sin ningún disfraz):
        // ahora es codigo_transaccion (formato TRX-XXXXXX).
        $this->assertArrayNotHasKey('comprobante', $response->json('data.detalle_pagos.0'));
        $this->assertArrayHasKey('pago_id', $response->json('data.detalle_pagos.0'));
        $this->assertSame(
            $transaccion->codigo_transaccion,
            $response->json('data.detalle_pagos.0.pago_id')
        );
        $this->assertNotSame($transaccion->id, $response->json('data.detalle_pagos.0.pago_id'));
    }
}
