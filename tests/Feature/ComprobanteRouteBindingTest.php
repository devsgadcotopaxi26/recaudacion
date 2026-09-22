<?php

namespace Tests\Feature;

use App\Models\TransaccionPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComprobanteRouteBindingTest extends TestCase
{
    use RefreshDatabase;

    private function crearPagoConfirmado(): TransaccionPago
    {
        return TransaccionPago::create([
            'placa' => 'ABC1234',
            'canal' => 'banco',
            'monto_total' => 10.00,
            'estado' => 'pagado',
            'fecha_pago' => now(),
        ]);
    }

    public function test_comprobante_responde_200_con_certificado_token(): void
    {
        $pago = $this->crearPagoConfirmado();

        $response = $this->get("/comprobante/{$pago->certificado_token}");

        $response->assertOk();
    }

    public function test_comprobante_responde_404_con_id_crudo_adivinado(): void
    {
        $pago = $this->crearPagoConfirmado();

        // Antes del fix, esto resolvía el mismo pago vía route model
        // binding por id — exactamente el IDOR reportado en la auditoría
        // (comprobante es secuencial: PAG-000001, PAG-000002...).
        $response = $this->get("/comprobante/{$pago->id}");

        $response->assertNotFound();
    }

    public function test_pago_confirmacion_responde_200_con_certificado_token(): void
    {
        $pago = $this->crearPagoConfirmado();

        $response = $this->get("/pago/confirmacion/{$pago->certificado_token}");

        $response->assertOk();
    }

    public function test_pago_confirmacion_responde_404_con_id_crudo_adivinado(): void
    {
        $pago = $this->crearPagoConfirmado();

        $response = $this->get("/pago/confirmacion/{$pago->id}");

        $response->assertNotFound();
    }

    public function test_id_de_otra_transaccion_tampoco_funciona_como_atajo(): void
    {
        // Dos transacciones consecutivas: confirma que ni siquiera el id
        // de una transacción DISTINTA (pero real) sirve como acceso.
        $pago1 = $this->crearPagoConfirmado();
        $pago2 = $this->crearPagoConfirmado();

        $response = $this->get("/comprobante/{$pago1->id}");
        $response->assertNotFound();

        $response = $this->get("/comprobante/{$pago2->id}");
        $response->assertNotFound();
    }
}
