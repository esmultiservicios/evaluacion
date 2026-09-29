<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')]);
    session_start();
}

$root = dirname(__DIR__);
$lock = $root.'/install.lock';
if (is_file($lock)) { header('Location: ../'); exit; }

function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function old(string $key, string $default=''): string { return h((string)($_POST[$key] ?? $default)); }
function checked(string $key, string $value, string $default=''): string { return ((string)($_POST[$key] ?? $default) === $value) ? 'checked' : ''; }
function installCsrf(): string { if(empty($_SESSION['install_csrf'])) $_SESSION['install_csrf']=bin2hex(random_bytes(32)); return (string)$_SESSION['install_csrf']; }
function verifyInstallCsrf(): void { $t=(string)($_POST['csrf']??''); if(!hash_equals((string)($_SESSION['install_csrf']??''),$t)) throw new RuntimeException('La sesión del instalador venció. Recarga la página.'); }
function jsonResponse(array $data,int $status=200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function smtpExpect($fp,array $codes): void { $resp=''; while(($line=fgets($fp,515))!==false){$resp.=$line;if(strlen($line)<4||$line[3]!=='-')break;} $code=(int)substr($resp,0,3); if(!in_array($code,$codes,true)) throw new RuntimeException('SMTP '.$code.': '.trim($resp)); }
function smtpCmd($fp,string $cmd,array $codes): void { fwrite($fp,$cmd."\r\n"); smtpExpect($fp,$codes); }
function mailHeader(string $v): string { return preg_match('/[^\x20-\x7E]/',$v)?'=?UTF-8?B?'.base64_encode($v).'?=':str_replace(["\r","\n"],'',$v); }
function testSmtp(array $c,string $to,string $company): array {
    $host=trim((string)$c['smtp_host']);$port=(int)$c['smtp_port'];$secure=strtolower((string)$c['smtp_secure']);$user=trim((string)$c['smtp_user']);$pass=(string)$c['smtp_password'];
    if($host===''||$port<1||$port>65535||!in_array($secure,['tls','ssl'],true)||!filter_var($user,FILTER_VALIDATE_EMAIL)||$pass==='') return ['success'=>false,'message'=>'Completa correctamente servidor, puerto, seguridad, correo y contraseña SMTP.'];
    $target=($secure==='ssl'?'ssl://':'tcp://').$host.':'.$port;$fp=@stream_socket_client($target,$errno,$errstr,15,STREAM_CLIENT_CONNECT);if(!$fp)return ['success'=>false,'message'=>'No se pudo conectar al SMTP: '.$errstr];stream_set_timeout($fp,20);
    try{smtpExpect($fp,[220]);smtpCmd($fp,'EHLO '.($_SERVER['HTTP_HOST']??'localhost'),[250]);if($secure==='tls'){smtpCmd($fp,'STARTTLS',[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('No se pudo habilitar TLS.');smtpCmd($fp,'EHLO '.($_SERVER['HTTP_HOST']??'localhost'),[250]);}smtpCmd($fp,'AUTH LOGIN',[334]);smtpCmd($fp,base64_encode($user),[334]);smtpCmd($fp,base64_encode($pass),[235]);smtpCmd($fp,'MAIL FROM:<'.$user.'>',[250]);smtpCmd($fp,'RCPT TO:<'.$to.'>',[250,251]);smtpCmd($fp,'DATA',[354]);$subject='Prueba de correo · Evaluación Corporativa';$html='<!doctype html><html><body style="margin:0;background:#f2f6f8;font-family:Arial,sans-serif;color:#18324a"><div style="max-width:620px;margin:36px auto;background:#fff;border:1px solid #dbe5eb;border-radius:18px;overflow:hidden"><div style="padding:22px 28px;border-top:4px solid #1595a7"><strong style="font-size:18px;color:#0b315b">'.h($company?:'Evaluación Corporativa').'</strong></div><div style="padding:28px"><div style="font-size:12px;font-weight:700;color:#1595a7">CONFIGURACIÓN DE CORREO</div><h1 style="font-size:26px;color:#0b315b">Prueba enviada correctamente</h1><p>La conexión SMTP del instalador funciona y está lista para enviar notificaciones.</p></div></div></body></html>';$headers=['From: '.mailHeader($company?:'Evaluación Corporativa').' <'.$user.'>','To: <'.$to.'>','Subject: =?UTF-8?B?'.base64_encode($subject).'?=','MIME-Version: 1.0','Content-Type: text/html; charset=UTF-8','Content-Transfer-Encoding: base64'];fwrite($fp,implode("\r\n",$headers)."\r\n\r\n".chunk_split(base64_encode($html))."\r\n.\r\n");smtpExpect($fp,[250]);smtpCmd($fp,'QUIT',[221]);fclose($fp);return ['success'=>true,'message'=>'Prueba SMTP enviada correctamente a '.$to.'.'];}catch(Throwable $e){@fwrite($fp,"QUIT\r\n");@fclose($fp);return ['success'=>false,'message'=>$e->getMessage()];}
}
function testGraph(array $c,string $to,string $company): array {
    if(!function_exists('curl_init'))return ['success'=>false,'message'=>'cURL no está habilitado en el servidor.'];$tenant=trim((string)$c['graph_tenant_id']);$client=trim((string)$c['graph_client_id']);$secret=(string)$c['graph_client_secret'];$from=trim((string)$c['graph_user']);if($tenant===''||$client===''||$secret===''||!filter_var($from,FILTER_VALIDATE_EMAIL))return ['success'=>false,'message'=>'Completa Tenant ID, Client ID, Client Secret y Graph User.'];
    $ch=curl_init('https://login.microsoftonline.com/'.rawurlencode($tenant).'/oauth2/v2.0/token');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_POSTFIELDS=>http_build_query(['client_id'=>$client,'scope'=>'https://graph.microsoft.com/.default','client_secret'=>$secret,'grant_type'=>'client_credentials']),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded']]);$raw=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);$j=json_decode((string)$raw,true);if($code<200||$code>=300||empty($j['access_token']))return ['success'=>false,'message'=>'Error obteniendo token de Microsoft Graph: '.($err?:'HTTP '.$code)];
    $html='<!doctype html><html><body style="margin:0;background:#f2f6f8;font-family:Arial,sans-serif;color:#18324a"><div style="max-width:620px;margin:36px auto;background:#fff;border:1px solid #dbe5eb;border-radius:18px;overflow:hidden"><div style="padding:22px 28px;border-top:4px solid #1595a7"><strong style="font-size:18px;color:#0b315b">'.h($company?:'Evaluación Corporativa').'</strong></div><div style="padding:28px"><div style="font-size:12px;font-weight:700;color:#1595a7">MICROSOFT GRAPH</div><h1 style="font-size:26px;color:#0b315b">Prueba enviada correctamente</h1><p>La conexión Microsoft Graph del instalador funciona y está lista para enviar notificaciones.</p></div></div></body></html>';
    $payload=['message'=>['subject'=>'Prueba de correo · Evaluación Corporativa','body'=>['contentType'=>'HTML','content'=>$html],'toRecipients'=>[['emailAddress'=>['address'=>$to]]]],'saveToSentItems'=>true];$ch=curl_init('https://graph.microsoft.com/v1.0/users/'.rawurlencode($from).'/sendMail');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE),CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$j['access_token'],'Content-Type: application/json']]);$resp=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);return $code===202?['success'=>true,'message'=>'Prueba Microsoft Graph enviada correctamente a '.$to.'.']:['success'=>false,'message'=>'Microsoft Graph no pudo enviar la prueba: '.($err?:'HTTP '.$code.' '.$resp)];
}

$checks=[
 ['label'=>'PHP >= 8.1','ok'=>version_compare(PHP_VERSION,'8.1.0','>='),'required'=>true,'help'=>'Versión actual: '.PHP_VERSION],
 ['label'=>'PDO MySQL','ok'=>extension_loaded('pdo_mysql'),'required'=>true,'help'=>'Necesario para conectar con MySQL.'],
 ['label'=>'cURL','ok'=>extension_loaded('curl'),'required'=>true,'help'=>'Necesario para Microsoft Graph.'],
 ['label'=>'OpenSSL','ok'=>extension_loaded('openssl'),'required'=>true,'help'=>'Cifra secretos y habilita TLS.'],
 ['label'=>'mbstring','ok'=>extension_loaded('mbstring'),'required'=>true,'help'=>'Soporte correcto para texto UTF-8.'],
 ['label'=>'Soporte XLSX','ok'=>class_exists('ZipArchive')||function_exists('gzinflate'),'required'=>true,'help'=>'Se requiere ZIP o zlib para leer la plantilla Excel .xlsx.'],
 ['label'=>'Raíz escribible','ok'=>is_writable($root),'required'=>true,'help'=>'Permite crear .env e install.lock.'],
 ['label'=>'uploads/ escribible','ok'=>is_writable($root.'/uploads'),'required'=>true,'help'=>'Permite guardar el logo de la empresa.'],
];
$serverReady=!array_filter($checks,fn($x)=>$x['required']&&!$x['ok']);

$scheme=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')?'https':'http';
$host=(string)($_SERVER['HTTP_HOST']??'localhost');
$scriptDir=str_replace('\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/install/index.php')));
$webRoot=preg_replace('#/install$#','',$scriptDir)?:'';
$autoUrl=$scheme.'://'.$host.($webRoot==='/'?'':rtrim($webRoot,'/'));

if($_SERVER['REQUEST_METHOD']==='POST'&&!empty($_POST['ajax_action'])){
    try{
        verifyInstallCsrf();$action=(string)$_POST['ajax_action'];
        if($action==='test_db'){$dbHost=trim((string)($_POST['db_host']??''));$dbPort=(int)($_POST['db_port']??3306);$dbUser=trim((string)($_POST['db_user']??''));$dbPass=(string)($_POST['db_pass']??'');if($dbHost===''||$dbPort<1||$dbPort>65535||$dbUser==='')throw new RuntimeException('Completa servidor, puerto y usuario de MySQL.');$pdo=new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>6]);$pdo->query('SELECT 1');jsonResponse(['success'=>true,'message'=>'Conexión MySQL verificada correctamente.']);}
        if($action==='test_mail'){$to=strtolower(trim((string)($_POST['test_to']??'')));if(!filter_var($to,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Ingresa un correo válido para recibir la prueba.');if((string)($_POST['mail_setup']??'now')!=='now')throw new RuntimeException('Selecciona “Configurar correo ahora”.');$method=strtoupper((string)($_POST['mail_method']??'SMTP'));$cfg=$_POST;$company=trim((string)($_POST['company_name']??'Evaluación Corporativa'));$res=$method==='GRAPH'?testGraph($cfg,$to,$company):testSmtp($cfg,$to,$company);jsonResponse($res,$res['success']?200:422);}
        throw new RuntimeException('Acción no reconocida.');
    }catch(Throwable $e){jsonResponse(['success'=>false,'message'=>$e->getMessage()],422);}
}

$error='';$success=false;$startStep=1;$installPhase=6;
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        verifyInstallCsrf();
        foreach($checks as $check)if($check['required']&&!$check['ok'])throw new RuntimeException('Debes corregir el requisito obligatorio: '.$check['label'].'.');
        $installPhase=2;$dbHost=trim((string)($_POST['db_host']??''));$dbPort=(int)($_POST['db_port']??3306);$dbName=trim((string)($_POST['db_name']??''));$dbUser=trim((string)($_POST['db_user']??''));$dbPass=(string)($_POST['db_pass']??'');if($dbHost===''||$dbPort<1||$dbPort>65535||$dbUser===''||!preg_match('/^[A-Za-z0-9_]+$/',$dbName))throw new RuntimeException('Completa correctamente los datos de MySQL. El nombre de la base solo puede usar letras, números y guion bajo.');
        $pdo=new PDO("mysql:host=$dbHost;port=$dbPort;charset=utf8mb4",$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$safe=str_replace('`','``',$dbName);$pdo->exec("CREATE DATABASE IF NOT EXISTS `$safe` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$pdo->exec("USE `$safe`");$sql=file_get_contents($root.'/database.sql');if($sql===false)throw new RuntimeException('No se pudo leer database.sql.');foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql)?:[])) as $stmt){if($stmt!==''&&!str_starts_with($stmt,'--'))$pdo->exec($stmt);}
        $installPhase=3;$company=trim((string)($_POST['company_name']??''));$app=trim((string)($_POST['app_name']??''));$url=rtrim(trim((string)($_POST['app_url']??'')),'/');if($company===''||$app===''||!filter_var($url,FILTER_VALIDATE_URL))throw new RuntimeException('Completa correctamente empresa, nombre de la dinámica y URL pública.');
        $installPhase=4;$adminName=trim((string)($_POST['admin_name']??''));$adminEmail=strtolower(trim((string)($_POST['admin_email']??'')));$adminPass=(string)($_POST['admin_password']??'');$adminConfirm=(string)($_POST['admin_password_confirm']??'');if($adminName===''||!filter_var($adminEmail,FILTER_VALIDATE_EMAIL)||strlen($adminPass)<8)throw new RuntimeException('Completa correctamente la cuenta administradora y usa una contraseña de al menos 8 caracteres.');if($adminPass!==$adminConfirm)throw new RuntimeException('Las contraseñas de la cuenta administradora no coinciden.');
        $installPhase=5;$mailSetup=(string)($_POST['mail_setup']??'now');$method=strtoupper((string)($_POST['mail_method']??'SMTP'));if(!in_array($method,['SMTP','GRAPH'],true))$method='SMTP';$replyTo=trim((string)($_POST['reply_to']??''));if($replyTo!==''&&!filter_var($replyTo,FILTER_VALIDATE_EMAIL))throw new RuntimeException('El Reply-To no es un correo válido.');if($mailSetup==='now'){if($method==='SMTP'){if(trim((string)($_POST['smtp_host']??''))===''||(int)($_POST['smtp_port']??0)<1||!filter_var(trim((string)($_POST['smtp_user']??'')),FILTER_VALIDATE_EMAIL)||(string)($_POST['smtp_password']??'')==='')throw new RuntimeException('Completa correctamente la configuración SMTP.');if(!in_array(strtolower((string)($_POST['smtp_secure']??'')),['tls','ssl'],true))throw new RuntimeException('Selecciona TLS o SSL para SMTP.');}else{if(trim((string)($_POST['graph_tenant_id']??''))===''||trim((string)($_POST['graph_client_id']??''))===''||(string)($_POST['graph_client_secret']??'')===''||!filter_var(trim((string)($_POST['graph_user']??'')),FILTER_VALIDATE_EMAIL))throw new RuntimeException('Completa correctamente la configuración de Microsoft Graph.');}}
        $installPhase=6;$key=bin2hex(random_bytes(32));$env="APP_NAME=\"".addcslashes($app,"\"\\")."\"\nAPP_URL=\"".addcslashes($url,"\"\\")."\"\nAPP_KEY=$key\nDB_HOST=\"".addcslashes($dbHost,"\"\\")."\"\nDB_PORT=$dbPort\nDB_NAME=\"".addcslashes($dbName,"\"\\")."\"\nDB_USER=\"".addcslashes($dbUser,"\"\\")."\"\nDB_PASS=\"".addcslashes($dbPass,"\"\\")."\"\n";if(file_put_contents($root.'/.env',$env)===false)throw new RuntimeException('No se pudo crear el archivo .env. Revisa permisos de escritura.');
        $enc=function(string $plain)use($key):string{if($plain==='')return '';$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($plain,'aes-256-gcm',hex2bin($key),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('No se pudo cifrar un secreto.');return 'enc:v1:'.base64_encode($iv.$tag.$cipher);};
        $pdo->beginTransaction();
        $st=$pdo->prepare("INSERT INTO users(name,email,password_hash,role,status) VALUES(?,?,?,'admin','active') ON DUPLICATE KEY UPDATE name=VALUES(name),password_hash=VALUES(password_hash),role='admin',status='active'");$st->execute([$adminName,$adminEmail,password_hash($adminPass,PASSWORD_DEFAULT)]);
        $settings=['company_name'=>$company,'app_name'=>$app,'browser_title'=>$app,'welcome_text'=>'Ingresa tu número de gafete para comenzar. La evaluación solo puede completarse una vez.','completion_text'=>'Gracias. Tu participación fue registrada correctamente.','questions_per_attempt'=>'5','question_order_mode'=>'random','passing_score'=>'70','support_email'=>$adminEmail,'notification_email'=>$adminEmail,'notify_on_submission'=>'0'];$st=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach($settings as $k=>$v)$st->execute([$k,$v]);
        $pdo->exec('UPDATE email_config SET active=0');$st=$pdo->prepare('INSERT INTO email_config(method,smtp_host,smtp_port,smtp_secure,smtp_user,smtp_password,graph_tenant_id,graph_client_id,graph_client_secret,graph_user,from_name,reply_to,bcc,save_to_sent_items,active) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$st->execute([$method,trim((string)($_POST['smtp_host']??'')),(int)($_POST['smtp_port']??587),strtolower((string)($_POST['smtp_secure']??'tls')),trim((string)($_POST['smtp_user']??'')),$enc((string)($_POST['smtp_password']??'')),trim((string)($_POST['graph_tenant_id']??'')),trim((string)($_POST['graph_client_id']??'')),$enc((string)($_POST['graph_client_secret']??'')),trim((string)($_POST['graph_user']??'')),$company,$replyTo,'',1,$mailSetup==='now'?1:0]);$pdo->commit();
        if(file_put_contents($lock,date('c'))===false)throw new RuntimeException('La instalación terminó, pero no se pudo bloquear el instalador. Revisa permisos.');$success=true;
    }catch(Throwable $e){if(isset($pdo)&&$pdo instanceof PDO&&$pdo->inTransaction())$pdo->rollBack();$error=$e->getMessage();$startStep=max(1,min(6,$installPhase));}
}

function svgIcon(string $name): string {
    $map=[
      'server'=>'<path d="M5 5h14v5H5zM5 14h14v5H5z"/><path d="M8 7.5h.01M8 16.5h.01"/>',
      'db'=>'<ellipse cx="12" cy="5" rx="7" ry="3"/><path d="M5 5v6c0 1.7 3.1 3 7 3s7-1.3 7-3V5M5 11v6c0 1.7 3.1 3 7 3s7-1.3 7-3v-6"/>',
      'building'=>'<path d="M4 21h16M6 21V6l6-3 6 3v15M9 9h.01M15 9h.01M9 13h.01M15 13h.01M10 21v-4h4v4"/>',
      'user'=>'<circle cx="12" cy="8" r="4"/><path d="M4.5 21a7.5 7.5 0 0 1 15 0"/>',
      'mail'=>'<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>',
      'review'=>'<path d="M9 5H6a2 2 0 0 0-2 2v12h16V7a2 2 0 0 0-2-2h-3M9 3h6v4H9zM8 12h8M8 16h5"/>',
      'check'=>'<path d="m5 12 4 4L19 6"/>',
      'arrow'=>'<path d="M5 12h14M13 6l6 6-6 6"/>',
      'back'=>'<path d="M19 12H5m6 6-6-6 6-6"/>',
      'test'=>'<path d="M9 3h6M10 3v5l-5 9a2 2 0 0 0 1.8 3h10.4A2 2 0 0 0 19 17l-5-9V3M8 15h8"/>',
      'lock'=>'<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
      'smtp'=>'<path d="M4 6h16v12H4zM4 7l8 6 8-6"/>',
      'graph'=>'<path d="M4 7h7v7H4zM13 10h7v7h-7zM8 16h7v4H8z"/>',
      'install'=>'<path d="M12 3v12m0 0 4-4m-4 4-4-4M5 20h14"/>',
      'later'=>'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    ];
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($map[$name]??$map['check']).'</svg>';
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Instalador · Evaluación Corporativa Premium</title><link rel="icon" href="../assets/img/favicon.svg"><link rel="shortcut icon" href="../assets/img/favicon.svg"><link rel="apple-touch-icon" href="../assets/img/favicon.svg">
<link rel="stylesheet" href="../assets/css/install.css?v=4">
<link rel="stylesheet" href="../assets/css/notify.css?v=4">
<link rel="stylesheet" href="../assets/vendor/sweetalert2/sweetalert2.local.css?v=4">
<script>window.INSTALL_NOTIFY=<?=json_encode($error!==''?['type'=>'error','message'=>$error]:null,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>;</script>
</head>
<body>
<main class="install-shell">
  <header class="install-hero">
    <div class="hero-copy"><div class="hero-kicker">Asistente de instalación</div><h1>Evaluación Corporativa Premium</h1><p>Configuración guiada, clara y segura. Solo verás un paso a la vez.</p></div>
    <div class="hero-mark">EC</div>
  </header>

<?php if($success): ?>
  <section class="success-card">
    <div class="success-badge"><?=svgIcon('check')?></div>
    <h2>Instalación completada</h2>
    <p>La base de datos, la cuenta administrativa y la configuración inicial quedaron listas. El instalador fue bloqueado automáticamente.</p>
    <div class="success-actions"><a class="btn primary" href="../admin/?page=login"><?=svgIcon('lock')?> <span>Ir al panel administrativo</span></a><a class="btn secondary" href="../"><?=svgIcon('arrow')?> <span>Abrir evaluación</span></a></div>
  </section>
<?php else: ?>
  <div class="wizard-layout">
    <aside class="wizard-sidebar">
      <div class="progress-overview"><strong id="wizardCurrent">Paso 1 de 6</strong><span>Avance de instalación</span><div class="progress-track"><i id="wizardProgress"></i></div></div>
      <nav class="wizard-nav" aria-label="Pasos del instalador">
        <button type="button" data-nav-step="0"><span class="nav-num">1</span><span class="nav-copy"><strong>Requisitos</strong><small>Servidor y extensiones</small></span></button>
        <button type="button" data-nav-step="1"><span class="nav-num">2</span><span class="nav-copy"><strong>Base de datos</strong><small>Conexión MySQL</small></span></button>
        <button type="button" data-nav-step="2"><span class="nav-num">3</span><span class="nav-copy"><strong>Empresa</strong><small>Identidad del sistema</small></span></button>
        <button type="button" data-nav-step="3"><span class="nav-num">4</span><span class="nav-copy"><strong>Administrador</strong><small>Cuenta principal</small></span></button>
        <button type="button" data-nav-step="4"><span class="nav-num">5</span><span class="nav-copy"><strong>Correo</strong><small>SMTP o Graph</small></span></button>
        <button type="button" data-nav-step="5"><span class="nav-num">6</span><span class="nav-copy"><strong>Revisión</strong><small>Confirmar e instalar</small></span></button>
      </nav>
    </aside>

    <section class="wizard-main">
      <form id="installForm" class="wizard-form" method="post" novalidate data-start-step="<?=$startStep?>" data-server-ready="<?=$serverReady?'1':'0'?>">
        <input type="hidden" name="csrf" value="<?=h(installCsrf())?>">

        <section class="wizard-step" data-step="1">
          <div class="step-head"><div class="step-icon"><?=svgIcon('server')?></div><div><h2>Requisitos del servidor</h2><p>Validamos automáticamente lo necesario. Los elementos obligatorios deben estar correctos antes de continuar.</p></div></div>
          <div class="requirements">
          <?php foreach($checks as $check): ?>
            <div class="req-card <?=$check['ok']?'ok':'bad'?> <?=$check['required']?'required':'optional'?>"><div class="req-state"><?=$check['ok']?'✓':($check['required']?'×':'!')?></div><div class="req-copy"><strong><?=h($check['label'])?></strong><small><?=h($check['help'])?><?=$check['required']?' · Obligatorio':' · Opcional'?></small></div></div>
          <?php endforeach; ?>
          </div>
          <div class="inline-note"><strong>ZIP:</strong> si la extensión no está habilitada, el sistema puede instalarse. La exportación usará un formato Excel compatible como respaldo.</div>
        </section>

        <section class="wizard-step" data-step="2">
          <div class="step-head"><div class="step-icon"><?=svgIcon('db')?></div><div><h2>Base de datos</h2><p>Ingresa las credenciales de MySQL. El instalador creará la base de datos si todavía no existe.</p></div></div>
          <div class="form-grid">
            <div class="field"><label for="db_host">Servidor</label><div class="input-wrap"><input id="db_host" name="db_host" value="<?=old('db_host','127.0.0.1')?>" required autocomplete="off"></div></div>
            <div class="field"><label for="db_port">Puerto</label><div class="input-wrap"><input id="db_port" type="number" min="1" max="65535" name="db_port" value="<?=old('db_port','3306')?>" required></div></div>
            <div class="field"><label for="db_name">Base de datos</label><div class="input-wrap"><input id="db_name" name="db_name" value="<?=old('db_name','evaluacion_premium')?>" required autocomplete="off"></div><span class="field-hint">Solo letras, números y guion bajo.</span></div>
            <div class="field"><label for="db_user">Usuario</label><div class="input-wrap"><input id="db_user" name="db_user" value="<?=old('db_user','root')?>" required autocomplete="username"></div></div>
            <div class="field full"><label for="db_pass">Contraseña</label><div class="input-wrap"><input id="db_pass" type="password" name="db_pass" value="<?=old('db_pass')?>" autocomplete="current-password"></div><span class="field-hint">En instalaciones locales con root puede estar vacía.</span></div>
          </div>
          <div class="test-bar"><div><div class="field-title">Verificar antes de continuar</div><span class="field-hint">Prueba la conexión sin crear todavía el sistema.</span></div><button class="btn secondary" type="button" id="testDbBtn"><?=svgIcon('test')?> <span class="btn-label">Probar conexión</span></button></div>
        </section>

        <section class="wizard-step" data-step="3">
          <div class="step-head"><div class="step-icon"><?=svgIcon('building')?></div><div><h2>Empresa y sistema</h2><p>Estos datos se usarán en el portal público, el panel administrativo y los correos del sistema.</p></div></div>
          <div class="form-grid">
            <div class="field"><label for="company_name">Empresa</label><div class="input-wrap"><input id="company_name" name="company_name" value="<?=old('company_name')?>" required placeholder="Nombre de la empresa"></div></div>
            <div class="field"><label for="app_name">Nombre de la dinámica</label><div class="input-wrap"><input id="app_name" name="app_name" value="<?=old('app_name','Evaluación Corporativa')?>" required></div></div>
            <div class="field full"><label for="app_url">URL pública</label><div class="input-wrap"><input id="app_url" type="url" name="app_url" value="<?=old('app_url',$autoUrl)?>" required></div><span class="field-hint">Debe apuntar a la raíz del proyecto, sin /install al final.</span></div>
          </div>
        </section>

        <section class="wizard-step" data-step="4">
          <div class="step-head"><div class="step-icon"><?=svgIcon('user')?></div><div><h2>Cuenta administradora</h2><p>Esta será la primera cuenta autorizada para administrar empleados, preguntas, reportes, correo y usuarios.</p></div></div>
          <div class="form-grid">
            <div class="field"><label for="admin_name">Nombre completo</label><div class="input-wrap"><input id="admin_name" name="admin_name" value="<?=old('admin_name')?>" required autocomplete="name"></div></div>
            <div class="field"><label for="admin_email">Correo</label><div class="input-wrap"><input id="admin_email" type="email" name="admin_email" value="<?=old('admin_email')?>" required autocomplete="email"></div></div>
            <div class="field"><label for="admin_password">Contraseña</label><div class="input-wrap"><input id="admin_password" type="password" minlength="8" name="admin_password" value="<?=old('admin_password')?>" required autocomplete="new-password"></div><span class="field-hint">Mínimo 8 caracteres. Puedes mostrarla con el icono del ojo.</span></div>
            <div class="field"><label for="admin_password_confirm">Confirmar contraseña</label><div class="input-wrap"><input id="admin_password_confirm" type="password" minlength="8" name="admin_password_confirm" value="<?=old('admin_password_confirm')?>" required autocomplete="new-password"></div></div>
          </div>
        </section>

        <section class="wizard-step" data-step="5">
          <div class="step-head"><div class="step-icon"><?=svgIcon('mail')?></div><div><h2>Configuración de correo</h2><p>Selecciona cómo enviará correos el sistema. Todo queda local y los secretos se almacenan cifrados.</p></div></div>
          <div class="radio-grid mail-switch">
            <label class="radio-card"><input type="radio" name="mail_setup" value="now" <?=checked('mail_setup','now','now')?>><span class="radio-ui"><span class="radio-icon"><?=svgIcon('mail')?></span><span class="radio-copy"><strong>Configurar ahora</strong><small>Deja SMTP o Microsoft Graph listo durante la instalación.</small></span><span class="radio-dot"></span></span></label>
            <label class="radio-card"><input type="radio" name="mail_setup" value="later" <?=checked('mail_setup','later')?>><span class="radio-ui"><span class="radio-icon"><?=svgIcon('later')?></span><span class="radio-copy"><strong>Configurar después</strong><small>Instala el sistema y completa el correo desde Administración.</small></span><span class="radio-dot"></span></span></label>
          </div>

          <div id="mailNow">
            <div class="field-title">Método de envío</div>
            <div class="radio-grid">
              <label class="radio-card"><input type="radio" name="mail_method" value="SMTP" <?=checked('mail_method','SMTP','SMTP')?>><span class="radio-ui"><span class="radio-icon"><?=svgIcon('smtp')?></span><span class="radio-copy"><strong>SMTP</strong><small>Servidor, puerto, TLS/SSL, usuario y App Password.</small></span><span class="radio-dot"></span></span></label>
              <label class="radio-card"><input type="radio" name="mail_method" value="GRAPH" <?=checked('mail_method','GRAPH')?>><span class="radio-ui"><span class="radio-icon"><?=svgIcon('graph')?></span><span class="radio-copy"><strong>Microsoft Graph</strong><small>Tenant ID, Client ID, Client Secret y mailbox.</small></span><span class="radio-dot"></span></span></label>
            </div>

            <div class="mail-group" data-mail="SMTP" style="margin-top:18px">
              <div class="form-grid">
                <div class="field"><label for="smtp_host">Servidor SMTP</label><div class="input-wrap"><input id="smtp_host" name="smtp_host" value="<?=old('smtp_host')?>" placeholder="smtp.office365.com"></div></div>
                <div class="field"><label for="smtp_port">Puerto</label><div class="input-wrap"><input id="smtp_port" type="number" min="1" max="65535" name="smtp_port" value="<?=old('smtp_port','587')?>"></div></div>
                <div class="field"><span class="field-title">Seguridad</span><div class="segmented"><label class="segment"><input type="radio" name="smtp_secure" value="tls" <?=checked('smtp_secure','tls','tls')?>><span>TLS</span></label><label class="segment"><input type="radio" name="smtp_secure" value="ssl" <?=checked('smtp_secure','ssl')?>><span>SSL</span></label></div></div>
                <div class="field"><label for="smtp_user">Correo / usuario</label><div class="input-wrap"><input id="smtp_user" type="email" name="smtp_user" value="<?=old('smtp_user')?>" autocomplete="username"></div></div>
                <div class="field full"><label for="smtp_password">Contraseña / App Password</label><div class="input-wrap"><input id="smtp_password" type="password" name="smtp_password" value="<?=old('smtp_password')?>" autocomplete="new-password"></div></div>
              </div>
            </div>

            <div class="mail-group" data-mail="GRAPH" style="margin-top:18px">
              <div class="form-grid">
                <div class="field"><label for="graph_tenant_id">Tenant ID</label><div class="input-wrap"><input id="graph_tenant_id" name="graph_tenant_id" value="<?=old('graph_tenant_id')?>"></div></div>
                <div class="field"><label for="graph_client_id">Client ID</label><div class="input-wrap"><input id="graph_client_id" name="graph_client_id" value="<?=old('graph_client_id')?>"></div></div>
                <div class="field full"><label for="graph_client_secret">Client Secret VALUE</label><div class="input-wrap"><input id="graph_client_secret" type="password" name="graph_client_secret" value="<?=old('graph_client_secret')?>" autocomplete="new-password"></div></div>
                <div class="field full"><label for="graph_user">Graph User / Mailbox</label><div class="input-wrap"><input id="graph_user" type="email" name="graph_user" value="<?=old('graph_user')?>" placeholder="correo@empresa.com"></div></div>
              </div>
            </div>

            <div class="form-grid" style="margin-top:15px"><div class="field full"><label for="reply_to">Reply-To <span style="font-weight:500;color:#8797a4">(opcional)</span></label><div class="input-wrap"><input id="reply_to" type="email" name="reply_to" value="<?=old('reply_to')?>"></div></div></div>
          </div>

          <div class="test-bar" id="testMailBar"><div class="field"><label for="test_to">Enviar prueba a</label><div class="input-wrap"><input id="test_to" type="email" name="test_to" value="<?=old('test_to')?>" placeholder="Usa tu correo para validar"></div></div><button class="btn secondary" type="button" id="testMailBtn"><?=svgIcon('test')?> <span class="btn-label">Enviar prueba</span></button></div>
        </section>

        <section class="wizard-step" data-step="6">
          <div class="step-head"><div class="step-icon"><?=svgIcon('review')?></div><div><h2>Revisa antes de instalar</h2><p>Confirma que los datos principales sean correctos. Las contraseñas y secretos nunca se muestran en este resumen.</p></div></div>
          <div class="summary-grid">
            <div class="summary-card full"><span>Base de datos</span><strong id="reviewDb">—</strong><p>La base se creará automáticamente si no existe.</p></div>
            <div class="summary-card"><span>Empresa</span><strong id="reviewCompany">—</strong></div>
            <div class="summary-card"><span>Dinámica</span><strong id="reviewApp">—</strong></div>
            <div class="summary-card full"><span>URL pública</span><strong id="reviewUrl">—</strong></div>
            <div class="summary-card"><span>Administrador</span><strong id="reviewAdmin">—</strong></div>
            <div class="summary-card"><span>Correo</span><strong id="reviewMail">—</strong></div>
          </div>
          <div class="inline-note"><strong>Al instalar:</strong> se generará una APP_KEY aleatoria, se cifrarán los secretos, se crearán las tablas y se bloqueará este asistente.</div>
        </section>

        <footer class="wizard-footer">
          <div class="step-counter">Todos los datos se mantienen dentro de tu instalación.</div>
          <div class="wizard-actions"><button class="btn ghost" type="button" id="prevStep"><?=svgIcon('back')?> <span>Anterior</span></button><button class="btn primary" type="button" id="nextStep"><span>Siguiente</span> <?=svgIcon('arrow')?></button><button class="btn primary" type="submit" id="installBtn" hidden><?=svgIcon('install')?> <span class="btn-label">Instalar sistema</span></button></div>
        </footer>
      </form>
    </section>
  </div>
<?php endif; ?>
</main>
<script src="../assets/vendor/jquery/jquery.min.js?v=3"></script>
<script src="../assets/vendor/sweetalert2/sweetalert2.local.js?v=3"></script>
<script src="../assets/js/notify.js?v=3"></script>
<script src="../assets/js/ui.js?v=3"></script>
<script src="../assets/js/install.js?v=3"></script>
</body>
</html>
