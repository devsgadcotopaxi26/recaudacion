<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\PagoDetalle;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Regla de negocio NO NEGOCIABLE: un pago siempre cubre el TOTAL de la
 * deuda pendiente (todos los años, del más antiguo al actual) en una
 * sola operación. Nunca se permite seleccionar un solo año a pagar,
 * aunque el banco envíe 'anio_fiscal'.
 *
 * Antes de este fix, BancaController::registrarPago() tenía una rama
 * (ver auditoría) que, con 'anio_fiscal' + un monto que coincidiera con
 * SOLO ese año, registraba el pago de ese año único y dejaba el resto
 * de la deuda pendiente sin cobrar ni rechazar la operación — violación
 * confirmada en vivo con una placa de 2 años (ver hallazgo). Esta
 * suite cubre que, tras el fix, 'anio_fiscal' ya no puede lograr eso.
 */
class RegistrarPagoSiempreConsolidadoTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => 'Banco de Prueba',
            'usuario' => 'banco_consolidado_test',
            'token' => 'token_consolidado_test',
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    /** Placa con 2 años de deuda pendiente: anio actual y el anterior. */
    private function fakeSriDosAnios(): void
    {
        $anioActual = (int) date('Y');
        $anioAnterior = $anioActual - 1;

        Http::fake([
            '*BaseVehiculo/obtenerPorNumeroPlacaOPorNumeroCampvOPorNumeroCpn*' => Http::response([
                'codigoVehiculo' => 888001,
                'numeroPlaca' => 'CNS0001',
                'descripcionMarca' => 'TEST',
                'descripcionModelo' => 'MODELO TEST',
                'anioAuto' => 2018,
                'nombreClase' => 'AUTOMOVIL',
                'cilindraje' => 1600,
                'ultimoAnioPagado' => 0,
            ], 200),
            '*ConsultaRubros/obtenerPorCodigoVehiculo*' => Http::response([
                [
                    'codigoRubro' => 777001,
                    'codigoTipoDeuda' => 'MATRICULA',
                    'valorRubro' => 100,
                    'anioDesdePago' => $anioAnterior,
                    'anioHastaPago' => $anioActual,
                    'nombreCortoBeneficiario' => 'GAD',
                    'descripcionRubro' => 'MATRICULA',
                ],
            ], 200),
            '*ConsultaComponente/obtenerListaComponentesPorCodigoConsultaRubro*' => Http::response([
                ['codigoComponente' => 'IMPUESTO', 'nombreComponente' => 'Impuesto', 'anioFiscal' => $anioActual, 'valorComponente' => 50],
                ['codigoComponente' => 'IMPUESTO', 'nombreComponente' => 'Impuesto', 'anioFiscal' => $anioAnterior, 'valorComponente' => 50],
            ], 200),
        ]);
    }

    public function test_anio_fiscal_no_permite_pagar_solo_un_anio_dejando_el_resto_pendiente(): void
    {
        $this->crearApiToken();
        $this->fakeSriDosAnios();
        $anioAnterior = (int) date('Y') - 1;

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'CNS0001']);
        $consulta->assertOk();
        $codigoConsulta = $consulta->json('data.codigo_consulta');
        $desglose = $consulta->json('data.desglose_anual');
        $this->assertCount(2, $desglose, 'Fixture debe generar 2 años pendientes.');

        $valorAnioAnterior = collect($desglose)->firstWhere('anio_fiscal', $anioAnterior)['monto_total'];

        // Intento de EXPLOIT: anio_fiscal apunta a un año especifico, y el
        // monto enviado es SOLO el de ese año (no el total pendiente).
        $intento = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'CNS0001',
                'codigo_consulta' => $codigoConsulta,
                'anio_fiscal' => $anioAnterior,
                'monto' => $valorAnioAnterior,
                'referencia_externa' => 'TXN-TEST-NOPARCIAL-001',
            ]);

        // Debe RECHAZARSE: el monto enviado (1 año) no coincide con el
        // total pendiente (2 años) -- ya no hay rama que lo acepte.
        $intento->assertStatus(400);
        $intento->assertJsonPath('message', 'El monto enviado no coincide con el total a pagar');

        // Nada debe haberse registrado -- ni ese año ni ningun otro.
        $this->assertSame(0, TransaccionPago::count());
        $this->assertSame(0, PagoDetalle::count());
    }

    public function test_pago_con_anio_fiscal_cobra_y_marca_pagado_el_total_de_todos_los_anios(): void
    {
        $this->crearApiToken();
        $this->fakeSriDosAnios();
        $anioActual = (int) date('Y');
        $anioAnterior = $anioActual - 1;

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'CNS0001']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');
        $totalPendiente = $consulta->json('data.totales.total_a_pagar');

        // Mismo anio_fiscal de antes, pero ahora con el MONTO TOTAL
        // (como ya haria un banco que calcula bien) -- debe cubrir AMBOS
        // años en una sola transaccion, ignorando la seleccion de anio_fiscal.
        $pago = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'CNS0001',
                'codigo_consulta' => $codigoConsulta,
                'anio_fiscal' => $anioAnterior,
                'monto' => $totalPendiente,
                'referencia_externa' => 'TXN-TEST-CONSOLIDADO-001',
            ]);

        $pago->assertStatus(201);
        $pago->assertJsonPath('data.anios_pagados', 2);
        $this->assertEqualsCanonicalizing(
            [$anioActual, $anioAnterior],
            collect($pago->json('data.pagos'))->pluck('anio_fiscal')->all()
        );

        // Confirmar en BD: AMBOS años quedan 'pagado', en UNA sola transaccion.
        $this->assertSame(1, TransaccionPago::count());
        $this->assertSame(2, PagoDetalle::where('estado', 'pagado')->count());
        $this->assertSame(
            0,
            PagoDetalle::where('placa', 'CNS0001')->where('estado', '!=', 'pagado')->count()
        );

        // La deuda ya no debe mostrar NINGUN año pendiente.
        $consultaFinal = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'CNS0001']);
        $consultaFinal->assertJsonPath('data.todos_pagados', true);
    }

    public function test_sin_anio_fiscal_tambien_cobra_siempre_el_total_pendiente(): void
    {
        // Control: confirma que el camino SIN anio_fiscal (el uso normal)
        // sigue funcionando igual que siempre.
        $this->crearApiToken();
        $this->fakeSriDosAnios();

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'CNS0001']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');
        $totalPendiente = $consulta->json('data.totales.total_a_pagar');

        $pago = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'CNS0001',
                'codigo_consulta' => $codigoConsulta,
                'monto' => $totalPendiente,
                'referencia_externa' => 'TXN-TEST-SINANIO-001',
            ]);

        $pago->assertStatus(201);
        $pago->assertJsonPath('data.anios_pagados', 2);
    }

    public function test_anio_fiscal_de_un_anio_ya_pagado_sigue_devolviendo_pago_existente(): void
    {
        // Confirma que el uso LEGITIMO de anio_fiscal (chequeo temprano de
        // duplicado) sigue funcionando -- no se elimino por completo, solo
        // se le quito la capacidad de seleccionar que se cobra.
        $this->crearApiToken();
        $this->fakeSriDosAnios();
        $anioActual = (int) date('Y');

        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'CNS0001']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');
        $totalPendiente = $consulta->json('data.totales.total_a_pagar');

        $primerPago = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'CNS0001',
                'codigo_consulta' => $codigoConsulta,
                'monto' => $totalPendiente,
                'referencia_externa' => 'TXN-TEST-DUPCHECK-001',
            ]);
        $primerPago->assertStatus(201);

        // Segundo intento, apuntando con anio_fiscal a un año que YA quedo
        // pagado en el primer pago consolidado.
        $intento = $this->withHeaders(['Authorization' => 'Bearer token_consolidado_test'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'CNS0001',
                'anio_fiscal' => $anioActual,
                'codigo_consulta' => $codigoConsulta,
                'monto' => $totalPendiente,
                'referencia_externa' => 'TXN-TEST-DUPCHECK-002',
            ]);

        $intento->assertStatus(400);
        $intento->assertJsonPath('pago_existente.codigo_consulta', $codigoConsulta);
    }
}
