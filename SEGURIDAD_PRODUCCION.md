# Guía de Seguridad para Producción

## Sistema de Recaudación - Prefectura Cotopaxi

Esta guía cubre todas las medidas de seguridad necesarias para proteger el sistema de pagos en producción.

---

## 🔒 1. Seguridad de Base de Datos

### ✅ Ya Implementado en Laravel

Laravel **YA protege automáticamente** contra inyección SQL mediante:

- **Eloquent ORM:** Todas las consultas usan prepared statements
- **Query Builder:** Sanitiza automáticamente los inputs
- **Validación de entrada:** Validator previene datos maliciosos

```php
// ✅ SEGURO - Laravel usa prepared statements
Vehiculo::where('placa', $request->placa)->first();

// ❌ NUNCA HACER - Consulta directa vulnerable
DB::select("SELECT * FROM vehiculos WHERE placa = '{$request->placa}'");
```

### 🔧 Configuración Adicional

**En `.env` de producción:**

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=recaudacion
DB_USERNAME=recaudacion_user  # Usuario específico, NO root
DB_PASSWORD=contraseña_fuerte_random_64_caracteres
```

---

## 🛡️ 2. HTTPS Obligatorio

### Certificado SSL/TLS

**Opción 1: Let's Encrypt (GRATIS)**

```bash
# Instalar Certbot
sudo apt-get install certbot python3-certbot-nginx

# Obtener certificado
sudo certbot --nginx -d tudominio.gob.ec
```

**Opción 2: Certificado Comercial**

- DigiCert, GlobalSign, etc.

### Forzar HTTPS en Laravel

Ya está implementado en `AppServiceProvider`, pero actualiza:

```php
// Force HTTPS en producción
if ($this->app->environment('production')) {
    URL::forceScheme('https');
}
```

---

## 🚫 3. Rate Limiting (Limitar Peticiones)

### Para Web (Frontend)

**Implementar en `routes/web.php`:**

```php
Route::middleware(['throttle:60,1'])->group(function () {
    Route::post('/consultar', [ConsultaController::class, 'buscar']);
    Route::post('/pago/iniciar', [PagoController::class, 'iniciar']);
});
```

### Para API (Bancos)

**Ya implementado, pero mejorar:**

```php
Route::middleware(['throttle:100,1'])->group(function () {
    Route::post('/consulta-deuda', [BancaController::class, 'consultarDeuda']);
    Route::post('/registrar-pago', [BancaController::class, 'registrarPago']);
});
```

---

## 🔐 4. Protección de Archivos Sensibles

### Configurar Nginx/Apache

**Nginx (`/etc/nginx/sites-available/tudominio`):**

```nginx
server {
    listen 443 ssl http2;
    server_name tudominio.gob.ec;

    root /var/www/recaudacion/public;
    index index.php;

    # Bloquear acceso a archivos sensibles
    location ~ /\. {
        deny all;
    }

    location ~ ^/(storage|vendor|database|bootstrap|config|routes|tests) {
        deny all;
    }

    # Permitir solo PHP en public/
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Headers de seguridad
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
}
```

---

## 🔥 5. Firewall del Servidor

### UFW (Ubuntu Firewall)

```bash
# Permitir solo puertos necesarios
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp     # SSH
sudo ufw allow 80/tcp     # HTTP
sudo ufw allow 443/tcp    # HTTPS
sudo ufw enable

# Ver estado
sudo ufw status
```

### Fail2Ban (Anti Brute Force)

```bash
# Instalar
sudo apt-get install fail2ban

# Configurar
sudo nano /etc/fail2ban/jail.local
```

```ini
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 3

[nginx-http-auth]
enabled = true

[nginx-noscript]
enabled = true
```

---

## 🌐 6. Lista Blanca de IPs (API Bancos)

### Ya Implementado

El middleware `ValidateApiToken` ya valida IPs:

```php
// Al generar token
php artisan api:generar-token "Banco Pichincha" --ip=200.123.45.67
```

### Lista Blanca Manual (Nginx)

```nginx
# Solo permitir IPs específicas a /api
location /api {
    allow 200.123.45.67;  # IP Banco Pichincha
    allow 201.234.56.78;  # IP Banco Guayaquil
    deny all;

    try_files $uri $uri/ /index.php?$query_string;
}
```

---

## 🔑 7. Encriptación de Datos Sensibles

### Variables de Entorno

```bash
# Generar APP_KEY fuerte
php artisan key:generate

# En producción
APP_ENV=production
APP_DEBUG=false  # ¡CRÍTICO!
```

### Encriptar Datos en Base de Datos

Para datos sensibles como cédulas:

```php
use Illuminate\Support\Facades\Crypt;

// Guardar encriptado
$vehiculo->cedula_propietario = Crypt::encryptString($cedula);

// Leer desencriptado
$cedula = Crypt::decryptString($vehiculo->cedula_propietario);
```

---

## 🛑 8. Protección CSRF y XSS

### CSRF (Ya Implementado)

Laravel protege automáticamente con tokens CSRF en todos los formularios.

### XSS (Cross-Site Scripting)

**Blade automáticamente escapa:**

```blade
{{ $usuario->nombre }}  <!-- ✅ Seguro -->
{!! $html !!}           <!-- ❌ Peligroso, solo usar con confianza -->
```

**Vue.js también escapa:**

```vue
{{ vehiculo.placa }}
<!-- ✅ Seguro -->
<div v-html="html"></div>
<!-- ❌ Peligroso -->
```

---

## 📝 9. Logging y Monitoreo

### Logs de Seguridad

```php
// Registrar intentos sospechosos
Log::channel('security')->warning('Intento de acceso no autorizado', [
    'ip' => $request->ip(),
    'url' => $request->fullUrl(),
    'user_agent' => $request->userAgent(),
]);
```

### Monitoreo en Tiempo Real

**Opciones:**

- **Laravel Telescope** (desarrollo)
- **Sentry** (producción)
- **New Relic** (enterprise)

---

## 🔒 10. Checklist de Seguridad Pre-Producción

### Base de Datos

- [ ] Usuario de BD específico (NO root)
- [ ] Contraseña fuerte (mínimo 32 caracteres)
- [ ] Acceso solo desde localhost
- [ ] Backups automáticos diarios

### Servidor

- [ ] Firewall configurado (solo 22, 80, 443)
- [ ] Fail2Ban instalado
- [ ] SSH solo con clave pública
- [ ] Actualizaciones automáticas de seguridad

### Aplicación

- [ ] `APP_DEBUG=false`
- [ ] `APP_ENV=production`
- [ ] HTTPS obligatorio
- [ ] Rate limiting activado
- [ ] Validación en todos los endpoints
- [ ] Logs de seguridad configurados

### Archivos

- [ ] `.env` fuera de web root
- [ ] Permisos correctos (755 carpetas, 644 archivos)
- [ ] `storage/` y `bootstrap/cache/` escribibles
- [ ] `.git/` bloqueado en Nginx/Apache

---

## 🚀 Comandos de Despliegue Seguro

```bash
# 1. Clonar repositorio
git clone https://github.com/tu-repo.git /var/www/recaudacion

# 2. Permisos correctos
sudo chown -R www-data:www-data /var/www/recaudacion
sudo chmod -R 755 /var/www/recaudacion
sudo chmod -R 775 /var/www/recaudacion/storage
sudo chmod -R 775 /var/www/recaudacion/bootstrap/cache

# 3. Instalar dependencias
cd /var/www/recaudacion
composer install --optimize-autoloader --no-dev
npm ci --production
npm run build

# 4. Configurar
cp .env.example .env
php artisan key:generate
nano .env  # Configurar producción

# 5. Base de datos
php artisan migrate --force

# 6. Optimizar
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 7. Reiniciar servicios
sudo systemctl restart nginx
sudo systemctl restart php8.2-fpm
```

---

## 📞 Soporte

Para consultas de seguridad:

- Email: seguridad@recaudacion.gob.ec
- Teléfono: (02) 123-4567

---

## ⚠️ CRÍTICO: Nunca Hacer

1. ❌ Usar `APP_DEBUG=true` en producción
2. ❌ Commitear `.env` al repositorio
3. ❌ Usar contraseñas débiles
4. ❌ Permitir root en base de datos
5. ❌ Desactivar CSRF protection
6. ❌ Permitir `register_globals` en PHP
7. ❌ Usar versiones antiguas de PHP/Laravel
8. ❌ Exponer información de errores al usuario
