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
    }catch(Throwable){}
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
      'refresh'=>'<path d="M20 7v5h-5M4 17v-5h5"/><path d="M6.1 8a7 7 0 0 1 11.8-2L20 8M4 16l2.1 2a7 7 0 0 0 11.8-2"/>'
    ];
    $body=$paths[$name]??$paths['question'];
    return '<svg class="'.e($class).'" viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'.$body.'</svg>';
}
