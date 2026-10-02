<?php
declare(strict_types=1);

final class QuestionExportService
{
    public function rows(): array
    {
        $pdo=db();
        $rows=$pdo->query('SELECT id,question_text,question_type,required_selections,time_limit_seconds,points,active,created_at FROM questions ORDER BY id ASC')->fetchAll();
        $q=$pdo->prepare('SELECT option_text,is_correct,sort_order FROM question_options WHERE question_id=? ORDER BY sort_order,id');
        foreach($rows as &$row){
            $q->execute([$row['id']]);
            $row['options']=$q->fetchAll();
        }
        unset($row);
        return $rows;
    }

    public function outputXlsx(): never
    {
        if(!class_exists('ZipArchive')) $this->outputExcelFallback();
        $rows=$this->rows();
        $headers=['ID','Pregunta','Tipo','Cantidad a seleccionar','Tiempo (s)','Opción 1','Opción 2','Opción 3','Opción 4','Respuestas correctas','Puntos','Publicar'];
        $all=[$headers];
        foreach($rows as $r){
            $opts=array_values($r['options']??[]);
            $correct=[];
            foreach($opts as $i=>$o) if((int)($o['is_correct']??0)===1) $correct[]=(string)($i+1);
            $all[]=[
                (string)$r['id'],(string)$r['question_text'],($r['question_type']??'single')==='multiple'?'Múltiple':'Una respuesta',
                (string)($r['required_selections']??1),(string)($r['time_limit_seconds']??0),
                (string)($opts[0]['option_text']??''),(string)($opts[1]['option_text']??''),
                (string)($opts[2]['option_text']??''),(string)($opts[3]['option_text']??''),
                implode(',',$correct),number_format((float)$r['points'],2,'.',''),(int)$r['active']===1?'Sí':'No'
            ];
        }
        $tmp=tempnam(sys_get_temp_dir(),'questions_');
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) $this->outputExcelFallback();
        $sheet='';
        foreach($all as $ri=>$row){
            $cells='';
            foreach($row as $ci=>$val){
                $ref=$this->col($ci+1).($ri+1);
                $style=$ri===0?' s="1"':'';
                $cells.='<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars((string)$val,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
            }
            $sheet.='<row r="'.($ri+1).'">'.$cells.'</row>';
        }
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Preguntas" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B315B"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $last=max(1,count($all));
        $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="9" customWidth="1"/><col min="2" max="2" width="58" customWidth="1"/><col min="3" max="5" width="20" customWidth="1"/><col min="6" max="9" width="28" customWidth="1"/><col min="10" max="12" width="18" customWidth="1"/></cols><sheetData>'.$sheet.'</sheetData><autoFilter ref="A1:L'.$last.'"/></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="preguntas-'.date('Ymd-His').'.xlsx"');
        header('Content-Length: '.filesize($tmp));
        readfile($tmp);@unlink($tmp);exit;
    }


    public function outputPdf(): never
    {
        $rows=$this->rows();$lines=[];
        $lines[]=setting('company_name','Tu empresa').' - '.setting('app_name','Evaluacion Corporativa');
        $lines[]='Banco de preguntas · '.date('d/m/Y H:i');$lines[]='';
        foreach($rows as $r){
            $state=(int)$r['active']===1?'Publicada':'Inactiva';
            $lines[]='#'.$r['id'].' ['.$state.'] '.$this->ascii((string)$r['question_text']);
            foreach(array_values($r['options']??[]) as $i=>$o){$mark=(int)($o['is_correct']??0)===1?'*':' ';$lines[]='  '.$mark.' '.($i+1).'. '.$this->ascii((string)$o['option_text']);}
            $lines[]='  Puntos: '.number_format((float)$r['points'],1);$lines[]='';
        }
        if(!$rows)$lines[]='Sin preguntas registradas.';
        $pdf=$this->simplePdf($lines);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="preguntas-'.date('Ymd-His').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
    }

    private function ascii(string $s): string{$x=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);return $x===false?$s:$x;}
    private function simplePdf(array $lines): string{$pages=array_chunk($lines,45);$objects=[];$pageIds=[];$fontId=3;$next=4;foreach($pages as $page){$pageId=$next++;$contentId=$next++;$pageIds[]=$pageId;$stream="BT /F1 9 Tf 42 800 Td 12 TL\n";foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.='('.$safe.") Tj T*\n";}$stream.="ET";$objects[$contentId]="<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";$objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";}$kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0=>0];$max=max(array_keys($objects));for($i=1;$i<=$max;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;}

    private function outputExcelFallback(): never
    {
        $rows=$this->rows();
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="preguntas-'.date('Ymd-His').'.xls"');
        echo "\xEF\xBB\xBF";
        echo '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse;width:100%}th{background:#0B315B;color:#fff}th,td{border:1px solid #D9E4EA;padding:8px;text-align:left;vertical-align:top}</style></head><body><h2>Banco de preguntas</h2><table><thead><tr><th>ID</th><th>Pregunta</th><th>Tipo</th><th>Cantidad</th><th>Tiempo (s)</th><th>Opción 1</th><th>Opción 2</th><th>Opción 3</th><th>Opción 4</th><th>Respuestas correctas</th><th>Puntos</th><th>Publicar</th></tr></thead><tbody>';
        foreach($rows as $r){$opts=array_values($r['options']??[]);$correct=[];foreach($opts as $i=>$o)if((int)($o['is_correct']??0)===1)$correct[]=(string)($i+1);$vals=[(string)$r['id'],(string)$r['question_text'],($r['question_type']??'single')==='multiple'?'Múltiple':'Una respuesta',(string)($r['required_selections']??1),(string)($r['time_limit_seconds']??0),(string)($opts[0]['option_text']??''),(string)($opts[1]['option_text']??''),(string)($opts[2]['option_text']??''),(string)($opts[3]['option_text']??''),implode(',',$correct),(string)$r['points'],(int)$r['active']===1?'Sí':'No'];echo '<tr>';foreach($vals as $v)echo '<td>'.htmlspecialchars($v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
        echo '</tbody></table></body></html>';exit;
    }

    private function col(int $n): string{ $s=''; while($n>0){$n--; $s=chr(65+$n%26).$s; $n=intdiv($n,26);} return $s; }
}
