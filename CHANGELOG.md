# CHANGELOG

Todas las modificaciones importantes del proyecto se documentarán en este archivo.

## [2.0.5] — 2026-07-28
- El bloque y el panel administrativo fueron ajustados al nuevo diseño SAVIO: lanzador compacto para personal autorizado, tarjetas y botones coherentes, y mejoras de accesibilidad visual en el panel.
- Se corrigieron los estilos del Mapa de Identidad para mantener la consistencia de botones, bordes y superposición de modales en las vistas de estudiante y administración.
- La consulta de perfiles, detalles AJAX y exportación quedó restringida a la capacidad explícita `viewstudentdata`; los roles docentes ya no reciben acceso automático a información sensible.
- Se añadieron y normalizaron las cadenas bilingües de permisos.
- Se retiraron las capacidades inactivas `viewreports` y `deletestudentdata`, ya que el bloque no ofrece una operación de borrado de datos de estudiantes.

## [2.0.4] - 2026-02-26
- Se resolvió un problema de discrepancia estadística en la tarjeta de "Perfiles Completos" del Dashboard y del bloque para el profesor (donde se mostraba un número superior al informe exportado) al unificar toda contabilidad mediante la iteración de perfiles individuales (Validación estricta de 5 tests completos).
- Se redujo a la mitad la presión de carga de la base de datos producida por la consulta duplicada `get_integrated_course_stats()` al reusar inteligentemente los datos en memoria en `admin_view.php` y en las vistas del profesor.
- Se reparó el paginador de usuarios de la tabla de reportes, evitando que la navegación AJAX y las búsquedas por texto limitaran o restablecieran erróneamente el visualizador a 50 estudiantes por página.

## [2.0.3] - 2026-02-18
- Se corrigió un bug de validación cruzada que bloqueaba permanentemente el guardado automático cuando estudiantes omitían el campo "Año de Ingreso".
- Se corrigió un error de permisos en la función `get_tmms24_summary` que causaba que los profesores recibieran el mensaje "Lo sentimos, pero no tiene los permisos para hacer esto" en la tarjeta "Exploración de las Habilidades Socioemocionales".

## [2.0.2] - 2026-01-18
- Se eliminaron las comprobaciones redundantes de administrador (`is_siteadmin()`) en `lib.php`, mejorando la detección correcta de roles locales (profesores vs estudiantes) y el sistema de permisos basado en capacidades.
- Se corrigió el estilo del botón principal del bloque en `student_view.mustache` y `styles.css` para asegurar una apariencia consistente con los otros bloques.

## [2.0.1] — 2026-01-18
- Opción para mostrar/ocultar las descripciones en el bloque principal.
- Se mantiene el titulo "Mapa de Identidad" en todas las vistas del bloque.
- Se ha eliminado las referencias a la palabra "Test".

## [2.0.0] — 2026-01-08
- A partir de la versión 2.0.0 se comienza a documentar este CHANGELOG.
- Diseñado desde cero mediante una renovación completa y moderna de la UI/UX del bloque, mapa, panel de administración y vistas individuales (Todas con diseño responsivo).
- Mejora en la experiencia/flujo de usuario (Profesores y Estudiantes).
- Guardado automático de respuestas y progreso.
- Uso de logos institucionales y paleta de colores oficial.
- Soporte para múltiples idiomas (Español e Inglés).
- Consistencia con los otros bloques (chaside, learning_style, personality_test y tmms_24).
- Seguridad mejorada.
- Optimización del rendimiento.
- Corrección de errores menores.
