# 🚀 COMANDOS PARA INSTALAR Y EJECUTAR EL PROYECTO

## ⚠️ IMPORTANTE: Ejecutar en orden

Abre la terminal de Laragon (click derecho en Laragon → Terminal) y navega a la carpeta del proyecto:

```bash
cd C:\laragon\www\recaudacion
```

---

## 1️⃣ INSTALAR DEPENDENCIAS PHP (Composer)

```bash
composer install
```

⏱️ Este proceso tomará 2-5 minutos aproximadamente.

---

## 2️⃣ INSTALAR DEPENDENCIAS JAVASCRIPT (NPM)

```bash
npm install
```

⏱️ Este proceso tomará 3-7 minutos aproximadamente.

---

## 3️⃣ GENERAR APPLICATION KEY

```bash
php artisan key:generate
```

✅ Este comando actualizará automáticamente el `APP_KEY` en tu archivo `.env`

---

## 4️⃣ CREAR BASE DE DATOS

### Opción A: Desde MySQL

```bash
# Abrir MySQL (Terminal de Laragon ya tiene MySQL en PATH)
mysql -u root

# Dentro de MySQL:
CREATE DATABASE recaudacion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

### Opción B: Desde Laragon (GUI)

1. Click derecho en Laragon
2. MySQL → Database → Create database
3. Nombre: `recaudacion`

---

## 5️⃣ EJECUTAR MIGRACIONES

```bash
php artisan migrate
```

✅ Esto creará todas las tablas: vehiculos, pagos, transacciones, jobs

---

## 6️⃣ CARGAR DATOS DE PRUEBA

```bash
php artisan db:seed
```

✅ Creará 5 vehículos de prueba que puedes consultar

---

## 7️⃣ COMPILAR ASSETS (VUE + TAILWIND)

### Para desarrollo (con hot reload):

```bash
npm run dev
```

⚠️ **IMPORTANTE**: Mantén esta terminal abierta mientras desarrollas

### Para producción (cuando estés listo para deploy):

```bash
npm run build
```

---

## 8️⃣ LEVANTAR EL SERVIDOR

### Opción A: Con Laragon (Recomendado)

1. Click en "Start All" en Laragon
2. Acceder a: `http://recaudacion.test`

### Opción B: Con php artisan serve

```bash
php artisan serve
```

Acceder a: `http://localhost:8000`

---

## 9️⃣ (OPCIONAL) LEVANTAR QUEUE WORKER

Si quieres probar los webhooks en desarrollo:

```bash
php artisan queue:work
```

⚠️ Mantén esta terminal abierta

---

## ✅ VERIFICAR QUE TODO FUNCIONA

1. Abre el navegador en `http://recaudacion.test` o `http://localhost:8000`
2. Click en "Consultar Deuda"
3. Ingresa:
   - **Placa:** ABC1234
   - **Cédula:** 1234567890
4. Deberías ver los datos del vehículo y el impuesto calculado

---

## 📊 DATOS DE PRUEBA

Usa estos datos para probar el sistema:

| Placa   | Cédula     | Propietario     | Impuesto |
| ------- | ---------- | --------------- | -------- |
| ABC1234 | 1234567890 | Juan Pérez      | $225.00  |
| XYZ5678 | 0987654321 | María Rodríguez | $85.00   |
| DEF9012 | 1122334455 | Carlos Sánchez  | $560.00  |

---

## 🔧 COMANDOS ÚTILES

### Limpiar caché (si algo no funciona):

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### Ver todas las rutas:

```bash
php artisan route:list
```

### Recrear base de datos desde cero:

```bash
php artisan migrate:fresh --seed
```

### Ver logs en tiempo real:

```bash
tail -f storage/logs/laravel.log
```

---

## 🎯 SIGUIENTE PASO: CONFIGURAR PASARELA

Una vez que el sistema esté funcionando localmente, necesitas:

1. Obtener el endpoint de tu pasarela de pagos
2. Editar `.env` con las credenciales
3. Seguir la guía en `INTEGRACION_PASARELA.md`

---

## ❗ SOLUCIÓN DE PROBLEMAS

### Error: "composer not found"

```bash
# Usar la ruta completa de Laragon:
C:\laragon\bin\composer\composer.bat install
```

### Error: "npm not found"

- Reinicia Laragon
- O instala Node.js manualmente desde https://nodejs.org

### Error: "Access denied for user 'root'"

- Edita `.env` y cambia `DB_PASSWORD=` a tu password de MySQL
- Por defecto Laragon usa password vacío

### Error al migrar: "Database recaudacion doesn't exist"

- Verifica que creaste la base de datos en el paso 4

### Vite error o pantalla blanca

- Verifica que `npm run dev` esté corriendo
- Refresca el navegador con Ctrl+F5

---

## 📞 TODO LISTO CUANDO:

✅ Puedas acceder a `http://recaudacion.test`  
✅ Veas la página de inicio diseñada  
✅ Puedas consultar un vehículo de prueba  
✅ Veas el resultado con el impuesto calculado

**¡Entonces el sistema está funcionando correctamente!** 🎉

---

## 🚀 CREAR COMANDO BATCH (OPCIONAL)

Para facilitar el inicio del proyecto, puedes crear un archivo `start.bat`:

```batch
@echo off
echo Iniciando Sistema de Recaudacion...
cd C:\laragon\www\recaudacion
start cmd /k "npm run dev"
timeout /t 3
start cmd /k "php artisan serve"
echo.
echo Sistema iniciado!
echo Frontend: http://localhost:5173
echo Backend: http://localhost:8000
pause
```

Guárdalo como `start.bat` en la carpeta del proyecto y ejecútalo con doble click.
