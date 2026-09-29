<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
try{
    $badge=trim((string)($_GET['badge']??''));
    if($badge==='') json_out(['success'=>false,'message'=>'Ingresa el gafete.'],422);
    $q=db()->prepare("SELECT id,badge,name,department FROM employees WHERE badge=? AND status='active' LIMIT 1");
    $q->execute([$badge]);
    $e=$q->fetch();
    if(!$e) json_out(['success'=>false,'message'=>'No encontramos un empleado activo con ese gafete.'],404);
    $q=db()->prepare('SELECT status,score,completed_at FROM evaluations WHERE employee_id=? LIMIT 1');
    $q->execute([$e['id']]);
    $existing=$q->fetch();
    if($existing && $existing['status']==='completed') {
        json_out(['success'=>false,'already'=>true,'message'=>'Esta evaluación ya fue contestada.'],409);
    }
    json_out(['success'=>true,'employee'=>$e,'resume'=>(bool)$existing]);
}catch(Throwable $e){
    json_out(['success'=>false,'message'=>'No se pudo validar el gafete.'],500);
}
