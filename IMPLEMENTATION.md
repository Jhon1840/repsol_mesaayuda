# Mesa de ayuda: implementación

## Arquitectura

Laravel 13 / PHP 8.5, Inertia 3, Vue 3 con TypeScript, Tailwind 4 y MySQL 8.4. Autenticación con Fortify y sesiones, sin registro público. Se integraron directamente las dependencias del stack y los formularios de autenticación para adaptar la interfaz al proyecto, en lugar de copiar el starter kit completo.

Docker Compose utiliza una imagen PHP local y servicios independientes para MySQL, worker y Vite. La imagen propia evita que el primer arranque dependa de un Dockerfile ubicado en `vendor`. Laravel Sail permanece instalado y el servicio `laravel.test` es compatible con sus comandos PHP/Artisan. Las operaciones de Node se ejecutan en el servicio `vite`. En desarrollo local, los correos se registran en el log de Laravel.

## Reglas

- Roles `user` y `support`. Usuarios normales solo ven y comentan sus tickets. Soporte administra cuentas y categorías.
- Las solicitudes se crean pendientes, sin responsable, con prioridad media.
- Asignación, prioridad y estado se modifican por separado.
- Estados: pendiente → en proceso; en proceso → pendiente/finalizado; finalizado → en proceso.
- Iniciar o reabrir requiere responsable activo. Finalizar exige solución escrita.
- Cada actualización usa bloqueo de fila y versión del ticket. Una versión antigua se rechaza sin sobrescribir datos.
- Cierre, reapertura, asignación, prioridad, comentarios y archivos producen eventos históricos.
- Reabrir limpia la resolución vigente y conserva los cierres anteriores en el historial. Las fechas de primera asignación y primer inicio permanecen.
- Antes de desactivar o quitar el rol a un técnico se deben reasignar sus tickets abiertos. El último soporte activo no se puede desactivar ni degradar.
- Adjuntos privados: JPG, PNG, PDF y TXT; 5 por operación, 10 MB cada uno. Descarga autorizada y nombres de almacenamiento generados.
- Avisos internos en cola después del commit, excluyendo al actor. Nuevas solicitudes notifican al soporte activo; el resto al solicitante y responsables involucrados.
- UTC en almacenamiento y America/La_Paz en interfaz y límites de filtros diarios.

## Interfaz

Login, recuperación de acceso, solicitudes propias, creación, detalle, tablero de soporte, usuarios, categorías y notificaciones. Sidebar responsive, estados vacíos, feedback de operaciones, búsqueda/filtros en URL, tabla paginada y Kanban con confirmación del servidor y modal de resolución. Existe alternativa mediante menú al arrastre.

Los indicadores son globales para el alcance del usuario y no cambian con los filtros del listado. Los finalizados del día cuentan tickets distintos con evento de cierre ese día. El promedio usa tiempo desde creación hasta primer cierre para primeros cierres del día; sin eventos muestra «Sin datos».

El tablero carga 12 tarjetas por columna inicialmente y permite ampliar por lotes hasta 120. Si hay más resultados ofrece continuar en la lista paginada, conservando los filtros, para mantener acotada la carga del tablero.

## Datos de ejemplo

`CategorySeeder` es el seeder base. `DemoSeeder` es opcional, solo local/testing y no duplica ni reinicia las cuentas si ya existe la demostración. Crea 8 cuentas, 18 tickets (6 pendientes, 5 en proceso, 7 finalizados), conversaciones, historial, 5 archivos TXT privados y notificaciones. No se ejecuta automáticamente con el seeder normal.

## Herramientas UI

Se aplicaron ui-ux-pro-max y better-ui. `ui-skills` está instalado como dependencia de desarrollo; `npm run ui:better` imprime la guía utilizada. Los overrides de dependencias de esa CLI actualizan sus dependencias transitivas reportadas por npm audit; no incorporan Astro ni React al frontend de la aplicación.

## Límites de la versión

Entorno Docker de desarrollo local. No incorpora despliegue de producción, SLA, chat en tiempo real ni integraciones externas. Los correos locales se registran en el log y no se envían. No se borran tickets ni se editan/eliminan comentarios. La reapertura está reservada a soporte.
