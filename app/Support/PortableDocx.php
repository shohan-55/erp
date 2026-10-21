<?php

namespace App\Support;

/**
 * Open XML Word document generator with embedded approved PNG/JPEG letterhead
 * and logo. True .docx ZIP, no cPanel Composer or ZIP extension requirement.
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

    private static function imageDrawing(string $relId,int $width,int $height,int $number):string
    {
        return '<w:p><w:r><w:drawing>'
            .'<wp:inline xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" distT="0" distB="0" distL="0" distR="0">'
            .'<wp:extent cx="'.$width.'" cy="'.$height.'"/>'
            .'<wp:docPr id="'.$number.'" name="Official branding image"/>'
            .'<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            .'<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            .'<pic:nvPicPr><pic:cNvPr id="'.$number.'" name="Brand image"/>'
            .'<pic:cNvPicPr/></pic:nvPicPr>'
            .'<pic:blipFill><a:blip xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" r:embed="'.$relId.'"/>'
            .'<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            .'<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="'.$width.'" cy="'.$height.'"/></a:xfrm>'
            .'<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            .'</pic:pic></a:graphicData></a:graphic></wp:inline>'
            .'</w:drawing></w:r></w:p>';
    }

    /**
     * @param array<int,array{text:string,bold?:bool}> $lines
     * @param array<int,array{bytes:string,mime:string}> $images
     */
    public static function make(array $lines,array $images=[]):string
    {
        $paragraphs='';$rels='';$media=[];$extensions=[];
        $seq=1;
        foreach($images as $image){
            $mime=$image['mime']??'';
            if(!in_array($mime,['image/png','image/jpeg'],true))continue;
            $bytes=$image['bytes']??'';
            if(strlen($bytes)<8 || strlen($bytes)>4*1024*1024)continue;
            $check=@getimagesizefromstring($bytes);
            if(!$check)continue;
            $ext=$mime==='image/png'?'png':'jpg';
            $name='brand-'.$seq.'.'.$ext;
            $width=(int)$check[0];$height=(int)$check[1];
            $maxWidth=$seq===1?5200000:880000;
            $maxHeight=$seq===1?1450000:880000;
            $scale=min($maxWidth/max(1,$width),$maxHeight/max(1,$height));
            $cx=max(100000,(int)round($width*$scale));
            $cy=max(100000,(int)round($height*$scale));
            $paragraphs.=self::imageDrawing('rIdBrand'.$seq,$cx,$cy,$seq);
            $rels.='<Relationship Id="rIdBrand'.$seq.'"'
                .' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"'
                .' Target="media/'.$name.'"/>';
            $media['word/media/'.$name]=$bytes;
            $extensions[$ext]=$mime;
            $seq++;
            if($seq>2)break; // one letterhead plus one logo, bounded output
        }
        foreach($lines as $line){
            $paragraphs.=self::paragraph((string)$line['text'],(bool)($line['bold']??false));
        }
        $document='<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$paragraphs
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/>'
            .'<w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/>'
            .'</w:sectPr></w:body></w:document>';

        $types='<?xml version="1.0" encoding="UTF-8"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>';
        foreach($extensions as $ext=>$mime){
            $types.='<Default Extension="'.$ext.'" ContentType="'.$mime.'"/>';
        }
        $types.='<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'</Types>';

        return PortableOfficeZip::make([
            '[Content_Types].xml'=>$types,
            '_rels/.rels'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
                .'</Relationships>',
            'word/document.xml'=>$document,
            'word/_rels/document.xml.rels'=>'<?xml version="1.0" encoding="UTF-8"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .$rels.'</Relationships>',
            ...$media,
        ]);
    }
}
