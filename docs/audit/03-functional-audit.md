# Auditoría funcional

## Criterio de severidad

- **P0 / crítica:** pérdida de dinero/derechos, acceso no autorizado grave o bloqueo de lanzamiento.
- **P1 / alta:** flujo principal incorrecto, corrupción potencial, exposición sensible o degradación severa al crecer.
- **P2 / media:** defecto relevante con workaround o deuda que eleva el costo/riesgo.
- **P3 / baja:** mejora de robustez, claridad o experiencia sin impacto inmediato alto.

## Top 20 de problemas

| ID | Sev. | Problema | Impacto | Evidencia |
|---|---:|---|---|---|
| F-01 | P0 | El miembro puede pagar con `manual` o `simulada`, que aprueban inmediatamente | Membresías/derechos gratuitos y fraude trivial | `RegistroDePasarelas.php:23-30,50-59`; `CompraController.php:97-111`; `PasarelaManual.php:20-22`; `PasarelaSimulada.php:22-24` |
| F-02 | P0 | La asistencia no confirma/consume el hold | Créditos retenidos para siempre y servicio prestado sin asiento de consumo | `CrearReserva.php:96-110`; `MarcarAsistencia.php:19-31` |
| F-03 | P0 | El reembolso no llama al proveedor | El sistema declara reembolso sin devolver dinero | `ReembolsarPago.php:31-76` |
| F-04 | P1 | La llamada PSP ocurre bajo transacción/lock y el portal no manda idempotency key | Locks largos, estado ambiguo y cobros duplicados ante timeout/fallo de commit | `CobrarOrden.php:35-43,56-88`; `CompraController.php:111` |
| F-05 | P1 | Intentos asíncronos múltiples no se orquestan ni concilian | Dos PSP pueden cobrar; intentos pendientes quedan huérfanos | `CobrarOrden.php:45-49,56-74`; procesadores webhook de Pagos |
| F-06 | P1 | Cancelar sesión solo cambia su estado | Reservas siguen confirmadas, holds activos, cupos/avisos incoherentes | `CancelarSesion.php:10-20` |
| F-07 | P1 | El acceso por sucursal está implementado pero no aplicado | Roles de sucursal no funcionan correctamente y acciones quedan tenant-wide | `ControlDeAcceso.php:30-42`; `SesionController.php:25-27,52-54`; `AsistenciaController.php:20-25` |
| F-08 | P1 | No hay conflictos de recurso/instructor ni coherencia recurso-sucursal/capacidad | Doble asignación física y horarios imposibles | `CrearSesionUnica.php:24-42`; `SesionController.php:56-71,93-99`; `AsignarPersonalSesion.php:18-28` |
| F-09 | P1 | Instructores pueden ver roster/marcar asistencia fuera de sus sesiones | Exposición de miembros y modificación no autorizada | `CatalogoDePermisos.php:60-65`; `AsistenciaController.php:20-25`; rutas de roster |
| F-10 | P1 | Idempotencia devuelve cualquier registro que comparta clave y la comprobación corre fuera del lock | Respuesta de operación ajena, carreras y errores 500 por unique | `CrearReserva.php:40-45`; `CobrarOrden.php:35-39`; migraciones de reservas/pagos |
| F-11 | P1 | La lista de espera se detiene si el primero no tiene saldo | Miembros elegibles posteriores nunca se promueven; cupo vacío | `PromoverListaEspera.php:39-60` |
| F-12 | P1 | La promoción usa el derecho antiguo y costo fijo, sin revalidar vigencia/restricciones | Reserva confirmada con derecho inválido o precio de crédito incorrecto | `CrearReserva.php:73-92`; `PromoverListaEspera.php:20,50-67` |
| F-13 | P1 | Renovación procesa todo con `get()` y renueva sin verificar acuerdo activo | Memoria/N+1; acuerdos cancelados pueden seguir recibiendo crédito | `GenerarCiclosEntitlement.php:25-41`; `GenerarCicloEntitlement.php:29-60` |
| F-14 | P1 | Concesión/top-up/consumo directos con permiso amplio y sin motivo/actor auditable | Abuso interno y beneficios gratis no reconciliables | `AcuerdoController.php:19-37`; `CatalogoDePermisos.php:53-59`; rutas de créditos |
| F-15 | P1 | No existe audit trail de operaciones sensibles | Imposible atribuir cambios, investigar fraude o demostrar controles | No hay modelos/migraciones/listeners de auditoría; solo correlation/log contextual |
| F-16 | P1 | Listados principales cargan todo | Tiempo/memoria crecen linealmente y UI queda inutilizable | `SesionController.php:42-45`; `CompraController.php:51-55`; `DerechoController.php:25-31`; múltiples `index()->get()` |
| F-17 | P1 | Cálculo de saldos produce N+1 y repite la suma | 3 consultas por derecho además del listado | `DerechoController.php:34-36`; `LibroMayor.php:25-39` |
| F-18 | P1 | La orden no valida moneda uniforme ni limita items/cantidad | Suma importes de monedas distintas y permite fulfillment masivo sin tope | `CrearOrden.php:27-48`; `CrearOrdenRequest.php:21-26` |
| F-19 | P2 | Portal familiar solo opera sobre la persona vinculada al usuario | Tutor no puede comprar/reservar/consultar por dependiente | `Portal/Support/MiembroActual.php`; endpoints `Portal` |
| F-20 | P2 | Móvil es health-only y las SPA carecen de selector de tenant | Promesa móvil incumplida; usuarios multi-tenant no pueden operar | `apps/mobile/lib/main.dart`; `ResolveTenantContext.php:53-55`; ambos `src/lib/api.ts` |

## Análisis de flujos críticos

### 1. Alta y selección de tenant

**Comportamiento correcto:** el header no se confía y se valida contra la pertenencia activa; si hay una sola pertenencia se selecciona. El scope de modelos se instala antes del route model binding.

**Defectos:**

- las SPA reciben `pertenencias` en `/me`, pero no muestran selector ni persisten/envían `X-Tenant-ID`;
- el estado del tenant no participa en la decisión; suspenderlo en datos no corta acceso;
- al finalizar un job se limpia `TenantContext`, pero no el team del `PermissionRegistrar` (`TenancyServiceProvider.php:50-51`), riesgo de contexto residual para jobs sin tenant;
- no hay alta/invitación/desactivación autoservicio de usuarios ni administración de dispositivos/tokens.

**Corrección:** store común `activeTenant`, interceptor que envíe header, pantalla de selección previa al dominio, middleware que exija `tenant.status=active` y pruebas HTTP/end-to-end con dos tenants. Limpiar ambos contextos en `finally` de request/job.

### 2. Configuración organizacional y catálogo

El CRUD de creación y lectura está presente, con relaciones y restricciones tenant en Eloquent. Faltan edición, archivo/publicación y transacciones en agregados. Ejemplos: crear organización+marca, persona+perfiles y hogar+tutela puede dejar datos parciales si falla la segunda escritura.

Los productos no tienen un estado público/archivado. `CompraController::productos()` lista todos (`:28-40`), por lo que un borrador, producto de cero pesos o producto operativo interno se publica automáticamente.

**Corrección:** estados `draft/active/archived`, ventanas de venta, límites por canal y transacciones en agregados. Toda relación debe validar pertenencia al mismo tenant, no solo depender de que cada lookup esté scoped.

### 3. Agenda y recursos

La conversión de zona local a UTC es consistente y las sesiones materializadas tienen unique por plantilla+inicio. No existe, sin embargo, una invariante de calendario:

- sesiones del mismo recurso pueden solaparse;
- un instructor puede asignarse simultáneamente a múltiples sesiones;
- un recurso de otra sucursal del mismo tenant puede usarse;
- la capacidad de sesión puede exceder la del recurso o la oferta;
- se puede asignar como instructor cualquier persona, sin perfil/staff de esa sucursal.

**Corrección:** servicio `AvailabilityPolicy` usado por sesión única, generación y reasignación; consulta de intervalo `[inicia_en, termina_en)` bajo lock lógico/advisory; validación recurso activo/sucursal/capacidad y staff activo. Los conflictos deben responder 409 con detalles accionables.

### 4. Reserva, crédito y asistencia

La reserva bloquea la sesión y cuenta confirmadas dentro de la transacción, una protección correcta contra sobrecupo. Para un derecho limitado crea una retención, pero el único consumo automático ocurre al cancelar tarde. Marcar `presente` o `ausente` solo hace `updateOrCreate` de asistencia. Por tanto:

- presente: el servicio fue prestado, pero el saldo no disminuye y el hold queda activo;
- ausente: no existe política explícita de no-show; también queda activo;
- sesión terminada sin asistencia: no hay cierre/reconciliador y el hold queda activo;
- sesión cancelada: ni reservas ni holds se tocan.

**Corrección:** máquina de estados y comando idempotente de cierre. Política configurable por oferta/tenant: `presente → confirm hold`, `ausente → perder/confirmar o liberar`, `cancelada por negocio → liberar`, `sin marcar tras tolerancia → excepción/cola de revisión`. Cada transición debe tener referencia única a la reserva.

### 5. Lista de espera

El FIFO y el lock son adecuados, pero el algoritmo solo examina el primer elemento. Si ese miembro ya no tiene saldo, retorna y bloquea a todos. Además, conserva `derecho_id` pero no un hold; al promover solo intenta saldo y no vuelve a ejecutar `ResolverDerecho` con fecha, restricciones y estado actual.

**Corrección:** recorrer un lote acotado; por cada candidato revalidar elegibilidad, seleccionar derecho vigente y registrar resultado (`promoted`, `skipped_no_entitlement`, `expired`). Mantener justicia con una política documentada; notificar y dar ventana de aceptación si el producto lo requiere.

### 6. Orden y checkout

Congelar precio unitario es correcto. La creación carece de `idempotency_key`, límites y validación de moneda. El portal crea una nueva orden en cada click. La API administrativa permite cantidades arbitrarias y `AprobarPago` crea un acuerdo por unidad sin límite, todo sincrónicamente.

**Corrección:** clave idempotente por `(tenant, actor, operation)`, hash del payload, máxima cantidad/items/importe, moneda única y un estado de producto vendible. Deshabilitar botones durante envío ayuda, pero no sustituye idempotencia del servidor.

### 7. Pago y webhook

La abstracción de pasarela es una buena base y los webhooks específicos verifican firma. El flujo actual mezcla intento local, llamada remota y fulfillment. Problemas adicionales:

- no se envía una clave idempotente estable a los PSP;
- no hay timeout/conexión/retry/circuit breaker explícitos;
- no existe tabla de eventos webhook con unique por proveedor+event-id;
- la tolerancia temporal antirreplay no está implementada para Stripe/Mercado Pago;
- el endpoint genérico `/webhooks/pagos/{proveedor}` es público y no firmado;
- Mercado Pago confirma por estado/referencia externa sin cotejar monto, moneda y merchant contra la orden;
- si un segundo intento se aprueba tras pagarse la orden, puede quedar un pago local pendiente y dinero cobrado externamente.

**Corrección:** inbox de eventos, antirreplay, validación completa de importe/moneda/cuenta, estados terminales de todos los intentos, conciliador periódico y alertas de discrepancia.

### 8. Reembolso

`ReembolsarPago` bloquea el pago y revierte ledger/acuerdos, pero no usa el adaptador. También puede competir con una reserva porque no bloquea derechos/retenciones en el mismo orden. Al revertir `-$saldo` incorpora top-ups posteriores no necesariamente financiados por esa orden.

**Corrección:** operación de devolución por línea/monto; API del PSP idempotente; bloquear/proyectar el beneficio originado por la orden, no el saldo total; confirmar devolución externa; compensar internamente; almacenar referencia, actor, motivo, timestamps y errores.

## Reglas de negocio faltantes

- Políticas por tenant/oferta para cancelación, no-show, booking window y costo en créditos.
- Estado y ventana de venta del producto.
- Elegibilidad por edad/nivel/consentimiento médico en actividades infantiles o de riesgo.
- Capacidad y disponibilidad física de recursos.
- Restricción de instructor a sucursal y sesión asignadas.
- Pausa/cancelación/congelamiento de membresía y prorrateo.
- Vencimiento de órdenes/intentos y limpieza de holds huérfanos.
- Conciliación entre órdenes, PSP, acuerdos y ledger.
- Definición de zona horaria efectiva para procesos de ciclo por tenant.

## Casos límite y pruebas faltantes

1. El portal debe rechazar siempre `manual` y `simulada` fuera de desarrollo; hoy existe una prueba que espera éxito manual.
2. Asistencia presente/ausente/no-show debe liquidar exactamente una vez incluso con dos requests concurrentes.
3. Cancelar sesión con confirmadas y espera debe liberar todos los holds y no promover.
4. Dos checkouts concurrentes con la misma clave y con claves diferentes.
5. PSP aprobado + timeout local; commit fallido tras aprobación; webhook antes de respuesta de checkout.
6. Dos proveedores aprobando intentos de la misma orden.
7. Reembolso concurrente con reserva/top-up/cierre de ciclo.
8. Usuario con dos tenants usando ambas SPA.
9. Rol de sucursal intentando leer/escribir otra sucursal.
10. Instructor intentando roster/asistencia de sesión no asignada.
11. Primer waitlisted sin saldo y segundo elegible.
12. Derecho expirado/cancelado mientras está en waitlist.
13. Sesiones solapadas por recurso e instructor, incluidas fronteras exactas.
14. Orden con monedas distintas, cientos de items y cantidades extremas.
15. Tenant suspendido, cerrado o en periodo de gracia.

## Funciones incompletas

- Móvil: solo health.
- Guardian/dependientes en autoservicio.
- Ciclo completo de asistencia y no-show.
- Reembolso real y conciliación financiera.
- Notificaciones de confirmación, recordatorio, promoción y cancelación.
- Selector multi-tenant.
- Gestión de cuenta: recuperar/cambiar contraseña, verificación, MFA, invitaciones.
- Auditoría y reporting operacional.
- Administración SaaS de tenants, planes, límites y facturación.

## Criterio de aceptación global

Un hallazgo no debe darse por cerrado solo con un cambio de UI. Requiere invariante en servidor, restricción de datos cuando corresponda, prueba negativa, prueba de reintento/concurrencia para flujos financieros y telemetría que permita detectar regresiones.
