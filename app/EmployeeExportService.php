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
        $rows=$this->rows();
        $sheet='';
        $sheet.='<row r="1" ht="30" customHeight="1">'.$this->xlsxRowCells(1,['Directorio de empleados','','','','','',''],[1,1,1,1,1,1,1]).'</row>';
        $sheet.='<row r="2">'.$this->xlsxRowCells(2,['Exportación administrativa · '.date('d/m/Y H:i'),'','','','','',''],[2,2,2,2,2,2,2]).'</row>';
        $sheet.='<row r="4" ht="22" customHeight="1">'.$this->xlsxRowCells(4,['ID','Gafete','Nombre','Departamento','Correo','Estado','Fecha de registro'],[3,3,3,3,3,3,3]).'</row>';
        $rnum=5;
        foreach($rows as $r){
            $vals=[(string)$r['id'],(string)$r['badge'],(string)$r['name'],(string)($r['department']??''),(string)($r['email']??''),(string)($r['status']==='active'?'Activo':'Inactivo'),(string)($r['created_at']??'')];
            $sheet.='<row r="'.$rnum.'">'.$this->xlsxRowCells($rnum,$vals,[4,4,4,4,4,4,4]).'</row>';
            $rnum++;
        }
        $last=max(4,$rnum-1);
        $sheetXml='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="4" topLeftCell="A5" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="15"/>'
            .'<cols><col min="1" max="1" width="8" customWidth="1"/><col min="2" max="2" width="14" customWidth="1"/><col min="3" max="3" width="30" customWidth="1"/><col min="4" max="4" width="24" customWidth="1"/><col min="5" max="5" width="32" customWidth="1"/><col min="6" max="6" width="14" customWidth="1"/><col min="7" max="7" width="20" customWidth="1"/></cols>'
            .'<sheetData>'.$sheet.'</sheetData>'
            .'<autoFilter ref="A4:G'.$last.'"/>'
            .'<mergeCells count="2"><mergeCell ref="A1:G1"/><mergeCell ref="A2:G2"/></mergeCells>'
            .'<pageMargins left="0.7" right="0.7" top="0.75" bottom="0.75" header="0.3" footer="0.3"/>'
            .'</worksheet>';

        $styles='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5">'
            .'<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="18"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><i/><sz val="11"/><color rgb="FF0B315B"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'
            .'<font><sz val="11"/><color rgb="FF16324F"/><name val="Calibri"/><family val="2"/></font>'
            .'</fonts>'
            .'<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0B315B"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8F6F8"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0F6F7E"/><bgColor indexed="64"/></patternFill></fill><fill><patternFill patternType="solid"><fgColor rgb="FFF7FBFC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFD9E4EA"/></left><right style="thin"><color rgb="FFD9E4EA"/></right><top style="thin"><color rgb="FFD9E4EA"/></top><bottom style="thin"><color rgb="FFD9E4EA"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="5">'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1"><alignment vertical="center"/></xf>'
            .'<xf numFmtId="0" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            .'<xf numFmtId="0" fontId="3" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment horizontal="center" vertical="center" wrapText="1"/></xf>'
            .'<xf numFmtId="0" fontId="4" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>'
            .'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';

        $files=[
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/><Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/><Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>',
            'docProps/core.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><dc:creator>Evaluación Corporativa</dc:creator><cp:lastModifiedBy>Evaluación Corporativa</cp:lastModifiedBy><dcterms:created xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">'.gmdate('Y-m-d\TH:i:s\Z').'</dcterms:modified></cp:coreProperties>',
            'docProps/app.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes"><Application>Evaluación Corporativa</Application></Properties>',
            'xl/workbook.xml'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><bookViews><workbookView xWindow="0" yWindow="0" windowWidth="24000" windowHeight="12000"/></bookViews><sheets><sheet name="Empleados" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml'=>$styles,
            'xl/worksheets/sheet1.xml'=>$sheetXml,
        ];
        $xlsx=$this->zipStore($files);

        $this->clearOutputBuffers();
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="empleados-'.date('Ymd-His').'.xlsx"');
        header('Content-Transfer-Encoding: binary');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Content-Length: '.strlen($xlsx));
        echo $xlsx;
        exit;
    }

    private function xlsxRowCells(int $row,array $values,array $styles): string
    {
        $cells='';
        foreach($values as $i=>$value){
            $ref=$this->col($i+1).$row;$style=(int)($styles[$i]??0);
            if($value===''){$cells.='<c r="'.$ref.'" s="'.$style.'"/>';continue;}
            $escaped=htmlspecialchars((string)$value,ENT_XML1|ENT_QUOTES,'UTF-8');
            $cells.='<c r="'.$ref.'" s="'.$style.'" t="inlineStr"><is><t xml:space="preserve">'.$escaped.'</t></is></c>';
        }
        return $cells;
    }

    /** @param array<string,string> $files */
    private function zipStore(array $files): string
    {
        $data='';$central='';$offset=0;$count=0;
        $now=getdate();$dosTime=(($now['hours']&31)<<11)|(($now['minutes']&63)<<5)|(int)floor(($now['seconds']&63)/2);$dosDate=((max(1980,$now['year'])-1980)<<9)|(($now['mon']&15)<<5)|($now['mday']&31);
        foreach($files as $name=>$content){
            $name=(string)$name;$content=(string)$content;$crc=(int)sprintf('%u',crc32($content));$size=strlen($content);$nameLen=strlen($name);
            $local="PK\x03\x04".pack('vvvvvVVVvv',20,0,0,$dosTime,$dosDate,$crc,$size,$size,$nameLen,0).$name.$content;
            $data.=$local;
            $central.="PK\x01\x02".pack('vvvvvvVVVvvvvvVV',20,20,0,0,$dosTime,$dosDate,$crc,$size,$size,$nameLen,0,0,0,0,0,$offset).$name;
            $offset+=strlen($local);$count++;
        }
        $centralOffset=strlen($data);$centralSize=strlen($central);
        return $data.$central."PK\x05\x06".pack('vvvvVVv',0,0,$count,$count,$centralSize,$centralOffset,0);
    }

    private function clearOutputBuffers(): void
    {
        while(ob_get_level()>0){@ob_end_clean();}
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
