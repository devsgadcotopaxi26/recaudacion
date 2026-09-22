<?php

namespace Tests\Feature;

use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaccionPagoTokenVerificacionTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_verificacion_se_genera_automaticamente_al_crear(): void
    {
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotEmpty($transaccion->token_verificacion);
        $this->assertSame(32, strlen($transaccion->token_verificacion));
    }

    public function test_certificado_token_se_sigue_generando_junto_al_nuevo_campo(): void
    {
        // Regresión: confirmar que agregar token_verificacion no rompió
        // la generación de certificado_token (mismo bloque creating()).
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotEmpty($transaccion->certificado_token);
        $this->assertSame(32, strlen($transaccion->certificado_token));
        $this->assertNotSame($transaccion->certificado_token, $transaccion->token_verificacion);
    }

    public function test_token_verificacion_es_distinto_entre_transacciones(): void
    {
        $a = TransaccionPago::create([
            'placa' => 'AAA1111',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $b = TransaccionPago::create([
            'placa' => 'BBB2222',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
        ]);

        $this->assertNotSame($a->token_verificacion, $b->token_verificacion);
    }

    public function test_no_se_sobreescribe_si_ya_viene_asignado(): void
    {
        $transaccion = TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'pasarela_ciudadana',
            'monto_total' => 10.00,
            'estado' => 'pendiente',
            'token_verificacion' => 'valor-fijo-de-prueba-000000000',
        ]);

        $this->assertSame('valor-fijo-de-prueba-000000000', $transaccion->token_verificacion);
    }
}
