# Guía de Instalación de Redis en Laragon

## Paso 1: Descargar Redis para Windows

1. **Descargar Redis:**

   - Ir a: https://github.com/tporadowski/redis/releases
   - Descargar: `Redis-x64-5.0.14.1.zip` (o la versión más reciente)

2. **Extraer en Laragon:**

   ```
   C:\laragon\bin\redis\
   ```

   Debería quedar así:

   ```
   C:\laragon\bin\redis\redis-x64-5.0.14.1\
       ├── redis-server.exe
       ├── redis-cli.exe
       ├── redis.windows.conf
       └── ...otros archivos
   ```

---

## Paso 2: Iniciar Redis

### Opción A: Desde Laragon (Recomendado)

1. Click derecho en **Laragon** → Menu → Quick app
2. Si no aparece Redis, continuar con Opción B

### Opción B: Ejecutar Manualmente

1. Abrir PowerShell en:

   ```
   C:\laragon\bin\redis\redis-x64-5.0.14.1\
   ```

2. Ejecutar:

   ```powershell
   .\redis-server.exe redis.windows.conf
   ```

3. Deberías ver:
   ```
   [####] Server started, Redis version 5.0.14.1
   [####] Ready to accept connections
   ```

---

## Paso 3: Verificar que Redis funcione

Abrir otra terminal y ejecutar:

```powershell
cd C:\laragon\bin\redis\redis-x64-5.0.14.1\
.\redis-cli.exe ping
```

**Respuesta esperada:** `PONG`

---

## Paso 4: Instalar extensión PHP Redis

1. Verificar versión de PHP en Laragon:

   ```powershell
   php -v
   ```

2. Descargar extensión desde:

   - https://pecl.php.net/package/redis
   - O desde: https://windows.php.net/downloads/pecl/releases/redis/

3. Copiar `php_redis.dll` a:

   ```
   C:\laragon\bin\php\php-8.2.x\ext\
   ```

4. Editar `php.ini`:

   ```
   C:\laragon\bin\php\php-8.2.x\php.ini
   ```

   Agregar al final:

   ```ini
   extension=redis
   ```

5. Reiniciar Laragon

6. Verificar:

   ```powershell
   php -m | findstr redis
   ```

   Debería mostrar: `redis`

---

## Paso 5: Configurar Laravel para usar Redis

1. Instalar paquete PHP Redis (si no está):

   ```powershell
   composer require predis/predis
   ```

2. Editar `.env`:

   ```env
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   QUEUE_CONNECTION=redis

   REDIS_HOST=127.0.0.1
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   ```

3. Limpiar cache de configuración:
   ```powershell
   php artisan config:clear
   php artisan cache:clear
   ```

---

## Paso 6: Probar Redis

Ejecutar en terminal:

```powershell
php artisan tinker
```

Luego dentro de tinker:

```php
Cache::put('test', 'Redis funciona!', 60);
Cache::get('test');
```

**Salida esperada:** `"Redis funciona!"`

---

## ⚠️ Solución de Problemas

### Error: "Connection refused"

- Asegurar que `redis-server.exe` esté corriendo
- Verificar puerto 6379 esté libre

### Error: "Class Redis not found"

- Verificar que `extension=redis` esté en `php.ini`
- Reiniciar Laragon completamente
- Ejecutar `php -m` para ver extensiones cargadas

### Redis no inicia

- Verificar que no haya otro proceso usando puerto 6379
- Ejecutar como administrador

---

## 🎯 Crear Script de Auto-inicio (Opcional)

Crear archivo `start-redis.bat`:

```batch
@echo off
cd C:\laragon\bin\redis\redis-x64-5.0.14.1\
start /B redis-server.exe redis.windows.conf
echo Redis iniciado en puerto 6379
```

Guardar en: `C:\laragon\bin\redis\start-redis.bat`

---

## ✅ Verificación Final

Ejecutar estos comandos para confirmar:

```powershell
# 1. Redis está corriendo
redis-cli ping
# Debe responder: PONG

# 2. PHP puede usar Redis
php -m | findstr redis
# Debe mostrar: redis

# 3. Laravel puede conectarse
php artisan tinker
Cache::put('laravel_test', 'OK', 10);
Cache::get('laravel_test');
# Debe mostrar: "OK"
```

---

## 🚀 Beneficios Obtenidos

- ⚡ Rate limiting súper rápido
- 🔄 Sessions en memoria (más veloces)
- 📊 Cache eficiente
- 🎯 Listo para producción

**¡Redis configurado correctamente!**
