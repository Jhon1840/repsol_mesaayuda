# Mesa de ayuda — dirección visual

Aplicación interna de soporte. Estilo funcional y sobrio con superficies claras, navegación lateral azul oscuro y acento índigo. El patrón de landing devuelto por ui-ux-pro-max no corresponde al producto; se adopta únicamente su estilo Minimalism & Swiss, recomendado para dashboards, como referencia.

- Tipografía: Instrument Sans alojada por el pipeline de Vite; fuentes del sistema como alternativa.
- Paleta y tamaños: tokens en `resources/css/interface.css`.
- Jerarquía: encabezado, resumen, filtros y contenido operativo. Una acción primaria por formulario.
- Estados identificados con texto e iconos; el color nunca es el único indicador.
- Better UI: superficies con sombras discretas, radios concéntricos, transiciones de propiedades explícitas a 150ms, `scale(.96)` en botones y soporte de movimiento reducido.
- Interacción: foco visible, etiquetas de formularios, feedback de guardado, estados vacíos y alternativa al drag & drop.
- Responsividad: sidebar móvil desplegable; tablero apilado; tablas desplazables en su propia región.

Skills aplicadas: ui-ux-pro-max y better-ui. La CLI ui-skills se instala como dependencia de desarrollo y se puede ejecutar con `npx ui-skills`.
