# Caso completo de punta a punta - ERM

Versión del plugin: 2.3.x · GLPI 12

Un ejemplo de un riesgo desde que alguien lo ve hasta que llega al Directorio. Los nombres son de una empresa de
ejemplo.

## 1. Alguien lo reporta

Un agente del contact center ve que el voicebot de cobranzas informó un monto de deuda equivocado. Desde la interfaz
simplificada usa **Reportar un riesgo o incidente**: "El voicebot le dijo a un cliente que debía el doble".

## 2. El gestor de riesgos lo analiza

El gestor lo ve en **Reportes del personal**. Como ya ocurrió, lo convierte en **evento de pérdida** (casi pérdida:
el cliente reclamó y se corrigió) y, como no existe un riesgo que lo cubra, también en **riesgo**.

## 3. El dueño lo completa con el asistente

La gerente de Cobranzas, dueña del riesgo, abre la **Biblioteca de riesgos**, elige "Respuestas incorrectas de un
asistente de IA" y lo adapta en el asistente:

| Pregunta | Respuesta |
|---|---|
| ¿Qué podría pasar? | Respuestas incorrectas del voicebot sobre montos de deuda |
| ¿Cada cuánto? (sin controles) | Probable: seguramente pase este año |
| ¿Qué tan grave? | Mayor: sanción del regulador de defensa del consumidor |
| ¿Qué nos protege hoy? | Montos tomados del core; auditoría del 5% de las llamadas |
| ¿Funcionan? | Creo que sí, pero nunca se probaron |
| Con controles | Poco probable, igual de grave |
| ¿Qué vamos a hacer? | Reducirlo con acciones |
| Primera acción | Limitar las respuestas a datos del core, responsable el líder de IA, 31/03 |

El riesgo queda con inherente 16 (Alto), residual 8 (Medio) y estado "En tratamiento". La ficha no muestra
sugerencias de calidad.

## 4. Se evalúa contra el apetito

La categoría "IA > Fiabilidad" tiene apetito Bajo y tolerancia Medio: el riesgo está **fuera del apetito pero dentro
de la tolerancia**. El tablero lo muestra en la tarjeta de apetito. El dueño fija el **riesgo objetivo** en Bajo para
junio.

## 5. Se sigue

- El responsable de la acción la actualiza en **Mi espacio ERM** y adjunta la evidencia al cerrarla. El dueño recibe
  el aviso por correo.
- Se define un **KRI**: "% de llamadas auditadas con monto incorrecto", alerta 1%, crítico 3%, medido mensualmente.
- Cuando todas las acciones se cierran, el dueño registra una **revisión** con la nueva evaluación. Si el residual no
  llega al objetivo, el tablero marca el tratamiento como **ineficaz**.

## 6. Se informa

Al cierre del trimestre el gestor toma la **foto** y descarga el **informe de Directorio**, que muestra el riesgo
nuevo, su evolución y la comparación con el trimestre anterior. El Comité lo analiza; si decidiera convivir con el
riesgo sin más acciones, lo **acepta** formalmente con justificación y vigencia de 12 meses (nunca la dueña).

## 7. Evidencia para la auditoría

Auditoría interna encuentra en la herramienta el reporte original, el evento, el riesgo con su historial de cambios,
las acciones con evidencia, la revisión, el KRI y el informe trimestral, más la metodología en PDF vigente a la fecha.
