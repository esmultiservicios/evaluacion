<?php
declare(strict_types=1);
final class Database{
    private static ?PDO $pdo=null;
    public static function instance(): PDO{
        if(self::$pdo)return self::$pdo;
        $dsn='mysql:host='.envv('DB_HOST','127.0.0.1').';port='.envv('DB_PORT','3306').';dbname='.envv('DB_NAME').';charset=utf8mb4';
        self::$pdo=new PDO($dsn,envv('DB_USER'),envv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
        return self::$pdo;
    }
}
