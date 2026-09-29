<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') json_out(['success'=>false],405);
try{
    verify_csrf();
    $badge=trim((string)($_POST['badge']??''));
    $pdo=db();
    $pdo->beginTransaction();
    $q=$pdo->prepare("SELECT * FROM employees WHERE badge=? AND status='active' LIMIT 1 FOR UPDATE");
    $q->execute([$badge]);
    $emp=$q->fetch();
    if(!$emp) throw new RuntimeException('Empleado no encontrado.');

    $q=$pdo->prepare('SELECT * FROM evaluations WHERE employee_id=? LIMIT 1 FOR UPDATE');
    $q->execute([$emp['id']]);
    $existing=$q->fetch();
    if($existing){
        if($existing['status']==='completed') throw new RuntimeException('Este gafete ya completó la evaluación.');
        $questions=[];
        $eq=$pdo->prepare('SELECT id,question_text_snapshot,sort_order FROM evaluation_questions WHERE evaluation_id=? ORDER BY sort_order');
        $eq->execute([$existing['id']]);
        foreach($eq->fetchAll() as $row){
            $op=$pdo->prepare('SELECT id,option_text_snapshot FROM evaluation_question_options WHERE evaluation_question_id=? ORDER BY sort_order,id');
            $op->execute([$row['id']]);
            $options=[];
            foreach($op->fetchAll() as $o) $options[]=['id'=>(int)$o['id'],'text'=>$o['option_text_snapshot']];
            $questions[]=['id'=>(int)$row['id'],'number'=>(int)$row['sort_order'],'text'=>$row['question_text_snapshot'],'options'=>$options];
        }
        $pdo->commit();
        json_out(['success'=>true,'resumed'=>true,'token'=>$existing['token'],'employee'=>['name'=>$emp['name'],'badge'=>$emp['badge'],'department'=>$emp['department']??''],'questions'=>$questions]);
    }

    $count=max(1,min(500,(int)setting('questions_per_attempt','5')));
    $orderMode=setting('question_order_mode','random');
    $orderSql=$orderMode==='sequential'?'q.id ASC':'RAND()';
    $qs=$pdo->query("SELECT q.id,q.question_text,q.points FROM questions q WHERE q.active=1 AND q.id IN (SELECT question_id FROM question_options GROUP BY question_id HAVING SUM(is_correct=1)=1 AND COUNT(*)>=2) ORDER BY ".$orderSql." LIMIT ".$count)->fetchAll();
    if(count($qs)<$count) throw new RuntimeException('No hay suficientes preguntas activas y válidas. Se necesitan al menos '.$count.'.');
    $token=bin2hex(random_bytes(32));
    $st=$pdo->prepare('INSERT INTO evaluations(employee_id,badge_snapshot,employee_name_snapshot,token,total_questions,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$emp['id'],$emp['badge'],$emp['name'],$token,$count,client_ip(),mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    $eid=(int)$pdo->lastInsertId();
    $out=[];
    foreach($qs as $i=>$qrow){
        $st=$pdo->prepare('INSERT INTO evaluation_questions(evaluation_id,question_id,question_text_snapshot,points_snapshot,sort_order) VALUES(?,?,?,?,?)');
        $st->execute([$eid,$qrow['id'],$qrow['question_text'],$qrow['points'],$i+1]);
        $eqid=(int)$pdo->lastInsertId();
        $op=$pdo->prepare('SELECT id,option_text,is_correct,sort_order FROM question_options WHERE question_id=? ORDER BY sort_order,id');
        $op->execute([$qrow['id']]);
        $ins=$pdo->prepare('INSERT INTO evaluation_question_options(evaluation_question_id,source_option_id,option_text_snapshot,is_correct_snapshot,sort_order) VALUES(?,?,?,?,?)');
        $view=[];
        foreach($op->fetchAll() as $o){
            $ins->execute([$eqid,$o['id'],$o['option_text'],$o['is_correct'],$o['sort_order']]);
            $view[]=['id'=>(int)$pdo->lastInsertId(),'text'=>$o['option_text']];
        }
        $out[]=['id'=>$eqid,'number'=>$i+1,'text'=>$qrow['question_text'],'options'=>$view];
    }
    $pdo->commit();
    json_out(['success'=>true,'resumed'=>false,'token'=>$token,'employee'=>['name'=>$emp['name'],'badge'=>$emp['badge'],'department'=>$emp['department']??''],'questions'=>$out]);
}catch(Throwable $e){
    if(db()->inTransaction()) db()->rollBack();
    json_out(['success'=>false,'message'=>$e->getMessage()],422);
}
