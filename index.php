<?php
require_once __DIR__.'/app/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
$params=$_GET;
$badge=trim((string)($params['participant']??($_SESSION['participant_employee_badge']??$_SESSION['game_employee_badge']??'')));
if($badge!=='')$params['participant']=$badge;
$query=$params?'?'.http_build_query($params):'';
header('Location: evaluation/'.$query, true, 302);
exit;
