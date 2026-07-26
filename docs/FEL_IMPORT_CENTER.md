# Centro de Importación FEL

El módulo importa XML, ZIP, XLSX, CSV y hojas XLS basadas en XML/HTML hacia una bandeja previa a contabilidad. Los originales se conservan en el disco privado configurado por `FEL_DISK` (por defecto `storage/app/private`) bajo `fel/companies/{uuid-empresa}/{año}/{mes}`. Los ZIP también se conservan para auditoría; los temporales extraídos se eliminan siempre.

## Instalación

1. Respaldar la base de datos.
2. Revisar las migraciones `2026_07_26_000000` a `2026_07_26_000004`.
3. Ejecutar `php artisan migrate` en el entorno deseado.
4. Compilar assets con `npm run build` si corresponde.

No se instaló una dependencia nueva. Se intentó `composer require maatwebsite/excel --no-interaction --no-scripts`, pero Composer revirtió el cambio porque PHP no tiene habilitada `ext-gd`. CSV y XLSX se procesan con lectores nativos por streaming. Para XLS binario antiguo, habilite GD y luego instale una versión compatible de `maatwebsite/excel`; mientras tanto, guarde esos archivos como XLSX o CSV. SpreadsheetML/HTML con extensión XLS sí está admitido.

## Seguridad y límites

Los límites son configurables en `config/fel.php`. XML rechaza DTD y entidades, usa `LIBXML_NONET`, ZIP bloquea rutas absolutas y `..`, y todas las consultas/acciones usan la empresa activa validada por `SetCompany`. Ninguna ruta acepta `company_id` del formulario.

## Alcance contable

La importación, aprobación, observación y rechazo no crean pólizas, no escriben movimientos y no modifican saldos ni libros. `journal_entry_id` permanece nulo; se reserva únicamente para una fase posterior.
