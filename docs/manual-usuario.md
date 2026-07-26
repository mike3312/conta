# Manual de usuario de ERP Conta

## 1. Inicio de sesión

1. Abre la dirección del sistema.
2. Ingresa el correo y la contraseña registrados.
3. Selecciona **Iniciar sesión**.

La aplicación también dispone de registro, recuperación de contraseña, confirmación de contraseña y edición del perfil. Si no hay una empresa disponible, el dashboard ofrece acceso para crearla.

## 2. Creación y selección de empresas

### Crear una empresa

1. Abre **Empresas** desde el menú lateral.
2. Selecciona **Nueva empresa**.
3. Completa nombre comercial, razón social y NIT. Correo, teléfono, dirección, municipio y departamento son opcionales.
4. Conserva marcada **Crear catálogo contable base** si deseas iniciar con la estructura incluida en el sistema.
5. Guarda la empresa.

La empresa recién creada queda asociada al usuario y pasa a ser la empresa activa.

### Cambiar la empresa activa

Utiliza el selector de empresa de la barra superior. Al cambiarlo, dashboard, catálogo, períodos, pólizas y reportes se actualizan para la empresa seleccionada.

No existe una opción consolidada de “Todas las empresas”. El sistema valida que la empresa de la sesión pertenezca al usuario y esté activa.

## 3. Catálogo de cuentas

El módulo **Catálogo de cuentas** muestra la estructura jerárquica de la empresa activa.

Para crear una cuenta:

1. Selecciona **Nueva cuenta**.
2. Elige una cuenta padre de encabezado, si corresponde.
3. Ingresa un código único dentro de la empresa y un nombre.
4. Selecciona el tipo: Activo, Pasivo, Capital, Ingreso, Gasto o Costo.
5. Marca **Permite movimientos contables** únicamente para cuentas que recibirán líneas de póliza.
6. Mantén activa la cuenta y guarda.

La naturaleza se asigna según el tipo: Activo, Gasto y Costo son de naturaleza deudora; Pasivo, Capital e Ingreso son de naturaleza acreedora.

Validaciones principales:

- El código no puede repetirse dentro de la misma empresa.
- Una cuenta de movimiento no puede tener cuentas hijas.
- Una cuenta con hijas no puede convertirse en cuenta de movimiento.
- Solo cuentas activas y de movimiento pueden utilizarse en pólizas.
- No puede eliminarse una cuenta que tenga cuentas hijas.

## 4. Períodos contables

En **Períodos contables** se definen el nombre, fecha inicial y fecha final de cada período.

- La fecha final debe ser igual o posterior a la fecha inicial.
- Dos períodos de la misma empresa no pueden traslaparse.
- Un período con pólizas relacionadas no puede eliminarse.
- Un período cerrado no puede editarse ni eliminarse.

Los estados disponibles son **Abierto** y **Cerrado**.

## 5. Pólizas contables

### Crear una póliza

1. Abre **Pólizas contables**.
2. Selecciona **Nueva póliza**.
3. Elige un período abierto.
4. Ingresa una fecha incluida en el rango del período.
5. Completa descripción y referencia opcional.
6. Agrega las líneas contables con cuenta, Debe o Haber.
7. Guarda como borrador.

Una línea no puede tener Debe y Haber positivos al mismo tiempo. Los importes no pueden ser negativos y solo admiten hasta dos decimales.

### Contabilizar

Una póliza en borrador puede editarse y permanecer descuadrada mientras se prepara. Para contabilizarla debe:

- Tener al menos dos líneas.
- Tener importes positivos.
- Cumplir total Debe = total Haber.
- Usar cuentas activas, de movimiento y de la empresa activa.
- Pertenecer a un período abierto.
- Tener una fecha dentro del período.

Al contabilizar, el sistema asigna un número correlativo por empresa y período. Una póliza contabilizada ya no puede editarse ni eliminarse.

### Anular

Una póliza contabilizada puede anularse mientras su período permanezca abierto. El motivo de anulación es obligatorio. La anulación conserva número y líneas para mantener la trazabilidad.

Estados posibles:

- **Borrador:** editable y eliminable en un período abierto.
- **Contabilizada:** incluida en libros, reportes y cálculos.
- **Anulada:** conservada para auditoría, pero excluida de los cálculos financieros.

## 6. Libro Diario

El Libro Diario presenta únicamente pólizas contabilizadas de la empresa activa. Permite filtrar por fechas, período, cuenta y búsqueda según las opciones visibles.

Cada póliza conserva todas sus líneas y muestra sus totales Debe y Haber. Los borradores y anulaciones no participan en el reporte financiero.

## 7. Libro Mayor

El Libro Mayor agrupa movimientos contabilizados por cuenta y muestra:

- Saldo anterior.
- Movimientos Debe y Haber del rango.
- Saldo acumulado.
- Naturaleza y advertencia de saldo contrario.

Puede filtrarse por fechas, período, cuenta específica, rango de cuentas y búsqueda.

## 8. Balance de Comprobación

Este reporte presenta saldos anteriores, movimientos y saldos finales en columnas deudoras y acreedoras. Permite comprobar que los movimientos y saldos generales permanezcan cuadrados.

## 9. Estado de Resultados

El Estado de Resultados utiliza pólizas contabilizadas y presenta cuentas de Ingreso y Gasto para el rango seleccionado.

- Ingresos: Haber menos Debe.
- Gastos: Debe menos Haber.
- Resultado: ingresos menos gastos.

El resultado se muestra como utilidad, pérdida o resultado cero. Puede consultarse por fechas o período e imprimirse desde el navegador.

## 10. Balance General

El Balance General calcula los saldos acumulados hasta una fecha de corte y presenta Activos, Pasivos, Patrimonio y resultado pendiente.

Estados de validación:

- **Balance cuadrado:** la diferencia es cero y no existen saldos contrarios.
- **Balance cuadrado con observaciones:** la diferencia es cero, pero existen cuentas de movimiento con saldo contrario a su naturaleza.
- **Balance descuadrado:** la ecuación contable presenta diferencia.

Cuando existen observaciones se muestra código, cuenta, naturaleza esperada, total Debe, total Haber y saldo contrario. Estas observaciones deben corregirse antes de cerrar el período.

## 11. Dashboard

El dashboard muestra información de la empresa activa:

- Ingresos.
- Gastos.
- Utilidad o pérdida neta.
- Activos totales.
- Gráfica mensual de ingresos y gastos.
- Actividad reciente de pólizas.
- Accesos rápidos a operaciones y reportes.

El período abierto se utiliza como rango predeterminado. Si no existe, se usa el mes actual. Los cálculos financieros consideran únicamente pólizas contabilizadas.

## 12. Cierre de períodos

1. Abre **Períodos contables**.
2. Localiza un período abierto.
3. Selecciona **Cerrar período**.
4. Lee la advertencia y confirma.

Antes de cerrar, el sistema valida que:

- El período pertenezca a la empresa activa.
- Continúe abierto.
- No contenga pólizas en borrador.
- Todas las pólizas contabilizadas estén cuadradas.
- Las fechas de las pólizas estén dentro del período.
- No existan cuentas de movimiento con saldo contrario según la auditoría del Balance General.

Un período vacío puede cerrarse. El cierre registra fecha y usuario. Después del cierre no es posible crear, editar, mover, eliminar, contabilizar ni anular pólizas del período. Los libros y reportes continúan disponibles para consulta.

No existe reapertura ni cierre forzado en la versión actual.

## 13. Mensajes y validaciones frecuentes

- **Primero debes seleccionar una empresa activa:** crea o selecciona una empresa válida.
- **Las fechas se traslapan con otro período contable:** utiliza un rango que no coincida con otro período de la empresa.
- **La fecha de la póliza debe estar dentro del período:** corrige la fecha o selecciona otro período abierto.
- **La suma del Debe debe ser exactamente igual a la suma del Haber:** revisa los importes antes de contabilizar.
- **Solo las pólizas en borrador pueden modificarse o contabilizarse:** la póliza ya cambió de estado.
- **El período contable seleccionado está cerrado:** las operaciones de modificación están bloqueadas.
- **No se puede cerrar el período porque contiene pólizas en borrador:** contabiliza o elimina los borradores válidamente antes de cerrar.
- **No se puede cerrar el período porque contiene pólizas descuadradas:** revisa las líneas de las pólizas contabilizadas.
- **No se puede cerrar el período porque contiene pólizas fuera de sus fechas:** corrige la relación entre fecha y período.
- **No se puede cerrar el período porque existen cuentas con saldo contrario:** revisa las observaciones del Balance General y registra las correcciones correspondientes.

Nunca corrijas información de otra empresa para resolver una validación: todas las operaciones deben realizarse con la empresa correcta seleccionada.
