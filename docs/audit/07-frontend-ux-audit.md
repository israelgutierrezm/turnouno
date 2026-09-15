# Auditoría de frontend, UX y móvil

## Conclusión

Las dos SPA permiten recorrer los flujos principales y compilan con TypeScript/ESLint. Su mayor problema no es el peso, sino la falta de una arquitectura de interacción coherente: navegación plana, acciones sensibles sin protección, estados/error inconsistentes y escasa accesibilidad. El portal no resuelve multi-tenant ni familia. Móvil no es todavía una aplicación de negocio.

## Admin web

### Navegación y arquitectura de información

`apps/admin-web/src/App.vue` presenta alrededor de catorce enlaces en una fila con wrap. En escritorio crea una navegación ruidosa; en móvil consume varias líneas y no ofrece jerarquía, drawer ni agrupación.

**Propuesta:**

- sidebar de escritorio y drawer móvil;
- grupos: Operación, Clientes, Oferta, Finanzas, Configuración;
- breadcrumb y título contextual;
- menú según permisos, sin asumir que ocultar reemplaza la autorización servidor;
- tenant/sucursal activos visibles y seleccionables;
- búsqueda/comando rápido para tareas frecuentes.

### Vistas monolíticas

`AgendaView.vue` (~601 líneas), `MembresiasView.vue` (~538) y `CatalogoView.vue` (~309) concentran fetch, transformación, formularios, errores y presentación.

**Impacto:** cambios frágiles, pruebas difíciles, rerenders amplios y patrones inconsistentes.

**Propuesta:** separar *page container*, composables de datos, formularios con schema, tablas/listas y servicios de dominio. No fragmentar por cada `div`; hacerlo por responsabilidad y flujo testeable.

### Acciones sensibles

Cancelar sesión/reserva, aprobar/rechazar comprobante, reembolsar y marcar asistencia suelen dispararse directamente desde botones pequeños. Faltan confirmación, motivo, resumen de impacto y estado irreversible.

**Propuesta:**

- diálogo accesible con objeto, monto/persona/sesión e impacto;
- motivo obligatorio para ajustes/rechazos/reembolsos;
- botón disabled y estado de progreso;
- resultado persistente con correlation ID visible en errores;
- opción de deshacer solo cuando la operación sea realmente reversible.

### Tablas y volumen

Las vistas reciben listas completas y no ofrecen paginación robusta, filtros persistentes o estados vacíos orientados a la tarea. En dispositivos pequeños, anchos fijos como `w-56`/`w-48` y grids base de dos columnas comprimen controles.

**Propuesta:** diseño mobile-first de una columna, filtros en sheet, tabla→cards en móvil, encabezados sticky, paginación/cursor y skeletons.

## Portal web

### Compra

La interfaz refleja pasarelas devueltas por backend, incluida `manual`/`simulada`, convirtiendo SEC-01 en un exploit visible. Además, orden y pago carecen de idempotency key y protección robusta contra doble submit.

**Propuesta:** mostrar solo métodos permitidos por canal; una sola intención de checkout reanudable; botón con estado; recuperación después de redirect; pantalla `pending` que sondee/reciba cambio seguro; recibo final con referencia.

### Familia y dependientes

El portal resuelve solo la persona vinculada al usuario. Aunque el backend modela tutelas, el tutor no puede elegir dependiente al consultar derechos, comprar o reservar.

**Propuesta:** selector de participante con permisos/consentimientos, avatar/edad, derechos por dependiente y confirmación clara de “comprador” vs “participante”. Evitar mezclar historiales de forma ambigua.

### Agenda

La agenda obtiene un conjunto limitado pero no ofrece selector de sucursal, filtros, cupos/lista de espera enriquecidos ni paginación. Debe distinguir visual y verbalmente:

- reservado, en espera, lleno y no elegible;
- costo y derecho que se usará;
- ventana/política de cancelación;
- zona horaria y cambios de horario;
- resultado de promoción.

### Perfil/compras

Los endpoints subyacentes cargan historiales completos. La UI necesita agrupar “próximas” vs “pasadas”, estados comprensibles, recibos y ayuda contextual. No debe mostrar un historial antiguo completo en el primer render.

## Responsive y móvil web

Problemas observados en clases/estructura:

- nav del portal en una fila flex sin menú móvil;
- controles `text-xs px-2 py-1`, por debajo de un objetivo táctil cómodo;
- grids `grid-cols-2` desde el breakpoint base;
- anchos fijos de selects/inputs;
- formularios y tablas sin estrategia para 320–375 px.

**Criterios:** objetivos táctiles de ~44×44 CSS px, una columna base, `sm/md` para expandir, sin scroll horizontal de página, y pruebas en 320/375/768/1280 px con zoom 200%.

## Accesibilidad

No se encontraron atributos `aria-*` en las vistas inspeccionadas ni un patrón de diálogo accesible. Que un `label` envuelva un input ayuda, pero no cubre navegación, errores y estados dinámicos.

### Prioridad A

- navegación semántica con nombre y skip link;
- foco visible y orden de tabulación lógico;
- labels/description/error relacionados con `for`, `aria-describedby`, `aria-invalid`;
- mensajes asíncronos mediante live region sin anunciar ruido;
- diálogos con focus trap, Escape y retorno de foco;
- no depender solo de color para estado;
- contraste AA y touch targets;
- tablas con captions/headers o representación card accesible.

### Verificación

ESLint no sustituye auditoría a11y. Incorporar axe en componentes/E2E, Lighthouse CI y recorrido manual solo teclado/lector de pantalla.

## Estados de interfaz

Debe existir un patrón compartido para cada consulta/acción:

```text
idle → loading → success(empty|data)
             ↘ recoverable error → retry
             ↘ auth expired → reauthenticate preserving intent
```

Hoy varias vistas usan mensajes genéricos y pierden `code`, `message` y correlation ID del contrato backend. Añadir interceptor común para:

- 401: cerrar sesión/reautenticar;
- 403: explicar alcance/tenant/sucursal;
- 409: mostrar conflicto de negocio y refrescar;
- 422: mapear errores por campo;
- 429: respetar `Retry-After`;
- 5xx/red: preservar intención y permitir reintento idempotente.

## Multi-tenant en cliente

Ambos stores tipan `pertenencias`, pero `src/lib/api.ts` solo configura cookies/XSRF y correlation ID. No hay `X-Tenant-ID`.

**Diseño recomendado:**

1. Tras login, si hay 0 tenants mostrar estado de invitación/soporte; si hay 1, seleccionarlo; si hay varios, pedir selección.
2. Persistir el ULID activo por cuenta en almacenamiento local seguro para UX, validándolo siempre con `/me`.
3. Interceptor añade `X-Tenant-ID`.
4. Al cambiar tenant, cancelar requests, limpiar stores/cache y navegar a un destino permitido.
5. Mostrar tenant y sucursal de manera imposible de confundir en acciones financieras.

## Arquitectura frontend

Duplicación actual entre admin y portal:

- cliente Axios y correlation ID;
- tipos básicos de `/me`;
- auth/session lifecycle;
- traducciones/patrones de error;
- controles/form styles.

Crear un paquete interno pequeño (`packages/web-core`) con cliente generado/tipado, auth, errores, tenant context y primitives accesibles. Mantener los componentes de negocio y navegación propios por audiencia. La versión distinta de Pinia (admin 4 vs portal 2) y Router (5 vs 4) debe armonizarse para evitar dos plataformas internas.

## Pruebas frontend

Estado actual verificado:

- admin: 3 archivos / 6 pruebas;
- portal: 1 archivo / 2 pruebas;
- ambas suites, lint y builds pasan.

La cobertura se concentra en auth/health/correlation, no en UI crítica. Añadir:

- componentes: validación, errores, confirmaciones, teclado;
- integración con Mock Service Worker para contratos API;
- E2E: login multi-tenant, compra segura, redirect/webhook pending, reservar/cancelar, tutor/dependiente, instructor asignado;
- regresión visual de breakpoints clave;
- axe/Lighthouse CI.

## Aplicación móvil

`apps/mobile/lib/main.dart` muestra una pantalla de salud; hay 2 pruebas del parser de health. No hay auth, routing de negocio, secure token storage, tenant selector, membresías, reserva, pago, dependientes, instructor, push ni deep links. `pubspec.yaml` todavía conserva descripción/comentarios del template, y Android mantiene TODOs de application ID/firma de release.

**Conclusión:** estimarla y comunicarla como producto nuevo. No prometer paridad.

### MVP móvil recomendado

1. Identidad segura, recuperación y tenant/participante.
2. Agenda, detalle, reservar/cancelar y waitlist.
3. Derechos/saldo e historial próximo.
4. Push/deep links para recordatorio, promoción y cancelación.
5. Compras solo después de estabilizar pagos web; preferir checkout alojado/SDK oficial.
6. Modo instructor separado por permisos: “mi agenda”, roster y asistencia.

Incluir analytics con consentimiento, crash reporting, certificate/network hardening proporcionado, almacenamiento seguro y remote kill switch/feature flags.

## Backlog UX priorizado

| Prioridad | Cambio |
|---|---|
| P0 | Retirar manual/simulada del portal y asegurar checkout |
| P1 | Selector tenant y limpieza de stores al cambiar |
| P1 | Confirmaciones/motivos/estado idempotente en acciones sensibles |
| P1 | Navegación responsive y touch targets |
| P1 | Patrón compartido de error/loading/empty y expiración de sesión |
| P1 | Paginación/filtros para listas |
| P2 | Portal de dependientes |
| P2 | Descomponer vistas administrativas |
| P2 | Baseline WCAG 2.2 AA y tests axe |
| P3 | Design system y paquete web compartido completo |
