# Verificación de entrega

## Comprobaciones técnicas

- Suite con MySQL 8.4 dentro de Docker: **17 pruebas, 134 assertions, todas correctas**.
- Compilación de Vue dentro del contenedor Node: correcta.
- TypeScript (`vue-tsc --noEmit`): sin errores.
- Laravel Pint: 47 archivos de aplicación comprobados; cachés generadas excluidas.
- npm audit y Composer audit: sin vulnerabilidades reportadas al entregar.
- Aplicación y MySQL saludables; worker funcionando, sin trabajos fallidos.
- Dependencias PHP y Node instaladas desde sus lockfiles dentro de Docker.

## Verificación en navegador

- Login de usuario y soporte, menú móvil y listado restringido al solicitante.
- Flujo real sobre HD-000019: creación como usuario, comentario, asignación e inicio como soporte, modal de resolución, cierre, historial y consulta de la solución como solicitante.
- Revisión visual en 1440 × 1000 y 390 × 844.
- En móvil, ancho de documento igual al viewport y navegación cerrada con `inert` para evitar foco fuera de pantalla.
- Drag & drop comprobado de pendiente a en proceso y de vuelta. La primera automatización desplazaba el documento entre presionar y arrastrar; mantener origen y destino visibles permitió comprobar los eventos nativos y la persistencia de ambas operaciones.
- Reinicio de MySQL comprobado: se conservaron los datos y los servicios volvieron a estado saludable.
- Evidencias locales en `output/playwright` (no versionadas).
- Los 409 observados durante recompilaciones corresponden a recarga por cambio de versión de assets de Inertia. El reinicio del contenedor también produjo un error transitorio de red durante la verificación; los flujos posteriores finalizaron correctamente.

## Better UI: ajustes aplicados

| Severidad | Ubicación | Antes | Después | Motivo |
|---|---|---|---|---|
| MEDIUM | resources/css/interface.css | Textos secundarios demasiado pequeños | Tipografía ampliada | Facilitar lectura del tablero |
| MEDIUM | resources/css/accessibility.css | Controles compactos en móvil | Áreas de interacción ampliadas | Facilitar pulsación y foco |
| MEDIUM | resources/js/layouts/AppLayout.vue | Sidebar fuera de pantalla con enlaces enfocables | `inert`, foco al abrir/cerrar y contenido de fondo inerte | Evitar navegación de teclado invisible |
| LOW | resources/css/accessibility.css | Radios iguales en contenedor y tarjeta | Radios exteriores diferenciados | Mejorar superficies anidadas |

No actionable UI-polish findings pendientes en las superficies revisadas. **Approve** para la revisión visual indicada. No verificado: reproducción de animaciones al 10% en DevTools y auditoría completa con lector de pantalla.
