<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cubre el fix de confiabilidad de SriVehiculoService::obtenerDetalleCompleto():
 * antes, un fallo de ConsultaComponente (timeout/5xx) se absorbía en
 * silencio y colapsaba el desglose por año en una sola entrada con el
 * valor total del rubro — perdiendo años reales de deuda sin avisar a
 * nadie, y encima cacheando ese resultado degradado 24h.
 *
 * Ahora ConsultaComponente pasa por el MISMO mecanismo de reintentos que
 * ya usan BaseVehiculo/ConsultaRubros (el for de obtenerDetalleCompleto);
 * si agota los reintentos, propaga SriConsultaIncompletaException en vez
 * de colapsar, y BancaController::consultarDeuda() la traduce en un error
 * explícito (502, error_code SRI_CONSULTA_INCOMPLETA) — nunca un 200 con
 * datos incompletos.
 */
class SriConsultaComponenteFalloTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Reintentos reales (default 1.5s) harían la suite lenta sin
        // aportar nada — el comportamiento bajo prueba es la LÓGICA de
        // reintento, no su temporización real.
        config(['sri.retry.delay_ms' => 5]);
    }

    private function crearApiToken(): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_componente_test',
            'token' => 'token_componente_test',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    private function fakeBaseYRubros(string $placa, int $codigoVehiculo, int $codigoRubro): void
    {
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => $codigoVehiculo,
                'numeroPlaca' => $placa,
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
                    'valorRubro' => 300,
                    'anioDesdePago' => (int) date('Y') - 2,
                    'anioHastaPago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA ATRASADA',
                    'codigoRubro' => $codigoRubro, // presente: el SRI SÍ promete desglose
                ],
            ], 200),
        ]);
    }

    public function test_consulta_componente_falla_incluso_tras_reintentos_devuelve_error_explicito_y_no_cachea(): void
    {
        $this->crearApiToken();
        $this->fakeBaseYRubros('FLL0001', 700001, 999001);

        // Todas las llamadas a ConsultaComponente fallan (500), sin importar
        // cuántos reintentos haga obtenerDetalleCompleto().
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 700001,
                'numeroPlaca' => 'FLL0001',
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
                    'valorRubro' => 300,
                    'anioDesdePago' => (int) date('Y') - 2,
                    'anioHastaPago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA ATRASADA',
                    'codigoRubro' => 999001,
                ],
            ], 200),
            '*ConsultaComponente/obtenerListaComponentesPorCodigoConsultaRubro*' => Http::response([], 500),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_componente_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'FLL0001']);

        $response->assertStatus(502);
        $response->assertJson([
            'success' => false,
            'error_code' => 'SRI_CONSULTA_INCOMPLETA',
        ]);

        // Nunca se sirvió (ni se guardó) un desglose colapsado — ni en esta
        // respuesta ni para la siguiente consulta.
        $this->assertFalse(Cache::has('sri:detalle:FLL0001'));
    }

    public function test_consulta_componente_falla_una_vez_y_se_recupera_en_el_reintento(): void
    {
        $this->crearApiToken();

        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 700002,
                'numeroPlaca' => 'FLL0002',
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
                    'valorRubro' => 220,
                    'anioDesdePago' => (int) date('Y') - 1,
                    'anioHastaPago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA ATRASADA',
                    'codigoRubro' => 999002,
                ],
            ], 200),
            // Primer intento falla (500), segundo intento (el reintento)
            // entrega el desglose real de 2 años.
            '*ConsultaComponente/obtenerListaComponentesPorCodigoConsultaRubro*' => Http::sequence()
                ->push([], 500)
                ->push([
                    ['codigoComponente' => 'IMPUESTO', 'nombreComponente' => 'Impuesto', 'anioFiscal' => (int) date('Y'), 'valorComponente' => 120],
                    ['codigoComponente' => 'IMPUESTO', 'nombreComponente' => 'Impuesto', 'anioFiscal' => (int) date('Y') - 1, 'valorComponente' => 100],
                ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_componente_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'FLL0002']);

        $response->assertOk();
        $desglose = $response->json('data.desglose_anual');

        $this->assertCount(2, $desglose, 'El desglose debe traer los 2 años reales, no colapsarse en 1.');
        $this->assertEqualsCanonicalizing(
            [(int) date('Y'), (int) date('Y') - 1],
            array_column($desglose, 'anio_fiscal')
        );

        // El resultado COMPLETO (ya recuperado) sí se cachea normalmente.
        $this->assertTrue(Cache::has('sri:detalle:FLL0002'));
    }

    public function test_camino_feliz_sin_fallas_mantiene_la_misma_estructura_de_siempre(): void
    {
        $this->crearApiToken();

        // Mismo fixture que ya usan RegistrarPagoIntegridadCabeceraDetalleTest
        // y otros tests de este proyecto: codigoRubro null, un solo año,
        // sin ninguna falla — regresión de que el camino feliz no cambió.
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 700003,
                'numeroPlaca' => 'FLL0003',
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

        $response = $this->withHeaders(['Authorization' => 'Bearer token_componente_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'FLL0003']);

        $response->assertOk();
        $data = $response->json('data');

        $this->assertEqualsCanonicalizing(
            ['codigo_consulta', 'placa', 'vehiculo', 'valor_matricula', 'todos_pagados', 'desglose_anual', 'totales', 'nota'],
            array_keys($data)
        );
        $this->assertCount(1, $data['desglose_anual']);
        $this->assertSame((int) date('Y'), $data['desglose_anual'][0]['anio_fiscal']);
        $this->assertSame(10.0, (float) $data['desglose_anual'][0]['monto_total']);
        $this->assertEqualsCanonicalizing(
            ['anio_fiscal', 'subtotal_matricula', 'rodaje', 'anios_atraso', 'monto_mora', 'monto_total', 'estado'],
            array_keys($data['desglose_anual'][0])
        );
        $this->assertTrue(Cache::has('sri:detalle:FLL0003'));
    }

    public function test_codigo_rubro_null_sin_falla_sigue_usando_el_fallback_original_sin_tratarse_como_error(): void
    {
        $this->crearApiToken();

        // codigoRubro null desde el SRI: caso LEGÍTIMO (el SRI nunca
        // prometió desglose por componente para este rubro), no una falla
        // — nunca debe disparar SriConsultaIncompletaException.
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 700004,
                'numeroPlaca' => 'FLL0004',
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
                    'valorRubro' => 50,
                    'anioHastaPago' => (int) date('Y'),
                    'anioDesdePago' => (int) date('Y'),
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA',
                    'codigoRubro' => null,
                ],
            ], 200),
        ]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_componente_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'FLL0004']);

        $response->assertOk();
        $response->assertJsonMissingPath('error_code');
        $this->assertCount(1, $response->json('data.desglose_anual'));
        $this->assertSame(50.0, (float) $response->json('data.desglose_anual.0.subtotal_matricula'));
    }
}
