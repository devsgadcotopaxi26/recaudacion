<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\PagoDetalle;
use App\Models\TransaccionPago;
use App\Services\SriVehiculoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Rama 'historial' de SriVehiculoService::consultarVehiculoCompleto() cuando
 * el último comprobante del SRI cubrió VARIOS años en un solo pago.
 *
 * Caso real: XAI0194 pagó el 2026-11-23 la matrícula 2023-2026 ($580.68) en
 * un solo comprobante; su pago anterior fue en 2022. El SRI pasa a reportar
 * ultimoAnioPagado=2026 y antes solo se cobraba 2026 — sin mora y con el
 * total del comprobante completo como base de un solo año ($58.07).
 *
 * Los datos del comprobante son los reales del SRI para esa placa.
 */
class RodajeHistorialPagoMultianioTest extends TestCase
{
    use RefreshDatabase;

    private const PLACA = 'XAI0194';
    private const ANIO_ACTUAL = 2026;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'sri.cache.enabled' => false,
            'sri.retry.delay_ms' => 5,
            'sri.inicio_cobro_rodaje' => '2026-01-01',
        ]);
    }

    private function fakeSri(string $fechaUltimoPago): void
    {
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 1273655,
                'numeroPlaca' => self::PLACA,
                'descripcionMarca' => 'NISSAN',
                'descripcionModelo' => 'SENTRA 1.6 M/T',
                'anioAuto' => 2006,
                'nombreClase' => '',
                'cilindraje' => 0,
                'ultimoAnioPagado' => self::ANIO_ACTUAL,
            ], 200),
            '*ConsultaRubros/obtenerPorCodigoVehiculo*' => Http::response([], 200),
            '*consultaPagos/obtenerPorPlacaCampvCpn*' => Http::response(['data' => [
                ['codigoRecaudacion' => 47581466, 'fechaDePago' => '2022-08-25 02:17', 'monto' => 76.03, 'tipoDeuda' => 'PAGO DEL VALOR DE LA MATRÍCULA'],
                ['codigoRecaudacion' => 65736798, 'fechaDePago' => $fechaUltimoPago, 'monto' => 580.68, 'tipoDeuda' => 'PAGO DEL VALOR DE LA MATRÍCULA'],
            ]], 200),
            '*consultaPagos/obtenerDetallesPago*' => Http::response(['data' => $this->comprobanteMultianio()], 200),
        ]);
    }

    /** Comprobante real 65736798: 22 componentes, años 2023-2026. */
    private function comprobanteMultianio(): array
    {
        $c = fn ($rubro, $comp, $anio, $valor) => [
            'descripcionRubro' => $rubro, 'descripcionComponente' => $comp, 'anio' => $anio, 'valor' => $valor,
        ];

        return [
            $c('IMPUESTO A LA PROPIEDAD', 'Impuesto', 2023, 6.25), $c('IMPUESTO A LA PROPIEDAD', 'Interes', 2023, 2.14),
            $c('IMPUESTO A LA PROPIEDAD', 'Impuesto', 2024, 6.25), $c('IMPUESTO A LA PROPIEDAD', 'Interes', 2024, 1.26),
            $c('IMPUESTO A LA PROPIEDAD', 'Impuesto', 2025, 6.25), $c('IMPUESTO A LA PROPIEDAD', 'Interes', 2025, 0.54),
            $c('IMPUESTO A LA PROPIEDAD', 'Impuesto', 2026, 6.25),
            $c('TASA SPPAT', 'Recargo', 2023, 35.47), $c('TASA SPPAT', 'Tasa', 2023, 33.78),
            $c('TASA SPPAT', 'Recargo', 2024, 55.74), $c('TASA SPPAT', 'Tasa', 2024, 33.78),
            $c('TASA SPPAT', 'Recargo', 2025, 50.67), $c('TASA SPPAT', 'Tasa', 2025, 33.78),
            $c('TASA SPPAT', 'Recargo', 2026, 55.74), $c('TASA SPPAT', 'Tasa', 2026, 33.78),
            $c('TASAS ANT', 'Tasa', 2023, 36),
            $c('TASAS ANT', 'Recargo', 2024, 25), $c('TASAS ANT', 'Tasa', 2024, 36),
            $c('TASAS ANT', 'Recargo', 2025, 25), $c('TASAS ANT', 'Tasa', 2025, 36),
            $c('TASAS ANT', 'Recargo', 2026, 25), $c('TASAS ANT', 'Tasa', 2026, 36),
        ];
    }

    private function porAnio(array $resultado): array
    {
        return collect($resultado['desglose_anual'])->keyBy('anio_fiscal')->all();
    }

    public function test_pago_multianio_reciente_cobra_todos_los_anios_que_cubrio_con_mora(): void
    {
        $this->fakeSri('2026-11-23 03:04');

        $r = (new SriVehiculoService())->consultarVehiculoCompleto(self::PLACA, self::ANIO_ACTUAL);
        $anios = $this->porAnio($r);

        $this->assertSame('historial', $r['metodo_sri']);
        $this->assertSame([2023, 2024, 2025, 2026], collect(array_keys($anios))->sort()->values()->all());

        // Base de cada año = todos sus componentes del comprobante (con recargos/intereses).
        $this->assertEquals(113.64, $anios[2023]['subtotal_matricula']);
        $this->assertEquals(156.77, $anios[2026]['subtotal_matricula']);

        // Mora = 10% del rodaje por año de atraso respecto al año actual.
        $this->assertSame(3, $anios[2023]['anios_atraso']);
        $this->assertEquals(3.41, $anios[2023]['monto_mora']);
        $this->assertEquals(3.16, $anios[2024]['monto_mora']);
        $this->assertEquals(1.52, $anios[2025]['monto_mora']);
        $this->assertEquals(0, $anios[2026]['monto_mora']);

        $this->assertEquals(58.06, $r['totales']['total_rodaje']);
        $this->assertEquals(8.09, $r['totales']['total_mora']);
        $this->assertEquals(66.15, $r['totales']['total_a_pagar']);
    }

    public function test_pago_multianio_anterior_al_inicio_del_cobro_mantiene_regla_previa(): void
    {
        // Mismo comprobante pero pagado antes de que el GADPC cobrara con este
        // sistema: se mantiene la regla de ultimoAnioPagado (solo año en curso),
        // pero ya con la base de ESE año, no con el total del comprobante.
        $this->fakeSri('2025-06-10 10:00');

        $r = (new SriVehiculoService())->consultarVehiculoCompleto(self::PLACA, self::ANIO_ACTUAL);
        $anios = $this->porAnio($r);

        $this->assertSame([2026], array_keys($anios));
        $this->assertEquals(156.77, $anios[2026]['subtotal_matricula']);
        $this->assertEquals(15.68, $r['totales']['total_a_pagar']);
    }

    public function test_anios_ya_pagados_localmente_no_vuelven_a_cobrarse(): void
    {
        $this->fakeSri('2026-11-23 03:04');

        $token = ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba', 'usuario' => 'banco_multianio',
            'token' => 'token_multianio', 'activo' => true, 'requests_permitidos' => 1000,
        ]);
        $transaccion = TransaccionPago::create([
            'placa' => self::PLACA, 'canal' => 'banco', 'api_token_id' => $token->id,
            'referencia_externa' => 'REF-MULTIANIO-1', 'monto_total' => 33.73,
            'estado' => 'pagado', 'fecha_pago' => now(),
        ]);
        foreach ([2023 => 14.77, 2024 => 18.96] as $anio => $monto) {
            PagoDetalle::create([
                'transaccion_pago_id' => $transaccion->id, 'placa' => self::PLACA, 'estado' => 'pagado',
                'anio_fiscal' => $anio, 'monto_impuesto' => $monto, 'monto_mora' => 0, 'monto_total' => $monto,
            ]);
        }

        $service = new SriVehiculoService();
        $r = $service->consultarVehiculoCompleto(self::PLACA, self::ANIO_ACTUAL);
        $conciliado = $service->conciliarPagosLocales(self::PLACA, $r['desglose_anual']);

        $anios = collect($conciliado['desglose_anual'])->keyBy('anio_fiscal');

        $this->assertSame([2025, 2026], $anios->keys()->sort()->values()->all());
        $this->assertSame('pendiente', $anios[2025]['estado']);
        $this->assertSame('pendiente', $anios[2026]['estado']);
    }
}
