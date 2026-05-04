# UltimaMilla Express — guidelines

Extracto de reglas críticas de `CLAUDE.md`. Para la versión completa (modelo de datos, settings tipados, branding, despliegue), leer `CLAUDE.md` en la raíz.

## Naming

- **Producto** = UltimaMilla Express. Naming técnico: `ultimamilla` / `UltimaMilla` libre en clases, configs, db, namespaces, dominios.
- **Cliente** = WeGroup. **NO se embebe en código** (clases, namespaces, tablas, configs, repos, dominios, emails, copy de UI).
- **Excepción inmutable**: strings dentro del archivo Excel de Pinit (`"WEGROUP"`, `"WE GROUP"`, `"CENTRO DE LA MODA"`, etc.) son input externo y se aceptan tal como vienen.

## Reglas de oro

1. **Zona horaria es `America/Bogota` siempre.** UTC-5 sin DST. Nunca `now()` desnudo cuando se compara con `fecha_operacion`.
2. **Deadline configurable.** Vive en `UltimaMillaSettings::hora_cierre_reservas` (HH:MM, default `"12:00"`). El día anterior a la operación, hasta esa hora hora Colombia, se puede editar/cancelar.
3. **Auth dual con dos guards:** `web` (admins, email + password) y `repartidor` (cédula + PIN de 4 dígitos hasheado).
4. **Constraint único en reservas:** un repartidor solo puede tener una reserva activa por fecha de operación.
5. **Modelo de datos limpio.** Nada que dificulte una migración a multi-tenant futura. FKs explícitas, sin "mágica".
6. **Sin features fuera de alcance.** NO WhatsApp API, NO SMS, NO push, NO API directa con Pinit, NO enrutamiento, NO niveles, NO calificaciones, NO multi-tenant.
7. **Formato de respuestas técnicas:** sin emojis, sin marketing. Comentarios solo donde aclaren reglas de negocio (no obvios).

## DB

- MySQL 5.7 en dev y prod (mismo engine en ambos entornos).
- Collation `utf8mb4_unicode_ci`. NO `utf8mb4_0900_ai_ci` (exclusivo de MySQL 8).
- Sin CTEs, sin window functions, sin CHECK constraints reales (5.7 los parsea pero los ignora).

## Stack confirmado

PHP 8.3+ · Laravel 12 · Filament 4 · Livewire 3 · Tailwind 4 · MySQL 5.7 · Redis + Horizon · Maatwebsite/Excel · Spatie activitylog · Spatie laravel-settings · Resend · Pest 3.

NO: Filament 5, Livewire 4, Inertia, React, Vue, Filament Blueprint (premium), WhatsApp Business API, SMS gateway, push notifications, integración API directa con Pinit.

## Reglas de negocio críticas

- **Cálculo de deadline** (`App\Services\DeadlineChecker::isReservaLocked`): combinar `fecha_operacion->subDay()` con `setTimezone('America/Bogota')` + `setTime` desde `hora_cierre_reservas`. Job cada 5 min marca `locked_at`.
- **Cupo efectivo del repartidor**: `cupo_personalizado ?? cupo_global_default`.
- **Validación de fecha operativa**: `fecha_operacion->dayOfWeek` debe estar en `dias_operativos`. Si no, error "Día no operativo".
- **Detección de devolución**: ruta con `total <= devolucion_threshold` se marca `es_devolucion = true`.
- **Múltiples rutas/día por repartidor**: sumar `total` y `entregados` de todas las `pinit_rutas` del mismo `repartidor_id` en la misma `fecha_operacion`. Excluir `es_devolucion = true` del cumplimiento, pero registrarlas.
- **Patrones de cumplimiento**: `sub_reserva`, `sobre_reserva`, `consistente` o `null`. Umbrales en `UltimaMillaSettings`.

## Parseo del archivo Pinit

- **Cédula**: col C `ID Operador` primaria; fallback al prefijo numérico de col D `Nombre operador`. Si nada, `repartidor_id = null` + warning, NO se descarta.
- **Ciudad**: regex `/^\d+\s+(MED|ITAGUI)\b/i` sobre col D. Si no matchea, `ciudad_parseada = null` + warning.
- **Nombre**: lo que queda en col D removiendo cédula, ciudad y sufijo de marca. Sufijos válidos en `config/ultimamilla.php` clave `pinit.sufijos_marca`. Lista inicial: `['WEGROUP AL', 'WEGROUP', 'WE GROUP', 'CENTRO DE LA MODA']`. Token `"AL"` suelto al final también se strip.
- **Status `"En curso"`**: entra al cruce con sus `entregados` actuales. NO filtrar.
- **Reimportación misma `fecha_archivo`**: import previo `done` se marca `superseded`; se recalculan los `cruces` de esa `fecha_operacion` (reemplazo, no acumulación). Activitylog evento `pinit_import.replaced`.

## Convenciones

- Modelos en español para conceptos de negocio (`Repartidor`, `Reserva`, `Asignacion`, `Cruce`, `PinitImport`, `PinitRuta`).
- Inglés para conceptos técnicos (`Job`, `Service`, `Controller`, `Settings`).
- Migraciones en inglés.
- Tests con Pest 3, no PHPUnit clásico.
- Commits en español: `feat:`, `fix:`, `refactor:`, `chore:`.
