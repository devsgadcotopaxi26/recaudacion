<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BancaVerificarPagoScopeTest extends TestCase
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

    public function test_el_dueno_encuentra_su_propia_transaccion_por_referencia_externa(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/verificar-pago', ['referencia_externa' => 'TXN-DUENIO-0001']);

        $response->assertOk()->assertJson(['success' => true]);
        // 'comprobante' y 'transaccion_id' (el id crudo) son información
        // interna — ya no se exponen al banco (ver auditoría), aunque el
        // modelo y las vistas internas los sigan usando normalmente.
        $response->assertJsonMissingPath('data.comprobante');
        $response->assertJsonMissingPath('data.transaccion_id');
    }

    public function test_el_dueno_encuentra_su_propia_transaccion_por_codigo_transaccion(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/verificar-pago', ['codigo_transaccion' => $transaccion->codigo_transaccion]);

        $response->assertOk()->assertJson(['success' => true]);
    }

    public function test_un_banco_rival_no_encuentra_la_transaccion_ajena_por_referencia_externa(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $bancoRival = $this->crearApiToken('banco_rival');
        $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_rival'])
            ->postJson('/api/v1/verificar-pago', ['referencia_externa' => 'TXN-DUENIO-0001']);

        $response->assertStatus(404);
    }

    public function test_un_banco_rival_no_encuentra_la_transaccion_ajena_por_codigo_transaccion(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $bancoRival = $this->crearApiToken('banco_rival');
        $transaccion = $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_rival'])
            ->postJson('/api/v1/verificar-pago', ['codigo_transaccion' => $transaccion->codigo_transaccion]);

        $response->assertStatus(404);
    }

    public function test_sin_ningun_criterio_devuelve_400(): void
    {
        $bancoA = $this->crearApiToken('banco_a');

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/verificar-pago', []);

        $response->assertStatus(400);
        $response->assertJson([
            'success' => false,
            'message' => 'Debe enviar codigo_transaccion o referencia_externa',
        ]);
    }

    public function test_codigo_consulta_ya_no_es_un_criterio_valido(): void
    {
        // codigo_consulta identifica una CONSULTA de deuda, no un pago —
        // dejó de ser un criterio válido de este endpoint (ver auditoría).
        // Enviarlo solo (sin codigo_transaccion ni referencia_externa) debe
        // comportarse igual que no enviar nada.
        $bancoA = $this->crearApiToken('banco_a');
        $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/verificar-pago', ['codigo_consulta' => 'CON-20260101-00001']);

        $response->assertStatus(400);
    }

    public function test_placa_y_anio_fiscal_ya_no_son_un_criterio_valido(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $this->crearTransaccionDe($bancoA);

        $response = $this->withHeaders(['Authorization' => 'Bearer token_estatico_banco_a'])
            ->postJson('/api/v1/verificar-pago', ['placa' => 'ABC1234', 'anio_fiscal' => 2026]);

        $response->assertStatus(400);
    }
}
