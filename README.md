# ERP Conta

ERP Conta es un SaaS contable multiempresa desarrollado con Laravel 12. Cada usuario puede trabajar con una empresa activa, mantener su catálogo de cuentas, registrar pólizas de doble partida y consultar libros, reportes y un dashboard financiero sin mezclar información entre empresas.

La empresa activa se conserva en `session('company_id')`. El middleware `SetCompany` comprueba que dicha empresa pertenezca al usuario y sincroniza el identificador de equipo utilizado por Spatie Laravel Permission.

## Funcionalidades implementadas

- Registro, inicio de sesión, recuperación de contraseña y perfil de usuario.
- Creación, listado y selección de empresas asociadas al usuario.
- Catálogo contable jerárquico por empresa, con cuentas de encabezado y de movimiento.
- Creación opcional de un catálogo base al registrar una empresa.
- Períodos contables abiertos y cerrados, con validación de traslapes.
- Pólizas contables en estados Borrador, Contabilizada y Anulada.
- Validación de cuentas, fechas, doble partida, Debe y Haber.
- Numeración correlativa de pólizas contabilizadas por empresa y período.
- Libro Diario, Libro Mayor y Balance de Comprobación.
- Estado de Resultados y Balance General.
- Auditoría de cuentas de movimiento con saldo contrario a su naturaleza.
- Cierre operativo de períodos con registro de fecha y usuario.
- Bloqueo de creación, edición, eliminación, contabilización y anulación de pólizas en períodos cerrados.
- Dashboard con ingresos, gastos, utilidad o pérdida, activos, gráfica mensual y actividad reciente.
- Interfaz global responsive basada en Bootstrap 5.
- Aislamiento de consultas y operaciones mediante la empresa activa.

## Tecnologías

- PHP `^8.2`
- Laravel `^12.0`
- Laravel Breeze
- Blade y Alpine.js
- Bootstrap `^5.3.8`
- Bootstrap Icons `^1.13.1`
- Chart.js `^4.5.1`
- Vite `^6.0.11`
- Spatie Laravel Permission `^6.25` con equipos habilitados
- MySQL para el entorno local descrito en esta guía
- PHPUnit 11 para pruebas automatizadas

## Requisitos en Windows con XAMPP

- Windows con XAMPP, PHP 8.2 o superior y el servicio MySQL iniciado. Apache solo es necesario si se decide servir el proyecto mediante XAMPP en lugar de `php artisan serve`.
- Extensiones PHP requeridas por Laravel habilitadas, incluyendo Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO MySQL, Session, Tokenizer y XML.
- Composer disponible desde PowerShell o Símbolo del sistema.
- Node.js y npm instalados.
- Git, si el proyecto se obtiene desde un repositorio.
- Una base de datos MySQL vacía creada para el proyecto.

Comprueba las herramientas desde la terminal:

```powershell
php -v
composer --version
node --version
npm --version
```

Si `php` no está disponible globalmente, utiliza el ejecutable de PHP incluido en XAMPP o agrega su directorio al `PATH` de Windows.

## Instalación

1. Abre PowerShell en el directorio del proyecto.
2. Instala las dependencias PHP:

   ```powershell
   composer install
   ```

3. Crea el archivo de entorno:

   ```powershell
   Copy-Item .env.example .env
   ```

4. Genera la clave de la aplicación:

   ```powershell
   php artisan key:generate
   ```

5. Crea una base de datos MySQL vacía desde phpMyAdmin o el cliente MySQL.
6. Configura `.env` como se indica en la siguiente sección.
7. Ejecuta las migraciones:

   ```powershell
   php artisan migrate
   ```

8. Instala las dependencias del frontend:

   ```powershell
   npm install
   ```

9. Inicia Laravel y Vite.

## Configuración de `.env` para MySQL

`.env.example` utiliza SQLite como valor inicial del esqueleto Laravel. Para trabajar con MySQL en XAMPP, cambia únicamente la configuración local de `.env` y utiliza los datos creados para tu instalación:

```dotenv
APP_NAME="ERP Conta"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

APP_LOCALE=es
APP_FALLBACK_LOCALE=es

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=nombre_base_contable
DB_USERNAME=usuario_mysql
DB_PASSWORD=contraseña_mysql
```

No publiques `.env`, `APP_KEY` ni credenciales. Los valores anteriores son marcadores y deben sustituirse por la configuración local real.

El proyecto usa por defecto sesiones, caché y colas respaldadas por base de datos. Las migraciones incluidas crean las tablas necesarias para esos controladores.

## Migraciones y datos iniciales

Las migraciones crean, entre otras, las siguientes tablas funcionales:

- `users`, `password_reset_tokens` y `sessions`
- `cache` y `cache_locks`
- `jobs`, `job_batches` y `failed_jobs`
- `tenants`
- `companies` y `company_user`
- `accounts`
- `accounting_periods`
- `journal_entries` y `journal_entry_lines`
- Tablas de roles y permisos de Spatie

Para crear o actualizar el esquema:

```powershell
php artisan migrate
```

`DatabaseSeeder` no ejecuta seeders automáticamente en el estado actual. El flujo recomendado para datos iniciales es:

1. Registrar un usuario desde la aplicación.
2. Crear una empresa.
3. Mantener seleccionada la opción **Crear catálogo contable base**.

Existe `AccountSeeder`, que trabaja únicamente sobre la primera empresa encontrada, y existe `RolesAndPermissionsSeeder` con una propuesta preliminar. No deben tratarse como configuración definitiva de producción.

> La definición final de roles, permisos y responsabilidades está pendiente de validación con el cliente.

## Comandos habituales

```powershell
# Dependencias PHP
composer install

# Dependencias y assets frontend
npm install
npm run dev
npm run build

# Laravel
php artisan key:generate
php artisan migrate
php artisan migrate:status
php artisan route:list
php artisan optimize:clear
php artisan test
```

El script definido en Composer puede iniciar servidor, cola, visor de logs y Vite en una sola terminal:

```powershell
composer run dev
```

## Iniciar el proyecto

Opción simple, usando dos terminales:

```powershell
# Terminal 1
php artisan serve

# Terminal 2
npm run dev
```

Abre `http://127.0.0.1:8000`, registra un usuario y crea la primera empresa.

Para validar los assets destinados a producción:

```powershell
npm run build
```

## Pruebas

La configuración de PHPUnit utiliza SQLite en memoria, aislada de la base MySQL local:

```powershell
php artisan test
```

Las pruebas cubren autenticación, empresa activa, aislamiento multiempresa, pólizas, reportes, dashboard, auditoría contable y cierre de períodos.

## Limitaciones actuales

- No existe consolidación de “Todas las empresas”; se trabaja con una empresa activa.
- No se implementó reapertura de períodos cerrados.
- El cierre no genera una póliza automática ni traslada la utilidad a resultados acumulados.
- No se implementaron exportaciones contables a Excel o PDF.
- Los seeders de empresa y tenant no generan datos.
- `DatabaseSeeder` no carga información inicial automáticamente.
- El envío real de correo depende de configurar el proveedor de correo; `.env.example` registra los mensajes en el log.
- La administración integral de roles y permisos todavía no está conectada como flujo funcional definitivo.
- **Los roles, permisos y alcances de cada perfil están pendientes de definición con el cliente.**

## Documentación adicional

- [Manual de usuario](docs/manual-usuario.md)
- [Guía de demostración](docs/demostracion.md)
