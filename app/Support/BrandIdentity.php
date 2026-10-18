<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class BrandIdentity
{
    public static function profile(object $company, object $workspace): object
    {
        $profile = DB::table('workspace_brand_profiles')
            ->where('company_id',$company->id)->where('workspace_id',$workspace->id)->first();

        return $profile ?: (object)[
            'company_id'=>$company->id,
            'workspace_id'=>$workspace->id,
            'public_name'=>$company->brand_name ?: $company->name,
            'office_address'=>null,'email'=>null,'phone'=>null,'website'=>null,
            'accent_color'=>$company->brand_accent_color ?: '#FCDF02',
            'sidebar_color'=>'#17191F','signatory_name'=>null,'signatory_title'=>null,
            'logo_asset_id'=>null,'letterhead_asset_id'=>null,'seal_asset_id'=>null,
        ];
    }

    public static function asset(object $actor,int $id): ?object
    {
        return DB::table('workspace_brand_assets')->where('id',$id)
            ->where('company_id',$actor->company_id)
            ->where('workspace_id',$actor->workspace_id)->first();
    }

    public static function inlineImage(?object $asset): ?string
    {
        if (! $asset || ! in_array($asset->mime,['image/png','image/jpeg'],true)) return null;
        $disk=Storage::disk('local');
        if (! $disk->exists($asset->storage_path)) return null;
        $bytes=$disk->get($asset->storage_path);
        if (strlen($bytes)>4*1024*1024) return null;
        return 'data:'.$asset->mime.';base64,'.base64_encode($bytes);
    }

    public static function contrast(string $first,string $second): float
    {
        $luminance=static function(string $hex): float {
            $hex=ltrim($hex,'#');
            $rgb=[];
            foreach ([0,2,4] as $i) {
                $c=hexdec(substr($hex,$i,2))/255;
                $rgb[]=$c<=.04045?$c/12.92:(($c+.055)/1.055)**2.4;
            }
            return .2126*$rgb[0]+.7152*$rgb[1]+.0722*$rgb[2];
        };
        $l1=$luminance($first);$l2=$luminance($second);
        return (max($l1,$l2)+.05)/(min($l1,$l2)+.05);
    }

    public static function onAccent(string $accent): string
    {
        return self::contrast($accent,'#17191F') >= 4.5 ? '#17191F' : '#FFFFFF';
    }
}
