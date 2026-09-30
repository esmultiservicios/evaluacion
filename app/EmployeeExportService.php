<?php
declare(strict_types=1);

final class EmployeeExportService
{
    public function rows(): array
    {
        return db()->query("SELECT id,badge,name,department,email,status,created_at FROM employees ORDER BY name,id")->fetchAll();
    }

    public function outputXlsx(): never
    {
        if(!class_exists('ZipArchive')) $this->outputExcelFallback();
        $rows=$this->rows();
        $all=[['ID','Gafete','Nombre','Departamento','Correo','Estado','Fecha de registro']];
        foreach($rows as $r){
            $all[]=[(string)$r['id'],(string)$r['badge'],(string)$r['name'],(string)($r['department']??''),(string)($r['email']??''),(string)($r['status']==='active'?'Activo':'Inactivo'),(string)($r['created_at']??'')];
        }
        $tmp=tempnam(sys_get_temp_dir(),'employees_');
        $zip=new ZipArchive();
        if($zip->open($tmp,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) $this->outputExcelFallback();
        $sheet='';
        foreach($all as $ri=>$row){
            $cells='';
            foreach($row as $ci=>$val){
                $ref=$this->col($ci+1).($ri+1);$style=$ri===0?' s="1"':'';
                $cells.='<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t>'.htmlspecialchars((string)$val,ENT_XML1|ENT_QUOTES,'UTF-8').'</t></is></c>';
            }
            $sheet.='<row r="'.($ri+1).'">'.$cells.'</row>';
        }
        $zip->addFromString('[Content_Types].xml','<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml','<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Empleados" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml','<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B315B"/><bgColor indexed="64"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>');
        $last=max(1,count($all));
        $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><cols><col min="1" max="2" width="14" customWidth="1"/><col min="3" max="3" width="34" customWidth="1"/><col min="4" max="4" width="25" customWidth="1"/><col min="5" max="5" width="34" customWidth="1"/><col min="6" max="7" width="20" customWidth="1"/></cols><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>'.$sheet.'</sheetData><autoFilter ref="A1:G'.$last.'"/></worksheet>');
        $zip->close();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="empleados-'.date('Ymd-His').'.xlsx"');
        header('Content-Length: '.filesize($tmp));readfile($tmp);@unlink($tmp);exit;
    }

    public function outputPdf(): never
    {
        $rows=$this->rows();$lines=[];$lines[]=setting('company_name','Tu empresa').' - '.setting('app_name','Evaluacion Corporativa');$lines[]='Directorio de empleados · '.date('d/m/Y H:i');$lines[]='';
        foreach($rows as $r){$lines[]=$this->ascii((string)$r['badge'].' | '.(string)$r['name'].' | '.(string)($r['department']?:'Sin departamento').' | '.(string)($r['email']?:'Sin correo').' | '.($r['status']==='active'?'Activo':'Inactivo'));}
        if(!$rows)$lines[]='Sin empleados registrados.';
        $pdf=$this->simplePdf($lines);header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="empleados-'.date('Ymd-His').'.pdf"');header('Content-Length: '.strlen($pdf));echo $pdf;exit;
    }

    private function outputExcelFallback(): never
    {
        $rows=$this->rows();header('Content-Type: application/vnd.ms-excel; charset=UTF-8');header('Content-Disposition: attachment; filename="empleados-'.date('Ymd-His').'.xls"');echo "\xEF\xBB\xBF";
        echo '<!doctype html><html><head><meta charset="UTF-8"><style>body{font-family:Arial,sans-serif}table{border-collapse:collapse;width:100%}th{background:#0B315B;color:#fff}th,td{border:1px solid #D9E4EA;padding:8px;text-align:left}</style></head><body><h2>Directorio de empleados</h2><table><thead><tr><th>ID</th><th>Gafete</th><th>Nombre</th><th>Departamento</th><th>Correo</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
        foreach($rows as $r){$vals=[$r['id'],$r['badge'],$r['name'],$r['department']??'',$r['email']??'',$r['status']==='active'?'Activo':'Inactivo',$r['created_at']??''];echo '<tr>';foreach($vals as $v)echo '<td>'.htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8').'</td>';echo '</tr>';}
        echo '</tbody></table></body></html>';exit;
    }

    private function ascii(string $s): string{$x=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s);return $x===false?$s:$x;}
    private function col(int $n): string{$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
    private function simplePdf(array $lines): string{$pages=array_chunk($lines,45);$objects=[];$pageIds=[];$fontId=3;$next=4;foreach($pages as $page){$pageId=$next++;$contentId=$next++;$pageIds[]=$pageId;$stream="BT /F1 9 Tf 32 800 Td 11 TL\n";foreach($page as $line){$safe=str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$line);$stream.='('.$safe.") Tj T*\n";}$stream.="ET";$objects[$contentId]="<< /Length ".strlen($stream)." >>\nstream\n$stream\nendstream";$objects[$pageId]="<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 842] /Resources << /Font << /F1 $fontId 0 R >> >> /Contents $contentId 0 R >>";}$kids=implode(' ',array_map(fn($id)=>$id.' 0 R',$pageIds));$objects[1]='<< /Type /Catalog /Pages 2 0 R >>';$objects[2]='<< /Type /Pages /Kids ['.$kids.'] /Count '.count($pageIds).' >>';$objects[3]='<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>';ksort($objects);$pdf="%PDF-1.4\n";$offsets=[0=>0];$max=max(array_keys($objects));for($i=1;$i<=$max;$i++){$offsets[$i]=strlen($pdf);$pdf.=$i." 0 obj\n".($objects[$i]??'<<>>')."\nendobj\n";}$xref=strlen($pdf);$pdf.="xref\n0 ".($max+1)."\n0000000000 65535 f \n";for($i=1;$i<=$max;$i++)$pdf.=sprintf('%010d 00000 n ', $offsets[$i])."\n";$pdf.="trailer\n<< /Size ".($max+1)." /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";return $pdf;}
}
