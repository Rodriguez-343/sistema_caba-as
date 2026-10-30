# Cabañas El Capitán

Sistema administrativo en Laravel + Blade + Bootstrap 5 + Vite.

## Requisitos

- PHP 8.2+
- Composer
- Node.js 20+ y npm
- MySQL 8+

## Configurar entorno (`.env`)

1. Copia el archivo de ejemplo:

```bash
copy .env.example .env
```

2. Edita `.env` y define al menos:

```dotenv
APP_NAME="Cabanas El Capitan"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sistema_cabanas
DB_USERNAME=tu_usuario_mysql
DB_PASSWORD=tu_password_mysql
```

3. Genera la clave de la aplicación:

```bash
php artisan key:generate
```

## Instalación en máquina host

```bash
composer install
npm ci --ignore-scripts
php artisan config:clear
php artisan cache:clear
```

## Migraciones

Ejecuta migraciones sobre la base configurada en `.env`:

```bash
php artisan migrate --force
```

Verificar estado:

```bash
php artisan migrate:status
```

## Build de frontend

Compilar assets para producción:

```bash
npm run build
```

Modo desarrollo (watch):

```bash
npm run dev
```

## Ejecutar aplicación

```bash
php artisan serve
```

## Pruebas automáticas

Las pruebas usan Pest con SQLite en memoria; no tocan la base de datos MySQL ni necesitan compilar el frontend.

```bash
php artisan test
```

Cubren el cálculo de tarifas, la disponibilidad de cabañas, el ciclo de vida de las reservaciones, el inicio de sesión y los permisos por rol.

## Despliegue en Ubuntu Server

Guía para producción con Nginx, PHP-FPM y MySQL. Ajusta `tu-dominio`, la versión de PHP (8.2 o superior) y la ruta `/var/www/cabanas` a tu servidor.

### 1. Paquetes

```bash
sudo apt update
sudo apt install nginx mysql-server php-fpm php-cli php-mysql php-mbstring php-xml php-curl php-zip php-intl php-bcmath unzip git composer nodejs npm
```

### 2. Código y dependencias

```bash
cd /var/www/cabanas
composer install --no-dev --optimize-autoloader   # --no-dev es obligatorio en producción
npm ci --ignore-scripts && npm run build
cp .env.example .env
php artisan key:generate
```

### 3. `.env` de producción

Cambia al menos estos valores respecto a `.env.example` (la base de datos y su usuario van aparte):

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio
LOG_LEVEL=warning
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

`APP_DEBUG=false` es obligatorio: con `true` los errores muestran rutas, consultas y datos de configuración.
`SESSION_SECURE_COOKIE=true` solo si el sitio se sirve por HTTPS (por ejemplo con Certbot).

### 4. Base de datos, permisos y caché

```bash
php artisan migrate --force
php artisan usuarios:crear-administrador nombre_de_tu_administrador
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R ug+rwX storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

> **Primer administrador.** Las migraciones solo cargan roles y permisos; no crean ninguna cuenta ni publican contraseñas. La primera cuenta administradora se crea con `usuarios:crear-administrador` (pide la contraseña sin mostrarla, mínimo 12 caracteres). Si una instalación anterior ya había creado la cuenta `admin` con la contraseña inicial, `php artisan migrate` la desactiva automáticamente.

Tras cada cambio de `.env` o de código ejecuta `php artisan optimize:clear` y vuelve a generar las cachés.

### 5. Nginx

El directorio raíz debe ser `public/`. Ejemplo mínimo (`/etc/nginx/sites-available/cabanas`):

```nginx
server {
    server_name tu-dominio;
    root /var/www/cabanas/public;
    index index.php;
    client_max_body_size 5M;

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location ~ /\.(?!well-known) { deny all; }
}
```

### 6. Tareas programadas y cola (obligatorio)

El sistema necesita dos procesos en segundo plano. Sin ellos **no** se finalizan automáticamente las estancias vencidas ni se sincronizan los calendarios iCal.

**Programador de tareas**: ejecuta `crontab -e -u www-data` y agrega:

```cron
* * * * * cd /var/www/cabanas && php artisan schedule:run >> /dev/null 2>&1
```

**Trabajador de la cola** (`QUEUE_CONNECTION=database`): crea `/etc/systemd/system/cabanas-cola.service`:

```ini
[Unit]
Description=Cola de Cabañas El Capitán
After=network.target mysql.service

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/cabanas/artisan queue:work --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl enable --now cabanas-cola
```

Después de cada despliegue reinicia el trabajador: `php artisan queue:restart`.

### 7. Respaldos

Respalda a diario la base de datos (`mysqldump`) y la carpeta `storage/app/cabanas`, donde se guardan las imágenes privadas de las cabañas. El archivo `.env` también debe respaldarse, pero en un lugar seguro y aparte.

### 8. Verificación

- `curl -I https://tu-dominio/up` responde 200.
- `php artisan schedule:list` muestra `calendario:sincronizar` y `reservaciones:finalizar-vencidas`.
- `systemctl status cabanas-cola` aparece activo.
