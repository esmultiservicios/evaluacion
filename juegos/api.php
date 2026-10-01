<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['ok'=>false,'message'=>'Método no permitido'],405);
try{
    verify_csrf();
    $action=(string)($_POST['action']??'');
    if($action==='lookup' || $action==='identify'){
        $badge=trim((string)($_POST['badge']??''));
        if($badge==='')throw new RuntimeException('Ingresa tu número de gafete.'); if(!preg_match('/^[0-9]+$/',$badge))throw new RuntimeException('El gafete solo puede contener números.');
        $st=db()->prepare("SELECT id,badge,name,department FROM employees WHERE badge=? AND status='active' LIMIT 1");
        $st->execute([$badge]);$e=$st->fetch();
        if(!$e)json_out(['ok'=>false,'message'=>'No encontramos un empleado activo con ese gafete.'],404);
        if($action==='identify'){
            $_SESSION['participant_employee_id']=(int)$e['id'];
            $_SESSION['participant_employee_badge']=(string)$e['badge'];
            $_SESSION['game_employee_id']=(int)$e['id'];
            $_SESSION['game_employee_badge']=(string)$e['badge'];
        }
        json_out(['ok'=>true,'employee'=>['id'=>(int)$e['id'],'badge'=>$e['badge'],'name'=>$e['name'],'department'=>$e['department']??'']]);
    }
    if($action==='complete'){
        $gameId=(int)($_POST['game_id']??0);$empId=(int)($_POST['employee_id']??0);$correct=max(0,(float)($_POST['correct']??0));$total=max(1,(int)($_POST['total']??1));
        if($gameId<=0||$empId<=0)throw new RuntimeException('No se pudo identificar correctamente la partida.');
        if((int)($_SESSION['participant_employee_id']??$_SESSION['game_employee_id']??0)!==$empId)throw new RuntimeException('La sesión del participante cambió. Vuelve al portal e ingresa tu gafete.');
        $pdo=db();$pdo->beginTransaction();
        $st=$pdo->prepare("SELECT id,badge,name FROM employees WHERE id=? AND status='active' LIMIT 1 FOR UPDATE");$st->execute([$empId]);$e=$st->fetch();if(!$e)throw new RuntimeException('Participante no válido o inactivo.');
        $st=$pdo->prepare('SELECT id,time_limit_seconds FROM games WHERE id=? AND active=1 LIMIT 1');$st->execute([$gameId]);$gameRow=$st->fetch();if(!$gameRow)throw new RuntimeException('El juego ya no está disponible.');
        $st=$pdo->prepare('SELECT position FROM game_assignments WHERE employee_id=? AND game_id=? LIMIT 1');$st->execute([$empId,$gameId]);$assignedPosition=$st->fetchColumn();if($assignedPosition===false)throw new RuntimeException('Este juego no está asignado al participante actual. Vuelve a Mis juegos.');
        $st=$pdo->prepare('SELECT id FROM game_attempts WHERE game_id=? AND employee_id=? AND completed_at IS NOT NULL LIMIT 1 FOR UPDATE');$st->execute([$gameId,$empId]);
        if($st->fetchColumn()){
            $pdo->rollBack();
            json_out(['ok'=>false,'already_completed'=>true,'message'=>'Este juego ya fue completado. Puedes abrirlo desde Mis juegos para revisar tu resultado, pero no volver a enviarlo.'],409);
        }
        $accuracy=round(min(100,$correct/$total*100),2);$responseSeconds=max(0,(float)($_POST['response_seconds']??0));$timeLimit=max(0,(int)($gameRow['time_limit_seconds']??0));$speedMetric=$timeLimit>0?round(max(0,min(100,(1-($responseSeconds/$timeLimit))*100)),2):0;$speedEnabled=setting('speed_scoring_enabled','0')==='1';$bonusWeight=max(0,min(40,(float)setting('speed_bonus_percent','20')))/100;$speedRatio=$speedMetric/100;$score=$speedEnabled&&$timeLimit>0?round(min(100,(($correct*(1+$bonusWeight*$speedRatio))/($total*(1+$bonusWeight)))*100),2):$accuracy;
        $raw=(string)($_POST['answers_json']??'');$answersJson=null;
        if($raw!==''){
            if(strlen($raw)>120000)throw new RuntimeException('El detalle de la partida es demasiado grande.');
            $decoded=json_decode($raw,true);
            if(!is_array($decoded))throw new RuntimeException('El detalle de la partida no es válido.');
            $answersJson=json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        }
        $st=$pdo->prepare('INSERT INTO game_attempts(game_id,employee_id,badge_snapshot,employee_name_snapshot,accuracy_score,speed_score,score,correct_answers,total_items,response_seconds,answers_json,completed_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())');
        $correctStored=(int)floor($correct+0.00001);$st->execute([$gameId,$empId,$e['badge'],$e['name'],$accuracy,$speedMetric,$score,$correctStored,$total,$responseSeconds,$answersJson]);
        $id=(int)$pdo->lastInsertId();$pdo->commit();
        json_out(['ok'=>true,'score'=>$score,'accuracy_score'=>$accuracy,'speed_score'=>$speedMetric,'speed_enabled'=>$speedEnabled,'response_seconds'=>round($responseSeconds,1),'attempt_id'=>$id]);
    }
    throw new RuntimeException('Acción no válida.');
}catch(Throwable $e){try{if(db()->inTransaction())db()->rollBack();}catch(Throwable){}json_out(['ok'=>false,'message'=>$e->getMessage()],422);}
