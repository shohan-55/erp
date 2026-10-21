<?php

/**
 * Hermetic QA-only backup/restore rehearsal. Uses invented records, its own
 * disposable SQLite file and ephemeral AES-256-GCM key. NEVER reads .env,
 * an ERP database or customer filesystem. It CANNOT prove production DR.
 */
declare(strict_types=1);

if(PHP_SAPI!=='cli' || getenv('PHASE23_SYNTHETIC_RESTORE')!=='1'
    || getenv('APP_ENV')!=='testing'){
    fwrite(STDERR,"Synthetic restore requires explicit test-only environment.\n");
    exit(1);
}
if(!extension_loaded('sqlite3') || !extension_loaded('openssl')){
    fwrite(STDERR,"Required synthetic SQLite/OpenSSL extensions unavailable.\n");
    exit(1);
}
$root=sys_get_temp_dir().'/erp-phase23-synthetic-'.bin2hex(random_bytes(12));
$cleanup=static function(string $path)use(&$cleanup):void {
    if(!is_dir($path))return;
    foreach(new FilesystemIterator($path) as $item){
        if($item->isDir()&&!$item->isLink())$cleanup($item->getPathname());
        else unlink($item->getPathname());
    }
    rmdir($path);
};
try {
    if(!mkdir($root,0700) || !mkdir($root.'/source',0700)
        || !mkdir($root.'/isolated-recovery',0700)){
        throw new RuntimeException('Temporary synthetic directory unavailable');
    }
    $source=$root.'/source/synthetic.sqlite';
    $db=new SQLite3($source);
    if(!$db->exec("CREATE TABLE synthetic_memberships (id INTEGER PRIMARY KEY, label TEXT NOT NULL)"))
        throw new RuntimeException('Synthetic database not initialized');
    if(!$db->exec("INSERT INTO synthetic_memberships (label) VALUES ('fixture-only-member-1'), ('fixture-only-member-2')"))
        throw new RuntimeException('Synthetic insert failed');
    $db->close();
    $fakePrivate="SYNTHETIC DOCUMENT ONLY. No employee/client data.\n";
    file_put_contents($root.'/source/fake-private.txt',$fakePrivate);
    // Close SQLite before copying the bytes, so no WAL checkpoint is pending.
    $bundle=json_encode([
        'database'=>base64_encode(file_get_contents($source)),
        'private_file'=>base64_encode($fakePrivate),
    ],JSON_THROW_ON_ERROR);
    $key=random_bytes(32);$nonce=random_bytes(12);$tag='';
    $cipher=openssl_encrypt($bundle,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
    if($cipher===false || strlen($tag)!==16)throw new RuntimeException('Synthetic encryption unavailable');
    $archive=$nonce.$tag.$cipher;
    $snapshot=$root.'/source/encrypted-fixture.bin';
    $target=$root.'/isolated-recovery/encrypted-fixture.bin';
    file_put_contents($snapshot,$archive);
    if(!copy($snapshot,$target) || !hash_equals(hash_file('sha256',$snapshot),hash_file('sha256',$target)))
        throw new RuntimeException('Backup transfer checksum failed');
    $bytes=file_get_contents($target);
    $plaintext=openssl_decrypt(substr($bytes,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($bytes,0,12),substr($bytes,12,16));
    if(!is_string($plaintext))throw new RuntimeException('Synthetic archive cannot decrypt');
    $data=json_decode($plaintext,true,8,JSON_THROW_ON_ERROR);
    $dbBytes=base64_decode($data['database'],true);
    $fileBytes=base64_decode($data['private_file'],true);
    if(!is_string($dbBytes)||!is_string($fileBytes))throw new RuntimeException('Synthetic data corrupted');
    $recoveredDB=$root.'/isolated-recovery/restored.sqlite';
    file_put_contents($recoveredDB,$dbBytes);
    $restored=new SQLite3($recoveredDB,SQLITE3_OPEN_READONLY);
    $count=(int)$restored->querySingle('SELECT COUNT(*) FROM synthetic_memberships');
    $restored->close();
    if($count!==2 || !hash_equals(hash('sha256',$fakePrivate),hash('sha256',$fileBytes)))
        throw new RuntimeException('Synthetic restore content not consistent');
    $tampered=$archive;
    $tampered[strlen($tampered)-1]=chr(ord($tampered[strlen($tampered)-1])^1);
    if(openssl_decrypt(substr($tampered,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,
        substr($tampered,0,12),substr($tampered,12,16))!==false)
        throw new RuntimeException('Tampered encrypted archive was accepted');
    echo json_encode([
        'decision'=>'SYNTHETIC_RESTORE_PASSED',
        'source'=>'generated_dummy_sqlite_and_fake_text_only',
        'live_data_used'=>false,'original_real_database_accessed'=>false,
        'isolated_restored_member_count'=>$count,
        'encrypted_aes_256_gcm'=>true,
        'transfer_sha256_validated'=>true,
        'tamper_rejection_verified'=>true,
        'production_restore_verified'=>false,
        'verified_200gb_backup_restore'=>false,
        'deployment_authorized'=>false,
    ],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
} catch(Throwable $e){
    fwrite(STDERR,"SYNTHETIC_RESTORE_FAILED (no real data touched)\n");
    exit(1);
} finally {
    // Cleanup never traverses outside its own randomly named /tmp directory.
    $cleanup($root);
}
