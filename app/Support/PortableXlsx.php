<?php

namespace App\Support;

/**
 * Genuine Office Open XML .xlsx export with inline string cells (no formulas).
 * Uses the same portable ZIP writer as DOCX, no external spreadsheet package.
 */
final class PortableXlsx
{
    private static function column(int $index):string
    {
        $out='';
        for($i=$index+1;$i>0;$i=intdiv($i-1,26)){
            $out=chr(65+($i-1)%26).$out;
        }
        return $out;
    }

    /** @param array<int,array<int,string|int|float|null>> $rows */
    public static function make(array $rows,string $sheetName='Official Documents'):string
    {
        $sheet='';
        foreach($rows as $rowIndex=>$row) {
            $number=$rowIndex+1;
            $cells='';
            foreach(array_values($row) as $column=>$value) {
                $ref=self::column($column).$number;
                $escaped=htmlspecialchars(
                    preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',(string)$value),
                    ENT_QUOTES|ENT_XML1,'UTF-8'
                );
                $cells.='<c r="'.$ref.'" t="inlineStr"><is><t xml:space="preserve">'
                    .$escaped.'</t></is></c>';
            }
            $sheet.='<row r="'.$number.'">'.$cells.'</row>';
        }
        $worksheet='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetData>'.$sheet.'</sheetData></worksheet>';
        $safeName=htmlspecialchars(mb_substr($sheetName,0,31),ENT_QUOTES|ENT_XML1,'UTF-8');
        $workbook='<?xml version="1.0" encoding="UTF-8"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
            .' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$safeName.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
        return PortableOfficeZip::make([
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>',
            'xl/workbook.xml'=>$workbook,
            'xl/_rels/workbook.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>',
            'xl/worksheets/sheet1.xml'=>$worksheet,
        ]);
    }
}
