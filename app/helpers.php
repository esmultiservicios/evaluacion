<?php
declare(strict_types=1);
function load_env(string $path): void {
    if (!is_file($path)) return;
    $lines=file($path, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) ?: [];
    foreach($lines as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=explode('=',$line,2);$v=trim($v);if(strlen($v)>=2&&(($v[0]==='"'&&$v[-1]==='"')||($v[0]==="'"&&$v[-1]==="'")))$v=substr($v,1,-1);$_ENV[trim($k)]=$v;putenv(trim($k).'='.$v);}
}
function envv(string $key,string $default=''): string { $v=$_ENV[$key]??getenv($key); return ($v===false||$v===null)?$default:(string)$v; }
function db(): PDO { return Database::instance(); }
function e(mixed $v): string { return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8'); }
function setting(string $key,string $default=''): string { static $cache=[]; if(array_key_exists($key,$cache))return $cache[$key]; try{$q=db()->prepare('SELECT setting_value FROM settings WHERE setting_key=?');$q->execute([$key]);$v=$q->fetchColumn();return $cache[$key]=$v===false?$default:(string)$v;}catch(Throwable){return $default;} }
function set_setting(string $key,string $value): void { $q=db()->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$q->execute([$key,$value]); }
function browser_title(string $fallback='Evaluación Corporativa'): string { $v=trim(setting('browser_title','')); return $v!==''?$v:setting('app_name',$fallback); }
function site_favicon(string $prefix=''): string { $path=trim(setting('favicon_path','')); if($path==='')$path=trim(setting('logo_path','')); if($path==='')$path='assets/img/favicon.svg'; $full=defined('ROOT_PATH')?ROOT_PATH.'/'.ltrim($path,'/'):''; $v=($full!==''&&is_file($full))?(string)filemtime($full):'1'; return $prefix.ltrim($path,'/').'?v='.$v; }
function app_url(string $path=''): string { return rtrim(envv('APP_URL',''),'/').'/'.ltrim($path,'/'); }
function client_ip(): string { return substr((string)($_SERVER['REMOTE_ADDR']??''),0,64); }
function csrf_token(): string { if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf']; }
function verify_csrf(): void { $t=(string)($_POST['csrf']??$_SERVER['HTTP_X_CSRF_TOKEN']??'');if(!hash_equals((string)($_SESSION['csrf']??''),$t))throw new RuntimeException('La sesión del formulario venció. Recarga la página.'); }
function json_out(array $data,int $code=200): never { http_response_code($code);header('Content-Type: application/json; charset=utf-8');echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit; }
function secret_key(): string { $hex=envv('APP_KEY'); if(!preg_match('/^[a-f0-9]{64}$/i',$hex))throw new RuntimeException('APP_KEY inválida.'); return hex2bin($hex); }
function secret_encrypt(string $plain): string { if($plain==='')return ''; $iv=random_bytes(12);$tag='';$c=openssl_encrypt($plain,'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,$iv,$tag);if($c===false)throw new RuntimeException('No se pudo cifrar el secreto.');return 'enc:v1:'.base64_encode($iv.$tag.$c); }
function secret_decrypt(?string $value): string { $v=(string)$value;if($v===''||!str_starts_with($v,'enc:v1:'))return $v;$raw=base64_decode(substr($v,7),true);if($raw===false||strlen($raw)<29)return '';$iv=substr($raw,0,12);$tag=substr($raw,12,16);$c=substr($raw,28);$p=openssl_decrypt($c,'aes-256-gcm',secret_key(),OPENSSL_RAW_DATA,$iv,$tag);return $p===false?'':$p; }
function audit(string $action,string $entityType='',?int $entityId=null,string $detail=''): void { try{$q=db()->prepare('INSERT INTO audit_log(user_id,action,entity_type,entity_id,detail,ip_address) VALUES(?,?,?,?,?,?)');$q->execute([$_SESSION['user_id']??null,$action,$entityType?:null,$entityId,$detail?:null,client_ip()]);}catch(Throwable){} }
function flash(string $type,string $message): void { $_SESSION['flash']=['type'=>$type,'message'=>$message]; }
function pull_flash(): ?array { $f=$_SESSION['flash']??null;unset($_SESSION['flash']);return $f; }

function ensure_runtime_schema(): void {
    static $done=false; if($done) return; $done=true;
    try{
        db()->exec("CREATE TABLE IF NOT EXISTS password_reset_tokens (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          user_id BIGINT UNSIGNED NOT NULL,
          token_hash CHAR(64) NOT NULL UNIQUE,
          expires_at DATETIME NOT NULL,
          used_at DATETIME NULL,
          ip_address VARCHAR(64) NULL,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_reset_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
          INDEX(user_id,expires_at), INDEX(expires_at,used_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS games (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          title VARCHAR(180) NOT NULL, slug VARCHAR(190) NOT NULL UNIQUE,
          game_type VARCHAR(40) NOT NULL, category VARCHAR(100) NOT NULL DEFAULT 'Ciberseguridad',
          difficulty ENUM('Fácil','Intermedio','Avanzado') NOT NULL DEFAULT 'Intermedio',
          intro TEXT NULL, content_json LONGTEXT NOT NULL, active TINYINT(1) NOT NULL DEFAULT 1,
          sound_enabled TINYINT(1) NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX(active,sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->exec("CREATE TABLE IF NOT EXISTS game_attempts (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, game_id BIGINT UNSIGNED NOT NULL,
          employee_id BIGINT UNSIGNED NULL, badge_snapshot VARCHAR(40) NULL, employee_name_snapshot VARCHAR(180) NULL,
          score DECIMAL(6,2) NOT NULL DEFAULT 0, correct_answers INT NOT NULL DEFAULT 0, total_items INT NOT NULL DEFAULT 0,
          answers_json LONGTEXT NULL,
          started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, completed_at DATETIME NULL,
          CONSTRAINT fk_ga_game FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
          CONSTRAINT fk_ga_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE SET NULL,
          INDEX(game_id,completed_at), INDEX(employee_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // v8.5: asignación persistente de juegos por participante. Evita que una selección
        // aleatoria cambie al recargar y conserva el recorrido una vez iniciado.
        db()->exec("CREATE TABLE IF NOT EXISTS game_assignments (
          id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          employee_id BIGINT UNSIGNED NOT NULL,
          game_id BIGINT UNSIGNED NOT NULL,
          position INT NOT NULL DEFAULT 0,
          rules_hash CHAR(64) NOT NULL,
          assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_gas_employee FOREIGN KEY(employee_id) REFERENCES employees(id) ON DELETE CASCADE,
          CONSTRAINT fk_gas_game FOREIGN KEY(game_id) REFERENCES games(id) ON DELETE CASCADE,
          UNIQUE KEY uq_game_assignment(employee_id,game_id),
          INDEX(employee_id,position), INDEX(rules_hash)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // v8.3: guarda el detalle de cada reto para revisión posterior sin permitir un segundo envío.
        try{
          $col=db()->query("SHOW COLUMNS FROM game_attempts LIKE 'answers_json'")->fetch();
          if(!$col) db()->exec("ALTER TABLE game_attempts ADD COLUMN answers_json LONGTEXT NULL AFTER total_items");
        }catch(Throwable){}
        $gameCount=(int)db()->query("SELECT COUNT(*) FROM games")->fetchColumn();
        if($gameCount===0){
          $games=game_seed_data();$ins=db()->prepare("INSERT INTO games(title,slug,game_type,category,difficulty,intro,content_json,active,sound_enabled,sort_order) VALUES(?,?,?,?,?,?,?,1,1,?)");
          foreach($games as $i=>$g)$ins->execute([$g['title'],$g['slug'],$g['type'],'Ciberseguridad',$g['difficulty'],$g['intro'],json_encode($g['items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$i+1]);
        }
        // v8.2: mejora los 8 juegos base ya instalados sin tocar juegos personalizados.
        if(setting('game_content_schema','1')!=='2'){
          $findBase=db()->prepare('SELECT id FROM games WHERE slug=? LIMIT 1');
          $up=db()->prepare('UPDATE games SET game_type=?,difficulty=?,intro=?,content_json=? WHERE slug=?');
          $add=db()->prepare('INSERT INTO games(title,slug,game_type,category,difficulty,intro,content_json,active,sound_enabled,sort_order) VALUES(?,?,?,?,?,?,?,1,1,?)');
          foreach(game_seed_data() as $i=>$g){
            $findBase->execute([$g['slug']]);
            if($findBase->fetchColumn()){
              $up->execute([$g['type'],$g['difficulty'],$g['intro'],json_encode($g['items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$g['slug']]);
            }else{
              $add->execute([$g['title'],$g['slug'],$g['type'],'Ciberseguridad',$g['difficulty'],$g['intro'],json_encode($g['items'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$i+1]);
            }
          }
          set_setting('game_content_schema','2');
        }

        // v8.4: banco inicial de preguntas. Se instala una sola vez y únicamente
        // cuando una instalación existente todavía no tiene ninguna pregunta.
        // Después el administrador conserva control total del banco.
        if(setting('question_seed_schema','0')!=='1'){
          $questionCount=(int)db()->query("SELECT COUNT(*) FROM questions")->fetchColumn();
          if($questionCount===0){
            $insertQuestion=db()->prepare('INSERT INTO questions(question_text,points,active,created_by) VALUES(?,1,1,NULL)');
            $insertOption=db()->prepare('INSERT INTO question_options(question_id,option_text,is_correct,sort_order) VALUES(?,?,?,?)');
            foreach(question_seed_data() as $question){
              $insertQuestion->execute([$question['question']]);
              $questionId=(int)db()->lastInsertId();
              foreach($question['options'] as $index=>$option){
                $insertOption->execute([$questionId,$option,$index===$question['correct']?1:0,$index+1]);
              }
            }
          }
          set_setting('question_seed_schema','1');
        }
    }catch(Throwable){}
}

function question_seed_data(): array {
 return [
  ['question'=>'Recibes un correo urgente que pide confirmar tu contraseña desde un enlace. ¿Qué debes hacer primero?','options'=>['Abrir el enlace para comprobar si funciona','Responder con tu contraseña para evitar el bloqueo','No interactuar y validar la solicitud por un canal oficial','Reenviar el correo a un compañero para que lo pruebe'],'correct'=>2],
  ['question'=>'Un compañero te solicita tu código MFA porque dice que TI está revisando tu cuenta. ¿Cuál es la acción correcta?','options'=>['Compartirlo si conoces al compañero','No compartirlo y confirmar con TI por un canal oficial','Compartirlo y cambiar la contraseña después','Enviar una captura del código'],'correct'=>1],
  ['question'=>'Recibes un archivo ZIP inesperado de un remitente externo. ¿Qué práctica es más segura?','options'=>['Abrirlo si el antivirus no muestra alertas','Cambiarle el nombre antes de abrirlo','Validar remitente y contexto antes de descargar o abrir','Subirlo a una carpeta compartida para revisarlo después'],'correct'=>2],
  ['question'=>'¿Cuál de estas contraseñas es más resistente?','options'=>['Empresa2026','Password123!','Lago-Cobre-Nube-47!','MiNombre123'],'correct'=>2],
  ['question'=>'Si te alejas de tu computadora por unos minutos, ¿qué debes hacer?','options'=>['Dejarla abierta si estás dentro de la oficina','Bloquear la pantalla antes de alejarte','Apagar solamente el monitor','Minimizar las ventanas abiertas'],'correct'=>1],
  ['question'=>'Encuentras una memoria USB sin identificar dentro de las instalaciones. ¿Qué debes hacer?','options'=>['Conectarla para identificar al dueño','Probarla en una computadora que no uses','Entregarla a TI o Seguridad sin conectarla','Abrirla únicamente si no contiene archivos ejecutables'],'correct'=>2],
  ['question'=>'¿Qué debes hacer con un correo que parece phishing?','options'=>['Eliminarlo sin avisar a nadie','Reportarlo por el canal oficial definido por la empresa','Responder para confirmar si el remitente es real','Abrir los enlaces desde el teléfono'],'correct'=>1],
  ['question'=>'Necesitas conectarte a una red Wi‑Fi pública para trabajar. ¿Cuál es la opción más segura?','options'=>['Conectarte a cualquier red con mejor señal','Usar el acceso corporativo seguro o VPN según el procedimiento de la empresa','Desactivar el antivirus para mejorar la conexión','Compartir archivos mientras la red esté disponible'],'correct'=>1],
  ['question'=>'¿Por qué es importante mantener actualizado el software corporativo?','options'=>['Solo para cambiar el diseño de las aplicaciones','Porque las actualizaciones pueden corregir vulnerabilidades conocidas','Para evitar tener que usar MFA','Porque aumenta automáticamente el espacio de almacenamiento'],'correct'=>1],
  ['question'=>'Un sitio muestra HTTPS y un candado. ¿Eso confirma por sí solo que el sitio pertenece a la empresa?','options'=>['Sí, el candado garantiza que el sitio es legítimo','Sí, siempre que cargue rápido','No, también se debe validar el dominio y el contexto','No, porque HTTPS nunca es seguro'],'correct'=>2],
 ];
}

function game_seed_data(): array {
 return [
 ['title'=>'Detecta el peligro','slug'=>'detecta-el-peligro','type'=>'hotspot','difficulty'=>'Intermedio','intro'=>'Explora un correo realista y toca las señales que podrían indicar un intento de fraude.','visual'=>'email-alert.svg','items'=>[
  ['prompt'=>'Encuentra las 3 señales de riesgo en este correo antes de continuar.','scene'=>'email','hotspots'=>[
    ['x'=>74,'y'=>24,'label'=>'Dominio sospechoso','correct'=>1,'explanation'=>'El remitente usa empresa-seguridad.example en lugar del dominio corporativo real.'],
    ['x'=>51,'y'=>49,'label'=>'Urgencia artificial','correct'=>1,'explanation'=>'La presión de tiempo busca que actúes sin validar la solicitud.'],
    ['x'=>51,'y'=>72,'label'=>'Enlace externo','correct'=>1,'explanation'=>'El botón dirige a un dominio que no pertenece a la empresa.'],
    ['x'=>16,'y'=>14,'label'=>'Logo','correct'=>0,'explanation'=>'El logo por sí solo no confirma legitimidad; puede copiarse fácilmente.']],
   'explanation'=>'Excelente: dominio, urgencia y destino del enlace deben validarse antes de interactuar.'],
  ['prompt'=>'Ahora localiza las 2 señales más importantes en este segundo mensaje.','scene'=>'attachment','hotspots'=>[
    ['x'=>73,'y'=>26,'label'=>'Remitente externo','correct'=>1,'explanation'=>'El dominio no coincide con el proveedor habitual.'],
    ['x'=>52,'y'=>70,'label'=>'Adjunto comprimido','correct'=>1,'explanation'=>'Un ZIP inesperado puede contener código malicioso.'],
    ['x'=>19,'y'=>48,'label'=>'Saludo','correct'=>0,'explanation'=>'Un saludo genérico puede llamar la atención, pero no prueba por sí solo que sea malicioso.']],
   'explanation'=>'Muy bien. Valida remitente y adjuntos inesperados por un canal oficial.']]],
 ['title'=>'Phishing o legítimo','slug'=>'phishing-o-legitimo','type'=>'binary','difficulty'=>'Fácil','intro'=>'Clasifica mensajes realistas y aprende qué señales pesan más al decidir.','visual'=>'phishing-check.svg','items'=>[
  ['prompt'=>'RRHH <rrhh@empresa.com> · “Tu constancia está disponible en el portal interno habitual.”','options'=>['Phishing','Legítimo'],'correct'=>1,'explanation'=>'Bien: remitente y canal esperado son señales positivas, aunque siempre conviene validar el enlace antes de abrirlo.'],
  ['prompt'=>'Microsoft Security <alert@micros0ft-security.example> · “Último aviso: confirma tu contraseña en 10 minutos.”','options'=>['Phishing','Legítimo'],'correct'=>0,'explanation'=>'Dominio alterado, presión de tiempo y solicitud de credenciales son señales claras de riesgo.'],
  ['prompt'=>'Compras <compras@empresa.com> · “Orden 4821 disponible en el ERP. Ingresa desde tu acceso habitual.”','options'=>['Phishing','Legítimo'],'correct'=>1,'explanation'=>'El mensaje remite al flujo conocido y no exige credenciales desde un enlace inesperado.']]],
 ['title'=>'¿Qué harías tú?','slug'=>'que-harias-tu','type'=>'scenario','difficulty'=>'Intermedio','intro'=>'Toma decisiones frente a situaciones de trabajo y descubre la alternativa más segura.','visual'=>'mfa-decision.svg','items'=>[
  ['prompt'=>'Un compañero te pide tu código MFA porque “TI está probando tu cuenta”.','options'=>['Se lo comparto si conozco al compañero','No lo comparto y valido con TI por un canal oficial','Lo envío y luego cambio la contraseña','Le mando una captura'],'correct'=>1,'explanation'=>'Un código MFA es personal. Valida solicitudes inesperadas por un canal oficial independiente.'],
  ['prompt'=>'Encuentras una memoria USB sin identificar en el estacionamiento.','options'=>['La conecto para identificar al dueño','La llevo a TI/Seguridad sin conectarla','La pruebo en una PC que no uso','La conecto solo si no tiene archivos .exe'],'correct'=>1,'explanation'=>'Dispositivos desconocidos pueden ser un vector de ataque. Entrégalos sin conectarlos.'],
  ['prompt'=>'Recibes una llamada que dice ser del banco y te pide instalar una app de soporte remoto.','options'=>['La instalo si saben mi nombre','Cuelgo y llamo al número oficial del banco','La instalo y luego la borro','Comparto pantalla sin dar contraseña'],'correct'=>1,'explanation'=>'Corta el contacto y valida por un canal que tú mismo inicies.']]],
 ['title'=>'Encuentra los errores','slug'=>'encuentra-los-errores','type'=>'find','difficulty'=>'Avanzado','intro'=>'Inspecciona una pantalla y encuentra todos los errores antes de enviar información sensible.','visual'=>'login-errors.svg','items'=>[
  ['prompt'=>'Encuentra los 3 errores de seguridad en esta pantalla de inicio de sesión.','scene'=>'login','hotspots'=>[
    ['x'=>55,'y'=>19,'label'=>'Dominio falso','correct'=>1,'explanation'=>'empresa-login.example no es el dominio corporativo esperado.'],
    ['x'=>52,'y'=>58,'label'=>'Solicita código MFA junto a la contraseña','correct'=>1,'explanation'=>'Un formulario inesperado que pide todo a la vez merece validación adicional.'],
    ['x'=>53,'y'=>80,'label'=>'Mensaje de presión','correct'=>1,'explanation'=>'La amenaza de bloqueo inmediato busca reducir tu tiempo de análisis.'],
    ['x'=>17,'y'=>34,'label'=>'Candado','correct'=>0,'explanation'=>'El candado solo indica cifrado de la conexión; no confirma la identidad del sitio.']],
   'explanation'=>'Perfecto. El dominio, la solicitud inusual y la presión de tiempo forman una combinación de alto riesgo.'],
  ['prompt'=>'Encuentra las 2 señales de riesgo en este aviso de soporte.','scene'=>'support','hotspots'=>[
    ['x'=>74,'y'=>28,'label'=>'Dominio externo','correct'=>1,'explanation'=>'El remitente no pertenece al dominio oficial.'],
    ['x'=>52,'y'=>69,'label'=>'Pide desactivar protección','correct'=>1,'explanation'=>'Soporte legítimo no debería pedirte desactivar controles de seguridad sin un procedimiento validado.'],
    ['x'=>20,'y'=>49,'label'=>'Número de ticket','correct'=>0,'explanation'=>'Un número de ticket puede ser inventado y no valida por sí solo el mensaje.']],
   'explanation'=>'Excelente. Reporta el mensaje y confirma el incidente desde el canal oficial.']]],
 ['title'=>'Reto rápido','slug'=>'reto-rapido','type'=>'speed','difficulty'=>'Intermedio','intro'=>'Responde micro-retos contra reloj. La precisión vale más que correr sin revisar.','visual'=>'speed-shield.svg','items'=>[
  ['prompt'=>'¿Cuál contraseña es más resistente?','options'=>['Empresa2026','Password123!','Lago-Cobre-Nube-47!','Edwin123'],'correct'=>2,'seconds'=>12,'explanation'=>'Las frases largas y únicas son más resistentes y más fáciles de recordar que patrones comunes.'],
  ['prompt'=>'¿Qué haces antes de escanear un QR inesperado?','options'=>['Lo abro y luego reviso','Valido quién lo envió y el destino','Desactivo datos móviles','Le tomo captura'],'correct'=>1,'seconds'=>10,'explanation'=>'Los QR pueden ocultar destinos. Valida contexto y procedencia antes de abrirlos.'],
  ['prompt'=>'¿Qué protege mejor una cuenta además de la contraseña?','options'=>['MFA','Modo oscuro','Cambiar el fondo','Cerrar el navegador'],'correct'=>0,'seconds'=>8,'explanation'=>'MFA agrega una capa adicional y reduce el impacto de una contraseña comprometida.']]],
 ['title'=>'Ordena los pasos','slug'=>'ordena-los-pasos','type'=>'order','difficulty'=>'Intermedio','intro'=>'Arrastra y suelta las acciones hasta construir la secuencia correcta de respuesta.','visual'=>'order-incident.svg','items'=>[
  ['prompt'=>'Ordena qué hacer ante un correo sospechoso.','options'=>['No interactuar con enlaces o adjuntos','Verificar remitente y contexto','Reportar por el canal oficial','Eliminar o aislar según el procedimiento'],'correctOrder'=>[0,1,2,3],'explanation'=>'La secuencia reduce la exposición, valida la sospecha y permite que Seguridad actúe.'],
  ['prompt'=>'Ordena la respuesta ante una contraseña posiblemente comprometida.','options'=>['Cambiar la contraseña desde el sitio oficial','Cerrar sesiones activas desconocidas','Activar o revisar MFA','Reportar el incidente si hubo acceso no autorizado'],'correctOrder'=>[0,1,2,3],'explanation'=>'Primero recupera el control de la cuenta, luego refuerza la autenticación y reporta cualquier acceso indebido.']]],
 ['title'=>'Relaciona conceptos','slug'=>'relaciona-conceptos','type'=>'match','difficulty'=>'Fácil','intro'=>'Arrastra cada concepto a su definición o selecciónalo y toca su destino desde el teléfono.','visual'=>'concept-map.svg','items'=>[
  ['prompt'=>'Relaciona cada concepto con la definición correcta.','pairs'=>[['Phishing','Mensaje diseñado para engañar y obtener datos'],['MFA','Segundo factor para verificar identidad'],['Ransomware','Malware que cifra o bloquea información'],['Ingeniería social','Manipulación de personas para obtener acceso']],'explanation'=>'Reconocer el vocabulario ayuda a identificar el riesgo más rápido en situaciones reales.'],
  ['prompt'=>'Relaciona cada acción con su objetivo.','pairs'=>[['Actualizar','Corregir vulnerabilidades conocidas'],['Respaldar','Poder recuperar información'],['Reportar','Permitir que Seguridad investigue'],['Bloquear pantalla','Evitar acceso cuando te alejas']],'explanation'=>'Cada hábito cubre una capa distinta de protección.']]],
 ['title'=>'Verdadero o falso','slug'=>'verdadero-o-falso','type'=>'truefalse','difficulty'=>'Fácil','intro'=>'Decide si cada afirmación es verdadera o falsa y recibe retroalimentación inmediata.','visual'=>'truefalse-lock.svg','items'=>[
  ['prompt'=>'Si un sitio tiene HTTPS, necesariamente pertenece a la empresa que dice representar.','options'=>['Verdadero','Falso'],'correct'=>1,'explanation'=>'HTTPS cifra la conexión, pero un atacante también puede usar un certificado válido en un dominio falso.'],
  ['prompt'=>'Un código MFA debe tratarse como una contraseña y no compartirse.','options'=>['Verdadero','Falso'],'correct'=>0,'explanation'=>'Correcto. Los códigos MFA son secretos temporales y no deben compartirse con otras personas.'],
  ['prompt'=>'Es seguro reutilizar la misma contraseña si solo se usa en sistemas de trabajo.','options'=>['Verdadero','Falso'],'correct'=>1,'explanation'=>'Reutilizar contraseñas aumenta el impacto si una sola cuenta se compromete.']]]
 ];
}
function user_initials(string $name): string {
    $parts=preg_split('/\s+/u',trim($name),-1,PREG_SPLIT_NO_EMPTY)?:[];
    if(!$parts)return 'US';
    $first=mb_substr($parts[0],0,1,'UTF-8');
    $last=count($parts)>1?mb_substr($parts[count($parts)-1],0,1,'UTF-8'):mb_substr($parts[0],1,1,'UTF-8');
    return mb_strtoupper($first.$last,'UTF-8');
}

function ui_icon(string $name,string $class='ui-icon'): string {
    $paths=[
      'dashboard'=>'<path d="M4 13h6V4H4v9Zm10 7h6V11h-6v9ZM4 20h6v-3H4v3Zm10-13h6V4h-6v3Z"/>',
      'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
      'question'=>'<circle cx="12" cy="12" r="10"/><path d="M9.1 9a3 3 0 1 1 5.2 2c-.9.7-1.8 1.2-1.8 3"/><path d="M12 17h.01"/>',
      'chart'=>'<path d="M3 3v18h18"/><path d="m7 16 4-4 3 3 5-7"/>',
      'settings'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .34 1.88l.06.06-2.83 2.83-.06-.06A1.7 1.7 0 0 0 15 19.4a1.7 1.7 0 0 0-1 .6 1.7 1.7 0 0 0-.4 1.1V21H9.6v-.1A1.7 1.7 0 0 0 8 19.4a1.7 1.7 0 0 0-1.88.34l-.06.06-2.83-2.83.06-.06A1.7 1.7 0 0 0 3.6 15a1.7 1.7 0 0 0-1.5-1H2v-4h.1A1.7 1.7 0 0 0 3.6 8a1.7 1.7 0 0 0-.34-1.88l-.06-.06 2.83-2.83.06.06A1.7 1.7 0 0 0 8 3.6a1.7 1.7 0 0 0 1-.6 1.7 1.7 0 0 0 .4-1.1V2h4v.1A1.7 1.7 0 0 0 15 3.6a1.7 1.7 0 0 0 1.88-.34l.06-.06 2.83 2.83-.06.06A1.7 1.7 0 0 0 19.4 8a1.7 1.7 0 0 0 1.5 1h.1v4h-.1a1.7 1.7 0 0 0-1.5 2Z"/>',
      'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
      'lock'=>'<rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>',
      'search'=>'<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
      'x'=>'<path d="M18 6 6 18M6 6l12 12"/>',
      'plus'=>'<path d="M12 5v14M5 12h14"/>',
      'upload'=>'<path d="M12 16V4M7 9l5-5 5 5"/><path d="M5 20h14"/>',
      'download'=>'<path d="M12 4v12M7 11l5 5 5-5"/><path d="M5 20h14"/>',
      'save'=>'<path d="M5 3h12l4 4v14H3V3h2Z"/><path d="M7 3v6h10V3M7 21v-7h10v7"/>',
      'trash'=>'<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
      'edit'=>'<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z"/>',
      'eye'=>'<path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6S2.5 12 2.5 12Z"/><circle cx="12" cy="12" r="2.6"/>',
      'logout'=>'<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/>',
      'menu'=>'<path d="M4 7h16M4 12h16M4 17h16"/>',
      'external'=>'<path d="M14 3h7v7M10 14 21 3"/><path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>',
      'chevron'=>'<path d="m9 18 6-6-6-6"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>',
      'clock'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
      'file'=>'<path d="M6 2h8l4 4v16H6V2Z"/><path d="M14 2v5h5M9 13h6M9 17h6"/>',
      'image'=>'<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
      'cards'=>'<rect x="3" y="4" width="8" height="7" rx="1"/><rect x="13" y="4" width="8" height="7" rx="1"/><rect x="3" y="13" width="8" height="7" rx="1"/><rect x="13" y="13" width="8" height="7" rx="1"/>',
      'list'=>'<path d="M9 6h11M9 12h11M9 18h11"/><circle cx="4" cy="6" r="1"/><circle cx="4" cy="12" r="1"/><circle cx="4" cy="18" r="1"/>',
      'home'=>'<path d="m3 11 9-8 9 8"/><path d="M5 10v11h14V10M9 21v-7h6v7"/>',
      'shield'=>'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/>',
      'refresh'=>'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 8a7 7 0 0 1 11.8-2L20 8M4 16l2.1 2a7 7 0 0 0 11.8-2"/>',
      'fullscreen'=>'<path d="M8 3H3v5M16 3h5v5M8 21H3v-5M16 21h5v-5"/>',
      'excel'=>'<path d="M5 3h10l4 4v14H5V3Z"/><path d="M15 3v5h5M8 11l5 6M13 11l-5 6"/>',
      'pdf'=>'<path d="M5 3h10l4 4v14H5V3Z"/><path d="M15 3v5h5"/><path d="M8 16v-5h2a1.5 1.5 0 0 1 0 3H8M13 16v-5h1.5a2.5 2.5 0 0 1 0 5H13M18 16v-5h3"/>'
    ];
    $body=$paths[$name]??$paths['question'];
    return '<svg class="'.e($class).'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$body.'</svg>';
}
