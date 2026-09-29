<?php
declare(strict_types=1);
final class Auth{
    public static function attempt(string $email,string $password): bool{
        $q=db()->prepare("SELECT * FROM users WHERE email=? AND status='active' LIMIT 1");$q->execute([strtolower(trim($email))]);$u=$q->fetch();
        if(!$u||!password_verify($password,(string)$u['password_hash']))return false;
        session_regenerate_id(true);$_SESSION['user_id']=(int)$u['id'];$_SESSION['user_name']=$u['name'];$_SESSION['user_role']=$u['role'];$_SESSION['user_email']=$u['email'];
        db()->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?')->execute([$u['id']]);audit('login','user',(int)$u['id']);return true;
    }
    public static function check(): bool{return !empty($_SESSION['user_id']);}
    public static function require(): void{if(!self::check()){header('Location: ?page=login');exit;}}
    public static function adminOnly(): void{self::require();if(($_SESSION['user_role']??'')!=='admin'){http_response_code(403);exit('Acceso restringido.');}}
    public static function logout(): void{audit('logout');$_SESSION=[];if(ini_get('session.use_cookies')){$p=session_get_cookie_params();setcookie(session_name(),'',time()-42000,$p['path'],$p['domain'],$p['secure'],$p['httponly']);}session_destroy();}
}
