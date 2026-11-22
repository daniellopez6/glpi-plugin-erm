# Guías rápidas por rol - ERM

Versión del plugin: 2.3.x · GLPI 12

Una página por rol: qué hacer, dónde y cada cuánto. Lo mismo está dentro de la herramienta en
**GRC Corporativo > Ayuda y glosario** (o **Guía de mi rol** en "Mi espacio ERM"). El detalle de cada pantalla está en
el [manual de usuario](manual-usuario.md) y un ejemplo completo en el [caso de punta a punta](caso-completo.md).

## Todo el personal

- **Reportar** algo que pasó, que casi pasa o que podría pasar: menú de la interfaz simplificada, **Reportar un riesgo
  o incidente**. Alcanza con contar qué pasó, dónde y cuándo.
- Seguir el estado en **Mis reportes**. El equipo de riesgos revisa todos los reportes.
- Reportar de buena fe nunca se sanciona.

## Dueño de riesgo

| Qué | Dónde | Cuándo |
|---|---|---|
| Registrar los riesgos del área | Tablero > **Registrar un riesgo paso a paso** o **Biblioteca de riesgos** | Al empezar y cuando aparece un riesgo nuevo |
| Atender las sugerencias de calidad | Ficha del riesgo, recuadro amarillo | Cada vez que se edita el riesgo |
| Definir el plan de tratamiento | Ficha del riesgo > pestaña **Plan de tratamiento** | Si la decisión es mitigar o transferir |
| Registrar eventos de pérdida | **Eventos de pérdida** > Agregar, vinculado al riesgo | Cuando algo pasa (aunque no haya pérdida) |
| Revisar el riesgo | Ficha > pestaña **Revisiones** > Registrar revisión | Al menos una vez al año |

Todo lo pendiente está en **Mi espacio ERM**. Un riesgo propio no se puede aceptar: lo acepta el Comité.

## Responsable de acciones o indicadores

- **Mi espacio ERM** muestra las acciones asignadas y los indicadores a medir.
- Actualizar el estado y el avance de cada acción; cargar la evidencia como documento.
- Registrar el valor de cada indicador en su fecha.
- Avisar al dueño del riesgo si una acción no se va a cumplir a tiempo (y cambiar la fecha con su acuerdo).

## Gestor de riesgos (CRO)

- **Semanal**: tablero (fuera del apetito, sin evaluar, con sugerencias de calidad, acciones vencidas) y reportes del
  personal (convertirlos en evento o riesgo, o descartarlos con motivo).
- **Trimestral**: tomar la foto (**Informes > Tomar foto**) y descargar el informe de Directorio.
- **Anual**: revisar con el Comité el apetito y la tolerancia de cada categoría, los criterios de impacto económico y
  la metodología (**Informes > Descargar la metodología**).
- La tarjeta **Primeros pasos** del tablero indica qué falta para que el proceso funcione completo.

## Comité de riesgos o Directorio

- Aprobar el apetito y la tolerancia por categoría.
- **Aceptar** formalmente los riesgos que no se tratarán: ficha del riesgo, tratamiento "Aceptar", justificación y
  vigencia. La aceptación vence a los 12 meses y debe renovarse.
- Aprobar las evaluaciones de impacto de los sistemas de IA (nunca las propias).
- Analizar en cada reunión el informe trimestral y el mapa integral.

## Auditoría interna

- Acceso de lectura a todo el registro, las revisiones, el historial de cambios y los eventos.
- Verificar: aceptaciones vigentes y no tomadas por el dueño, acciones vencidas, riesgos sin revisar en el año,
  evidencias de las acciones cerradas, aprobaciones de IA por terceros.
- Evidencia descargable: metodología en PDF (con la fecha de generación) e informes trimestrales.

## Responsable de IA

- Registrar cada sistema de IA (propósito, datos, proveedor, clase según el AI Act) en **Sistemas de IA**.
- Completar la evaluación de impacto y enviarla a aprobación; quien aprueba no puede haberla escrito ni editado.
- Si el impacto es significativo, **Crear riesgo** desde la evaluación.
- Repetir la evaluación una vez al año o cuando el sistema cambia.
