# Revisión better-ui

Alcance: tablero, filtros, navegación compartida, detalle del ticket y superficies comunes. Se conservan la paleta, los componentes y la densidad del proyecto.

**Claridad de interacción y estados**

| Severity | Location | Before | After | Why |
| --- | --- | --- | --- | --- |
| MEDIUM | resources/js/pages/Tickets/Index.vue:348 | Consultas frecuentes requieren varios filtros | Accesos a asignadas a mí, sin asignar y urgentes | Reduce pasos y mantiene una selección visible |
| MEDIUM | resources/js/pages/Tickets/Index.vue:480 | Solo se mostraba la cantidad de filtros | Filtros con nombres y eliminación individual; respuesta de carga y vacío | El usuario entiende qué limita los resultados |
| MEDIUM | resources/js/pages/Tickets/Index.vue:503 | Columnas apiladas en móvil | Selector de estado con cantidades y una columna visible | Facilita llegar a solicitudes en proceso o finalizadas |
| MEDIUM | resources/js/pages/Tickets/Show.vue:416 | Se podía intentar iniciar atención sin responsable | Acción deshabilitada con explicación junto al control | Previene un error recuperable y explica el siguiente paso |
| MEDIUM | resources/js/pages/Tickets/Show.vue:25 | Las pestañas solo respondían al clic | Flechas, Home, End y foco coordinado | Navegación coherente con su semántica de pestañas |

**Superficies, alineación e iconos**

| Severity | Location | Before | After | Why |
| --- | --- | --- | --- | --- |
| MEDIUM | resources/css/polish.css:3 | Bordes y elevaciones poco diferenciados | Sombras por capas, radios concéntricos y hover/foco visibles | Distingue tarjetas y contenedores sin añadir ruido |
| LOW | resources/css/polish.css:74 | Trazo uniforme junto a distintos pesos de texto | Iconos de 1.5 px y de 2 px junto a acciones semibold | Equilibra el peso óptico |
| LOW | resources/css/polish.css:273 | Navegación activa poco destacada | Fondo, indicador lateral y aria-current | Hace reconocible la ubicación actual |

Verificado en Edge con Playwright: filtros urgentes, sin asignar y asignadas a mí; retirada individual y limpieza; búsqueda sin resultados; regreso del navegador; pestañas por teclado; comentario vacío y válido sin enviarlo; bloqueo de inicio sin asignación; navegación móvil y cierre con Escape. Capturas a 1440 × 1000 y 390 × 844, sin desbordamiento horizontal en tablero y detalle. Movimiento reducido devuelve transiciones de 0 s. Compilación y TypeScript correctos; 17 pruebas de Laravel, 134 aserciones, correctas.

Not verified: reproducción al 10 % en el panel Animations, estado de carga bajo red lenta, lectores de pantalla, otros navegadores y revisión visual completa de administración y recuperación de contraseña. La aprobación se limita a las superficies e interacciones comprobadas.

Capturas: `output/playwright/ui-polish-desktop.png`, `ui-polish-mobile.png`, `ui-polish-detail.png`, `ui-polish-detail-mobile.png`, `ui-polish-unassigned.png`.

Preparación del repositorio: se instaló Laravel Boost como dependencia de desarrollo y se ejecutó su instalador de guías, conforme a AGENTS.md.

Approve
