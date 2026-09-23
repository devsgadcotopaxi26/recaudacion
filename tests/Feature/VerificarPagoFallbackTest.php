<?php

namespace Tests\Feature;

use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificarPagoFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function crearPagoConfirmado(): TransaccionPago
    {
        return TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'referencia_externa' => 'TXN-1790021641092',
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);
    }

    public function test_encuentra_el_pago_por_codigo_transaccion(): void
    {
        $pago = $this->crearPagoConfirmado();

        $response = $this->get("/verificar/{$pago->codigo_transaccion}");

        $response->assertInertia(fn ($page) => $page
            ->component('Pago/Verificacion')
            ->where('valido', true)
            ->where('pago.placa', 'ABC1234')
        );
    }

    public function test_referencia_externa_sola_ya_no_encuentra_nada(): void
    {
        // Sin fallback: referencia_externa por sí sola (sin ser también un
        // codigo_transaccion válido) debe comportarse igual que un valor
        // que no existe en absoluto.
        $this->crearPagoConfirmado();

        $response = $this->get('/verificar/TXN-1790021641092');

        $response->assertInertia(fn ($page) => $page
            ->component('Pago/Verificacion')
            ->where('valido', false)
        );
    }

    public function test_referencia_inexistente_no_encuentra_nada(): void
    {
        $response = $this->get('/verificar/NO-EXISTE-NADA');

        $response->assertInertia(fn ($page) => $page
            ->component('Pago/Verificacion')
            ->where('valido', false)
        );
    }
}
