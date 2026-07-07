# 00_PROJECT_VISION.md

# Proyecto Conta

## Visión

Conta es un sistema ERP de contabilidad desarrollado en Laravel, diseñado inicialmente para empresas y contadores de Guatemala.

El objetivo es ofrecer una plataforma moderna, segura y escalable que permita administrar múltiples empresas desde una sola instalación, automatizando procesos contables, fiscales, administrativos y financieros.

El sistema será desarrollado con una arquitectura modular para facilitar su mantenimiento, escalabilidad y futura comercialización bajo el modelo SaaS (Software as a Service).

---

# Objetivos

## Objetivo General

Desarrollar un ERP contable moderno que simplifique la administración financiera de empresas guatemaltecas mediante la automatización de procesos contables, tributarios y administrativos.

## Objetivos específicos

- Administrar múltiples empresas desde una sola plataforma.
- Gestionar usuarios, roles y permisos por empresa.
- Automatizar la generación de partidas contables.
- Administrar compras, ventas e inventario.
- Controlar caja y bancos.
- Generar libros contables autorizados.
- Generar estados financieros.
- Facilitar el cumplimiento de obligaciones tributarias.
- Preparar la integración con FEL y SAT.
- Comercializar el sistema como plataforma SaaS.

---

# Público objetivo

Inicialmente el sistema estará orientado a:

- Contadores independientes.
- Oficinas contables.
- Pequeñas empresas.
- Medianas empresas.
- Comercios.
- Empresas de servicios.

---

# Alcance Inicial

Primera versión (MVP)

- Multiempresa
- Multiusuario
- Roles y permisos
- Clientes
- Proveedores
- Productos
- Inventario
- Compras
- Ventas
- IVA
- Contabilidad
- Reportes

---

# Alcance Futuro

Después del MVP podrán agregarse módulos como:

- Nómina
- Activos fijos
- CRM
- Recursos Humanos
- POS avanzado
- API pública
- Aplicación móvil
- Facturación electrónica completa
- Inteligencia de negocios
- Integración bancaria

---

# Tecnologías

Backend

- Laravel 12
- PHP 8.4+
- MySQL
- Spatie Laravel Permission
- Laravel Breeze

Frontend

- Blade
- boostrap 5
- JavaScript

Herramientas
Git
GitHub
Composer
NPM
Vite
Visual Studio Code
Cursor (cuando lo utilices)
ChatGPT (Arquitectura y desarrollo)

---

# Arquitectura

El sistema seguirá una arquitectura modular basada en dominios de negocio.

Cada módulo será independiente y seguirá las convenciones oficiales de Laravel.

Toda la lógica de negocio será implementada mediante Services, Policies y Form Requests, evitando lógica compleja en controladores.

---

# Multiempresa

El sistema soportará múltiples empresas.

Un mismo usuario podrá pertenecer a varias empresas.

Cada empresa tendrá:

- Usuarios
- Clientes
- Proveedores
- Productos
- Inventario
- Compras
- Ventas
- Contabilidad

Los datos estarán completamente aislados entre empresas.

---

# Mercado objetivo

El sistema será desarrollado inicialmente para Guatemala.

Configuraciones iniciales:

- País: Guatemala
- Moneda: GTQ
- Idioma: Español
- Zona Horaria: America/Guatemala

Posteriormente podrá adaptarse para otros países.

---

# Filosofía del Proyecto

El proyecto prioriza:

- Código limpio.
- Arquitectura escalable.
- Seguridad.
- Rendimiento.
- Mantenibilidad.
- Modularidad.
- Buenas prácticas de Laravel.

No se desarrollará únicamente para cumplir una necesidad académica, sino con la intención de convertirse en un producto profesional listo para producción.

---

# Inspiración

El proyecto toma como referencia funcional diversos sistemas ERP y contables utilizados en Guatemala.

La implementación será completamente original, utilizando una arquitectura moderna basada en Laravel y siguiendo estándares de desarrollo profesional.

---

# Estado del Proyecto

Fase actual:

Sprint 1

Infraestructura y Arquitectura

Estado:

🟢 En desarrollo