<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancaAdminReporteConciliacionScopeTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(string $usuario, string $entidad): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => $entidad,
            'usuario' => $usuario,
            'token' => "token_estatico_{$usuario}",
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    private function crearTransaccionDe(ApiToken $duenio, string $placa, string $referencia): TransaccionPago
    {
        $transaccion = TransaccionPago::create([
            'placa' => $placa,
            'canal' => 'banco',
            'api_token_id' => $duenio->id,
            'codigo_consulta' => 'CON-20260101-' . substr(md5($referencia), 0, 5),
            'referencia_externa' => $referencia,
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);

        $transaccion->detalles()->create([
            'placa' => $placa,
            'estado' => 'pagado',
            'anio_fiscal' => 2026,
            'monto_impuesto' => 10.00,
            'monto_total' => 10.00,
        ]);

        return $transaccion;
    }

    /**
     * CRÍTICO — antes de este fix, cualquier banco veía la conciliación
     * de TODAS las entidades en este endpoint (sin scope por
     * api_token_id), a pesar de estar protegido solo con token bancario,
     * el mismo que cualquier entidad puede usar.
     */
    public function test_cada_banco_solo_ve_su_propia_conciliacion(): void
    {
        $bancoA = $this->crearApiToken('banco_a_admin_reporte', 'Banco A');
        $bancoB = $this->crearApiToken('banco_b_admin_reporte', 'Banco B');

        $this->crearTransaccionDe($bancoA, 'AAA1111', 'TXN-BANCO-A-001');
        $this->crearTransaccionDe($bancoB, 'BBB2222', 'TXN-BANCO-B-001');

        $filtros = [
            'fecha_desde' => now()->subDay()->format('Y-m-d'),
            'fecha_hasta' => now()->addDay()->format('Y-m-d'),
        ];

        $comoBancoA = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a_admin_reporte'])
            ->postJson('/api/v1/admin/reporte-conciliacion', $filtros);

        $comoBancoA->assertOk()->assertJson(['success' => true]);
        $detalleA = $comoBancoA->json('data.detalle_pagos');
        $this->assertCount(1, $detalleA);
        $this->assertSame('TXN-BANCO-A-001', $detalleA[0]['referencia_pago']);

        $comoBancoB = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_b_admin_reporte'])
            ->postJson('/api/v1/admin/reporte-conciliacion', $filtros);

        $comoBancoB->assertOk()->assertJson(['success' => true]);
        $detalleB = $comoBancoB->json('data.detalle_pagos');
        $this->assertCount(1, $detalleB);
        $this->assertSame('TXN-BANCO-B-001', $detalleB[0]['referencia_pago']);

        // resumen_por_entidad también debe reflejar el scope (solo la
        // propia entidad), no todas.
        $this->assertCount(1, $comoBancoA->json('data.resumen_por_entidad'));
        $this->assertCount(1, $comoBancoB->json('data.resumen_por_entidad'));
    }

    public function test_variante_bancaria_no_expone_id_crudo_ni_comprobante(): void
    {
        $banco = $this->crearApiToken('banco_admin_reporte_id', 'Banco Id');
        $transaccion = $this->crearTransaccionDe($banco, 'CCC3333', 'TXN-ID-001');

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_admin_reporte_id'])
            ->postJson('/api/v1/admin/reporte-conciliacion', [
                'fecha_desde' => now()->subDay()->format('Y-m-d'),
                'fecha_hasta' => now()->addDay()->format('Y-m-d'),
            ]);

        $response->assertOk();
        $fila = $response->json('data.detalle_pagos.0');

        $this->assertArrayNotHasKey('comprobante', $fila);
        $this->assertSame($transaccion->codigo_transaccion, $fila['pago_id']);
        $this->assertNotSame($transaccion->id, $fila['pago_id']);
    }
}
