<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cubre dos cosas a la vez, a propósito:
 *
 * 1. El renombre de 'pago_id' -> 'codigo_transaccion' dentro del
 *    sub-objeto 'pago' de desglose_anual (SriVehiculoService::
 *    conciliarPagosLocales(), ver auditoría) — mismo valor, nueva clave.
 * 2. Que /consulta-deuda-rodaje-bancos NO ganó ningún campo nuevo
 *    (en particular, NO 'reversion' — ese campo es exclusivo de
 *    verificar-pago) ni cambió el resto de su forma como efecto
 *    colateral del trabajo sobre verificar-pago.
 */
class ConsultaDeudaPagoIdRenombradoTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_pagoid_test',
            'token' => 'token_estatico_pagoid_test',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    private function fakeSri(): void
    {
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 999777,
                'numeroPlaca' => 'ZZT7777',
                'descripcionMarca' => 'TEST',
                'descripcionModelo' => 'MODELO TEST',
                'anioAuto' => 2020,
                'nombreClase' => 'AUTOMOVIL',
                'cilindraje' => 1600,
                'ultimoAnioPagado' => 0,
            ], 200),
            '*ConsultaRubros/obtenerPorCodigoVehiculo*' => Http::response([
                [
                    'codigoTipoDeuda' => 'MATRICULA',
                    'valorRubro' => 100,
                    'anioHastaPago' => (int) date('Y'),
                    'anioDesdePago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA',
                    'codigoRubro' => null,
                ],
            ], 200),
        ]);
    }

    public function test_desglose_anual_pago_usa_codigo_transaccion_y_no_pago_id(): void
    {
        $this->crearApiToken();
        $this->fakeSri();

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_estatico_pagoid_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT7777']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');

        $registro = $this->withHeaders(['Authorization' => 'Bearer token_estatico_pagoid_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT7777',
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-PAGOID-001',
            ]);
        $registro->assertStatus(201);
        $codigoTransaccion = $registro->json('data.codigo_transaccion');

        $consultaPostPago = $this->withHeaders(['Authorization' => 'Bearer token_estatico_pagoid_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT7777']);

        $consultaPostPago->assertOk();
        $consultaPostPago->assertJsonPath('data.desglose_anual.0.estado', 'pagado');

        // Mismo valor que ya devuelve registrar-pago, ahora con el mismo
        // nombre de clave dentro del sub-objeto 'pago'.
        $consultaPostPago->assertJsonPath('data.desglose_anual.0.pago.codigo_transaccion', $codigoTransaccion);
        $consultaPostPago->assertJsonMissingPath('data.desglose_anual.0.pago.pago_id');

        // La estructura general de este endpoint no ganó ningún campo
        // nuevo relacionado a reversión — 'reversion' es exclusivo de
        // verificar-pago, nunca debe aparecer aquí.
        $consultaPostPago->assertJsonMissingPath('data.reversion');
        $consultaPostPago->assertJsonMissingPath('data.desglose_anual.0.pago.reversion');

        // 'referencia_externa'/'entidad_recaudadora' (antes 'referencia'/
        // 'entidad', ver auditoría de nombres): mismo dato, ahora
        // consistente con los nombres que ya usan registrar-pago y
        // verificar-pago — este sub-objeto era el único lugar donde el
        // renombre nunca se había completado.
        $consultaPostPago->assertJsonMissingPath('data.desglose_anual.0.pago.referencia');
        $consultaPostPago->assertJsonMissingPath('data.desglose_anual.0.pago.entidad');
        $consultaPostPago->assertJsonPath(
            'data.desglose_anual.0.pago.referencia_externa',
            'TXN-TEST-PAGOID-001'
        );
        $consultaPostPago->assertJsonPath(
            'data.desglose_anual.0.pago.entidad_recaudadora',
            'Banco de Prueba'
        );

        $this->assertEqualsCanonicalizing(
            ['codigo_transaccion', 'codigo_consulta', 'registro_historico', 'referencia_externa', 'fecha_pago', 'entidad_recaudadora'],
            array_keys($consultaPostPago->json('data.desglose_anual.0.pago'))
        );
    }
}
