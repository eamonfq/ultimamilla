# Guía completa: testing en WSL2 + Laragon (Windows)

## 1. Arquitectura del entorno

```
Windows host
├── Laragon (C:\laragon\)
│   ├── Apache/Nginx → sirve PHP en :80
│   ├── PHP CLI       → php.exe
│   ├── MySQL         → :3306
│   └── www\          → tus proyectos
│
└── WSL2 (Ubuntu)
    ├── Node 22 (nvm)  → build tools, Vite, npm scripts
    ├── git/bash       → control de versiones, scripts shell
    └── ve los archivos en /mnt/c/laragon/www/
```

**Misma carpeta, dos sistemas accediéndola simultáneamente**: `C:\laragon\www\proyecto` ↔ `/mnt/c/laragon/www/proyecto`. Edita en cualquiera, ambos lo ven.

## 2. Reparto de responsabilidades

| Tarea | Dónde corre | Por qué |
|---|---|---|
| **Servir PHP** (Laravel, WordPress, scripts sueltos) | Laragon (Windows) | Laragon ya tiene Apache + PHP + MySQL configurados con auto-virtual-hosts |
| **MySQL / MariaDB** | Laragon | Listo desde el panel; puerto 3306 expuesto |
| **`composer install` / artisan** | Laragon terminal o WSL (cualquiera funciona) | Composer es PHP, no tiene binarios nativos; sin riesgo |
| **`npm install` / build de frontend** | **Solo WSL** | Para evitar el problema de binarios nativos (Rollup/esbuild) |
| **Vite dev server** | WSL | El puerto `localhost:5133` funciona directo en navegador de Windows |
| **git, bash scripts, sed/grep/find** | WSL | Linux tiene mejores herramientas de línea de comandos |

## 3. Laragon: levantar el entorno PHP

1. Abrir Laragon → **Start All** (Apache + MySQL arrancan)
2. Auto-virtual-hosts: cualquier carpeta en `C:\laragon\www\miapp` queda accesible en `http://miapp.test` (Laragon edita el hosts file automático)
3. Panel de Laragon → **Menu → Apache → sites-enabled** para revisar configs

### Probar un script PHP suelto

```
C:\laragon\www\test\index.php
```
```php
<?php phpinfo();
```
Abrir `http://test.test` en el navegador. Listo.

### Probar PHP por CLI (desde WSL también funciona)

WSL puede llamar al `php.exe` de Laragon directamente:
```bash
# Desde WSL, agregar Laragon a PATH (una vez en ~/.bashrc):
export PATH="$PATH:/mnt/c/laragon/bin/php/php-8.3.0-Win32-vs16-x64"

php -v
php script.php
```

O usar el PHP de Linux (apt):
```bash
sudo apt install php-cli php-mysql php-xml php-mbstring
```

## 4. MySQL: acceso desde ambos lados

### Desde Windows (HeidiSQL viene con Laragon)
- Host: `127.0.0.1`, user: `root`, password: vacío

### Desde WSL
```bash
sudo apt install mysql-client
mysql -h 127.0.0.1 -u root
```

### Desde código PHP en Laragon
```php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=midb', 'root', '');
```

## 5. WSL: Node.js para frontend

Setup una sola vez:
```bash
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
source ~/.nvm/nvm.sh
nvm install 22
echo 'source ~/.nvm/nvm.sh && nvm use 22 2>/dev/null' >> ~/.bashrc
```

Cada proyecto:
```bash
cd /mnt/c/laragon/www/proyecto
npm install
npm run dev    # Vite arranca; abrir el puerto que diga (este proyecto: 5133)
```

**Regla**: `npm install` SIEMPRE desde WSL. Si alguna vez se mezcla, `rm -rf node_modules package-lock.json && npm install`.

## 6. Workflow típico de un proyecto fullstack (Laravel + Vite)

Terminal **Laragon** (PHP):
```bash
cd C:\laragon\www\miapp
composer install
php artisan migrate
php artisan serve     # opcional; Laragon ya sirve via Apache
```

Terminal **WSL** (frontend):
```bash
cd /mnt/c/laragon/www/miapp
npm install
npm run dev           # Vite con HMR
```

Navegador:
- App PHP: `http://miapp.test` (Apache/Laragon)
- Vite assets: inyectados automático vía `@vite()` directive

## 7. URLs y puertos típicos

| Servicio | URL |
|---|---|
| Sitio PHP (Laragon) | `http://<carpeta>.test` |
| MySQL | `127.0.0.1:3306` |
| Vite dev (WSL) | `http://localhost:5133` |
| phpMyAdmin | `http://localhost/phpmyadmin` |
| Mailpit (Laragon) | `http://localhost:8025` |

WSL2 mapea `localhost` automáticamente al host Windows, no necesitas IP especial.

## 8. Probar un script PHP rápido (sandbox)

```bash
mkdir -p C:\laragon\www\sandbox
echo "<?php var_dump([1,2,3]);" > C:\laragon\www\sandbox\test.php
```
Abrir `http://sandbox.test/test.php`. Listo.

O por CLI desde WSL con PHP de Linux:
```bash
echo '<?php var_dump([1,2,3]);' | php
```

## 9. Probar APIs / endpoints

```bash
# Desde WSL
curl http://miapp.test/api/users
curl -X POST http://miapp.test/api/login -d 'user=foo&pass=bar'

# JSON pretty con jq
curl -s http://miapp.test/api/users | jq
```

## 10. Cosas que se rompen seguido

| Problema | Fix |
|---|---|
| `Cannot find module @rollup/rollup-linux-...` | `rm -rf node_modules package-lock.json && npm install` (desde WSL) |
| `nvm: command not found` en WSL | `source ~/.nvm/nvm.sh` |
| `http://miapp.test` no resuelve | Laragon → Menu → Quick app → Add to hosts; o reiniciar Laragon |
| MySQL no conecta | Laragon → Start All; revisar puerto 3306 libre |
| Permisos raros en archivos editados desde WSL | `chmod -R 755 .` (no afecta Windows pero limpia atributos WSL) |
| Cambios de `.env` no aplican | Laravel: `php artisan config:clear` |
| `git pull` lento en `/mnt/c/...` | Es normal: el filesystem cruzado es 10x más lento que el nativo de WSL. Para repos grandes, clonar en `~/proyectos/` dentro de WSL |

## 11. Editor

VS Code conecta a ambos lados:
- Abierto en Windows: edita archivos de `C:\laragon\www\...` directo
- Con extensión **WSL**: `code .` desde WSL abre VS Code conectado a Linux, terminal integrada usa bash WSL

## 12. Checklist al empezar a "probar y probar"

```bash
# 1. Laragon corriendo (Start All)
# 2. WSL terminal:
cd /mnt/c/laragon/www/<proyecto>
source ~/.nvm/nvm.sh && nvm use 22
npm install                     # solo si node_modules no existe o falló

# 3. Si es Laravel/PHP también:
#    - Otra terminal Laragon: composer install / php artisan migrate
# 4. Levantar dev:
npm run dev                     # frontend
# y/o abrir http://<proyecto>.test en navegador
```

A partir de ahí: editar → recargar navegador → repetir. HMR de Vite y Apache hacen el resto.
