# Sistema de Recaudación - Impuesto al Rodaje

Sistema web para consultar y pagar impuestos vehiculares desarrollado con Laravel 11 + Vue 3 + Inertia.js

## 🚀 Instalación

### 1. Instalar Dependencias PHP

```bash
composer install
```

### 2. Instalar Dependencias JavaScript

```bash
npm install
```

### 3. Configurar Entorno

```bash
# Copiar archivo de entorno
copy .env.example .env

# Generar key de aplicación
php artisan key:generate
```

### 4. Configurar Base de Datos

Edita el archivo `.env` y configura tu base de datos:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=recaudacion
DB_USERNAME=root
DB_PASSWORD=
```

Crea la base de datos en MySQL:

```sql
CREATE DATABASE recaudacion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 5. Ejecutar Migraciones

```bash
php artisan migrate
```

### 6. Seed de Datos de Prueba

```bash
php artisan db:seed
```

Esto creará vehículos de prueba que puedes consultar:

- **Placa:** ABC1234 | **Cédula:** 1234567890
- **Placa:** XYZ5678 | **Cédula:** 0987654321
- **Placa:** DEF9012 | **Cédula:** 1122334455

### 7. Compilar Assets Frontend

**Para desarrollo (con hot reload):**

```bash
npm run dev
```

**Para producción:**

```bash
npm run build
```

### 8. Levantar Servidor

**Opción 1 - Con Laragon:**

- Click en "Start All" en Laragon
- Accede a: `http://recaudacion.test`

**Opción 2 - Con php artisan:**

```bash
php artisan serve
```

- Accede a: `http://localhost:8000`

## ⚙️ Configuración de Pasarela de Pagos

Edita el archivo `.env` con los datos de tu pasarela:

```env
PAYMENT_GATEWAY_URL=https://api.tupasarela.com/generar-link
PAYMENT_GATEWAY_API_KEY=tu_api_key_aqui
PAYMENT_GATEWAY_SECRET_KEY=tu_secret_key_aqui
PAYMENT_GATEWAY_TIMEOUT=30
```

## 📁 Estructura del Proyecto

```
recaudacion/
├── app/
│   ├── Http/Controllers/
│   │   ├── VehiculoController.php    # Consultas de vehículos
│   │   ├── PagoController.php        # Proceso de pago
│   │   └── WebhookController.php     # Recepción de webhooks
│   ├── Models/
│   │   ├── Vehiculo.php              # Modelo de vehículos
│   │   ├── Pago.php                  # Modelo de pagos
│   │   └── Transaccion.php           # Log de transacciones
│   ├── Services/
│   │   └── PaymentGatewayService.php # Integración con pasarela
│   └── Jobs/
│       └── ProcessPaymentWebhook.php # Procesamiento asíncrono
├── database/
│   ├── migrations/                   # Migraciones de BD
│   └── seeders/                      # Datos de prueba
├── resources/
│   ├── js/
│   │   ├── Pages/                    # Componentes Vue (páginas)
│   │   │   ├── Home.vue
│   │   │   ├── Consulta/
│   │   │   └── Pago/
│   │   └── Layouts/                  # Layouts Vue
│   ├── css/
│   │   └── app.css                   # Estilos Tailwind
│   └── views/
│       └── app.blade.php             # Layout principal
└── routes/
    └── web.php                       # Rutas de la aplicación
```

## 🔄 Flujo de Funcionamiento

1. **Usuario consulta** su vehículo con placa y cédula
2. **Sistema calcula** el impuesto según el avalúo
3. **Usuario procede** al pago
4. **Sistema genera** link de pago enviando datos al endpoint de la pasarela
5. **Pasarela devuelve** link de pago
6. **Usuario es redirigido** a la pasarela para pagar
7. **Después del pago**, usuario vuelve al sistema
8. **Webhook notifica** el resultado del pago (aprobado/rechazado)
9. **Sistema actualiza** el estado del pago en la base de datos

## 🧪 Modo de Prueba (Sin Pasarela Real)

El proyecto incluye un modo de prueba. En `PaymentGatewayService.php`, usa el método `generarLinkPrueba()` en lugar de `generarLinkPago()` para simular pagos sin conexión real.

## 📊 Base de Datos

### Tablas principales:

- **vehiculos** - Datos de vehículos registrados
- **pagos** - Registro de pagos (pendientes, pagados, fallidos)
- **transacciones** - Log de todas las operaciones con la pasarela
- **jobs** - Cola de trabajos asíncronos

## 🔐 Webhook Endpoint

El endpoint del webhook está en:

```
POST /webhook/pago
```

Este endpoint:

- ✅ No requiere CSRF token (excluido automáticamente)
- ✅ Verifica firma de la pasarela(si la proporciona)
- ✅ Procesa en cola para responder rápido (200 OK)
- ✅ Registra todo en la tabla de transacciones

## 🎨 Personalización

### Cambiar colores del tema:

Edita `tailwind.config.js`:

```javascript
colors: {
    government: {
        blue: '#003e78',  // Azul gubernamental
        gold: '#d4af37',  // Dorado
    }
}
```

### Modificar cálculo de impuesto:

Edita `app/Models/Vehiculo.php` → método `calcularImpuesto()`

## 📝 Comandos Útiles

```bash
# Limpiar caché
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Ver rutas
php artisan route:list

# Ejecutar queue worker (para webhooks)
php artisan queue:work

# Ejecutar migraciones frescas
php artisan migrate:fresh --seed
```

## 🛠️ Desarrollo

El proyecto usa:

- **Laravel 11** - Backend framework
- **Vue 3** - Frontend framework
- **Inertia.js** - Bridge entre Laravel y Vue
- **Tailwind CSS** - Estilos
- **Vite** - Build tool

## 📞 Soporte

Para soporte técnico o dudas sobre el sistema, contactar al equipo de desarrollo.

## 📄 Licencia

Este proyecto es para uso interno de la institución pública.
