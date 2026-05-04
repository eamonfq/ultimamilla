# UltimaMilla Express — Sistema de Reservas

Sistema de planeación de reservas de paquetes para repartidores. Piloto mono-cliente que complementa a Pinit TMS importando el archivo Excel diario y cruzando reservado vs entregado.

## Stack

PHP 8.3 · Laravel 12 · Filament 4 · Livewire 3 · Tailwind 4 · MySQL 5.7 · Redis · Pest 3.

## Requisitos locales

- PHP 8.3+
- MySQL 5.7
- Redis
- Node 22+ (con nvm en WSL si trabajás sobre Windows)
- Composer 2.x

## Instalación

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Crear las bases de datos (dev y testing)
mysql -u root -p -e "CREATE DATABASE ultimamilla_express CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE DATABASE ultimamilla_express_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Ajustar credenciales DB en .env y correr migraciones + seed
php artisan migrate --seed

# Levantar dev server y assets
npm run dev
php artisan serve
```

Login admin de prueba: `admin@ultimamilla.test` / `password` en `/admin`.

## Tests

```bash
php artisan test
```

Pest 3, RefreshDatabase activado, conecta contra `ultimamilla_express_testing`.

## Despliegue a producción

VPS Hetzner Ubuntu 24.04 · usuario `deployer` · deploy key en `~/.ssh/github_deploy`.

**URL:** `https://ultimamilla.deploytive.com`

### Requisitos del servidor

- PHP 8.3-FPM con extensiones: pcntl, posix, redis, mbstring, xml, curl, zip, gd
- MySQL 8
- Redis
- Nginx
- Supervisor (para Horizon)
- Certbot (HTTPS)
- Node 22+ y npm

### Primer deploy

```bash
# 1. Crear base de datos
sudo mysql -e "CREATE DATABASE ultimamilla_reservas CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
sudo mysql -e "CREATE USER 'ultimamilla'@'localhost' IDENTIFIED BY 'PASSWORD_FUERTE';"
sudo mysql -e "GRANT ALL PRIVILEGES ON ultimamilla_reservas.* TO 'ultimamilla'@'localhost'; FLUSH PRIVILEGES;"

# 2. Clonar repo
cd /var/www
git clone git@github.com:eamonfq/ultimamilla.git ultimamilla
cd ultimamilla

# 3. Dependencias
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# 4. Configurar entorno
cp .env.production.example .env
nano .env  # Llenar DB_PASSWORD, APP_KEY se genera abajo
php artisan key:generate
php artisan storage:link

# 5. Migraciones y seeds
php artisan migrate --force
php artisan db:seed --force

# 6. Permisos
sudo chown -R deployer:www-data /var/www/ultimamilla
sudo chmod -R 755 /var/www/ultimamilla
sudo chmod -R 775 /var/www/ultimamilla/storage /var/www/ultimamilla/bootstrap/cache

# 7. Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 8. Health check
php artisan ultimamilla:health
```

### Nginx

Crear `/etc/nginx/sites-available/ultimamilla.deploytive.com` (ver docs/PROMPT-FASE-9.md para config completa), luego:

```bash
sudo ln -s /etc/nginx/sites-available/ultimamilla.deploytive.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d ultimamilla.deploytive.com
```

### Supervisor (Horizon)

Crear `/etc/supervisor/conf.d/ultimamilla-horizon.conf`:

```ini
[program:ultimamilla-horizon]
process_name=%(program_name)s
command=php /var/www/ultimamilla/artisan horizon
autostart=true
autorestart=true
user=deployer
redirect_stderr=true
stdout_logfile=/var/www/ultimamilla/storage/logs/horizon.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl start ultimamilla-horizon
```

### Cron (scheduler)

```bash
crontab -e
# Agregar:
# * * * * * cd /var/www/ultimamilla && php artisan schedule:run >> /dev/null 2>&1
```

### Deploys futuros

```bash
# En tu máquina local:
git push origin main

# En el VPS:
./deploy.sh
```

### Sudoers para deploy sin password

```bash
sudo visudo -f /etc/sudoers.d/ultimamilla-deploy
# Contenido:
# deployer ALL=(ALL) NOPASSWD: /usr/bin/supervisorctl restart ultimamilla-horizon
# deployer ALL=(ALL) NOPASSWD: /bin/systemctl reload php8.3-fpm
```
