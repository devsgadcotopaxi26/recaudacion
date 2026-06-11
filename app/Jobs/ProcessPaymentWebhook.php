<?php

namespace App\Jobs;

use App\Services\PaymentGatewayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de intentos
     */
    public $tries = 3;

    /**
     * Tiempo de espera entre reintentos (segundos)
     */
    public $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $webhookData
    ) {
    }

    /**
     * Execute the job.
     */
    public function handle(PaymentGatewayService $paymentService): void
    {
        try {
            Log::info('Procesando webhook en queue', [
                'attempt' => $this->attempts(),
                'data' => $this->webhookData
            ]);

            $resultado = $paymentService->procesarWebhook($this->webhookData);

            if (!$resultado['success']) {
                Log::warning('Webhook procesado con advertencias', [
                    'message' => $resultado['message']
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Error al procesar webhook en queue', [
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            // Si no es el último intento, volver a lanzar la excepción para reintentar
            if ($this->attempts() < $this->tries) {
                throw $e;
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Job de webhook falló después de todos los intentos', [
            'error' => $exception->getMessage(),
            'data' => $this->webhookData
        ]);
    }
}
