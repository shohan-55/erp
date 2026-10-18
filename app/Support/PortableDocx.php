<?php

namespace App\Support;

/**
 * Portable, real Office Open XML DOCX writer. Uses uncompressed ZIP entries,
 * avoiding an optional ZipArchive/PHPWord extension on shared cPanel hosting.
 * Text is XML escaped and never executes HTML from employee data.
 */
final class PortableDocx
{
    private static function paragraph(string $value,bool $bold=false):string
    {
        $clean=preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$value);
        $text=htmlspecialchars($clean,ENT_QUOTES|ENT_XML1,'UTF-8');
        $weight=$bold?'<w:rPr><w:b/></w:rPr>':'';
        return '<w:p><w:r>'.$weight.'<w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
    }

    /** @param array<int,array{text:string,bold?:bool}> $lines */
    public static function make(array $lines):string
    {
        $paragraphs='';
        foreach($lines as $line){
            $paragraphs.=self::paragraph($line['text'],(bool)($line['bold']??false));
        }
        $document='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$paragraphs
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/>'
            .'</w:sectPr></w:body></w:document>';
        $files=[
            '[Content_Types].xml'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
                .'</Types>',
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',
            'word/document.xml'=>$document,
        ];

        $bytes='';$directory='';$number=0;
        foreach($files as $name=>$value){
            $offset=strlen($bytes);
            $size=strlen($value);$crc=crc32($value);
            $n=strlen($name);
            $bytes.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$size,$size,$n,0).$name.$value;
            $directory.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$size,$size,$n,0,0,0,0,0,$offset).$name;
            $number++;
        }
        $directoryOffset=strlen($bytes);
        return $bytes.$directory.pack('VvvvvVVv',0x06054b50,0,0,$number,$number,strlen($directory),$directoryOffset,0);
    }
}
