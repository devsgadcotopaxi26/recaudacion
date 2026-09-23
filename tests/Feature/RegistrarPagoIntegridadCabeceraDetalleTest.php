<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\PagoDetalle;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RegistrarPagoIntegridadCabeceraDetalleTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_test',
            'token' => 'token_estatico_prueba',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    private function fakeSri(): void
    {
        // Un solo rubro de MATRICULA, año en curso, sin mora — codigoRubro
        // null para que el servicio no intente pedir componentes (rama
        // "empty($detallesRubro)" usa anioHasta/valorTotalRubro directo).
        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 999001,
                'numeroPlaca' => 'ZZT9999',
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

    public function test_un_descuadre_forzado_revierte_toda_la_transaccion(): void
    {
        $token = $this->crearApiToken();
        $this->fakeSri();

        // 1) Consultar deuda para obtener un codigo_consulta real.
        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);

        $consulta->assertOk();
        $codigoConsulta = $consulta->json('data.codigo_consulta');
        $montoEsperado = $consulta->json('data.totales.total_a_pagar');

        $this->assertSame(10.0, (float) $montoEsperado);

        // 2) Forzar el descuadre: un listener de modelo corrompe el monto
        // del detalle DESPUÉS de que registrarPago() ya calculó y validó
        // todo con los números correctos — simula un bug futuro (evento,
        // observer, etc.) que altera lo que realmente queda persistido,
        // sin tocar la lógica de registrarPago() para lograrlo.
        PagoDetalle::creating(function (PagoDetalle $detalle) {
            $detalle->monto_total = 999999.99;
        });

        // 3) Registrar el pago con el monto correcto (pasa la validación
        // de tolerancia de ±$1.00 normalmente) — el descuadre solo debe
        // manifestarse en la guarda cabecera/detalle nueva. Sin fecha_pago
        // en el body: ya no es un campo aceptado (ver auditoría).
        $registro = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT9999',
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-DESCUADRE-001',
            ]);

        $registro->assertStatus(500);

        // 4) Confirmar que NO quedó ningún registro huérfano — ni la
        // cabecera ni el detalle sobrevivieron al rollback, a pesar de
        // que el detalle sí se llegó a crear (con create()) antes de que
        // la excepción disparara el rollback del DB::transaction().
        $this->assertSame(0, TransaccionPago::count());
        $this->assertSame(0, PagoDetalle::count());
    }

    public function test_un_pago_normal_sin_descuadre_se_registra_correctamente(): void
    {
        // Control: confirma que la guarda nueva NO rompe el flujo normal
        // (sin el listener que corrompe datos).
        $token = $this->crearApiToken();
        $this->fakeSri();

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);

        $codigoConsulta = $consulta->json('data.codigo_consulta');

        // Confirma de principio a fin que un request SIN fecha_pago en el
        // body (ya no es un campo aceptado, ver auditoría) sigue
        // registrando el pago correctamente.
        $registro = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT9999',
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-OK-001',
            ]);

        $registro->assertStatus(201);
        $this->assertSame(1, TransaccionPago::count());
        $this->assertSame(1, PagoDetalle::count());

        $transaccion = TransaccionPago::first();
        $this->assertNotNull(
            $transaccion->fecha_pago,
            'fecha_pago debe seguir llenándose server-side (now()) aunque el banco ya no la reporte.'
        );

        // 'comprobante' es información interna/contable — ya no se expone
        // al banco (ver auditoría), aunque comprobante() del modelo y las
        // vistas internas lo sigan usando normalmente.
        $registro->assertJsonMissingPath('data.comprobante');
    }

    public function test_pago_existente_no_expone_el_id_crudo(): void
    {
        // Rama de error "año ya pagado" (pago_existente): 'id' (el id
        // crudo, sin el disfraz del prefijo PAG- de comprobante) tampoco
        // debe exponerse al banco — mismo riesgo de enumeración.
        $token = $this->crearApiToken();
        $this->fakeSri();
        $anioActual = (int) date('Y');

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');

        $primerPago = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT9999',
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-DUPLICADO-001',
            ]);
        $primerPago->assertStatus(201);

        // Segundo intento sobre el mismo año, ya pagado — dispara la rama
        // pago_existente. anio_fiscal explícito para que registrarPago()
        // busque un PagoDetalle 'pagado' de ese año puntual.
        $intentoDuplicado = $this->withHeaders(['Authorization' => 'Bearer token_estatico_prueba'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT9999',
                'anio_fiscal' => $anioActual,
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-DUPLICADO-002',
            ]);

        $intentoDuplicado->assertStatus(400);
        $intentoDuplicado->assertJsonPath('pago_existente.codigo_consulta', $codigoConsulta);
        $intentoDuplicado->assertJsonMissingPath('pago_existente.id');
    }
}
