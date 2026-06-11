# ✅ Ngrok Configurado - Listo para Webhooks

## 🎉 Estado: COMPLETADO

### URLs Configuradas

- **Ngrok Público:** `https://ungradating-unusably-pearly.ngrok-free.dev`
- **Webhook URL:** `https://ungradating-unusably-pearly.ngrok-free.dev/webhook/pago`
- **Aplicación:** `https://ungradating-unusably-pearly.ngrok-free.dev`

---

## 🧪 Pasos para Probar el Webhook

### 1. Generar un Nuevo Pago

Ahora que tenemos ngrok configurado, genera un nuevo pago:

1. Abre: `https://ungradating-unusably-pearly.ngrok-free.dev`

   ⚠️ **Nota**: La primera vez ngrok te mostrará una pantalla de advertencia. Click en "Visit Site" para continuar.

2. Click "Consultar Deuda"

3. Ingresa placa: `XYZ5678` (usa un diferente al anterior)

4. Click "Consultar Deuda"

5. Click "Proceder al Pago"

6. **El sistema enviará a Pago Medios el webhook URL:**
   ```
   https://ungradating-unusably-pearly.ngrok-free.dev/webhook/pago
   ```

---

### 2. Completar el Pago en Pago Medios

Serás redirigido a una URL como:

```
https://payurl.link/XXXXXXXXX
```

**Completa el pago** con los datos de prueba de Pago Medios.

---

### 3. Pago Medios Enviará el Webhook

Una vez completado el pago, Pago Medios automáticamente enviará un POST a:

```
https://ungradating-unusably-pearly.ngrok-free.dev/webhook/pago
```

---

### 4. Ver los Datos del Webhook

El webhook mostrará en **pantalla HTML**:

```
Webhook Recibido de Pago Medios

Headers:
{
  "content-type": "application/json",
  ...
}

Body (Parsed):
{
  "status": "...",
  "custom_value": "PAG-X",
  ...
}

Raw Body:
...
```

Y también se guardará en el log:

```
storage/logs/laravel.log
```

---

## 📊 Ver Logs en Tiempo Real

En otra terminal, ejecuta:

```powershell
Get-Content storage\logs\laravel.log -Wait -Tail 50
```

Verás algo como:

```
[INFO] === WEBHOOK RECIBIDO DE PAGO MEDIOS ===
{
  "headers": {...},
  "body": {...},
  "raw_body": "...",
  ...
}
```

---

## 🔍 Monitorear Ngrok

Puedes ver el tráfico de ngrok en:

```
http://127.0.0.1:4040
```

Abre esa URL en tu navegador para ver una interfaz web con:

- Todas las peticiones HTTP
- Headers
- Body
- Responses
- Timeline

---

## ⚠️ Importante

### Ngrok Session

La URL de ngrok **cambia cada vez que reinicias** ngrok, a menos que tengas una cuenta de pago.

Si cierras ngrok y lo vuelves a abrir:

1. Obtendrás una nueva URL
2. Deberás actualizar `.env` nuevamente
3. Ejecutar `php artisan config:clear`

### Mantener Ngrok Activo

La ventana de PowerShell con ngrok debe permanecer abierta todo el tiempo que estés probando.

---

## 🎯 Próximos Pasos Después del Webhook

Una vez que captures los datos del webhook:

1. **Analiza el formato** que envía Pago Medios

2. **Actualiza** `ProcessPaymentWebhook.php` para mapear correctamente:

   - Estado del pago (approved/rejected/pending)
   - Referencia de transacción
   - Datos adicionales

3. **Actualiza** `PaymentGatewayService::procesarWebhook()` con el mapeo correcto

4. **Prueba nuevamente** para verificar que el pago se marca como pagado en la BD

---

## 📝 Datos Capturados Anteriormente

**Último pago generado (sin ngrok):**

- Pago ID: 5
- Link: `https://payurl.link/brW48_9040002519544`
- Token: `cha_iArFifWElSUVUOx5HqvV2904`
- Monto: $225.00

---

## ✅ Checklist

- [x] Ngrok instalado
- [x] Ngrok iniciado
- [x] URL pública obtenida: `https://ungradating-unusably-pearly.ngrok-free.dev`
- [x] `.env` actualizado con URL de ngrok
- [x] Caché limpiada
- [x] Webhook controller configurado para mostrar datos
- [ ] **Generar nuevo pago con ngrok activo**
- [ ] **Completar pago en Pago Medios**
- [ ] **Capturar datos del webhook**
- [ ] **Mapear datos en el código**

---

## 🚀 ¡Listo para Probar!

**Todo está configurado.** Ahora:

1. Abre `https://ungradating-unusably-pearly.ngrok-free.dev`
2. Genera un pago
3. Complétalo en Pago Medios
4. ¡Ve los datos del webhook!
