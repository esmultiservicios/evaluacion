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

## Ajustes v7.3 — presentación ejecutiva
- Tipografía y tamaños reforzados en administración, evaluación pública y portal de juegos.
- Botón de pantalla completa en login, panel, evaluación y juegos.
- Navegación interna del panel sin recarga completa para conservar el fullscreen entre módulos y también al guardar formularios.
- Acciones con estilo premium sin botones blancos ni enlaces de acción sueltos.
- Exportación de Preguntas y Juegos a Excel y PDF con iconografía consistente.
- KPIs con contador animado, movimiento sutil y efecto visual profesional (respeta `prefers-reduced-motion`).

## Actualización v7.4
- Botones de modales y confirmaciones con iconografía consistente; confirmaciones locales incluyen cerrar, cancelar y acción.
- Foco automático en el primer campo útil de formularios y modales.
- Selector `Mostrar` incluye `Todos (N)` y muestra todos los registros sin paginar.
- Reglas del portal de juegos: mostrar todos o una cantidad definida y selección aleatoria o en orden.
- Las reglas de juegos pueden administrarse desde Juegos y Configuración y se aplican al portal público.

## Actualización v7.5 — formularios e importaciones robustas
- El foco automático ignora la búsqueda global y los filtros: solo entra al primer campo útil de formularios normales o modales.
- Guardado administrativo reforzado con respuesta JSON para navegación interna: cada acción devuelve `showNotify` de éxito, información o error sin quedar silenciosa.
- Validación visual y funcional de campos `required`, incluidos archivos obligatorios de importación.
- Importaciones de empleados y preguntas validan carga, extensión y tamaño máximo de 10 MB con mensajes claros.
- Juegos incorpora plantilla Excel e importación masiva por motor, con creación/actualización segura por nombre.
- Configuración usa tarjetas de igual altura por fila para eliminar espacios muertos sin alterar el contenido.
- Se mantiene SweetAlert2 local para confirmaciones y no se usan `alert()` ni `confirm()` nativos.


## v7.6 · Navegación entre sitios
- Evaluación y Juegos muestran un selector superior de **SITIOS** con ambos portales juntos.
- El panel administrativo separa claramente **SITIOS** (Ir a evaluación / Ir a juegos) de **PANEL** (Preguntas / Juegos / Reportes).
- Se conservan Pantalla completa, Sonido y Administración sin mezclar los accesos públicos con los módulos internos.
- Ajustes responsive para mantener el navbar limpio en escritorio, tablet y móvil.

## Ajustes v7.7
- Corregido el guardado AJAX de formularios administrativos: el campo oculto `action` ya no puede sobrescribir la URL real del formulario.
- Mensajes `showNotify` visibles por encima de modales para éxito, error, info/warning y danger.
- Hover del navbar sin invertir el texto a blanco; navegación pública y administrativa más consistente.
- Empleados ahora permite exportar el directorio a Excel y PDF, además de importar Excel/CSV.
- Importadores de Empleados, Preguntas y Juegos aceptan CSV con coma, punto y coma o tabulación, además de XLSX.
- Se mantienen validaciones required, SweetAlert2 local y ausencia de `alert()` / `confirm()` nativos.


## Ajustes v7.8
- El modal de empleados permanece abierto después de guardar. Al crear, queda limpio para continuar cargando; al editar, conserva el registro abierto.
- Compatibilidad de reimportación reforzada para exportaciones de empleados, preguntas y juegos.
- CSV admite coma, punto y coma y tabulador real.
- Exportación de juegos incluye Introducción y conserva Estado/Sonido para reimportación.

### v7.9 · Acciones uniformes
- Regla visual: cuando un registro tiene más de una acción, se agrupan dentro del dropdown **Acciones**.
- Empleados ahora usa el mismo patrón premium de Juegos/Preguntas en vista **Detalle** y **Miniatura**.
- Editar y Eliminar empleado ya no aparecen como iconos flotantes separados.
- Los registros con una única acción conservan un solo botón identificado, sin crear menús innecesarios.

## v8.0 · Juegos personalizados por gafete y reportes
- El portal de juegos solicita primero el gafete y confirma al empleado activo.
- La experiencia saluda al participante por nombre y mantiene su identidad durante los juegos.
- La selección de juegos respeta `Todos/Cantidad definida` y `Aleatorio/En orden`; en modo aleatorio la selección queda estable para cada empleado.
- Cada partida completada queda asociada a gafete, nombre, juego, resultado y fecha.
- Reportes incorpora una sección independiente de Juegos con búsqueda, paginación, Excel y PDF.
- La evaluación tradicional también refuerza el saludo personalizado por nombre.

## v8.1 · Identificación uniforme en Evaluación
- El acceso público de Evaluación adopta la misma experiencia visual premium de Juegos.
- Pantalla inicial en dos paneles: contexto de la experiencia a la izquierda e identificación por gafete a la derecha.
- Se conserva la validación automática del empleado, el saludo personalizado y el flujo de una sola participación.
- En escritorio, gafete y botón Continuar quedan alineados; en móvil se apilan para mantener legibilidad y área táctil cómoda.
- Después de confirmar el gafete, el flujo vuelve al asistente amplio de confirmación y preguntas para no reducir el espacio de lectura.

## v8.2 · Juegos interactivos y experiencia pública uniforme
- Los 8 motores de juego incluyen apoyo visual local en SVG, sin depender de recursos externos.
- **Detecta el peligro** y **Encuentra los errores** permiten localizar señales directamente sobre escenas visuales.
- **Ordena los pasos** permite arrastrar y soltar; en móvil incorpora controles ↑/↓ como alternativa táctil.
- **Relaciona conceptos** permite drag & drop en escritorio y selección + destino en dispositivos táctiles.
- **Reto rápido** incorpora cuenta regresiva visual y feedback inmediato.
- Phishing/legítimo, escenarios y verdadero/falso mantienen decisiones interactivas con retroalimentación educativa.
- Los juegos base ya instalados se actualizan una sola vez al nuevo contenido mediante `game_content_schema=4`; juegos personalizados no se sobrescriben.
- Evaluación permite avanzar con Enter desde el gafete, saluda al participante y muestra panel uniforme de Asignadas, Completadas y Progreso.
- Evaluación incorpora **Cambiar participante** antes, durante y después de responder, con confirmación SweetAlert2.
- El portal de Juegos conserva identificación por gafete, saludo, progreso y registro de partidas por empleado.

## Ajustes v8.3

- Participante sincronizado entre Evaluación y Juegos; cambiar participante limpia la sesión anterior y evita que el navegador restaure una identidad obsoleta.
- Identificación de Juegos con búsqueda automática por gafete, vista previa del empleado y botón Continuar habilitado únicamente cuando el gafete es válido.
- Juegos de una sola entrega por participante: al completar un juego queda en modo revisión y el backend rechaza un segundo envío.
- Las partidas nuevas guardan detalle por reto (`answers_json`) para poder revisar lo realizado; instalaciones existentes agregan la columna automáticamente.
- Botón de siguiente/culminar bloqueado en retos de señales hasta completar la acción requerida.
- Encabezado “Mis juegos + participante” fijo durante los retos y barra de participante fija durante las preguntas.
- Tarjetas de juegos con microanimaciones profesionales y soporte `prefers-reduced-motion`.
- Panel previo de Evaluación renovado con identidad, reglas, métricas y guía de inicio.

## v8.4 · viewport + banco inicial de preguntas
- Evaluación y detalle de cada juego se adaptan al alto visible cuando el contenido cabe; en pantallas bajas conservan scroll natural.
- El catálogo principal de juegos no se fuerza al alto del viewport porque puede contener múltiples tarjetas.
- Instalaciones existentes sin ninguna pregunta reciben una sola vez un banco inicial de 10 preguntas activas y válidas de concientización en ciberseguridad.
- El inicio de evaluación valida el banco y, si el administrador deja menos preguntas válidas que las configuradas, muestra la cantidad disponible y la acción necesaria.

## v8.5 · Reglas inteligentes del portal de juegos

- **Todos** asigna todos los juegos publicados; **Cantidad definida** limita el número y habilita validación dinámica según el banco activo.
- **Aleatoria estable** crea una asignación persistente por participante: no cambia al recargar ni al volver a entrar. Si el participante ya comenzó, se conserva su recorrido aunque el administrador cambie las reglas.
- **En orden** respeta `sort_order` del banco de juegos.
- Nueva navegación **Libre / Guiada**. En modo guiado solo se habilita el siguiente reto pendiente; los completados permanecen disponibles en modo revisión.
- Nuevo objetivo **Todos / Cantidad mínima**. El portal muestra meta, progreso y estado de objetivo cumplido.
- La configuración muestra un resumen en vivo y deshabilita campos que no aplican para evitar combinaciones inválidas.
- Se incorpora `game_assignments` y la actualización de esquema se ejecuta automáticamente en instalaciones existentes.

## v8.6 · Corrección de cantidades en reglas de juegos

- Corregido el campo **Juegos requeridos para completar**: ya no fuerza el valor máximo mientras el administrador está escribiendo.
- Corregido el mismo comportamiento en **Cantidad de juegos** para mantener consistencia entre ambas reglas numéricas.
- Los valores se validan en vivo para el resumen, pero solo se normalizan al salir del campo, cambiar la regla o guardar.
- Al enfocar un campo numérico de estas reglas se selecciona su contenido para que escribir un nuevo número reemplace el anterior, evitando concatenaciones como `8` → `85`.
- Se mantiene la validación del backend: nunca se guardará una cantidad menor a 1 ni mayor a los juegos realmente asignables/publicados.

## v8.7.0 · Evaluación y juegos con resultados definitivos
- Gafetes exclusivamente numéricos en captura manual, portal, evaluación, juegos e importación.
- Preguntas de una respuesta o selección múltiple con cantidad requerida configurable.
- Tiempo límite opcional por pregunta y por juego (`0` = sin límite).
- Puntuación opcional por rapidez desde Configuración; la rapidez solo bonifica respuestas correctas y los reportes priorizan aciertos y luego menor tiempo.
- Sin revelar aciertos/errores durante la participación: el detalle aparece únicamente en el resumen final.
- Respuestas definitivas: al avanzar no se puede volver a corregir; participaciones completadas quedan en modo revisión.
- Juegos de hotspots, ordenamiento y relación permiten completar la acción aunque esté incorrecta; el resultado se evalúa al final.
- Reportes consolidados por persona con evaluación, juegos, aciertos, promedio y tiempo total.
- Mensajes `showNotify` y confirmaciones siempre por encima de los modales.
- Contenido base más intuitivo: términos técnicos explicados, ejemplo legítimo de RRHH con `lear.com`, phishing claramente diferenciado y un tercer reto en “Encuentra los errores”.


## v8.7.1 - participación cerrada y reinicio administrativo
- Todos los juegos asignados quedan disponibles desde el inicio; no se bloquean por recorrido guiado.
- Cada juego se guarda una sola vez y luego queda únicamente en modo revisión.
- Al completar todos los juegos asignados, el portal muestra resumen final y conserva acceso de solo lectura.
- La evaluación completada continúa disponible en modo revisión y no genera nuevas preguntas.
- Administración > Empleados incorpora Reiniciar evaluación, Reiniciar juegos y Reiniciar todo.
- Reiniciar juegos elimina resultados y asignación del empleado para que reciba una nueva asignación con las reglas vigentes.

## v8.8 — administración masiva, grupos y dashboard
- Reinicio global de participaciones desde Empleados y limpieza separada de resultados desde Reportes.
- Eliminación masiva protegida con palabra de confirmación `ELIMINAR`.
- Acciones globales para desactivar/eliminar preguntas y juegos.
- Grupos/campañas para preguntas y juegos. Cada empleado puede recibir un grupo de preguntas y un grupo de juegos; al cambiar de grupo se libera la participación anterior de ese módulo para recibir el nuevo contenido.
- Preferencia Detalle/Miniatura persistida en base de datos por usuario; en móvil se fuerza Miniatura.
- Exportación Excel/PDF en Participaciones recientes y gráficas comparativas de Evaluaciones/Juegos.

## v8.9.2 · Select2 de categorías, configuración y acciones de usuarios

- Todos los `<select>` del panel administrativo pasan por Select2 local, incluyendo formularios, modales, paginación y navegación dinámica.
- Empleados: `Categoría / campaña` ahora es un Select2 alimentado por las categorías creadas y conserva la selección al editar.
- Preguntas: la categoría/campaña ahora se selecciona desde Select2 y se restaura correctamente al editar.
- Juegos: la categoría/campaña ahora se selecciona desde Select2; el valor se sincroniza con `group_name` para que la asignación por campaña y el portal utilicen la misma categoría.
- Importación de juegos: conserva/sincroniza `group_name` al crear o actualizar registros.
- Configuración: se aumentó la separación visual de `Puntuación por rapidez`, su interruptor y el campo del bono máximo.
- Usuarios: tanto en vista Detalle como Miniatura se usa `Acciones` con Editar, Activar/Desactivar y Eliminar. La sesión actual y el último administrador activo están protegidos.

## v8.9.5 · persistencia unificada en Reportes

- Reportes usa una sola preferencia de vista `Detalle/Miniatura` para Resumen por persona, Resultados de preguntas y Reporte de juegos.
- Cambiar la vista en cualquiera de los bloques actualiza los demás bloques de Reportes en la misma pantalla y guarda la selección en `user_preferences`.
- Al salir de Reportes y volver a entrar, la vista seleccionada se restaura desde base de datos para el usuario administrativo.
- En móvil se conserva la regla de seguridad visual: Miniatura se fuerza automáticamente para evitar desbordamientos.
- Se conservan las exportaciones Excel/PDF de Categorías incorporadas en v8.9.4.

## Ajuste responsive v8.99
- Portal de evaluación ampliado en escritorio para aprovechar mejor pantallas grandes sin perder adaptación móvil/tablet.
- Tarjetas, preguntas, resumen final y pantalla de participante con tipografía y espaciado más legibles en PC.
- Portal de juegos ampliado hasta 4 tarjetas grandes por fila en escritorio, 3/2/1 según el ancho disponible.
- Tarjetas de juegos con imágenes, títulos, descripción y acciones de mayor tamaño.
- Panel administrativo optimizado para pantallas grandes y pequeñas.
- Control de Puntuación por rapidez reconstruido visualmente para que el switch y el estado Activa/Inactiva siempre sean visibles.


## Ajustes v9.00
- El portal de preguntas aprovecha el ancho real de PC/laptop y aumenta la lectura de preguntas, opciones, resumen y datos del participante.
- Se elimina el acceso redundante “Participar” del encabezado público; “Preguntas” ya representa ese sitio.
- Puntuación por rapidez usa el mismo componente visual de switch que Notificar al completar evaluación.
- El selector Mostrar conserva 6, 12, 24, 48 y Todo, con desplegable completo y sin recortes.
- Se incrementan versiones de recursos para evitar estilos anteriores en caché.

## Ajustes v9.05
- El nombre visible de la experiencia deja de estar fijo como `Cyber Challenge`: ahora toma automáticamente la **Categoría / campaña** asignada al participante.
- Renombrar una categoría desde Administración actualiza el título mostrado en los portales de Preguntas y Juegos, conservando la propagación existente hacia contenido y empleados.
- Preguntas y Juegos muestran una etiqueta visible del portal (`PREGUNTAS` / `JUEGOS`) para que el participante identifique inmediatamente dónde se encuentra.
- La descripción de la categoría se aprovecha como contexto ejecutivo cuando está configurada.
- La imagen SOAR deja de ocupar un bloque independiente después de identificar al participante y pasa a integrarse dentro del hero principal de Preguntas y Juegos.
- En el detalle de cada juego se conserva el contexto `JUEGOS` y el nombre de la categoría activa.


## Ajustes v9.06
- Preguntas usa un encabezado de participante uniforme con Juegos: avatar, saludo, gafete, métricas y cambio de participante.
- La sesión del participante en Preguntas se restaura al recargar; solo se limpia al cambiar/corregir participante.
- El progreso local de la evaluación (pregunta actual, selecciones y temporizadores) se conserva durante recargas de la misma pestaña.
- La marca SOAR queda integrada en los heroes de Preguntas y Juegos con `object-fit: contain`, mayor área útil y etiqueta de contexto para evitar recortes.
- Se incrementaron versiones de recursos estáticos para evitar que el navegador conserve CSS/JS anterior.


## Ajustes v9.07

- Preguntas ya no entra automáticamente al cuestionario al recargar. Si existe un participante activo, vuelve a la pantalla de confirmación con su avatar, métricas y botón **Cambiar participante**; solo entra al cuestionario al pulsar **Comenzar evaluación** o **Continuar evaluación**.
- Se conserva la sesión del participante y el avance guardado, por lo que recargar no obliga a volver a ingresar el gafete ni borra el progreso.
- El acceso **Administración** de los portales públicos de Preguntas y Juegos ahora abre el panel administrativo en una pestaña nueva para no sacar al participante de su pantalla actual.

## Ajustes v9.09 · visor universal de imágenes

- Todos los retos con imagen muestran una acción clara **Ver imagen grande**.
- El visor ampliado incorpora controles de zoom, desplazamiento y adaptación responsive.
- Los juegos de detección conservan y muestran en el visor los marcadores seleccionados.
- Los marcadores ahora usan un punto exacto + número desplazado con línea guía para evitar tapar el texto de la imagen.
- Los puntos también pueden seleccionarse desde la vista ampliada sin revelar la respuesta correcta antes de finalizar.

## Ajustes v9.10 · inspección guiada y avance consistente

- Los retos de detectar/encontrar usan marcadores con punto pequeño, línea y flecha para indicar dónde revisar sin cubrir el contenido.
- El reto de inicio de sesión incorpora un candado visible para que el punto 4 tenga una referencia visual real.
- Se reajustaron visualmente los puntos del reto de login para evitar que el marcador quede encima del dominio, MFA o mensaje de presión.
- Se agregó una guía de inspección neutral: explica qué zona revisar sin revelar si el punto es correcto o incorrecto.
- El visor ampliado conserva los marcadores, permite seleccionarlos directamente y muestra la misma guía de inspección.
- En "Ordena los pasos" se aclara que cualquier orden puede guardarse y permite avanzar; la corrección se muestra al final.
- En "Relaciona conceptos" se aclara que, una vez relacionadas todas las opciones, se puede avanzar aunque alguna relación sea incorrecta; los aciertos se muestran al final.
- Ajustes responsive para las nuevas guías, flechas y leyendas en escritorio, tablet y móvil.


## Ajustes v9.11 · guías rápidas y control coherente de tiempos

- Los marcadores de inspección ahora muestran **número + nombre del elemento** (Remitente, Urgencia, Destino, Dominio, MFA, Candado, etc.) y la flecha termina en el punto exacto.
- Se corrigieron las posiciones del reto de correo para que los marcadores apunten al remitente, mensaje de urgencia, destino del enlace y nombre visible del remitente, sin señalar elementos decorativos.
- Todos los motores de juego incorporan una **Guía rápida** con criterios de análisis antes de responder.
- `Phishing o legítimo` presenta remitente, correo, dominio y mensaje en una tarjeta estructurada; además aclara el significado de `@lear.com` y de “acceso habitual” dentro del ejercicio.
- `Reto rápido` deja de usar temporizadores internos ocultos. Solo existe cuenta regresiva cuando el juego tiene un `Tiempo límite del juego` mayor que 0.
- Se limpia una sola vez cualquier partida del `Reto rápido` base que hubiera quedado cerrada por el temporizador interno anterior mientras el juego estaba configurado en 0, permitiendo repetir correctamente ese caso afectado.
- Con tiempo `0`, cualquier juego queda realmente **sin límite**.
- Preguntas mantienen su tiempo individual por pregunta; `0` continúa significando sin límite.
- Administración incorpora **Tiempo para todas** en Preguntas y **Tiempo para todos** en Juegos para aplicar un mismo valor masivamente, conservando después la posibilidad de editar cada registro de forma individual.
- Los juegos base instalados se actualizan una sola vez mediante `game_content_schema=4`; los juegos personalizados no se sobrescriben.

## Ajustes v9.13 · prioridad móvil en Preguntas y Juegos

- Durante una pregunta activa se elimina el bloque grande de participante/métricas y se deja únicamente un botón compacto **Regresar** para volver al inicio del cuestionario.
- Las métricas y **Cambiar participante** permanecen en la pantalla inicial del cuestionario, incluyendo el progreso guardado de la sesión.
- En Juegos, el resumen móvil se reorganiza en una cuadrícula compacta y el botón **Cambiar participante** conserva un tamaño pequeño y consistente, evitando iconos o controles sobredimensionados.
- Se ajustan espacios y tamaños móviles para dar prioridad visual al contenido de preguntas y juegos.
