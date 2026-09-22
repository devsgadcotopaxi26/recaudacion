<?php

namespace Tests\Feature;

use App\Models\ApiToken;
use App\Models\TransaccionPago;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferenciaExternaUniquePerEntidadTest extends TestCase
{
    use RefreshDatabase;

    private function crearApiToken(string $usuario): ApiToken
    {
        return ApiToken::create([
            'entidad_nombre' => "Entidad {$usuario}",
            'usuario' => $usuario,
            'token' => "token_{$usuario}",
            'activo' => true,
            'requests_permitidos' => 1000,
        ]);
    }

    public function test_dos_entidades_distintas_pueden_usar_la_misma_referencia_externa(): void
    {
        $bancoA = $this->crearApiToken('banco_a');
        $bancoB = $this->crearApiToken('banco_b');

        $transaccionA = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'api_token_id' => $bancoA->id,
            'referencia_externa' => 'TXN-000001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
        ]);

        // Antes de esta migración, esto violaba UNIQUE(referencia_externa)
        // global. Con la restricción compuesta, dos entidades distintas
        // pueden coincidir en su propio esquema de numeración.
        $transaccionB = TransaccionPago::create([
            'placa' => 'XYZ5678',
            'canal' => 'banco',
            'api_token_id' => $bancoB->id,
            'referencia_externa' => 'TXN-000001',
            'monto_total' => 20.00,
            'estado' => 'pagado',
        ]);

        $this->assertNotSame($transaccionA->id, $transaccionB->id);
        $this->assertSame('TXN-000001', $transaccionA->referencia_externa);
        $this->assertSame('TXN-000001', $transaccionB->referencia_externa);
    }

    public function test_la_misma_entidad_no_puede_repetir_su_propia_referencia_externa(): void
    {
        $bancoA = $this->crearApiToken('banco_a');

        TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'api_token_id' => $bancoA->id,
            'referencia_externa' => 'TXN-000001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
        ]);

        // Protección de idempotencia: un reintento del mismo banco con la
        // misma referencia_externa debe seguir fallando, igual que antes
        // de esta migración.
        $this->expectException(QueryException::class);

        TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'api_token_id' => $bancoA->id,
            'referencia_externa' => 'TXN-000001',
            'monto_total' => 10.00,
            'estado' => 'pagado',
        ]);
    }

    public function test_pasarela_ciudadana_con_api_token_id_null_no_choca_entre_si(): void
    {
        // api_token_id es nullable (canal pasarela_ciudadana) — MySQL
        // trata cada NULL como distinto en un índice único, así que dos
        // transacciones de pasarela con api_token_id NULL no deberían
        // chocar entre sí aunque compartan referencia_externa.
        $t1 = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'api_token_id' => null,
            'referencia_externa' => 'GATEWAY-TOKEN-001',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $t2 = TransaccionPago::create([
            'placa' => 'XYZ5678',
            'canal' => 'pasarela_ciudadana',
            'api_token_id' => null,
            'referencia_externa' => 'GATEWAY-TOKEN-001',
            'monto_total' => 20.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotSame($t1->id, $t2->id);
    }
}
