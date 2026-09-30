<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST') json_out(['success'=>false],405);
try{
    verify_csrf();
    $badge=trim((string)($_POST['badge']??''));
    if($badge===''||!preg_match('/^[0-9]+$/',$badge)) throw new RuntimeException('Ingresa un gafete válido usando solo números.');
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
        $questions=[];
        $eq=$pdo->prepare('SELECT * FROM evaluation_questions WHERE evaluation_id=? ORDER BY sort_order');
        $eq->execute([$existing['id']]);
        foreach($eq->fetchAll() as $row){
            $op=$pdo->prepare('SELECT id,option_text_snapshot,is_correct_snapshot FROM evaluation_question_options WHERE evaluation_question_id=? ORDER BY sort_order,id');
            $op->execute([$row['id']]);
            $options=[];$correctTexts=[];
            foreach($op->fetchAll() as $o){
                $options[]=['id'=>(int)$o['id'],'text'=>$o['option_text_snapshot']];
                if((int)$o['is_correct_snapshot']===1)$correctTexts[]=(string)$o['option_text_snapshot'];
            }
            $selectedIds=json_decode((string)($row['selected_option_ids_json']??''),true);
            if(!is_array($selectedIds))$selectedIds=!empty($row['selected_option_id'])?[(int)$row['selected_option_id']]:[];
            $selectedTexts=json_decode((string)($row['selected_option_texts_json']??''),true);
            if(!is_array($selectedTexts))$selectedTexts=!empty($row['selected_option_text'])?[(string)$row['selected_option_text']]:[];
            $questions[]=[
                'id'=>(int)$row['id'],'number'=>(int)$row['sort_order'],'text'=>$row['question_text_snapshot'],'options'=>$options,
                'type'=>(string)($row['question_type_snapshot']??'single'),'required'=>max(1,(int)($row['required_selections_snapshot']??1)),
                'time_limit'=>max(0,(int)($row['time_limit_seconds_snapshot']??0)),
                'selected_ids'=>array_map('intval',$selectedIds),'selected_texts'=>array_values($selectedTexts),
                'correct_texts'=>$correctTexts,'correct'=>$row['is_correct']===null?null:(bool)$row['is_correct'],
                'response_seconds'=>(float)($row['response_seconds']??0)
            ];
        }
        $pdo->commit();
        if($existing['status']==='completed'){
            json_out(['success'=>true,'review'=>true,'resumed'=>false,'token'=>$existing['token'],
                'employee'=>['name'=>$emp['name'],'badge'=>$emp['badge'],'department'=>$emp['department']??''],
                'questions'=>$questions,'score'=>(float)$existing['score'],'accuracy_score'=>(float)($existing['accuracy_score']??$existing['score']),
                'speed_score'=>(float)($existing['speed_score']??0),'response_seconds'=>(float)($existing['response_seconds']??0),
                'correct'=>(int)$existing['correct_answers'],'total'=>(int)$existing['total_questions'],
                'completed_at'=>$existing['completed_at'],'speed_enabled'=>setting('speed_scoring_enabled','0')==='1']);
        }
        foreach($questions as &$item){unset($item['selected_ids'],$item['selected_texts'],$item['correct_texts'],$item['correct'],$item['response_seconds']);}unset($item);
        json_out(['success'=>true,'resumed'=>true,'token'=>$existing['token'],'employee'=>['name'=>$emp['name'],'badge'=>$emp['badge'],'department'=>$emp['department']??''],'questions'=>$questions,'speed_enabled'=>setting('speed_scoring_enabled','0')==='1']);
    }

    $count=max(1,min(500,(int)setting('questions_per_attempt','5')));
    $orderMode=setting('question_order_mode','random');
    $orderSql=$orderMode==='sequential'?'q.id ASC':'RAND()';
    $validSql="SELECT COUNT(*) FROM questions q WHERE q.active=1 AND q.id IN (
      SELECT qo.question_id FROM question_options qo JOIN questions q2 ON q2.id=qo.question_id
      GROUP BY qo.question_id,q2.question_type,q2.required_selections
      HAVING COUNT(*)>=2 AND ((q2.question_type='single' AND SUM(qo.is_correct=1)=1) OR (q2.question_type='multiple' AND SUM(qo.is_correct=1)=GREATEST(1,q2.required_selections)))
    )";
    $validCount=(int)$pdo->query($validSql)->fetchColumn();
    if($validCount<$count) throw new RuntimeException('El banco de preguntas no está listo. Hay '.$validCount.' pregunta'.($validCount===1?'':'s').' publicada'.($validCount===1?'':'s').' y válida'.($validCount===1?'':'s').', pero la evaluación está configurada para '.$count.'. Publica al menos '.$count.' preguntas válidas desde Administración > Preguntas.');
    $qs=$pdo->query("SELECT q.id,q.question_text,q.points,q.question_type,q.required_selections,q.time_limit_seconds FROM questions q WHERE q.active=1 AND q.id IN (
      SELECT qo.question_id FROM question_options qo JOIN questions q2 ON q2.id=qo.question_id
      GROUP BY qo.question_id,q2.question_type,q2.required_selections
      HAVING COUNT(*)>=2 AND ((q2.question_type='single' AND SUM(qo.is_correct=1)=1) OR (q2.question_type='multiple' AND SUM(qo.is_correct=1)=GREATEST(1,q2.required_selections)))
    ) ORDER BY ".$orderSql." LIMIT ".$count)->fetchAll();
    $token=bin2hex(random_bytes(32));
    $st=$pdo->prepare('INSERT INTO evaluations(employee_id,badge_snapshot,employee_name_snapshot,token,total_questions,ip_address,user_agent) VALUES(?,?,?,?,?,?,?)');
    $st->execute([$emp['id'],$emp['badge'],$emp['name'],$token,$count,client_ip(),mb_substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500)]);
    $eid=(int)$pdo->lastInsertId();
    $out=[];
    foreach($qs as $i=>$qrow){
        $type=$qrow['question_type']==='multiple'?'multiple':'single';$required=$type==='multiple'?max(1,(int)$qrow['required_selections']):1;$time=max(0,(int)$qrow['time_limit_seconds']);
        $st=$pdo->prepare('INSERT INTO evaluation_questions(evaluation_id,question_id,question_text_snapshot,points_snapshot,question_type_snapshot,required_selections_snapshot,time_limit_seconds_snapshot,sort_order) VALUES(?,?,?,?,?,?,?,?)');
        $st->execute([$eid,$qrow['id'],$qrow['question_text'],$qrow['points'],$type,$required,$time,$i+1]);
        $eqid=(int)$pdo->lastInsertId();
        $op=$pdo->prepare('SELECT id,option_text,is_correct,sort_order FROM question_options WHERE question_id=? ORDER BY sort_order,id');
        $op->execute([$qrow['id']]);
        $ins=$pdo->prepare('INSERT INTO evaluation_question_options(evaluation_question_id,source_option_id,option_text_snapshot,is_correct_snapshot,sort_order) VALUES(?,?,?,?,?)');
        $view=[];
        foreach($op->fetchAll() as $o){
            $ins->execute([$eqid,$o['id'],$o['option_text'],$o['is_correct'],$o['sort_order']]);
            $view[]=['id'=>(int)$pdo->lastInsertId(),'text'=>$o['option_text']];
        }
        $out[]=['id'=>$eqid,'number'=>$i+1,'text'=>$qrow['question_text'],'type'=>$type,'required'=>$required,'time_limit'=>$time,'options'=>$view];
    }
    $pdo->commit();
    json_out(['success'=>true,'resumed'=>false,'token'=>$token,'employee'=>['name'=>$emp['name'],'badge'=>$emp['badge'],'department'=>$emp['department']??''],'questions'=>$out,'speed_enabled'=>setting('speed_scoring_enabled','0')==='1']);
}catch(Throwable $e){
    if(db()->inTransaction()) db()->rollBack();
    json_out(['success'=>false,'message'=>$e->getMessage()],422);
}
