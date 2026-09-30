<?php
declare(strict_types=1);

final class GameAttemptReportService
{
    public function rows(): array
    {
        $sql="SELECT a.id,a.badge_snapshot AS badge,a.employee_name_snapshot AS name,
                     emp.department,g.title AS game,g.category,g.difficulty,
                     a.correct_answers,a.total_items,a.accuracy_score,a.speed_score,a.score,a.response_seconds,a.completed_at
              FROM game_attempts a
              JOIN games g ON g.id=a.game_id
              LEFT JOIN employees emp ON emp.id=a.employee_id
              WHERE a.completed_at IS NOT NULL
              ORDER BY a.completed_at DESC,a.id DESC";
        return db()->query($sql)->fetchAll();
    }

    public function outputXlsx(): never
    {
        if(!class_exists('ZipArchive')) $this->outputExcelFallback();
        $rows=$this->rows();
        $all=[['Gafete','Nombre','Departamento','Juego','Categoría','Dificultad','Correctas','Retos','Exactitud','Rapidez','Puntuación final','Tiempo (s)','Fecha']];
        foreach($rows as $r){
            $all[]=[(string)$r['badge'],(string)$r['name'],(string)($r['department']??''),(string)$r['game'],(string)$r['category'],(string)$r['difficulty'],(string)$r['correct_answers'],(string)$r['total_items'],number_format((float)($r['accuracy_score']??$r['score']),2,'.',''),number_format((float)($r['speed_score']??0),2,'.',''),number_format((float)$r['score'],2,'.',''),number_format((float)($r['response_seconds']??0),1,'.',''),(string)$r['completed_at']];
        }
        $tmp=tempnam(sys_get_temp_dir(),'gameattempts_');$zip=new ZipArchive();$zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE);
        $sheet='';foreach($all as $ri=>$row){$cells='';foreach($row as $ci=>$val){$ref=$this->col($ci+1).($ri+1);$style=$ri===0?' s="1"':'';$cells.='<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars((string)$val,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$sheet.='<row r="'.($ri+1).'">'.$cells.'</row>';}
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Participación juegos" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="1"><fill><patternFill patternType="none"/></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs></styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="1" width="16" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="6" width="24" customWidth="1"/><col min="7" max="13" width="16" customWidth="1"/></cols><sheetData>'.$sheet.'</sheetData><autoFilter ref="A1:M'.max(1,count($all)).'"/></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="participacion-juegos-'.date('Ymd-His').'.xlsx"');header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
    }

    public function outputPdf(): never
    {
        $rows=$this->rows();$lines=[];$lines[]=setting('company_name','Tu empresa').' - '.setting('app_name','Evaluacion Corporativa');$lines[]='Reporte de participacion en juegos · '.date('d/m/Y H:i');$lines[]='';$lines[]=sprintf('%-11s %-22s %-22s %-9s %-7s %-16s','GAFETE','NOMBRE','JUEGO','ACIERTOS','NOTA','FECHA');$lines[]=str_repeat('-',92);
        foreach($rows as $r){$lines[]=sprintf('%-11s %-22s %-22s %-9s %-7s %-16s',substr($this->ascii((string)$r['badge']),0,11),substr($this->ascii((string)$r['name']),0,22),substr($this->ascii((string)$r['game']),0,22),$r['correct_answers'].'/'.$r['total_items'],number_format((float)$r['score'],1).'%',date('d/m/Y H:i',strtotime((string)$r['completed_at'])));}if(!$rows)$lines[]='Sin partidas completadas.';
        $pdf=$this->simplePdf($lines);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="participacion-juegos-'.date('Ymd-His').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
    }

    private function outputExcelFallback(): never
    {
        $rows=$this->rows();header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="participacion-juegos-'.date('Ymd-His').'.xls"');echo "\xEF\xBB\xBF";
        echo '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse;width:100%}th{background:#0B315B;color:#fff}th,td{border:1px solid #D9E4EA;padding:8px;text-align:left}</style></head><body><h2>Participación en juegos</h2><table><thead><tr><th>Gafete</th><th>Nombre</th><th>Departamento</th><th>Juego</th><th>Categoría</th><th>Dificultad</th><th>Correctas</th><th>Retos</th><th>Puntuación</th><th>Fecha</th></tr></thead><tbody>';
        foreach($rows as $r){$vals=[$r['badge'],$r['name'],$r['department']??'',$r['game'],$r['category'],$r['difficulty'],$r['correct_answers'],$r['total_items'],number_format((float)$r['score'],1).'%',(string)$r['completed_at']];echo '<tr>';foreach($vals as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}if(!$rows)echo '<tr><td colspan="10">Sin partidas completadas.</td></tr>';echo '</tbody></table></body></html>';exit;
    }
    private function ascii(string $s): string{$x=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);return $x===false?$s:$x;}
    private function col(int $n): string{$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
    private function simplePdf(array $lines): string{$pages=array_chunk($lines,45);$objects=[];$pageIds=[];$fontId=3;$next=4;foreach($pages as $page){$pageId=$next++;$contentId=$next++;$pageIds[]=$pageId;$stream="BT /F1 9 Tf 35 800 Td 11 TL\n";foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.='('.$safe.") Tj T*\n";}$stream.="ET";$objects[$contentId]="<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";$objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";}$kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0=>0];$max=max(array_keys($objects));for($i=1;$i<=$max;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;}
}
