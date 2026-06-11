#!/bin/sh
set -e

# Asegurar que las carpetas de almacenamiento y caché tengan los permisos correctos
# Esto se ejecuta cada vez que el contenedor inicia, haciendo el cambio permanente
# incluso si se usan volúmenes/bind-mounts en AlmaLinux.

echo "Configurando permisos de storage y bootstrap/cache..."
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache

chmod -R 775 /var/www/html/storage
chmod -R 775 /var/www/html/bootstrap/cache

# Ejecutar el comando principal (usualmente php-fpm)
exec "$@"
