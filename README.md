# Evaluación Corporativa Premium

Sistema PHP 8.1+ / MySQL para evaluaciones cerradas con identificación por gafete, banco administrable de preguntas, selección aleatoria u ordenada, puntuación, administración protegida y reportes PDF/Excel.

## Instalación
1. Publique los archivos en Apache/IIS con PHP 8.1+.
2. Asegure PDO MySQL, cURL, OpenSSL y mbstring. ZIP es recomendado, pero la lectura XLSX incluye respaldo basado en zlib.
3. Dé permiso de escritura temporal a la raíz del proyecto y a `uploads/`.
4. Abra `/install/` y siga el asistente guiado.
5. Al finalizar se crea `install.lock` y el instalador queda bloqueado.

## Administración Premium
- Topbar fijo con accesos directos, búsqueda global y perfil con avatar de iniciales.
- Cerrar sesión dentro del menú del usuario.
- KPIs por módulo.
- Listados sin tablas HTML: DIV + Grid/Flex, búsqueda, limpiar con X, total, Mostrar X registros, paginación y vista Miniatura/Detalle.
- Modales propios para empleados, preguntas y usuarios, por encima del topbar.
- `showNotify` local para success, error, info y warning.
- `Swal.fire` local para confirmaciones.
- Select2 local para selects.
- Controles radio, switches y checks con UI propia.

## Empleados
- Alta/edición manual.
- Importación desde Excel `.xlsx` o CSV.
- Carga mediante seleccionar, arrastrar/soltar o pegar archivo.
- Plantilla profesional `plantilla_empleados.xlsx` con Gafete, Nombre, Departamento y Correo. No requiere Estado; los nuevos se importan activos.

## Preguntas
- Banco sin límite fijo de 10 preguntas: puedes cargar tantas filas como necesites.
- Alta manual, importación Excel/CSV, publicación, edición, eliminación y vista previa.
- Plantilla `plantilla_preguntas.xlsx` con hoja de captura, hoja de ejemplo e instrucciones.
- Exportación de las preguntas actuales en Excel desde Administración.
- Configuración de cuántas preguntas mostrar por evaluación (1 a 500).
- Selección configurable: aleatoria o siguiendo el orden del banco.

## Seguridad y acceso
- Login administrativo.
- Recuperación de contraseña por correo con token de un solo uso y vencimiento de 30 minutos.
- Contraseñas con `password_hash`.
- Secretos SMTP/Graph cifrados con AES-256-GCM.

## Correo
- SMTP y Microsoft Graph.
- Plantillas HTML transaccionales.
- Prueba de envío desde Administración.

## Flujo público
- Menú superior fijo.
- Ingreso de gafete y búsqueda automática del nombre.
- Confirmación visual de identidad antes de crear/iniciar el intento.
- Una participación por empleado.
- Asistente de preguntas una por una con barra de progreso, respondidas y pendientes.
- Las preguntas quedan persistentes por intento para no cambiar al recargar.
- Confirmación final con SweetAlert2 y resultado calculado sobre 100.

## Identidad del navegador
- Desde Administración → Configuración puedes cambiar el nombre de la pestaña, el logo del sistema y el favicon.
- Si no cargas un favicon, el sistema usa el logo configurado; si tampoco hay logo, usa el icono EC incluido localmente.
- Las notificaciones de estado usan showNotify local y las confirmaciones complejas usan SweetAlert2 local.
