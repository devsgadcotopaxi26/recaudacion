<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RevertirPagoTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(string $usuario): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => "Entidad {$usuario}",
            'usuario' => $usuario,
            'token' => "token_estatico_{$usuario}",
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    private function crearTransaccionDe(ApiToken $duenio, array $overrides = []): TransaccionPago
    {
        $transaccion = TransaccionPago::create(array_merge([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'api_token_id' => $duenio->id,
            'codigo_consulta' => 'CON-20260101-00001',
            'referencia_externa' => 'TXN-DUENIO-0001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ], $overrides));

        $transaccion->detalles()->create([
            'placa' => $transaccion->placa,
            'estado' => 'pagado',
            'anio_fiscal' => 2026,
            'monto_impuesto' => 10.00,
            'monto_total' => 10.00,
        ]);

        return $transaccion;
    }

    /**
     * Mismo fake que RegistrarPagoIntegridadCabeceraDetalleTest: un solo
     * rubro MATRICULA, año en curso, sin mora.
     */
    private function fakeSri(): void
    {
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

    public function test_happy_path_revierte_pago_dentro_de_24h_mismo_banco(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
                'motivo' => 'Placa digitada incorrectamente por el cajero',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonPath('data.codigo_transaccion', $transaccion->codigo_transaccion);
        $response->assertJsonPath('data.estado', 'reversado');
        $response->assertJsonPath('data.placa', $transaccion->placa);
        // Agregado (auditoría de nombres): la respuesta de revertir-pago
        // ahora confirma de vuelta el motivo que el banco acaba de enviar,
        // sin que tenga que llamar aparte a verificar-pago para verlo.
        $response->assertJsonPath('data.motivo_reversion', 'Placa digitada incorrectamente por el cajero');
        // JSON serializa un float entero sin punto decimal ("10", no
        // "10.0") — assertSame con 10.0 falla por tipo, no por valor
        // (mismo patrón ya documentado en BancaVerificarPagoScopeTest).
        $response->assertJsonPath('data.monto_revertido', 10);

        $transaccion->refresh();
        $this->assertSame('reversado', $transaccion->estado);
        $this->assertNotNull($transaccion->revertido_en);
        $this->assertSame('Placa digitada incorrectamente por el cajero', $transaccion->revertido_motivo);
        $this->assertSame($bancoA->id, $transaccion->revertido_por_api_token_id);

        // Reversión siempre TOTAL: todos los detalles de la transacción
        // quedan en 'reversado', no solo la cabecera.
        $this->assertSame(
            0,
            $transaccion->detalles()->where('estado', '!=', 'reversado')->count()
        );
    }

    public function test_despues_de_revertir_el_anio_vuelve_a_pendiente_en_consulta_nueva(): void
    {
        $this->crearApiToken('banco_a');
        $this->fakeSri();

        // 1) Consultar deuda y pagar, flujo real de punta a punta.
        $consulta = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);
        $codigoConsulta = $consulta->json('data.codigo_consulta');

        $registro = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/registrar-pago', [
                'placa' => 'ZZT9999',
                'codigo_consulta' => $codigoConsulta,
                'monto' => 10,
                'referencia_externa' => 'TXN-TEST-REVERSION-001',
            ]);
        $registro->assertStatus(201);
        $codigoTransaccion = $registro->json('data.codigo_transaccion');

        // 2) Confirmar que, antes de revertir, el año ya aparece pagado.
        $consultaPostPago = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);
        $consultaPostPago->assertJsonPath('data.desglose_anual.0.estado', 'pagado');

        // 3) Revertir el pago.
        $reversion = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $codigoTransaccion,
                'motivo' => 'Monto incorrecto, se registró por duplicado',
            ]);
        $reversion->assertOk();

        // 4) Sin tocar conciliarPagosLocales(): la siguiente consulta debe
        // mostrar el año como pendiente otra vez, automáticamente.
        $consultaPostReversion = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/consulta-deuda-rodaje-bancos', ['placa' => 'ZZT9999']);
        $consultaPostReversion->assertJsonPath('data.desglose_anual.0.estado', 'pendiente');
    }

    public function test_rechaza_si_pasaron_mas_de_24h_desde_created_at(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA);

        // created_at no es fillable (ver TransaccionPago::$fillable) —
        // se fuerza directo en la fila, igual que haría el paso del tiempo
        // real, sin pasar por Eloquent.
        DB::table('transacciones_pago')
            ->where('id', $transaccion->id)
            ->update(['created_at' => now()->subHours(25)]);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
                'motivo' => 'Intento de reversión fuera de ventana',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error_code', 'FUERA_DE_VENTANA_REVERSION');
        // >= 25, no un entero exacto: diffInHours(..., absolute: true) en
        // Carbon 3 devuelve fracción de hora (ej. 25.00009...), no 25 justo.
        $this->assertGreaterThanOrEqual(25, $response->json('horas_transcurridas'));

        $transaccion->refresh();
        $this->assertSame('pagado', $transaccion->estado, 'El estado no debe cambiar si la reversión fue rechazada.');
    }

    public function test_un_banco_rival_no_puede_revertir_el_pago_de_otro_banco(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $bancoRival = $this->crearApiToken('banco_rival');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_rival'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
                'motivo' => 'Intento de reversión de un pago ajeno',
            ]);

        // Mismo 404 genérico que verificarPago(): no debe revelar que la
        // transacción existe pero pertenece a otra entidad.
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'No se encontró ningún pago con los datos proporcionados',
            'error_code' => 'PAGO_NO_ENCONTRADO',
        ]);

        $transaccion->refresh();
        $this->assertSame('pagado', $transaccion->estado);
    }

    public function test_rechaza_si_ya_estaba_reversado(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA, ['estado' => 'reversado']);
        $transaccion->detalles()->update(['estado' => 'reversado']);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
                'motivo' => 'Segundo intento de reversión, no debería permitirse',
            ]);

        $response->assertStatus(400);
        $response->assertJsonPath('error_code', 'PAGO_YA_ANULADO');
    }

    public function test_rechaza_si_falta_el_motivo(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['motivo']);
        $response->assertJsonPath('error_code', 'DATOS_INVALIDOS');
    }

    public function test_rechaza_si_el_motivo_es_muy_corto(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/revertir-pago', [
                'codigo_transaccion' => $transaccion->codigo_transaccion,
                'motivo' => 'corto',
            ]);

        $response->assertStatus(400);
        $response->assertJsonValidationErrors(['motivo']);
        $response->assertJsonPath('error_code', 'DATOS_INVALIDOS');
    }
}
