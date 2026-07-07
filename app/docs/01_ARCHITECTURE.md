# Arquitectura del Proyecto

## Propósito

Este documento define la arquitectura oficial del ERP Conta.

Todas las decisiones de diseño, estructura y desarrollo deberán seguir las reglas aquí descritas para mantener un código limpio, consistente y fácil de mantener.

---

# Objetivos

La arquitectura del proyecto busca:

- Facilitar el crecimiento del sistema.
- Reducir deuda técnica.
- Mantener separación de responsabilidades.
- Permitir que nuevos desarrolladores comprendan rápidamente el proyecto.
- Mantener una base sólida para un ERP de nivel profesional.

---

# Principios

El proyecto seguirá los siguientes principios.

## SOLID

- Single Responsibility Principle
- Open / Closed Principle
- Liskov Substitution Principle
- Interface Segregation Principle
- Dependency Inversion Principle

## Clean Code

Todo el código deberá priorizar:

- Legibilidad
- Simplicidad
- Reutilización
- Consistencia

---

# Arquitectura General

El flujo de una petición será el siguiente.

```
Request
    │
Route
    │
Middleware
    │
Controller
    │
Form Request
    │
Policy
    │
Service
    │
Repository (cuando sea necesario)
    │
Model
    │
Database
```

Cada capa tiene una única responsabilidad.

---

# Responsabilidades

## Controller

Responsable de:

- recibir la petición
- llamar al Service
- devolver la respuesta

Nunca debe contener:

- reglas de negocio
- consultas complejas
- validaciones

---

## Form Request

Responsable de validar la información recibida.

Ejemplo:

CreateCompanyRequest

UpdateCompanyRequest

---

## Policy

Responsable de la autorización.

Siempre utilizar Policies y Spatie Permission.

Nunca validar permisos manualmente.

Correcto

```php
$this->authorize('update', $company);
```

Incorrecto

```php
if ($user->role == 'admin')
```

---

## Service

Responsable de la lógica del negocio.

Ejemplos

CompanyService

SaleService

AccountingService

Los Services deben ser pequeños y especializados.

---

## Repository

Solo será utilizado cuando exista una consulta compleja o reutilizable.

No utilizaremos Repository únicamente por seguir un patrón.

Eloquent será suficiente para la mayoría de consultas.

---

## Model

Los modelos representan entidades.

Deben contener únicamente:

- relaciones
- scopes
- accessors
- mutators
- casts

No deben contener reglas de negocio complejas.

---

# Organización de Services

No existirá un único directorio lleno de clases.

Incorrecto

```
Services/

CompanyService.php
SaleService.php
ClientService.php
PurchaseService.php
AccountingService.php
...
```

Correcto

```
Services/

Company/
    CreateCompanyService.php
    UpdateCompanyService.php
    DeleteCompanyService.php

Sales/
    CreateSaleService.php
    CancelSaleService.php

Accounting/
    CreateJournalEntryService.php
```

Cada clase tendrá una única responsabilidad.

---

# Organización del proyecto

```
app/

Actions/

DTOs/

Enums/

Exceptions/

Helpers/

Http/
    Controllers/
    Middleware/
    Requests/
    Resources/

Models/

Policies/

Repositories/

Services/

Traits/

ViewModels/
```

Las carpetas serán creadas únicamente cuando sean necesarias.

---

# Convenciones

## Código

Todo el código estará escrito en inglés.

Ejemplos

Company

Client

Supplier

Sale

Purchase

Warehouse

InventoryMovement

---

## Base de datos

Las tablas estarán en inglés.

companies

clients

sales

products

---

## Interfaz

Toda la interfaz estará en español.

Empresas

Clientes

Productos

Ventas

Compras

---

## Permisos

Siempre utilizar

```
module.action
```

Ejemplo

```
companies.view
companies.create
companies.update
companies.delete
```

---

# Multiempresa

El sistema será Multi Tenant.

Un usuario podrá pertenecer a varias empresas.

Cada empresa tendrá sus propios:

- usuarios
- clientes
- proveedores
- productos
- inventario
- compras
- ventas
- reportes

Los datos estarán completamente aislados.

---

# UUID

Las entidades principales utilizarán:

- id
- uuid

El UUID será utilizado para exposición pública.

---

# Soft Deletes

Se utilizarán Soft Deletes en todas las entidades principales.

Ejemplos

Companies

Users

Clients

Suppliers

Products

---

# Convenciones para Base de Datos

Toda tabla deberá tener:

- Primary Key
- UUID (cuando aplique)
- Foreign Keys
- Índices
- Soft Deletes (cuando aplique)
- Timestamps

---

# Flujo de desarrollo

Antes de iniciar un módulo:

1. Documentación
2. Diseño
3. Base de datos
4. Modelo
5. Relaciones
6. Permisos
7. Policy
8. Requests
9. Services
10. Controller
11. Views
12. Pruebas
13. Actualizar documentación

---

# Definition of Done

Un módulo únicamente estará terminado cuando tenga:

- Migraciones
- Modelos
- Relaciones
- Policies
- Requests
- Services
- Controllers
- CRUD
- Permisos
- Pruebas
- Documentación

---

# Tecnologías

Backend

- Laravel 12
- PHP 8.4+
- MySQL
- Spatie Permission

Frontend

- Blade
- Bootstrap 5
- JavaScript

Herramientas

- Git
- GitHub
- Composer
- NPM
- Vite
- Visual Studio Code

---

# Decisiones Arquitectónicas

- Laravel será el framework principal.
- Bootstrap será el framework CSS oficial del proyecto.
- Se utilizará Blade como motor de plantillas.
- La autenticación utilizará Laravel Breeze.
- Los permisos serán gestionados con Spatie Permission.
- El sistema será Multiempresa utilizando Teams.
- La lógica del negocio vivirá en Services.
- Los controladores permanecerán delgados.
- El proyecto estará orientado inicialmente al mercado de Guatemala.

---

# Mejoras Futuras

La arquitectura permitirá agregar posteriormente:

- API REST
- Aplicación móvil
- Integraciones con SAT
- Importación automática de FEL
- Webhooks
- Microservicios (si algún día fueran necesarios)