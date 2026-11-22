# Revisión de seguridad - ERM

Versión revisada: 2.0.2 · Fecha: 22/11/2026 · Alcance: todo el código del plugin (`setup.php`, `hook.php`, `src/`,
`front/`, `templates/`), no sólo los últimos cambios.

## Método

1. **Búsqueda de patrones de riesgo**: SQL armado a mano, salida sin escapar, funciones peligrosas (`eval`, `exec`,
   `unserialize`, `include` dinámico), manejo de archivos y red, uso de variables globales de la petición.
2. **Revisión de autorización** de los 17 controladores web y de cada clase cuando se usa por la **API REST de GLPI**
   (que no pasa por los controladores): alta, lectura, listado, modificación y borrado.
3. **Revisión de reglas de negocio con impacto en seguridad**: segregación de funciones (aceptación de riesgos,
   aprobación de evaluaciones de IA), alcance por entidad y por "ver sólo propios", tareas automáticas que cambian de
   sesión.
4. **Dependencias**: `composer audit` y contenido del paquete que se instala.
5. **Verificación en una instancia real** (GLPI 12.0.2) con usuarios de distintos perfiles.

## Resultado general

| Área | Resultado |
|---|---|
| Inyección SQL | Sin hallazgos. Todas las consultas usan el constructor de consultas de GLPI (parámetros escapados); el único SQL literal es el DDL fijo de la instalación. |
| XSS | Sin hallazgos. Las plantillas Twig escapan por defecto; cada `\|raw` recibe HTML generado con escape (insignias, enlaces y desplegables de GLPI). Los correos los escapa el motor de notificaciones de GLPI. El PDF escapa cada valor. |
| CSRF | Sin hallazgos. Todas las acciones son POST con token CSRF validado por GLPI (`csrf_compliant`). Los GET sólo leen. |
| Funciones peligrosas, archivos, red | No se usan. No hay carga de archivos propia (se usan los documentos de GLPI). |
| Autorización en la interfaz web | Correcta: cada controlador verifica el permiso y la entidad antes de actuar. |
| Autorización por la API REST | **Hallazgos corregidos en 2.0.2** (ver abajo). |
| Segregación de funciones | Correcta y probada: el dueño no acepta su riesgo; quien escribe o edita una evaluación de IA no la aprueba; las evaluaciones enviadas o aprobadas y las revisiones no se modifican. |
| Dependencias | Sin dependencias en ejecución. `composer audit`: sin vulnerabilidades conocidas en las de desarrollo, que no se incluyen en el paquete. |
| Secretos | El repositorio se revisa antes de cada publicación (contraseñas, tokens, direcciones internas). |

## Hallazgos corregidos

| # | Hallazgo | Severidad | Corrección |
|---|---|---|---|
| 1 | Por la API, los listados de indicadores, mediciones, revisiones y vínculos (tablas sin entidad propia) mostraban registros de todas las entidades y de riesgos ajenos a perfiles "ver sólo propios". | Media | Filtro de visibilidad por riesgo o evento visible, aplicado por GLPI a la API y al buscador (2.0.2). |
| 2 | Por la API se podían crear, modificar o borrar vínculos de riesgos y eventos sin permiso sobre el riesgo o el evento, y cambiar a qué riesgo apuntaba un vínculo. | Media | Permisos por registro delegados al riesgo o evento; sólo el comentario de un vínculo es modificable (2.0.2). |
| 3 | Por la API se podía mover un indicador o una medición a otro riesgo, y un riesgo, evento, sistema de IA o configuración a una entidad sin acceso. | Media | El riesgo de un indicador y el indicador de una medición no cambian; mover a otra entidad exige acceso a ella (2.0.2). |
| 4 | Los reportes del personal se podían crear o cambiar de estado por la API sin pasar por su circuito. | Baja | Sólo se crean y revisan por su circuito (2.0.2). |
| 5 | La tarea de KRI automáticos ejecutaba la búsqueda con los permisos de su dueño aunque estuviera desactivado. | Baja | No se ejecuta para usuarios desactivados o eliminados (2.0.2). |
| 6 | Hallazgos de versiones anteriores, ya corregidos: acciones movidas de riesgo (1.4.1), estadísticas con eventos de otras entidades (1.5.1), alcance de las fotos trimestrales (1.8.1), KRI con búsquedas ajenas (1.9.1 y 1.9.2), evaluaciones de IA editables tras el envío y lista de editores truncable (2.0.0 y 2.0.1). | Media | Ver CHANGELOG. |

## Riesgos residuales y recomendaciones de operación

- **Permisos de GLPI**: la seguridad depende de asignar bien los perfiles por entidad. Usar los perfiles "ERM - ..."
  como base y revisar periódicamente quién tiene "Aprobar evaluaciones", "Aceptar riesgos" y "Configuración".
- **API REST**: habilitarla sólo si se usa, con clientes API restringidos por IP y tokens personales.
- **Notificaciones**: los correos incluyen el nombre de los riesgos; usar un servidor SMTP con TLS.
- **Desinstalación**: borra todos los datos del plugin; hacer un backup antes.
- **Historial**: GLPI registra los cambios, pero su historial no es inmutable a nivel de base de datos; si se
  requiere, enviar los registros a un SIEM.

Para reportar una vulnerabilidad, ver [SECURITY.md](../SECURITY.md).
