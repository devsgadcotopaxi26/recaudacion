# 🔧 Guía de Integración con tu Endpoint de Pasarela

## 📍 Ubicación del Código de Integración

El archivo principal que debes editar es:

```
app/Services/PaymentGatewayService.php
```

## 🎯 Método Principal: `generarLinkPago()`

Este método es el que envía datos a tu endpoint y recibe el link de pago.

### Paso 1: Configurar el Endpoint en .env

```env
# Tu endpoint que devuelve el link
PAYMENT_GATEWAY_URL=https://api.tupasarela.com/tu-endpoint

# Credenciales si las requiere
PAYMENT_GATEWAY_API_KEY=tu_api_key
PAYMENT_GATEWAY_SECRET_KEY=tu_secret_key
```

### Paso 2: Ajustar el Formato de Envío

Abre `app/Services/PaymentGatewayService.php` y modifica el array `$requestData` según lo que requiera tu pasarela:

```php
// EJEMPLO ACTUAL (línea 35 aprox):
$requestData = [
    'monto' => $pago->monto_total,
    'referencia_interna' => 'PAG-' . $pago->id,
    'descripcion' => "Impuesto al Rodaje {$pago->anio_fiscal} - Placa: {$vehiculo->placa}",
    'callback_url' => config('payment.callback_url'),
    'webhook_url' => config('payment.webhook_url'),
    'cliente' => [
        'nombre' => $vehiculo->propietario,
        'cedula' => $vehiculo->cedula_propietario,
    ],
];

// CAMBIA A LO QUE TU PASARELA NECESITA
// Ejemplo alternativo:
$requestData = [
    'amount' => $pago->monto_total,
    'reference' => 'PAG-' . $pago->id,
    'customer_name' => $vehiculo->propietario,
    'customer_id' => $vehiculo->cedula_propietario,
    'return_url' => config('payment.callback_url'),
    // ... lo que necesite tu API
];
```

### Paso 3: Ajustar Headers HTTP

```php
// EJEMPLO ACTUAL (línea 50 aprox):
$response = Http::timeout($this->timeout)
    ->withHeaders([
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Accept' => 'application/json',
    ])
    ->post($this->apiUrl, $requestData);

// AJUSTA SEGÚN TU PASARELA
// Ejemplo con API Key en header:
$response = Http::timeout($this->timeout)
    ->withHeaders([
        'X-API-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
    ])
    ->post($this->apiUrl, $requestData);

// Ejemplo con autenticación básica:
$response = Http::timeout($this->timeout)
    ->withBasicAuth($this->apiKey, $this->secretKey)
    ->post($this->apiUrl, $requestData);
```

### Paso 4: Extraer el Link de la Respuesta

```php
// EJEMPLO ACTUAL (línea 55 aprox):
if ($response->successful() && isset($responseData['link_pago'])) {
    $pago->update([
        'link_pago' => $responseData['link_pago'],
        'referencia_pago' => $responseData['referencia'] ?? null,
    ]);

    return [
        'success' => true,
        'link_pago' => $responseData['link_pago'],
        'referencia' => $responseData['referencia'] ?? null,
    ];
}

// AJUSTA según el nombre de campos que devuelva tu API
// Ejemplo si tu API devuelve { "payment_url": "https://..." }
if ($response->successful() && isset($responseData['payment_url'])) {
    $pago->update([
        'link_pago' => $responseData['payment_url'],
        'referencia_pago' => $responseData['transaction_id'] ?? null,
    ]);

    return [
        'success' => true,
        'link_pago' => $responseData['payment_url'],
        'referencia' => $responseData['transaction_id'] ?? null,
    ];
}
```

## 🔔 Configurar el Webhook (Opcional)

Si tu pasarela envía notificaciones POST cuando el pago es confirmado:

### 1. El endpoint del webhook ya está creado:

```
POST https://tu-dominio.com/webhook/pago
```

### 2. Ajustar el procesamiento en `PaymentGatewayService.php`

Método: `procesarWebhook()` (línea 100 aprox)

```php
// EJEMPLO ACTUAL:
$referenciaInterna = $webhookData['referencia_interna'] ?? null;
$estado = $webhookData['estado'] ?? null;
$referenciaPasarela = $webhookData['referencia_pasarela'] ?? null;

// AJUSTA según lo que envíe tu pasarela:
$referenciaInterna = $webhookData['reference'] ?? null;
$estado = $webhookData['status'] ?? null;
$referenciaPasarela = $webhookData['transaction_id'] ?? null;
```

### 3. Mapear Estados

```php
// EJEMPLO ACTUAL:
if ($estado === 'aprobado' || $estado === 'pagado' || $estado === 'success') {
    $pago->marcarComoPagado($referenciaPasarela);
}

// AJUSTA según los estados de tu pasarela:
if ($estado === 'APPROVED' || $estado === 'COMPLETED') {
    $pago->marcarComoPagado($referenciaPasarela);
} elseif ($estado === 'REJECTED' || $estado === 'FAILED') {
    $pago->marcarComoFallido();
}
```

### 4. Verificar Firma (Seguridad)

Si tu pasarela envía una firma para validar:

```php
// Método: verificarFirma() (línea 90 aprox)
public function verificarFirma(array $data, string $firma): bool
{
    // Ejemplo genérico con HMAC SHA256:
    $firmaCalculada = hash_hmac('sha256', json_encode($data), $this->secretKey);
    return hash_equals($firmaCalculada, $firma);

    // O si tu pasarela usa otro método, ajustar aquí
}
```

## 📋 Ejemplo Completo de Adaptación

Supongamos que tu pasarela requiere:

**REQUEST:**

```json
POST https://api.pasarela.com/create-payment
Headers: X-API-Key: abc123

{
  "total": 100.50,
  "ref": "PAG-123",
  "customer": {
    "name": "Juan Pérez",
    "dni": "1234567890"
  },
  "urls": {
    "success": "https://miapp.com/callback",
    "webhook": "https://miapp.com/webhook"
  }
}
```

**RESPONSE:**

```json
{
  "status": "ok",
  "payment_link": "https://pasarela.com/pay/xyz789",
  "tx_id": "TXN-456"
}
```

### Modificación en PaymentGatewayService.php:

```php
// 1. Cambiar $requestData (línea 35):
$requestData = [
    'total' => $pago->monto_total,
    'ref' => 'PAG-' . $pago->id,
    'customer' => [
        'name' => $vehiculo->propietario,
        'dni' => $vehiculo->cedula_propietario,
    ],
    'urls' => [
        'success' => config('payment.callback_url'),
        'webhook' => config('payment.webhook_url'),
    ]
];

// 2. Cambiar headers (línea 50):
$response = Http::timeout($this->timeout)
    ->withHeaders([
        'X-API-Key' => $this->apiKey,
        'Content-Type' => 'application/json',
    ])
    ->post($this->apiUrl, $requestData);

// 3. Cambiar extracción de respuesta (línea 55):
if ($response->successful() && isset($responseData['payment_link'])) {
    $pago->update([
        'link_pago' => $responseData['payment_link'],
        'referencia_pago' => $responseData['tx_id'] ?? null,
    ]);

    return [
        'success' => true,
        'link_pago' => $responseData['payment_link'],
        'referencia' => $responseData['tx_id'] ?? null,
    ];
}
```

## ✅ Checklist de Integración

- [ ] Obtener documentación de la API de tu pasarela
- [ ] Configurar `PAYMENT_GATEWAY_URL` en `.env`
- [ ] Configurar credenciales en `.env`
- [ ] Ajustar formato de `$requestData`
- [ ] Ajustar headers HTTP
- [ ] Ajustar extracción del link de respuesta
- [ ] Probar con datos de prueba
- [ ] (Opcional) Configurar webhook
- [ ] (Opcional) Implementar verificación de firma

## 🧪 Modo de Prueba

Para probar sin conectar a la pasarela real, cambia temporalmente en `PagoController.php` (línea 35):

```php
// Cambiar de:
$resultado = $this->paymentService->generarLinkPago($pago);

// A:
$resultado = $this->paymentService->generarLinkPrueba($pago);
```

Esto simulará el pago sin hacer llamadas HTTP reales.

## 📞 ¿Necesitas Ayuda?

Una vez que tengas el endpoint y la documentación de tu pasarela, puedo ayudarte a:

- Adaptar el código exacto
- Agregar manejo de errores específicos
- Implementar validaciones adicionales

Solo compárteme:

1. El URL del endpoint
2. El formato JSON que espera recibir
3. El formato JSON que devuelve
4. Tipo de autenticación (API Key, Bearer Token, etc.)
