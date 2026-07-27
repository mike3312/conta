# Sincronización FEL con libros fiscales

Esta integración utiliza el importador FEL existente. No crea otro flujo de carga de archivos.

Los documentos fiscales se generan automáticamente, pero no se crean pólizas ni asientos contables.

## Flujo

`FelDocumentImportService` conserva su responsabilidad actual de procesar XML, ZIP, XLS, XLSX y CSV. Después de guardar o enriquecer `FelDocument`, delega el mapeo a `FelFiscalDocumentSyncService`. El adaptador aplica las reglas de `FiscalDocumentService`, vincula ambos registros y devuelve un resultado controlado: creado, actualizado, sin cambios, observado o conflicto.

La sincronización también se ejecuta en duplicados FEL y después de una reclasificación explícita. `FULL_DETAIL` tiene prioridad sobre `SUMMARY`; un resumen nunca reemplaza un XML completo.

## Mapeo

- `PURCHASE` alimenta Libro de Compras y usa al emisor como tercero.
- `SALE` alimenta Libro de Ventas y usa al receptor como tercero.
- `UNKNOWN` queda observado hasta su reclasificación.
- `FACT`, `FCAM`, `NCRE`, `NDEB`, `FPEQ` y `FESP` se mapean mediante `config/fiscal_fel.php`.
- Tipos desconocidos se conservan como `OTHER` con advertencia.
- Combustible se clasifica `FUEL`.
- `PEQ` y `FPEQ` se clasifican `SMALL_TAXPAYER`, sin crédito fiscal.
- Hospedaje se clasifica provisionalmente como `SERVICES` con advertencia.
- Documentos generales se clasifican `OTHER`; no se presume bienes o servicios.
- Las ventas nunca generan crédito fiscal. Las compras tampoco lo reciben automáticamente sin una regla confiable.

Los importes se redondean de seis a dos decimales con aritmética decimal basada en strings. No se traslada una diferencia a exento o no afecto para forzar la conciliación. Los resúmenes tabulares se incorporan únicamente cuando contienen datos suficientes.

## Estados y anulaciones

Solo `fel_documents.fiscal_status` controla la anulación fiscal. Los estados humanos aprobado, observado y rechazado no cambian el estado legal del libro.

El parser existente reconoce XML de anulación por `NumeroDocumentoAAnular`. Si el DTE original existe, actualiza FEL y el documento fiscal vinculado a `VOIDED`. Si la anulación llega primero, se registra una fila observada sin crear encabezados incompletos.

Los documentos anulados permanecen visibles, pero `FiscalBooksService` los excluye de los totales activos.

## Períodos y moneda

Se asigna automáticamente un único período abierto de la misma empresa que contenga la fecha de emisión. Un período cerrado deja la sincronización observada y no se modifica. Sin período se permite el documento sin asociación, dejando advertencia.

GTQ utiliza tasa 1. Una moneda extranjera sin tasa confiable queda observada; nunca se inventa una tasa 1.

## Duplicados y conflictos

La detección prioriza `fel_document_id` y después UUID normalizado dentro de la empresa. Un documento manual con el mismo UUID no se sobrescribe ni vincula silenciosamente. Los UUID se normalizan sin modificar masivamente los registros históricos.

## Trazabilidad

`source_metadata` guarda versión del mapeo, lote, nivel de detalle, usuario importador, usuario sincronizador, fecha, acción, cálculos y advertencias. El XML completo permanece exclusivamente en almacenamiento privado FEL.

## Backfill

Vista previa obligatoria recomendada:

```bash
php artisan fiscal:sync-fel --dry-run
```

Ejecución posterior, únicamente después de revisar el resumen:

```bash
php artisan fiscal:sync-fel --no-interaction
```

Opciones: `--company`, `--document`, `--only-missing`, `--force-update`, `--dry-run` y `--chunk`.

Diagnóstico de solo lectura:

```bash
php artisan fiscal:diagnose-fel-sync
```

## Limitaciones

- El parser actual no separa de forma universal montos exentos y no afectos.
- Los resúmenes tabulares pueden carecer de base imponible.
- No se determina automáticamente el derecho a crédito fiscal.
- No existe cálculo final de IVA, conexión SAT ni conciliación automática de anulaciones recibidas antes del DTE.
- No se crean pólizas y `journal_entry_id` permanece `null` en documentos sincronizados.
