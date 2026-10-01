<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') json_out(['success'=>false,'message'=>'Método no permitido.'],405);
try{
    verify_csrf();
    $action=(string)($_POST['action']??'');
    if($action==='clear'){
        unset($_SESSION['participant_employee_id'],$_SESSION['participant_employee_badge'],$_SESSION['game_employee_id'],$_SESSION['game_employee_badge']);
        json_out(['success'=>true]);
    }
    if($action==='current'){
        $employeeId=(int)($_SESSION['participant_employee_id']??$_SESSION['game_employee_id']??0);
        if($employeeId<=0) json_out(['success'=>true,'active'=>false,'employee'=>null,'evaluation_status'=>null]);
        $q=db()->prepare("SELECT id,badge,name,department,question_group FROM employees WHERE id=? AND status='active' LIMIT 1");
        $q->execute([$employeeId]);
        $e=$q->fetch();
        if(!$e){
            unset($_SESSION['participant_employee_id'],$_SESSION['participant_employee_badge'],$_SESSION['game_employee_id'],$_SESSION['game_employee_badge']);
            json_out(['success'=>true,'active'=>false,'employee'=>null,'evaluation_status'=>null]);
        }
        $_SESSION['participant_employee_id']=(int)$e['id'];
        $_SESSION['participant_employee_badge']=(string)$e['badge'];
        $_SESSION['game_employee_id']=(int)$e['id'];
        $_SESSION['game_employee_badge']=(string)$e['badge'];
        $groupName=trim((string)($e['question_group']??''));
        $groupDescription='';
        if($groupName!==''){
            try{
                $gq=db()->prepare('SELECT description FROM content_groups WHERE name=? LIMIT 1');
                $gq->execute([$groupName]);
                $groupDescription=trim((string)($gq->fetchColumn()?:''));
            }catch(Throwable $groupError){
                // La categoría descriptiva es opcional y no debe romper la sesión del participante.
                $groupDescription='';
            }
        }
        $e['question_group']=$groupName;
        $e['group_description']=$groupDescription;
        $eq=db()->prepare('SELECT status,score FROM evaluations WHERE employee_id=? LIMIT 1');
        $eq->execute([(int)$e['id']]);
        $existing=$eq->fetch()?:null;
        json_out([
            'success'=>true,
            'active'=>true,
            'employee'=>$e,
            'evaluation_status'=>$existing?(string)$existing['status']:null,
            'score'=>$existing&&$existing['status']==='completed'?(float)$existing['score']:null
        ]);
    }
    if($action==='select'){
        $badge=trim((string)($_POST['badge']??''));
        if($badge==='') throw new RuntimeException('Ingresa el gafete.');
        if(!preg_match('/^[0-9]+$/',$badge)) throw new RuntimeException('El gafete solo puede contener números.');
        $q=db()->prepare("SELECT id,badge,name,department FROM employees WHERE badge=? AND status='active' LIMIT 1");
        $q->execute([$badge]);$e=$q->fetch();
        if(!$e) json_out(['success'=>false,'message'=>'No encontramos un empleado activo con ese gafete.'],404);
        $_SESSION['participant_employee_id']=(int)$e['id'];
        $_SESSION['participant_employee_badge']=(string)$e['badge'];
        $_SESSION['game_employee_id']=(int)$e['id']; // compatibilidad con instalaciones anteriores
        $_SESSION['game_employee_badge']=(string)$e['badge'];
        json_out(['success'=>true,'employee'=>['id'=>(int)$e['id'],'badge'=>$e['badge'],'name'=>$e['name'],'department'=>$e['department']??'']]);
    }
    throw new RuntimeException('Acción no válida.');
}catch(Throwable $e){json_out(['success'=>false,'message'=>$e->getMessage()],422);}
