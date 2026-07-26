# Guía de demostración de ERP Conta

## Objetivo

Demostrar el flujo completo de una empresa: catálogo, período, pólizas, dashboard, libros, reportes, aislamiento multiempresa y cierre operativo.

Los nombres y datos utilizados son exclusivamente ficticios. No uses contraseñas, NIT reales ni información de clientes.

## Escenario

- **Empresa:** Comercializadora Demo
- **Período:** agosto 2026
- **Rango:** 01/08/2026 al 31/08/2026
- **Moneda:** GTQ

Utiliza un NIT ficticio reservado para demostración y un correo que no corresponda a una persona real.

## Preparación del catálogo

Al crear la empresa, deja marcada **Crear catálogo contable base**. El catálogo base ya incluye Caja General, Bancos, Capital Social y Ventas.

Para reproducir exactamente el escenario también deben existir como cuentas activas de movimiento:

| Cuenta | Tipo requerido | Naturaleza asignada por el sistema |
|---|---|---|
| IVA crédito fiscal | Activo | Deudora |
| IVA débito fiscal | Pasivo | Acreedora |
| Compras | Gasto | Deudora |
| Gasto de energía eléctrica | Gasto | Deudora |

Estas cuentas no forman parte del catálogo base actual. Créelas desde **Catálogo de cuentas → Nueva cuenta**. Los códigos deben ser únicos dentro de la empresa; para la demostración pueden usarse códigos libres coherentes con el catálogo existente.

> Para obtener la utilidad esperada de Q2,500 en el Estado de Resultados actual, la cuenta Compras debe configurarse con tipo **Gasto**. El reporte incluye cuentas de Ingreso y Gasto.

## Crear el período

1. Abre **Períodos contables**.
2. Crea el período **agosto 2026**.
3. Usa fecha inicial `01/08/2026` y fecha final `31/08/2026`.
4. Confirma que quede en estado **Abierto**.

## Registrar las pólizas

Utiliza fechas distintas dentro de agosto de 2026 y contabiliza cada póliza después de verificar su cuadre.

### Póliza 1: aporte inicial de capital

| Cuenta | Debe | Haber |
|---|---:|---:|
| Bancos | Q20,000.00 | Q0.00 |
| Capital Social | Q0.00 | Q20,000.00 |
| **Totales** | **Q20,000.00** | **Q20,000.00** |

### Póliza 2: venta gravada

| Cuenta | Debe | Haber |
|---|---:|---:|
| Caja General | Q5,600.00 | Q0.00 |
| Ventas | Q0.00 | Q5,000.00 |
| IVA débito fiscal | Q0.00 | Q600.00 |
| **Totales** | **Q5,600.00** | **Q5,600.00** |

### Póliza 3: compra pagada desde bancos

| Cuenta | Debe | Haber |
|---|---:|---:|
| Compras | Q2,000.00 | Q0.00 |
| IVA crédito fiscal | Q240.00 | Q0.00 |
| Bancos | Q0.00 | Q2,240.00 |
| **Totales** | **Q2,240.00** | **Q2,240.00** |

### Póliza 4: energía eléctrica

| Cuenta | Debe | Haber |
|---|---:|---:|
| Gasto de energía eléctrica | Q500.00 | Q0.00 |
| Bancos | Q0.00 | Q500.00 |
| **Totales** | **Q500.00** | **Q500.00** |

## Resultados esperados

### Saldos relevantes

| Concepto | Cálculo | Saldo |
|---|---|---:|
| Bancos | 20,000 − 2,240 − 500 | Q17,260.00 |
| Caja General | 5,600 | Q5,600.00 |
| IVA crédito fiscal | 240 | Q240.00 |
| IVA débito fiscal | 600 acreedor | Q600.00 |
| Capital Social | 20,000 acreedor | Q20,000.00 |
| Ingresos | 5,000 | Q5,000.00 |
| Gastos | 2,000 + 500 | Q2,500.00 |

### Reportes

| Resultado | Importe esperado |
|---|---:|
| Activos | Q23,100.00 |
| Pasivos | Q600.00 |
| Capital | Q20,000.00 |
| Utilidad | Q2,500.00 |
| Patrimonio total | Q22,500.00 |
| Diferencia | Q0.00 |

Comprobación:

```text
Activos = Bancos + Caja + IVA crédito fiscal
Q23,100 = Q17,260 + Q5,600 + Q240

Pasivos + Patrimonio
Q23,100 = Q600 + Q22,500
```

El Balance General debe mostrar **Balance cuadrado** sin observaciones. El Estado de Resultados debe mostrar utilidad de Q2,500. El dashboard debe reflejar ingresos de Q5,000, gastos de Q2,500, utilidad de Q2,500 y activos de Q23,100 para el período.

## Guion sugerido de demostración

1. Inicia sesión y muestra el selector de empresa.
2. Crea **Comercializadora Demo** con catálogo base.
3. Completa las cuatro cuentas adicionales.
4. Crea el período agosto 2026.
5. Registra la primera póliza como borrador y muestra que puede guardarse antes de contabilizar.
6. Contabiliza la póliza y señala su número correlativo.
7. Registra y contabiliza las otras tres pólizas.
8. Abre el dashboard y selecciona agosto 2026.
9. Revisa Libro Diario y confirma las cuatro pólizas.
10. Abre Libro Mayor para Bancos y explica el saldo Q17,260.
11. Revisa el Estado de Resultados y confirma utilidad Q2,500.
12. Revisa el Balance General y confirma diferencia Q0.00 sin saldos contrarios.

## Demostración de aislamiento entre empresas

1. Crea una segunda empresa ficticia, por ejemplo **Servicios Demo**, sin utilizar datos reales.
2. Selecciónala desde la barra superior.
3. Muestra que no aparecen las pólizas, cuentas personalizadas ni resultados de Comercializadora Demo.
4. Registra opcionalmente un período o una póliza de prueba en Servicios Demo.
5. Regresa a Comercializadora Demo.
6. Confirma que sus cuatro pólizas y sus reportes permanecen sin cambios.

La demostración debe enfatizar que no existe consolidación entre empresas y que toda consulta depende exclusivamente de la empresa activa.

## Demostración del cierre y bloqueo

1. Antes de cerrar, crea temporalmente un borrador dentro de agosto 2026.
2. Intenta cerrar el período: el sistema debe rechazarlo porque contiene una póliza en borrador.
3. Elimina el borrador o complétalo y contabilízalo si corresponde a un movimiento válido.
4. Verifica que todas las pólizas estén cuadradas, dentro del rango y que el Balance General no tenga observaciones por saldos contrarios.
5. Vuelve a **Períodos contables**, selecciona **Cerrar período** y confirma el modal.
6. Comprueba el estado Cerrado, la fecha y el usuario que realizó el cierre.
7. Intenta crear una póliza en agosto 2026: el período ya no estará disponible como período abierto.
8. Intenta editar, eliminar, contabilizar o anular una póliza relacionada: el servidor debe rechazar la operación.
9. Abre Libro Diario, Libro Mayor, Estado de Resultados y Balance General para demostrar que la información histórica sigue disponible.

El cierre no elimina movimientos, no genera una póliza automática y no puede forzarse ni revertirse en la versión actual.
