<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly'=>true,'samesite'=>'Lax','secure'=>(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')]);
    session_start();
}
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH.'/app/helpers.php';
require_once ROOT_PATH.'/app/Database.php';
require_once ROOT_PATH.'/app/Auth.php';
require_once ROOT_PATH.'/app/EmailTemplates.php';
require_once ROOT_PATH.'/app/EmailService.php';
require_once ROOT_PATH.'/app/ReportService.php';
require_once ROOT_PATH.'/app/XlsxService.php';
require_once ROOT_PATH.'/app/EmployeeExportService.php';
require_once ROOT_PATH.'/app/QuestionExportService.php';
require_once ROOT_PATH.'/app/GameExportService.php';
require_once ROOT_PATH.'/app/GroupExportService.php';
require_once ROOT_PATH.'/app/GameAttemptReportService.php';
load_env(ROOT_PATH.'/.env');
if (is_file(ROOT_PATH.'/.env')) ensure_runtime_schema();
if (!is_file(ROOT_PATH.'/.env') && !str_contains($_SERVER['REQUEST_URI'] ?? '', '/install')) {
    header('Location: install/'); exit;
}
