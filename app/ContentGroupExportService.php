<?php
declare(strict_types=1);

final class ContentGroupExportService
{
    public function rows(): array
    {
        $sql="SELECT cg.id,cg.name,cg.description,cg.active,cg.question_visible,cg.game_visible,cg.created_at,
                    (SELECT COUNT(*) FROM questions q WHERE q.group_name=cg.name) question_count,
                    (SELECT COUNT(*) FROM games g WHERE g.group_name=cg.name) game_count,
                    (SELECT COUNT(*) FROM employees e WHERE e.question_group=cg.name OR e.game_group=cg.name) employee_count
              FROM content_groups cg
              ORDER BY cg.active DESC,cg.name";
        return db()->query($sql)->fetchAll();
    }

    public function outputXlsx(): never
    {
        if(!class_exists('ZipArchive')) $this->outputExcelFallback();
        $rows=$this->rows();
        $all=[['ID','Categoría / campaña','Descripción','Preguntas','Juegos','Empleados','Preguntas visible','Juegos visible','Estado','Creada']];
        foreach($rows as $r){
            $all[]=[(string)$r['id'],(string)$r['name'],(string)($r['description']??''),(string)$r['question_count'],(string)$r['game_count'],(string)$r['employee_count'],(int)$r['active']===1?'Activa':'Inactiva',(string)($r['created_at']??'')];
        }
        $tmp=tempnam(sys_get_temp_dir(),'groups_');
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) $this->outputExcelFallback();
        $sheet='';
        foreach($all as $ri=>$row){$cells='';foreach($row as $ci=>$val){$ref=$this->col($ci+1).($ri+1);$style=$ri===0?' s="1"':'';$cells.='<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars((string)$val,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';}$sheet.='<row r="'.($ri+1).'">'.$cells.'</row>';}
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Categorias" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B315B"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $last=max(1,count($all));
        $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="30" customWidth="1"/><col min="3" max="3" width="48" customWidth="1"/><col min="4" max="8" width="16" customWidth="1"/></cols><sheetData>'.$sheet.'</sheetData><autoFilter ref="A1:H'.$last.'"/></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="categorias-'.date('Ymd-His').'.xlsx"');
        header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
    }

    public function outputPdf(): never
    {
        $rows=$this->rows();$lines=[];
        $lines[]=setting('company_name','Tu empresa').' - '.setting('app_name','Evaluacion Corporativa');
        $lines[]='Categorias / campanas · '.date('d/m/Y H:i');$lines[]='';
        $lines[]=sprintf('%-4s %-26s %-10s %-8s %-10s %-10s','ID','CATEGORIA','PREG.','JUEGOS','EMPLEADOS','ESTADO');
        $lines[]=str_repeat('-',78);
        foreach($rows as $r){$lines[]=sprintf('%-4s %-26s %-10s %-8s %-10s %-10s',(string)$r['id'],substr($this->ascii((string)$r['name']),0,26),(string)$r['question_count'],(string)$r['game_count'],(string)$r['employee_count'],(int)$r['active']===1?'Activa':'Inactiva');}
        if(!$rows)$lines[]='Sin categorias registradas.';
        $pdf=$this->simplePdf($lines);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="categorias-'.date('Ymd-His').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
    }

    private function outputExcelFallback(): never
    {
        $rows=$this->rows();header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="categorias-'.date('Ymd-His').'.xls"');echo "\xEF\xBB\xBF";
        echo '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse;width:100%}th{background:#0B315B;color:#fff}th,td{border:1px solid #D9E4EA;padding:8px;text-align:left}</style></head><body><h2>Categorías / campañas</h2><table><thead><tr><th>ID</th><th>Categoría</th><th>Descripción</th><th>Preguntas</th><th>Juegos</th><th>Empleados</th><th>Estado</th></tr></thead><tbody>';
        foreach($rows as $r){$vals=[$r['id'],$r['name'],$r['description']??'',$r['question_count'],$r['game_count'],$r['employee_count'],(int)$r['active']===1?'Activa':'Inactiva'];echo '<tr>';foreach($vals as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
        echo '</tbody></table></body></html>';exit;
    }
    private function ascii(string $s): string{$x=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);return $x===false?$s:$x;}
    private function col(int $n): string{$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
    private function simplePdf(array $lines): string{$pages=array_chunk($lines,45);$objects=[];$pageIds=[];$fontId=3;$next=4;foreach($pages as $page){$pageId=$next++;$contentId=$next++;$pageIds[]=$pageId;$stream="BT /F1 10 Tf 42 800 Td 12 TL\n";foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.='('.$safe.") Tj T*\n";}$stream.="ET";$objects[$contentId]="<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";$objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";}$kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0=>0];$max=max(array_keys($objects));for($i=1;$i<=$max;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;}
}
