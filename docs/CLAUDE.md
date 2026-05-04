# CLAUDE.md — Sistema de Reservas UltimaMilla Express

Contexto persistente para Claude Code en este repo. Léelo al inicio de cada sesión.

## Qué es este proyecto

Sistema de planeación de reservas de paquetes para repartidores de WeGroup, operador logístico de Temu/SHEIN en Medellín-Itagüí, Colombia. NO es multi-tenant. Es mono-cliente. Piloto de 3 meses por USD 120/mes.

Complementa a Pinit TMS (sistema externo que el cliente ya usa). NO lo reemplaza, NO lo duplica. Pinit cubre lo que pasa después de que llega la mercancía. Este sistema cubre lo que pasa antes: planeación, reservas, capacidad semanal, administración propia de repartidores. Diferenciador clave: importa el archivo Excel de Pinit y cruza reservado vs entregado.

El piloto debe tener look corporativo premium ("wow al ingresar"). Cero apariencia de tablero genérico. Cuidar branding, tipografía, espaciado, microinteracciones.

## Stack confirmado (no cambiar sin justificación explícita del owner)

- PHP 8.3+
- Laravel 12
- **FilamentPHP 4** (panel admin) — `filament/filament:"^4.0"`
- **Livewire 3** (frontend repartidor + integración Filament 4) — `livewire/livewire:"^3.5"`
- Alpine.js (viene con Livewire)
- **Tailwind CSS 4.1+** (Filament 4 ya soporta Tailwind 4 nativamente para custom themes)
- MySQL 5.7 en desarrollo y producción (mismo engine en ambos entornos para evitar bugs sutiles). Implica: collation `utf8mb4_unicode_ci` (NO `utf8mb4_0900_ai_ci`, exclusivo de MySQL 8); sin CTEs, sin window functions, sin CHECK constraints reales (5.7 los parsea pero los ignora). Ajustar `config/database.php` para forzar collation 5.7-compatible y evitar que migraciones nuevas usen sintaxis 8-only.
- Redis + Horizon para colas
- Maatwebsite/Excel para import/export Excel
- spatie/laravel-activitylog para auditoría
- spatie/laravel-settings para configuración global tipada
- Resend para email transaccional
- Laravel Boost (gratis, viene con `laravel new`) — instalado para que Claude Code use docs y guidelines de Filament 4
- Pest 3 para tests
- Despliegue en VPS Hetzner (Ubuntu 24.04, Nginx, PHP-FPM)

NO se usa: Filament 5, Livewire 4, Inertia, React, Vue, Filament Blueprint (premium, no se paga), WhatsApp Business API, SMS gateway, push notifications, integración API directa con Pinit.

## Branding

- Tipografía: **Inter** (Google Fonts), pesos 400/500/600/700.
- Paleta provisional (confirmar con owner si quiere cambiar):
  - Neutros: Slate de Tailwind (slate-50 a slate-950) para fondos, bordes, texto secundario.
  - Acento primario: Indigo (indigo-600 default, indigo-500 hover, indigo-700 active).
  - Éxito: Emerald-600. Advertencia: Amber-500. Error: Rose-600.
- Densidad: cómoda pero no espaciada al extremo. Filament default `Density::Comfortable`.
- Dark mode: habilitado por defecto, switch en topbar.
- Logo: provisional, texto "UltimaMilla" en Inter Bold con un punto Indigo. Reemplazable cuando el cliente provea logo real (componente Blade `<x-logo />`).
- Login customizado: imagen de fondo o gradiente Slate→Indigo, formulario centrado con sombra suave, sin chrome de Filament default.

## Reglas de oro

1. **Zona horaria es `America/Bogota` siempre.** UTC-5 sin DST. Configurar en `config/app.php` y usar Carbon con tz explícita en cualquier cálculo de deadlines. Nunca `now()` desnudo cuando se compara con `fecha_operacion`.

2. **El deadline de edición de reservas es configurable, no hardcoded.** Vive en `UltimaMillaSettings::hora_cierre_reservas` (formato `"HH:MM"`, default `"12:00"`). Significa: el día anterior a la operación, hasta esa hora hora Colombia, se puede editar/cancelar. Pasado eso, `Reserva::isLocked()` devuelve true.

3. **Auth dual con dos guards:**
   - Guard `web` para admins (Filament estándar, email + password). Tabla `users`.
   - Guard `repartidor` para repartidores. Tabla `repartidores`. Login por cédula + PIN de 4 dígitos hasheado.
   - El PIN se genera al crear el repartidor y se muestra una sola vez al admin con botón copiar. Acción "regenerar PIN" disponible siempre.

4. **Constraint único en reservas:** un repartidor solo puede tener una reserva activa por fecha de operación. Si quiere cambiar, edita la existente.

5. **Modelo de datos limpio.** Aunque el piloto sea mono-cliente, NO embeber lógica que dificulte una migración a multi-tenant en el futuro (Proyecto 2). Usar nombres claros, FKs explícitas, sin "mágica".

6. **Sin features fuera de alcance.** Si el código sugiere agregar WhatsApp API, SMS, push notifications, integración API con Pinit, enrutamiento, sistema de niveles, calificaciones, multi-tenant: NO. Quedan para Proyecto 2.

7. **Formato de respuestas técnicas:** sin emojis, sin marketing, sin frases tipo "lo cual es genial". Código limpio, comentarios solo donde aclaren reglas de negocio (no obvios).

## Modelo de datos

```
users                — admins de Filament (id, name, email, password)
repartidores         — id, cedula (unique), nombre, telefono,
                       ciudad (MED|ITAGUI), placa, pin (hashed),
                       cupo_personalizado (nullable int),
                       activo (bool), last_login_at, timestamps
reservas             — id, repartidor_id (FK), fecha_operacion (date),
                       paquetes (int), rutas (1|2|3),
                       estado (activa|cancelada),
                       locked_at (nullable, se llena al pasar deadline),
                       timestamps
                       UNIQUE (repartidor_id, fecha_operacion)
asignaciones         — id, reserva_id (FK), paquetes_asignados (int),
                       ajuste_motivo (nullable text),
                       entregado_a_repartidor_at (nullable),
                       ajustado_por (FK users), timestamps
pinit_imports        — id, archivo_path, fecha_archivo (date),
                       total_filas, status (pending|processing|done|failed|superseded),
                       errores (json), warnings (json),
                       importado_por (FK users), timestamps
pinit_rutas          — id, pinit_import_id (FK), id_ruta_pinit,
                       cedula, nombre_operador, ciudad_parseada, placa,
                       repartidor_id (nullable FK, null si cédula no existe),
                       status, total, entregados, porcentaje,
                       excepciones (json), tiempos (json),
                       performance (json), es_devolucion (bool)
cruces               — id, fecha_operacion (date), repartidor_id (FK),
                       reservado, asignado, entregado,
                       cumplimiento_pct, patron (sub_reserva|sobre_reserva|consistente),
                       pinit_import_id (FK)
```

Auditoría con `spatie/laravel-activitylog` (sin tabla custom).
Settings con `spatie/laravel-settings` (clase tipada, ver abajo).

## Settings tipados (spatie/laravel-settings)

Clase `App\Settings\UltimaMillaSettings`:

```php
class UltimaMillaSettings extends Settings
{
    public int $cupo_global_default;          // ej: 30
    public string $hora_cierre_reservas;       // "12:00" formato HH:MM
    public int $umbral_minimo_diario;          // ej: 500 paquetes/día
    public array $dias_operativos;             // [1,2,3,4,5,6] => Lun a Sáb (Carbon dayOfWeek: 0=Dom)
    public int $devolucion_threshold;          // 3 (rutas con total ≤ esto se marcan devolución)
    public float $cumplimiento_sobre_reserva_max; // 0.80
    public float $cumplimiento_consistente_min;   // 0.90
    public float $cumplimiento_sub_reserva_cupo_max; // 0.70

    public static function group(): string { return 'ultimamilla'; }
}
```

Editable desde una Custom Page de Filament en `/admin/configuracion`. Validaciones del form:
- `cupo_global_default`: entero ≥ 1.
- `hora_cierre_reservas`: regex `/^([01]\d|2[0-3]):[0-5]\d$/`.
- `umbral_minimo_diario`: entero ≥ 0.
- `dias_operativos`: array no vacío de enteros 0-6 únicos.

## Reglas de negocio críticas

**Cálculo de deadline (en `App\Services\DeadlineChecker`):**
```php
public function isReservaLocked(Reserva $reserva, UltimaMillaSettings $settings): bool {
    [$h, $m] = explode(':', $settings->hora_cierre_reservas);
    $deadline = $reserva->fecha_operacion
        ->copy()
        ->subDay()
        ->setTimezone('America/Bogota')
        ->setTime((int) $h, (int) $m, 0);
    return now('America/Bogota')->gte($deadline);
}
```
Job programado cada 5 minutos: marca `locked_at = now()` en reservas que pasaron deadline.

**Cupo efectivo del repartidor:**
```php
// App\Models\Repartidor
public function cupoEfectivo(UltimaMillaSettings $settings): int {
    return $this->cupo_personalizado ?? $settings->cupo_global_default;
}
```

**Validación de fecha operativa al crear/editar reserva:**
- `fecha_operacion->dayOfWeek` debe estar en `$settings->dias_operativos`. Si no, error "Día no operativo".
- Vista semanal del repartidor: los 7 días siguientes filtrados por días operativos. Si hoy es jueves y los operativos son Lun-Sáb, mostrar viernes, sábado, lunes... hasta llegar a 7 cards.

**Parseo del archivo Pinit:**

El archivo trae 38 columnas. Las relevantes para el cruce son `ID ruta` (A), `Carrier` (B), `ID Operador` (C), `Nombre operador` (D), `Placa` (F), `Almacén` (G), `Status` (I), `Total` (J), `Entregados` (K), `Porcentaje de entrega` (M), códigos de excepción (S–Z), `Delivery exceptions` (AA), entregados por intento (AC–AF), kilómetros y tiempos (AH–AK).

Reglas de parseo por campo:

- **Cédula**: usar columna **`ID Operador`** (col C) como fuente primaria. Si C está vacía, fallback al prefijo numérico de **`Nombre operador`** (col D) con regex `/^(\d+)\s+/`. Si tampoco hay match, la `pinit_ruta` se persiste con `repartidor_id = null` y se agrega warning a `pinit_imports.warnings` indicando la fila y el contenido crudo de col D. La fila NO se descarta — queda visible para que el admin investigue.

- **Ciudad** (`ciudad_parseada`): segundo token de col D con regex `/^\d+\s+(MED|ITAGUI)\b/i`. Si el token no coincide con `MED` o `ITAGUI`, la fila se persiste con `ciudad_parseada = null` y warning; no se descarta.

- **Nombre del operador** (`nombre_operador`): lo que queda en col D tras remover cédula, ciudad y el sufijo de marca al final. Los sufijos válidos viven en **`config/ultimamilla.php`** bajo la clave `pinit.sufijos_marca`. Lista inicial:
  ```php
  ['WEGROUP AL', 'WEGROUP', 'WE GROUP', 'CENTRO DE LA MODA']
  ```
  Match case-insensitive, en orden de la lista (poner el más específico primero — `"WEGROUP AL"` antes que `"WEGROUP"`). Tras remover el sufijo de marca, si queda un token suelto `"AL"` al final, removerlo también (significado desconocido, no asumir). Cerrar con `trim()`. Variantes futuras se agregan a la config sin tocar código. La regla "el repartidor no debe contar" NO se resuelve en el parser, sino con `repartidores.activo = false`.

- **Carrier (col B)** y **Almacén (col G)** son metadata fija de la operación; se ignoran para el cruce y no se persisten en `pinit_rutas`.

- **Status (col I)**: las rutas con status `"En curso"` entran al cruce con sus `entregados` actuales tal como vienen en el archivo. El status se persiste en `pinit_rutas.status` para visualización pero no filtra el cálculo de cumplimiento. Razón: el cliente confirmó en pre-venta que las rutas pueden cerrarse al día siguiente (devoluciones tardías, pico y placa terminando en la mañana siguiente). Filtrar `"En curso"` sería excluir trabajo real.

Nota sobre naming: los strings `"WEGROUP"`, `"WE GROUP"`, `"CENTRO DE LA MODA"`, etc. son **input externo de Pinit** y son inmutables — el parser tiene que aceptarlos tal como vienen. La regla del proyecto de no usar "WeGroup" en naming nuestro (clases, archivos, variables, namespaces, dominios) NO aplica al contenido de archivos externos.

**Detección de devolución:** ruta con `total <= $settings->devolucion_threshold` se marca `es_devolucion = true`.

**Cédula del archivo Pinit no encontrada en `repartidores`:**
- La `pinit_ruta` se guarda con `repartidor_id = null`.
- Al final del job, se agrega un warning a `pinit_imports.warnings` con la lista de cédulas no encontradas y la cantidad de rutas afectadas.
- El cruce ignora estas filas (no se calcula `cumplimiento` para ellas).
- En el panel admin, la página de detalle del import muestra estas cédulas con un botón "Crear repartidor" que prerellena el form.

**Reimportación del archivo Pinit (misma `fecha_archivo`):**
- Si al subir un import la `fecha_archivo` ya existe en `pinit_imports` con `status = done`, el import previo se marca `status = superseded` (no se borra; queda en historial para auditoría).
- El import nuevo se procesa normalmente. Al finalizar el job, los `cruces` con esa `fecha_operacion` se eliminan y se recalculan desde el nuevo import. Reemplazo total, no acumulación.
- La sustitución se registra en activitylog con evento `pinit_import.replaced` y properties `{old_id, new_id, fecha_archivo}`, sobre el subject del nuevo `PinitImport`.
- Si el import previo está en `pending` o `processing`, frenar la reimportación y avisar al admin antes de aceptar el nuevo (evita race condition con el job en curso).
- Caso de uso: el admin sube el archivo a las 10 AM con varias rutas en estado "En curso" y lo vuelve a subir a las 2 PM con esas mismas rutas ya cerradas.

**Cálculo de patrón en cruces** (umbrales en settings):
- `sub_reserva`: entregó ≥ `cumplimiento_consistente_min` de lo reservado, pero reservado < `cumplimiento_sub_reserva_cupo_max` del cupo efectivo.
- `sobre_reserva`: entregó < `cumplimiento_sobre_reserva_max` de lo reservado.
- `consistente`: entregó ≥ `cumplimiento_consistente_min` y reservó ≥ `cumplimiento_sub_reserva_cupo_max` del cupo.
- En otros casos, patrón = null.

**Cruce considera múltiples rutas por repartidor el mismo día:** sumar `total` y `entregados` de todas las `pinit_rutas` con mismo `repartidor_id` y misma `fecha_operacion` (excluyendo `es_devolucion = true` del cálculo de cumplimiento, pero registrándolas).

**Alerta de capacidad insuficiente (dashboard):**
- Widget que compara `SUM(reservas.paquetes WHERE fecha_operacion = mañana AND estado = activa)` contra `$settings->umbral_minimo_diario`.
- Si suma < umbral: card en rojo, "Faltan X paquetes para alcanzar el mínimo operativo de mañana".
- Si suma ≥ umbral: card en verde, "Capacidad cubierta".

## Estructura de carpetas esperada

```
app/
  Filament/
    Resources/         # Repartidores, Reservas, AuditLog
    Pages/             # Configuracion, RecepcionMañana, ImportarPinit, DashboardCruces
    Widgets/           # CapacidadMañana, ReservasSemana, CumplimientoSemana
  Http/
    Controllers/
    Livewire/
      Repartidor/      # Login, Reservas, Confirmacion
  Models/
  Jobs/
    ProcesarPinitImport.php
    CalcularCruces.php
    BloquearReservasVencidas.php  # corre cada 5 min
  Services/
    PinitParser.php
    CruceCalculator.php
    DeadlineChecker.php
  Settings/
    UltimaMillaSettings.php
  Exports/
config/
resources/
  views/
    repartidor/        # Vistas Blade del frontend repartidor
    layouts/
      repartidor.blade.php   # Layout mobile-first
    components/
      logo.blade.php
routes/
  web.php
tests/
  Feature/
    Repartidor/
    Admin/
    Pinit/
```

## Convenciones

- Nombres de modelos en español para conceptos de negocio (`Repartidor`, `Reserva`, `Asignacion`, `Cruce`, `PinitImport`, `PinitRuta`).
- Nombres en inglés para conceptos técnicos (`Job`, `Service`, `Controller`, `Settings`).
- Migraciones en inglés.
- Tests con Pest, no PHPUnit clásico. Cobertura mínima en lógica crítica: deadline, parser Pinit, cálculo de cruces.
- Commits en español, formato corto: `feat: ...`, `fix: ...`, `refactor: ...`, `chore: ...`.
- Variables y comentarios de negocio en español. Nombres de funciones en inglés cuando son técnicas, en español cuando son de dominio.

## Lo que NO se construye en el piloto

WhatsApp Business API · SMS · Push notifications · Integración API con Pinit (solo import de Excel) · Enrutamiento · App nativa · Multi-tenant · Marca masiva tipo Uber · Sistema de niveles oro/platino · Bonificaciones · Calificaciones de clientes finales · Cobros COD reales (solo se leen del archivo Pinit como referencia) · PWA (queda para Fase 9).

## Despliegue

VPS Hetzner ya operativo (mismo patrón que AzuCouriers, SuriParts):
- Repo: `github.com/eamonfq/ultimamilla-express` (a crear).
- Deploy con usuario `deployer`, deploy key existente.
- Nginx vhost en `/etc/nginx/sites-available/ultimamilla-express`.
- PHP 8.3-FPM pool dedicado.
- Cron de Laravel scheduler (`* * * * * cd /path && php artisan schedule:run`).
- Supervisor para Horizon.
- Certbot wildcard ya configurado.
- Subdominio destino: `reservas.ultimamilla.com` (cliente provee CNAME) o fallback `ultimamilla.deploytive.com`.

===

<laravel-boost-guidelines>
=== .ai/ultimamilla rules ===

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

=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- filament/filament (FILAMENT) - v4
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- livewire/livewire (LIVEWIRE) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- pestphp/pest (PEST) - v3
- phpunit/phpunit (PHPUNIT) - v11
- tailwindcss (TAILWINDCSS) - v4

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.
- To check environment variables, read the `.env` file directly.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

# Laravel 12

- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

## Laravel 12 Structure

- In Laravel 12, middleware are no longer registered in `app\Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console/Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app\Console/Commands/` are automatically available and do not require manual registration.

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== livewire/core rules ===

# Livewire

- Livewire allow to build dynamic, reactive interfaces in PHP without writing JavaScript.
- You can use Alpine.js for client-side interactions instead of JavaScript frameworks.
- Keep state server-side so the UI reflects it. Validate and authorize in actions as you would in HTTP requests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

</laravel-boost-guidelines>
