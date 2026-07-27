# Libros fiscales de compras y ventas

Los libros fiscales almacenan documentos en `fiscal_documents`, separados tanto de los XML importados en el Centro FEL como de las pólizas contables. La creación, edición o anulación de un documento fiscal no crea asientos ni modifica libros contables.

## Seguridad

La empresa se obtiene exclusivamente de `session('company_id')`. Los controladores verifican asociación activa, tenant y dirección del libro, y cada consulta incluye `company_id`. Los períodos y pólizas opcionales también deben pertenecer a la empresa activa.

## Reglas

- Los importes se almacenan positivos; las notas de crédito se restan únicamente al calcular reportes.
- Las notas de débito y documentos normales suman.
- Los documentos anulados se conservan y no participan en totales.
- Los períodos cerrados bloquean creación, edición y anulación.
- Pequeño contribuyente y exento requieren IVA cero; pequeño contribuyente no genera crédito fiscal.
- La suma de componentes admite una diferencia máxima de Q0.02 frente al total.

## Instalación y verificación

```bash
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan test --filter=FiscalBooksTest
```

`FiscalDocumentDemoSeeder` es opcional y nunca se invoca desde `DatabaseSeeder`.

La determinación final de IVA por pagar o saldo a favor, la declaración, la confrontación contable y la conexión directa con SAT quedan fuera de esta fase.

## Integración con FEL

Los libros reciben automáticamente los documentos reconocidos por el importador FEL existente. El vínculo se conserva mediante `fiscal_documents.fel_document_id`, mientras el UUID funciona como clave de idempotencia por empresa. Los documentos con datos insuficientes, operación desconocida, moneda sin tipo de cambio, conflicto manual o período cerrado quedan observados y no generan registros fiscales incompletos.

Consulte [FEL_FISCAL_SYNC.md](FEL_FISCAL_SYNC.md) para el mapeo, comandos y limitaciones.

Esta integración utiliza el importador FEL existente. No crea otro flujo de carga de archivos.

Los documentos fiscales se generan automáticamente, pero no se crean pólizas ni asientos contables.
