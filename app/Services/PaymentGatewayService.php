<?php

namespace App\Services;

use App\Models\Pago;
use App\Models\Transaccion;
use App\Models\Vehiculo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class PaymentGatewayService
{
    private string $apiUrl;
    private string $apiKey;
    private string $secretKey;
    private int $timeout;

    public function __construct()
    {
        $this->apiUrl = config('payment.api_url');
        $this->apiKey = config('payment.api_key');
        $this->secretKey = config('payment.secret_key');
        $this->timeout = config('payment.timeout', 30);
    }

    /**
     * Generar link de pago en la pasarela (Pago Medios)
     */
    public function generarLinkPago(Pago $pago): array
    {
        try {
            // Calcular valores con y sin IVA (IVA 12% en Ecuador)
            // El monto total ya incluye IVA, necesitamos desglosarlo
            $montoSinIVA = round($pago->monto_total / 1.15, 2);
            $montoIVA = round($pago->monto_total - $montoSinIVA, 2);

            // Obtener datos de facturación (si existen)
            $datosFact = $pago->datos_facturacion ?? [];

            // Preparar datos según formato de Pago Medios
            // amount = amount_without_tax + amount_with_tax + tax_value
            $requestData = [
                'integration' => true,
                'third_party_id' => $pago->id, // ID numérico del pago
                'third' => [
                    'document' => $datosFact['documento'] ?? '0999999999001',
                    'document_type' => isset($datosFact['tipo_documento']) && $datosFact['tipo_documento'] === 'ruc' ? '04' : '05',
                    'name' => $datosFact['nombre'] ?? 'Propietario de ' . $pago->placa,
                    'email' => $datosFact['email'] ?? 'noreply@recaudacion.gob.ec',
                    'phones' => $datosFact['telefono'] ?? '0000000000',
                    'address' => $datosFact['direccion'] ?? 'Ecuador',
                    'type' => 'Individual'
                ],
                'generate_invoice' => 0,
                'description' => "Impuesto al Rodaje - Placa: {$pago->placa}",
                'amount' => floatval($pago->monto_total),
                'amount_with_tax' => floatval($montoSinIVA),
                'amount_without_tax' => 0.00,
                'tax_value' => floatval($montoIVA), // IVA 12%
                'settings' => [],
                'notify_url' => config('payment.webhook_url'),
                'custom_value' => 'PAG-' . $pago->id,
                'has_cards' => 1,
                'has_de_una' => 1,
                'has_paypal' => 0,
                'has_safetypay' => false,
                'platform_settings' => []
            ];

            Log::info('Enviando petición a Pago Medios', [
                'pago_id' => $pago->id,
                'placa' => $pago->placa,
                'monto' => $pago->monto_total,
                'url' => $this->apiUrl
            ]);

            // Hacer la petición HTTP usando cURL (como en el ejemplo)
            $ch = curl_init();

            curl_setopt_array($ch, [
                CURLOPT_URL => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                ],
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            // Log detallado de la respuesta
            Log::info('Respuesta de Pago Medios', [
                'http_code' => $httpCode,
                'response' => $response,
                'curl_error' => $curlError
            ]);

            if ($curlError) {
                throw new Exception('Error cURL: ' . $curlError);
            }

            $responseData = json_decode($response, true);

            // Log del JSON decodificado
            Log::info('Respuesta JSON decodificada', [
                'data' => $responseData
            ]);

            // Registrar transacción
            Transaccion::registrar(
                $pago->id,
                'api_call',
                $requestData,
                $responseData,
                $responseData['success'] ?? false ? 'exitoso' : 'fallido',
                $responseData['success'] ?? false ? 'Link generado' : 'Error'
            );

            // Verificar respuesta exitosa
            if (isset($responseData['success']) && $responseData['success'] === true && isset($responseData['data']['url'])) {

                $linkPago = $responseData['data']['url'];
                $token = $responseData['data']['token'] ?? null;

                // Actualizar el pago con el link y token generados
                $pago->update([
                    'link_pago' => $linkPago,
                    'referencia_pago' => $token,
                ]);

                Log::info('Link de pago generado exitosamente', [
                    'pago_id' => $pago->id,
                    'url' => $linkPago,
                    'token' => $token
                ]);

                return [
                    'success' => true,
                    'link_pago' => $linkPago,
                    'referencia' => $token,
                ];
            } else {
                $errorMsg = $responseData['message'] ?? $responseData['error'] ?? json_encode($responseData) ?? 'Error desconocido al generar link';
                throw new Exception($errorMsg);
            }

        } catch (Exception $e) {
            Log::error('Error al generar link de pago en Pago Medios', [
                'pago_id' => $pago->id,
                'error' => $e->getMessage()
            ]);

            // Registrar error
            Transaccion::registrar(
                $pago->id,
                'api_call',
                $requestData ?? null,
                null,
                'error',
                $e->getMessage()
            );

            return [
                'success' => false,
                'message' => 'Error al conectar con la pasarela de pagos: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar firma del webhook (implementar según tu pasarela)
     */
    public function verificarFirma(array $data, string $firma): bool
    {
        // Implementar según la pasarela específica
        // Ejemplo genérico:
        $firmaCalculada = hash_hmac('sha256', json_encode($data), $this->secretKey);

        return hash_equals($firmaCalculada, $firma);
    }

    /**
     * Procesar webhook de confirmación de pago de Pago Medios
     */
    public function procesarWebhook(array $webhookData): array
    {
        try {
            Log::info('Procesando webhook de Pago Medios', ['data' => $webhookData]);

            // Extraer datos del webhook de Pago Medios
            $status = $webhookData['status'] ?? null;
            $customValue = $webhookData['customValue'] ?? null;
            $reference = $webhookData['reference'] ?? null;
            $authorizationCode = $webhookData['authorizationCode'] ?? null;
            $message = $webhookData['message'] ?? '';

            if (!$customValue) {
                throw new Exception('customValue no proporcionado en el webhook');
            }

            // Extraer el ID del pago del customValue (formato: PAG-X)
            $pagoId = str_replace('PAG-', '', $customValue);
            $pago = Pago::find($pagoId);

            if (!$pago) {
                throw new Exception("Pago no encontrado: {$pagoId}");
            }

            // Mapear el estado de Pago Medios
            // 1 = Aprobado, 0 = Pendiente, 2 = Rechazado, 3 = Reversado
            $estadoPago = 'pendiente';
            $estadoTransaccion = 'procesando';

            switch ($status) {
                case '1':
                case 1:
                    $estadoPago = 'pagado';
                    $estadoTransaccion = 'exitoso';
                    break;

                case '0':
                case 0:
                    $estadoPago = 'pendiente';
                    $estadoTransaccion = 'pendiente';
                    break;

                case '2':
                case 2:
                    $estadoPago = 'fallido';
                    $estadoTransaccion = 'fallido';
                    break;

                case '3':
                case 3:
                    $estadoPago = 'reversado';
                    $estadoTransaccion = 'reversado';
                    break;

                default:
                    $estadoPago = 'pendiente';
                    $estadoTransaccion = 'desconocido';
            }

            // Registrar webhook en transacciones
            Transaccion::registrar(
                $pago->id,
                'webhook',
                $webhookData,
                null,
                $estadoTransaccion,
                $message
            );

            // Actualizar el pago según el estado
            if ($status == '1' || $status == 1) {
                // Pago aprobado
                $pago->marcarComoPagado($authorizationCode);

                Log::info('Pago confirmado vía webhook', [
                    'pago_id' => $pago->id,
                    'referencia' => $reference,
                    'autorizacion' => $authorizationCode
                ]);
            } elseif ($status == '2' || $status == 2) {
                // Pago rechazado
                $pago->marcarComoFallido();

                Log::warning('Pago rechazado vía webhook', [
                    'pago_id' => $pago->id,
                    'mensaje' => $message
                ]);
            } elseif ($status == '3' || $status == 3) {
                // Pago reversado
                $pago->update([
                    'estado' => 'reversado',
                    'fecha_pago' => now(),
                ]);

                Log::warning('Pago reversado vía webhook', [
                    'pago_id' => $pago->id,
                    'mensaje' => $message
                ]);
            }

            return [
                'success' => true,
                'message' => 'Webhook procesado correctamente',
                'estado' => $estadoPago,
                'pago_id' => $pago->id
            ];

        } catch (Exception $e) {
            Log::error('Error al procesar webhook de Pago Medios', [
                'error' => $e->getMessage(),
                'data' => $webhookData
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generar link de pago de prueba (para desarrollo)
     */
    public function generarLinkPrueba(Pago $pago): array
    {
        // Para desarrollo: generar link simulado
        $linkPrueba = route('pago.confirmacion', ['pago' => $pago->id]) . '?test=1';

        $pago->update([
            'link_pago' => $linkPrueba,
            'referencia_pago' => 'TEST-' . time(),
        ]);

        return [
            'success' => true,
            'link_pago' => $linkPrueba,
            'referencia' => 'TEST-' . time(),
        ];
    }
}
