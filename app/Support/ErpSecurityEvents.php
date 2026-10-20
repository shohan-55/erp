<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ErpSecurityEvents
{
    public const KINDS=[
        'login.accepted'=>'notice',
        'login.denied'=>'warning',
        'account.login.accepted'=>'notice',
        'account.login.denied'=>'warning',
        'account.password.reset'=>'high',
        'account.mfa.disabled'=>'high',
        'integration.token.created'=>'notice',
        'integration.token.revoked'=>'notice',
        'integration.tokens.revoked'=>'high',
    ];

    /**
     * Intentionally minimal security metadata. No actor input, tokens,
     * e-mail addresses, file names, full IP or private source data.
     */
    public static function record(string $kind,Request $request,?int $companyId=null,?int $userId=null):void
    {
        if(!array_key_exists($kind,self::KINDS)){
            throw new InvalidArgumentException('Unrecognized security event category.');
        }
        $ip=$request->ip();
        $key=(string)config('app.key');
        DB::table('erp_security_events')->insert([
            'company_id'=>$companyId,
            'user_id'=>$userId,
            'kind'=>$kind,
            'severity'=>self::KINDS[$kind],
            'daily_ip_fingerprint'=>is_string($ip)&&$ip!==''
                ?hash_hmac('sha256',now()->toDateString().'|'.$ip,$key):null,
            'created_at'=>now(),
        ]);
    }

    /** All memberships, all companies. Account credential compromise
     * invalidates all previously issued integration tokens simultaneously.
     */
    public static function revokeUserTokens(int $userId,Request $request,?int $companyId=null):int
    {
        $count=DB::table('erp_integration_tokens')->where('user_id',$userId)
            ->whereNull('revoked_at')->update([
                'revoked_at'=>now(),'updated_at'=>now(),
            ]);
        self::record('integration.tokens.revoked',$request,$companyId,$userId);
        return $count;
    }
}
