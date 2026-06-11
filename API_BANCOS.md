# 🏦 API de Integración Bancaria - Sistema de Recaudación

API REST para integración con entidades bancarias y sistemas externos de recaudación del impuesto vehicular.

---

## 📋 **Información General**

- **Base URL:** `http://your-domain.com/api/v1`
- **Autenticación:** Bearer Token
- **Formato:** JSON
- **Charset:** UTF-8

---

## 🔐 **Autenticación**

Todas las peticiones requieren un token de autenticación en el header:

```http
Authorization: Bearer YOUR_API_TOKEN
```

### Obtener Token

Contactar al administrador del sistema para obtener credenciales de acceso.

---

## 📍 **Endpoints Disponibles**

### 1️⃣ **Consultar Deuda**

Consulta el monto adeudado de un vehículo para un año fiscal específico.

**Endpoint:** `POST /api/v1/consulta-deuda`

#### Request

```json
{
  "placa": "XBB4552",
  "anio_fiscal": 2026
}
```

#### Parámetros

| Campo         | Tipo    | Requerido | Descripción                                  |
| ------------- | ------- | --------- | -------------------------------------------- |
| `placa`       | string  | Sí        | Placa del vehículo (máx. 10 caracteres)      |
| `anio_fiscal` | integer | No        | Año fiscal a consultar (default: año actual) |

#### Response Exitoso (200)

```json
{
  "success": true,
  "data": {
    "placa": "XBB4552",
    "propietario": "N/A",
    "cedula": "N/A",
    "anio_fiscal": 2026,
    "monto_adeudado": 60.51,
    "estado": "pendiente",
    "vehiculo": {
      "marca": "CHEVROLET",
      "modelo": "AVEO FAMILY",
      "anio": 2015,
      "tipo": "SEDAN",
      "descripcion": "CHEVROLET AVEO FAMILY 2015"
    },
    "rubros": [
      {
        "nombre": "MATRICULACION",
        "valor": 50.0
      },
      {
        "nombre": "RODAJE",
        "valor": 10.51
      }
    ],
    "valor_matricula": 605.13
  }
}
```

#### Response Error - Placa No Existe (500)

```json
{
  "success": false,
  "message": "No se pudo consultar la información del vehículo",
  "error": "No existe el vehículo con identificación DEF9012"
}
```

#### Response Error - Ya Pagado (404)

```json
{
  "success": false,
  "message": "El vehículo no tiene deuda pendiente para este año",
  "pago_existente": {
    "fecha_pago": "2026-01-20 11:28:25",
    "referencia": "BANCO-TEST-001",
    "monto": 60.51
  }
}
```

---

### 2️⃣ **Registrar Pago**

Registra un pago realizado a través de la entidad bancaria.

**Endpoint:** `POST /api/v1/registrar-pago`

#### Request

```json
{
  "placa": "XBB4552",
  "anio_fiscal": 2026,
  "monto": 60.51,
  "referencia_externa": "BANCO-TEST-001",
  "fecha_pago": "2026-01-20 11:30:00",
  "entidad_recaudadora": "Banco Pichincha"
}
```

#### Parámetros

| Campo                 | Tipo     | Requerido | Descripción                                            |
| --------------------- | -------- | --------- | ------------------------------------------------------ |
| `placa`               | string   | Sí        | Placa del vehículo (máx. 10 caracteres)                |
| `anio_fiscal`         | integer  | Sí        | Año fiscal del pago (2020-2030)                        |
| `monto`               | decimal  | Sí        | Monto del pago (mín. 0.01)                             |
| `referencia_externa`  | string   | Sí        | Referencia/comprobante del banco (máx. 100 caracteres) |
| `fecha_pago`          | datetime | Sí        | Fecha y hora del pago (formato: Y-m-d H:i:s)           |
| `entidad_recaudadora` | string   | Sí        | Nombre de la entidad bancaria (máx. 100 caracteres)    |

#### Response Exitoso (201)

```json
{
  "success": true,
  "message": "Pago registrado exitosamente",
  "data": {
    "pago_id": 34,
    "comprobante": "PAG-000034",
    "fecha_registro": "2026-01-20 11:28:25",
    "monto_registrado": 60.51,
    "placa": "XBB4552",
    "anio_fiscal": 2026
  }
}
```

#### Response Error - Validación (400)

```json
{
  "success": false,
  "message": "Datos inválidos",
  "errors": {
    "placa": ["El campo placa es obligatorio."],
    "monto": ["El campo monto debe ser mayor a 0.01."]
  }
}
```

#### Response Error - Ya Pagado (400)

```json
{
  "success": false,
  "message": "El vehículo ya tiene el impuesto pagado para este año",
  "pago_existente": {
    "id": 33,
    "fecha_pago": "2026-01-20 10:15:00",
    "referencia": "BANCO-PREV-001",
    "monto": 60.51
  }
}
```

#### Response Error - Monto Incorrecto (400)

```json
{
  "success": false,
  "message": "El monto enviado no coincide con el impuesto calculado",
  "monto_enviado": 50.0,
  "monto_esperado": 60.51,
  "diferencia": 10.51
}
```

> **Nota:** El sistema tiene una tolerancia de **$1.00** en la diferencia del monto.

---

## 🔄 **Flujo de Integración Recomendado**

### Paso 1: Consultar Deuda

Antes de procesar un pago, consultar la deuda pendiente:

```bash
POST /api/v1/consulta-deuda
{
    "placa": "XBB4552",
    "anio_fiscal": 2026
}
```

### Paso 2: Procesar Pago en Sistema Bancario

El usuario realiza el pago en el sistema del banco.

### Paso 3: Registrar Pago

Una vez confirmado el pago, registrarlo en el sistema:

```bash
POST /api/v1/registrar-pago
{
    "placa": "XBB4552",
    "anio_fiscal": 2026,
    "monto": 60.51,
    "referencia_externa": "BCO-TXN-123456",
    "fecha_pago": "2026-01-20 15:30:00",
    "entidad_recaudadora": "Banco Pichincha"
}
```

### Paso 4: Guardar Comprobante

Almacenar el `pago_id` y `comprobante` retornados para futuras consultas.

---

## ⚠️ **Validaciones Importantes**

### 1. **Validación de Monto**

El sistema consulta automáticamente al SRI para validar que el monto sea correcto:

- ✅ Tolerancia: **±$1.00**
- ❌ Si la diferencia es mayor, se rechaza el pago

### 2. **Prevención de Duplicados**

El sistema verifica que no exista un pago previo para:

- Misma `placa`
- Mismo `anio_fiscal`
- Estado `pagado`

### 3. **Verificación con SRI**

Cada consulta y registro valida la información contra el servicio del SRI:

- ✅ Verifica que la placa exista
- ✅ Obtiene el monto correcto de impuestos
- ✅ Consulta rubros actualizados

---

## 📊 **Códigos de Respuesta HTTP**

| Código | Descripción                                 |
| ------ | ------------------------------------------- |
| `200`  | Consulta exitosa                            |
| `201`  | Pago registrado exitosamente                |
| `400`  | Errores de validación o datos inválidos     |
| `401`  | Token de autenticación inválido             |
| `404`  | Recurso no encontrado o sin deuda pendiente |
| `500`  | Error interno del servidor                  |

---

## 🧪 **Ejemplos de Uso**

### cURL - Consultar Deuda

```bash
curl -X POST "http://localhost:8000/api/v1/consulta-deuda" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "placa": "XBB4552",
    "anio_fiscal": 2026
  }'
```

### cURL - Registrar Pago

```bash
curl -X POST "http://localhost:8000/api/v1/registrar-pago" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "placa": "XBB4552",
    "anio_fiscal": 2026,
    "monto": 60.51,
    "referencia_externa": "BANCO-TEST-001",
    "fecha_pago": "2026-01-20 11:30:00",
    "entidad_recaudadora": "Banco de Prueba"
  }'
```

### JavaScript (Fetch)

```javascript
// Consultar deuda
const consultarDeuda = async (placa) => {
  const response = await fetch("http://localhost:8000/api/v1/consulta-deuda", {
    method: "POST",
    headers: {
      Authorization: "Bearer YOUR_TOKEN",
      "Content-Type": "application/json",
    },
    body: JSON.stringify({
      placa: placa,
      anio_fiscal: 2026,
    }),
  });

  return await response.json();
};

// Registrar pago
const registrarPago = async (datosPago) => {
  const response = await fetch("http://localhost:8000/api/v1/registrar-pago", {
    method: "POST",
    headers: {
      Authorization: "Bearer YOUR_TOKEN",
      "Content-Type": "application/json",
    },
    body: JSON.stringify(datosPago),
  });

  return await response.json();
};
```

---

## 🔧 **Solución de Problemas**

### Error: "Log [sri] is not defined"

Este es un error interno que no afecta la funcionalidad. El sistema usa un logger de emergencia automáticamente.

### Error: "Monto no coincide"

Verificar que el monto enviado sea exactamente el retornado en la consulta de deuda. Hay tolerancia de $1.00.

### Error: "Vehículo no encontrado"

La placa no existe en el sistema del SRI. Verificar:

1. ✅ Placa escrita correctamente
2. ✅ Vehículo matriculado en Ecuador
3. ✅ Sin espacios adicionales

---

## 📞 **Soporte**

Para dudas o problemas con la integración:

- **Email:** soporte@recaudacion.gob.ec
- **Documentación:** Ver este archivo
- **Logs:** Revisar `storage/logs/laravel.log` para debugging

---

## 📝 **Changelog**

### v1.0 - 2026-01-20

- ✅ Endpoint `/consulta-deuda` implementado
- ✅ Endpoint `/registrar-pago` implementado
- ✅ Validación automática con SRI
- ✅ Prevención de pagos duplicados
- ✅ Tolerancia de $1.00 en montos

---

**Última actualización:** 2026-01-20  
**Versión API:** 1.0
