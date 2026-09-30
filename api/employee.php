<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
try{
    $badge=trim((string)($_GET['badge']??''));
    if($badge==='') json_out(['success'=>false,'message'=>'Ingresa el gafete.'],422); if(!preg_match('/^[0-9]+$/',$badge)) json_out(['success'=>false,'message'=>'El gafete solo puede contener números.'],422);
    $q=db()->prepare("SELECT id,badge,name,department,question_group,game_group FROM employees WHERE badge=? AND status='active' LIMIT 1");
    $q->execute([$badge]);
    $e=$q->fetch();
    if(!$e) json_out(['success'=>false,'message'=>'No encontramos un empleado activo con ese gafete.'],404);
    $group=resolve_content_group($e,'questions');
    $e['question_group']=$group['name'];
    $e['group_description']=$group['description'];
    $e['group_source']=$group['source'];
    $q=db()->prepare('SELECT status,score,completed_at FROM evaluations WHERE employee_id=? LIMIT 1');
    $q->execute([$e['id']]);
    $existing=$q->fetch();
    json_out(['success'=>true,'employee'=>$e,'resume'=>(bool)$existing && $existing['status']!=='completed','completed'=>(bool)$existing && $existing['status']==='completed','score'=>$existing&&$existing['status']==='completed'?(float)$existing['score']:null]);
}catch(Throwable $e){
    json_out(['success'=>false,'message'=>'No se pudo validar el gafete.'],500);
}
