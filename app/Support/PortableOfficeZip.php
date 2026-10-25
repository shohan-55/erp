<?php

namespace App\Support;

/** Minimal Open Packaging Conventions ZIP (store-only; portable on cPanel). */
final class PortableOfficeZip
{
    /** @param array<string,string> $files */
    public static function make(array $files):string
    {
        $data='';$central='';$count=0;
        foreach($files as $name=>$contents) {
            $offset=strlen($data);
            $length=strlen($contents);
            $crc=crc32($contents);
            $nameLength=strlen($name);
            $data.=pack('VvvvvvVVVvv',0x04034b50,20,0,0,0,0,$crc,$length,$length,$nameLength,0)
                .$name.$contents;
            $central.=pack('VvvvvvvVVVvvvvvVV',0x02014b50,20,20,0,0,0,0,$crc,$length,
                $length,$nameLength,0,0,0,0,0,$offset).$name;
            $count++;
        }
        return $data.$central.pack('VvvvvVVv',0x06054b50,0,0,$count,$count,
            strlen($central),strlen($data),0);
    }
}
