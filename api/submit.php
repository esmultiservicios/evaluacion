<?php
require_once dirname(__DIR__).'/app/bootstrap.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['success'=>false],405);
try{
 verify_csrf();
 $token=trim((string)($_POST['token']??''));$answers=$_POST['answers']??[];$times=$_POST['times']??[];
 if(!is_array($answers)||!is_array($times))throw new RuntimeException('Respuestas inválidas.');
 $pdo=db();$pdo->beginTransaction();$q=$pdo->prepare("SELECT * FROM evaluations WHERE token=? LIMIT 1 FOR UPDATE");$q->execute([$token]);$ev=$q->fetch();if(!$ev)throw new RuntimeException('Evaluación no encontrada.');if($ev['status']==='completed')throw new RuntimeException('Esta evaluación ya fue enviada.');
 $q=$pdo->prepare('SELECT * FROM evaluation_questions WHERE evaluation_id=? ORDER BY sort_order FOR UPDATE');$q->execute([$ev['id']]);$questions=$q->fetchAll();
 $correct=0;$totalPoints=0.0;$earned=0.0;$totalSeconds=0.0;$timedSpeedSum=0.0;$timedWeight=0.0;$summary=[];$speedEnabled=setting('speed_scoring_enabled','0')==='1';$bonusWeight=max(0,min(40,(float)setting('speed_bonus_percent','20')))/100;$competitiveEarned=0.0;$competitiveMax=0.0;
 foreach($questions as $eq){
   $qid=(string)$eq['id'];$raw=$answers[$qid]??$answers[(int)$eq['id']]??[];$selectedIds=is_array($raw)?array_values(array_unique(array_map('intval',$raw))):[(int)$raw];$selectedIds=array_values(array_filter($selectedIds,fn($v)=>$v>0));
   $type=($eq['question_type_snapshot']??'single')==='multiple'?'multiple':'single';$required=$type==='multiple'?max(1,(int)($eq['required_selections_snapshot']??1)):1;$timeLimit=max(0,(int)($eq['time_limit_seconds_snapshot']??0));$responseSeconds=max(0,(float)($times[$qid]??$times[(int)$eq['id']]??0));$totalSeconds+=$responseSeconds;
   if(count($selectedIds)>$required)throw new RuntimeException('Una pregunta contiene más respuestas de las permitidas.');
   $all=$pdo->prepare('SELECT id,option_text_snapshot,is_correct_snapshot FROM evaluation_question_options WHERE evaluation_question_id=? ORDER BY sort_order,id');$all->execute([$eq['id']]);$allOptions=$all->fetchAll();$validIds=array_map(fn($o)=>(int)$o['id'],$allOptions);foreach($selectedIds as $sid)if(!in_array($sid,$validIds,true))throw new RuntimeException('Una respuesta no pertenece a esta evaluación.');
   $correctIds=[];$correctTexts=[];$selectedTexts=[];foreach($allOptions as $o){if((int)$o['is_correct_snapshot']===1){$correctIds[]=(int)$o['id'];$correctTexts[]=(string)$o['option_text_snapshot'];}if(in_array((int)$o['id'],$selectedIds,true))$selectedTexts[]=(string)$o['option_text_snapshot'];}
   sort($selectedIds);sort($correctIds);$ok=count($selectedIds)===$required&&$selectedIds===$correctIds;if($ok)$correct++;
   $points=(float)$eq['points_snapshot'];$totalPoints+=$points;if($ok)$earned+=$points;
   $speedRatio=0.0;if($timeLimit>0){$speedRatio=max(0.0,min(1.0,1-($responseSeconds/$timeLimit)));$timedSpeedSum+=$speedRatio*$points;$timedWeight+=$points;}
   $maxMultiplier=($speedEnabled&&$timeLimit>0)?(1+$bonusWeight):1;$competitiveMax+=$points*$maxMultiplier;if($ok)$competitiveEarned+=$points*(($speedEnabled&&$timeLimit>0)?(1+$bonusWeight*$speedRatio):1);
   $speedBonus=round($speedRatio*100,2);
   $selectedOne=$selectedIds[0]??null;$selectedTextOne=$selectedTexts[0]??null;$correctTextOne=$correctTexts[0]??null;
   $pdo->prepare('UPDATE evaluation_questions SET selected_option_id=?,selected_option_text=?,selected_option_ids_json=?,selected_option_texts_json=?,correct_option_text=?,correct_option_texts_json=?,is_correct=?,response_seconds=?,speed_bonus=?,answered_at=NOW() WHERE id=?')->execute([$selectedOne,$selectedTextOne,json_encode($selectedIds),json_encode($selectedTexts,JSON_UNESCAPED_UNICODE),$correctTextOne,json_encode($correctTexts,JSON_UNESCAPED_UNICODE),$ok?1:0,$responseSeconds,$speedBonus,$eq['id']]);
   $summary[]=['number'=>(int)$eq['sort_order'],'question'=>$eq['question_text_snapshot'],'selected'=>$selectedTexts,'correct_answers'=>$correctTexts,'correct'=>$ok,'response_seconds'=>round($responseSeconds,1),'time_limit'=>$timeLimit];
 }
 $accuracy=$totalPoints>0?round(($earned/$totalPoints)*100,2):0;$speedMetric=$timedWeight>0?round(($timedSpeedSum/$timedWeight)*100,2):0;
 // Con rapidez activa, cada acierto puede ganar un bono según el tiempo. Una respuesta incorrecta nunca recibe bono.
 $final=$speedEnabled&&$timedWeight>0&&$competitiveMax>0?round(min(100,($competitiveEarned/$competitiveMax)*100),2):$accuracy;
 $pdo->prepare("UPDATE evaluations SET status='completed',correct_answers=?,accuracy_score=?,speed_score=?,score=?,response_seconds=?,completed_at=NOW() WHERE id=?")->execute([$correct,$accuracy,$speedMetric,$final,$totalSeconds,$ev['id']]);$pdo->commit();
 audit('evaluation_completed','evaluation',(int)$ev['id'],'Score '.$final.' accuracy '.$accuracy.' time '.$totalSeconds);
 $notify=setting('notify_on_submission','0')==='1';$notifyTo=setting('notification_email','');if($notify&&filter_var($notifyTo,FILTER_VALIDATE_EMAIL)){try{$ev2=db()->prepare('SELECT * FROM evaluations WHERE id=?');$ev2->execute([$ev['id']]);$row=$ev2->fetch();(new EmailService())->send($notifyTo,'Nueva evaluación completada · '.$row['employee_name_snapshot'],EmailTemplates::submission($row));}catch(Throwable){}}
 json_out(['success'=>true,'score'=>$final,'accuracy_score'=>$accuracy,'speed_score'=>$speedMetric,'speed_enabled'=>$speedEnabled,'response_seconds'=>round($totalSeconds,1),'correct'=>$correct,'total'=>count($questions),'summary'=>$summary,'message'=>setting('completion_text','Gracias. Tu participación fue registrada correctamente.')]);
}catch(Throwable $e){if(db()->inTransaction())db()->rollBack();json_out(['success'=>false,'message'=>$e->getMessage()],422);}
