<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPaymentWebhook;
use App\Services\PaymentGatewayService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentGatewayService $paymentService
    ) {
    }

    /**
     * Recibir webhook de la pasarela de pagos
     */
    public function pago(Request $request)
    {
        try {
            // Log completo del webhook recibido
            Log::info('=== WEBHOOK RECIBIDO DE PAGO MEDIOS ===', [
                'headers' => $request->headers->all(),
                'body' => $request->all(),
                'method' => $request->method(),
                'ip' => $request->ip(),
            ]);

            // Procesar webhook INMEDIATAMENTE (no en cola)
            $resultado = $this->paymentService->procesarWebhook($request->all());

            if ($resultado['success']) {
                Log::info('Webhook procesado exitosamente', $resultado);

                // Obtener el pago procesado
                $pagoId = $resultado['pago_id'] ?? null;

                if ($pagoId) {
                    // Redirigir a la página de confirmación
                    return redirect()->route('pago.confirmacion', ['pago' => $pagoId]);
                }
            } else {
                Log::error('Error procesando webhook', $resultado);
            }

            // Si algo salió mal, redirigir al inicio con mensaje
            return redirect()->route('home')
                ->with('error', 'Hubo un problema al procesar tu pago. Por favor contacta con soporte.');

        } catch (\Exception $e) {
            Log::error('Error al recibir webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Redirigir al inicio con mensaje de error
            return redirect()->route('home')
                ->with('error', 'Hubo un error al procesar tu pago. Por favor contacta con soporte.');
        }
    }
}
