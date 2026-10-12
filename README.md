# Mesa de ayuda

Mesa de ayuda interna con Laravel 13, Inertia 3, Vue 3, TypeScript y MySQL 8.4. Ejecutada localmente mediante Docker Compose. Interfaz en español, dos roles, Kanban y datos de ejemplo opcionales.

## Arranque en Windows

Requisito: Docker Desktop abierto, con contenedores Linux y backend WSL2. No necesitas instalar PHP, Composer, Node ni MySQL en el equipo para ejecutar la aplicación.

Desde PowerShell, en esta carpeta:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\setup.ps1 -Demo
```

El script crea `.env` si falta, construye la imagen, instala dependencias dentro de Docker, compila Vue, genera una clave solo si no existe, ejecuta migraciones y carga ejemplos. No borra datos existentes.

- Aplicación: http://localhost:8088
- Los correos locales, como la recuperación de acceso, se registran en `storage/logs/laravel.log` y no se envían.
- MySQL se accede desde la red interna de Docker; no ocupa el puerto 3306 de Windows ni requiere Laragon.

En Linux o WSL2:

```sh
sh scripts/setup.sh --demo
```

Para una instalación sin ejemplos, omite `-Demo` o `--demo` y crea el primer soporte:

```sh
docker compose exec laravel.test php artisan helpdesk:create-support
```

## Cuentas de demostración

Todas usan la contraseña **`DemoMesa2026!`**.

| Rol | Correo | Nombre |
|---|---|---|
| Soporte | soporte@demo.test | Carlos Mendoza |
| Soporte | ana@demo.test | Ana Rojas |
| Soporte | diego@demo.test | Diego Vargas |
| Usuario | usuario@demo.test | Lucía Fernández |
| Usuario | juan@demo.test | Juan Pérez |
| Usuario | maria@demo.test | María López |
| Usuario | pedro@demo.test | Pedro Salazar |
| Usuario | valeria@demo.test | Valeria Torres |

Los ejemplos incluyen 18 tickets, 8 categorías, comentarios, soluciones, historial, 5 adjuntos y notificaciones. Son exclusivamente para desarrollo local. El seeder es repetible y no duplica una demostración existente.

```sh
docker compose exec laravel.test php artisan db:seed --class=DemoSeeder
```

El seeder normal solo crea las categorías iniciales:

```sh
docker compose exec laravel.test php artisan db:seed
```

## Uso

Usuarios: crear solicitudes, ver las propias, comentar, adjuntar y consultar soluciones. Soporte: tablero global, filtros y búsqueda, asignación, prioridad, gestión de estados, resolución, reapertura, historial, cuentas y categorías.

La asignación no inicia automáticamente la atención. Para pasar a «En proceso» se exige un responsable activo; para finalizar se exige una solución. El menú de cada tarjeta permite cambiar estados sin arrastrar. Los cambios concurrentes se rechazan con un aviso para recargar.

Adjuntos privados de hasta 10 MB, máximo cinco por operación: JPG, PNG, PDF o TXT. Solo el solicitante y soporte pueden descargarlos.

## Comandos habituales

```sh
# Iniciar / detener conservando base de datos y adjuntos
docker compose up -d
docker compose down

# Estado y logs
docker compose ps
docker compose logs --tail=100 laravel.test worker

# Migraciones / notificaciones fallidas
docker compose exec laravel.test php artisan migrate
docker compose exec laravel.test php artisan queue:failed
docker compose exec laravel.test php artisan queue:retry all

# Suite rápida (SQLite en memoria, dentro de Docker)
docker compose exec laravel.test php vendor/bin/phpunit

# Suite de integración con MySQL (usa la BD testing separada)
docker compose exec laravel.test php vendor/bin/phpunit -c phpunit.mysql.xml

# Frontend: comprobar tipos y generar assets
docker compose run --rm --no-deps vite npm run typecheck
docker compose run --rm --no-deps vite npm run build

# Instalar dependencias Node después de actualizar el lockfile
docker compose run --rm --no-deps vite npm ci
```

La base de datos se conserva en el volumen `mesa-ayuda_sail-mysql`. Composer y Node utilizan volúmenes propios para evitar lecturas lentas sobre carpetas de Windows. Los archivos privados viven en `storage/app/private` dentro de la carpeta del proyecto. No uses `docker compose down -v` si quieres conservar los datos.

## Desarrollo con Vite

```sh
docker compose --profile dev up -d vite
```

Vite escucha en http://localhost:5173; la aplicación sigue en http://localhost:8088. Para volver a assets compilados, detén Vite y elimina solamente `public/hot`, luego compila.

En PowerShell:

```powershell
docker compose stop vite
Remove-Item -LiteralPath .\public\hot -ErrorAction SilentlyContinue
docker compose run --rm --no-deps vite npm run build
```

Si los puertos están ocupados, cambia `APP_PORT`, `APP_URL` o `VITE_PORT` en `.env` antes de arrancar. El puerto de Vite debe mantenerse coordinado con su configuración.

## UI Skills y Better UI

`ui-skills` está instalado como dependencia de desarrollo. Puedes usarlo en Docker:

```sh
docker compose run --rm --no-deps vite npx ui-skills start
docker compose run --rm --no-deps vite npm run ui:better
```

También están disponibles `npx ui-skills` y `npm run ui:better` si tienes Node instalado localmente. Se aplicaron las guías ui-ux-pro-max y better-ui; las decisiones visuales están en `design-system/mesa-de-ayuda/MASTER.md`.

## Respaldo local

Respalda la base de datos y `storage/app/private` juntos. Para obtener un dump sin depender de la codificación de redirección de PowerShell:

```powershell
docker compose exec mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqldump -uroot --single-transaction --no-tablespaces "$MYSQL_DATABASE" > /tmp/helpdesk-backup.sql'
docker compose cp mysql:/tmp/helpdesk-backup.sql .\helpdesk-backup.sql
Copy-Item -LiteralPath .\storage\app\private -Destination .\adjuntos-backup -Recurse
```

## Detalles técnicos y alcance

Consulta `IMPLEMENTATION.md` para reglas, arquitectura y decisiones. Es un entorno local de desarrollo; el despliegue de producción no forma parte de esta versión. Los correos de recuperación se escriben en el log local; las notificaciones de tickets son internas y las procesa el worker.
# repsol_mesaayuda
