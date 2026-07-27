# Centro de Importación FEL

El módulo importa XML, ZIP, XLSX, CSV y hojas XLS basadas en XML/HTML hacia una bandeja previa a contabilidad. Los originales se conservan en el disco privado configurado por `FEL_DISK` bajo `fel/companies/{uuid-empresa}/{año}/{mes}`. Los temporales extraídos de ZIP se eliminan al terminar el lote.

## Instalación

1. Respaldar la base de datos.
2. Ejecutar `php artisan migrate`.
3. Ejecutar `php artisan optimize:clear`.
4. Ejecutar `php artisan test --filter=Fel`.

No se instaló una dependencia nueva. CSV y XLSX se procesan con lectores nativos por streaming. XLSX se valida internamente como paquete Open XML y puede llegar desde Windows con MIME `application/zip` u `application/octet-stream`.

El formato XLS binario antiguo no está soportado: debe guardarse como XLSX o CSV. SpreadsheetML/HTML con extensión XLS sí está admitido. Un XLS binario nunca se marca como importado.

## Diagnóstico

Cada fila fallida conserva un código público seguro y su etapa: `FEL-UPLOAD-VALIDATION`, `FEL-MIME`, `FEL-ZIP-EXTRACT`, `FEL-XML-READ`, `FEL-XML-PARSE`, `FEL-XML-UUID`, `FEL-XML-DATA`, `FEL-EXCEL-READ`, `FEL-EXCEL-HEADERS`, `FEL-EXCEL-ROW`, `FEL-STORAGE`, `FEL-DATABASE`, `FEL-ENUM` o `FEL-UNKNOWN`.

El log técnico incluye lote, empresa, tenant, archivo, tipo, etapa, clase, mensaje técnico, archivo/línea y traza. No incluye el cuerpo XML ni firmas digitales.

La migración `2026_07_27_000000_add_diagnostics_to_fel_imports.php` amplía `fel_documents.fel_version`: una URI de namespace FEL válida supera los 30 caracteres originales y MySQL estricto la rechazaba con SQLSTATE `22001`.

## Seguridad y alcance contable

XML rechaza DTD y entidades, usa `LIBXML_NONET`; ZIP bloquea rutas absolutas y `..`; todas las consultas usan la empresa activa. La importación, aprobación, observación y rechazo no crean pólizas ni movimientos. `journal_entry_id` permanece nulo.

## Reclasificación

La Bandeja de revisión ofrece una reclasificación manual en dos pasos. La vista previa ejecuta las reglas sin escribir en la base de datos; la confirmación reutiliza `FelReclassificationService`, procesa por bloques y conserva el documento original, la revisión humana y cualquier vínculo contable. La empresa siempre se toma de la sesión y nunca de datos enviados por el formulario. Tanto la vista previa como la ejecución aplican Post/Redirect/Get, por lo que el navegador termina en una ruta GET y el selector de empresa nunca reutiliza una URL de acción POST.

Para soporte técnico se mantiene el comando `php artisan fel:reclassify`, con `--company`, `--only-unknown`, `--dry-run` y `--include-reviewed`. El comando invoca el mismo servicio de dominio que la interfaz web.
