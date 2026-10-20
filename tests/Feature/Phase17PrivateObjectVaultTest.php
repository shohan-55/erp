<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase17PrivateObjectVaultTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('erp_vault');
    }

    private function firm(string $slug='private-vault'): array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Secure Accounting Practice','slug'=>$slug,'timezone'=>'Asia/Dhaka',
            'active'=>true,'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $ws=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'partner-unit','name'=>'Partner Unit',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['partner','manager','student','unassigned'] as $role){
            $user=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$ws,'user_id'=>$user->id,
                'role'=>$role==='unassigned'?'student':$role,
                'active'=>true,'monthly_allowance'=>'3000.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$role]=(object)['id'=>$id,'user'=>$user];
        }
        DB::table('memberships')->where('id',$people['student']->id)->update([
            'manager_membership_id'=>$people['manager']->id,
        ]);
        return compact('company','ws','slug','people');
    }

    private function url(array $f,string $tail='vault'): string
    {
        return '/c/'.$f['slug'].'/app/'.$tail;
    }

    private function pdf(string $suffix='-original'): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n"
            .str_repeat('Private audit fieldwork evidence'.$suffix."\n",45)
            ."%%EOF\n";
    }

    private function reserve(array $f,string $bytes, array $override=[])
    {
        return $this->postJson($this->url($f,'vault/uploads'),array_merge([
            'title'=>'Private Audit Evidence', 'filename'=>'working-papers.pdf',
            'expected_bytes'=>strlen($bytes), 'visibility'=>'partner',
        ],$override));
    }

    private function chunk(array $f,int $id,int $number,string $bytes)
    {
        return $this->post($this->url($f,"vault/uploads/$id/parts/$number"),[
            'chunk'=>UploadedFile::fake()->createWithContent('private-part.bin',$bytes),
        ],['Accept'=>'application/json']);
    }

    private function finish(array $f,int $id)
    {
        return $this->postJson($this->url($f,"vault/uploads/$id/complete"));
    }

    private function createObject(array $f,string $bytes,array $options=[]): int
    {
        $result=$this->reserve($f,$bytes,$options);
        $result->assertCreated();
        $id=$result->json('upload_id');
        $size=(int)config('erp_vault.chunk_bytes');
        $parts=str_split($bytes,$size);
        foreach($parts as $i=>$part) $this->chunk($f,$id,$i+1,$part)->assertCreated();
        $this->finish($f,$id)->assertOk();
        return (int)DB::table('erp_vault_objects')->where('upload_id',$id)->value('id');
    }

    public function test_reservation_chunks_hash_and_object_commit_are_private_and_exactly_once(): void
    {
        $f=$this->firm();
        $data=$this->pdf();
        $this->actingAs($f['people']['student']->user)
            ->reserve($f,$data)->assertForbidden();
        $this->actingAs($f['people']['partner']->user);
        $reserve=$this->reserve($f,$data);
        $reserve->assertCreated()->assertJsonPath('parts',1);
        $id=$reserve->json('upload_id');
        $this->assertDatabaseHas('erp_vault_quotas',[
            'company_id'=>$f['company'],'workspace_id'=>$f['ws'],
            'used_bytes'=>0,'reserved_bytes'=>strlen($data),
        ]);
        $this->finish($f,$id)->assertUnprocessable()->assertJsonValidationErrors('chunk');
        $this->chunk($f,$id,1,$data)->assertCreated();
        $this->chunk($f,$id,1,$data)->assertOk();
        $this->chunk($f,$id,1,$this->pdf('replaced'))->assertUnprocessable();
        $this->finish($f,$id)->assertOk();
        $this->finish($f,$id)->assertOk();
        $obj=DB::table('erp_vault_objects')->firstOrFail();
        $this->assertSame(hash('sha256',$data),$obj->sha256);
        $this->assertSame('application/pdf',$obj->mime);
        $this->assertSame(strlen($data),(int)$obj->byte_size);
        Storage::disk('erp_vault')->assertExists($obj->object_key);
        $this->assertDatabaseHas('erp_vault_quotas',[
            'used_bytes'=>strlen($data),'reserved_bytes'=>0,
        ]);
        $this->assertDatabaseCount('erp_vault_objects',1);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'vault.object.verified','entity_id'=>$obj->id,
        ]);
        $this->get($this->url($f,'vault/objects/'.$obj->id.'/download'))
            ->assertOk()->assertHeader('Cache-Control','private, no-store, max-age=0');
        $this->actingAs($f['people']['student']->user)
            ->get($this->url($f,'vault/objects/'.$obj->id.'/download'))->assertNotFound();
    }

    public function test_chunked_multi_part_upload_does_not_load_entire_payload_as_json(): void
    {
        $f=$this->firm();
        config()->set('erp_vault.chunk_bytes',1024);
        $data=$this->pdf('split-into-many-chunks');
        $this->actingAs($f['people']['partner']->user);
        $upload=$this->reserve($f,$data);
        $upload->assertCreated();
        $id=$upload->json('upload_id');
        $parts=str_split($data,1024);
        foreach($parts as $i=>$part) $this->chunk($f,$id,$i+1,$part)->assertCreated();
        $this->assertDatabaseCount('erp_vault_parts',count($parts));
        $this->finish($f,$id)->assertOk();
        $obj=DB::table('erp_vault_objects')->firstOrFail();
        $this->assertSame(hash('sha256',$data),$obj->sha256);
        $this->assertSame(strlen($data),(int)$obj->byte_size);
    }

    public function test_partner_quota_reservation_blocks_oversubscription_and_abort_releases_it(): void
    {
        $f=$this->firm();
        $data=$this->pdf();
        $this->actingAs($f['people']['partner']->user)
            ->reserve($f,$data)->assertCreated();
        DB::table('erp_vault_quotas')->where('workspace_id',$f['ws'])
            ->update(['limit_bytes'=>strlen($data)+20]);
        $this->reserve($f,$data)->assertUnprocessable()
            ->assertJsonValidationErrors('expected_bytes');
        $id=(int)DB::table('erp_vault_uploads')->value('id');
        $this->postJson($this->url($f,"vault/uploads/$id/cancel"))->assertOk();
        $this->postJson($this->url($f,"vault/uploads/$id/cancel"))->assertOk();
        $this->assertDatabaseHas('erp_vault_quotas',[
            'workspace_id'=>$f['ws'],'used_bytes'=>0,'reserved_bytes'=>0,
        ]);
        $this->reserve($f,$data)->assertCreated();
        $this->assertDatabaseCount('erp_vault_objects',0);
    }

    public function test_malformed_contents_and_bogus_private_disk_never_commit(): void
    {
        $f=$this->firm();
        $this->actingAs($f['people']['partner']->user);
        $fake="Not a real PDF. ".str_repeat('x',2000);
        $res=$this->reserve($f,$fake);
        $res->assertCreated();
        $id=$res->json('upload_id');
        $this->chunk($f,$id,1,$fake)->assertCreated();
        $this->finish($f,$id)->assertUnprocessable()->assertJsonValidationErrors('document');
        $this->assertDatabaseCount('erp_vault_objects',0);
        $this->assertDatabaseHas('erp_vault_quotas',[
            'used_bytes'=>0,'reserved_bytes'=>strlen($fake),
        ]);
        config()->set('erp_vault.disk','public');
        $this->reserve($f,$this->pdf())->assertUnprocessable()
            ->assertJsonValidationErrors('document');
        $this->assertDatabaseCount('erp_vault_objects',0);
    }

    public function test_recipient_and_team_visibility_enforce_live_membership_boundaries(): void
    {
        $a=$this->firm('vault-a');
        $b=$this->firm('vault-b');
        $this->actingAs($a['people']['partner']->user);
        $obj=$this->createObject($a,$this->pdf(),[
            'visibility'=>'team',
            'subject_membership_id'=>$a['people']['student']->id,
        ]);
        $this->actingAs($a['people']['student']->user)
            ->get($this->url($a,'vault/objects/'.$obj.'/download'))->assertOk();
        $this->actingAs($a['people']['manager']->user)
            ->get($this->url($a,'vault/objects/'.$obj.'/download'))->assertOk();
        $this->actingAs($a['people']['unassigned']->user)
            ->get($this->url($a,'vault/objects/'.$obj.'/download'))->assertNotFound();
        $this->actingAs($b['people']['partner']->user)
            ->get($this->url($b,'vault/objects/'.$obj.'/download'))->assertNotFound();
        $this->get($this->url($b))->assertOk()->assertDontSeeText('Private Audit Evidence');
        $this->postJson($this->url($b,'vault/uploads/'.$obj.'/cancel'))->assertNotFound();
        $this->reserve($b,$this->pdf(),[
            'visibility'=>'subject',
            'subject_membership_id'=>$a['people']['student']->id,
        ])->assertNotFound();
        DB::table('memberships')->where('id',$a['people']['student']->id)
            ->update(['manager_membership_id'=>null]);
        $this->actingAs($a['people']['manager']->user)
            ->get($this->url($a,'vault/objects/'.$obj.'/download'))->assertNotFound();
    }

    public function test_archive_and_retention_hold_do_not_delete_files_or_release_quota(): void
    {
        $f=$this->firm();
        $this->actingAs($f['people']['partner']->user);
        $data=$this->pdf();
        $id=$this->createObject($f,$data,[
            'visibility'=>'subject',
            'subject_membership_id'=>$f['people']['student']->id,
        ]);
        $doc=DB::table('erp_vault_objects')->firstOrFail();
        $this->post($this->url($f,'vault/objects/'.$id.'/hold'),[
            'legal_hold'=>1,'reason'=>'Statutory audit supporting working papers must be preserved.',
        ])->assertRedirect();
        $this->assertDatabaseHas('erp_vault_objects',['id'=>$id,'legal_hold'=>true]);
        $this->post($this->url($f,'vault/objects/'.$id.'/archive'))->assertRedirect();
        $this->actingAs($f['people']['student']->user)
            ->get($this->url($f,'vault/objects/'.$id.'/download'))->assertNotFound();
        $this->actingAs($f['people']['partner']->user)
            ->get($this->url($f,'vault/objects/'.$id.'/download'))->assertOk();
        Storage::disk('erp_vault')->assertExists($doc->object_key);
        $this->assertDatabaseHas('erp_vault_quotas',[
            'workspace_id'=>$f['ws'],'used_bytes'=>strlen($data),
        ]);
        $this->assertDatabaseHas('tenant_audit_events',[
            'action'=>'vault.object.hold_changed','entity_id'=>$id,
        ]);
    }

    public function test_expired_staging_cleanup_releases_only_reserved_uploads(): void
    {
        $f=$this->firm();
        $this->actingAs($f['people']['partner']->user);
        $data=$this->pdf();
        $finished=$this->createObject($f,$data);
        $pending=$this->reserve($f,$data);
        $pending->assertCreated();
        $id=$pending->json('upload_id');
        $this->chunk($f,$id,1,$data)->assertCreated();
        $part=DB::table('erp_vault_parts')->where('upload_id',$id)->firstOrFail();
        Storage::disk('local')->assertExists($part->private_path);
        $this->travel(25)->hours();
        Artisan::call('erp:vault:expire-staging',['--dry-run'=>true]);
        $this->assertDatabaseHas('erp_vault_uploads',['id'=>$id,'status'=>'pending']);
        Artisan::call('erp:vault:expire-staging');
        Artisan::call('erp:vault:expire-staging');
        $this->assertDatabaseHas('erp_vault_uploads',['id'=>$id,'status'=>'expired']);
        $this->assertDatabaseHas('erp_vault_quotas',[
            'used_bytes'=>strlen($data),'reserved_bytes'=>0,
        ]);
        Storage::disk('local')->assertMissing($part->private_path);
        $original=DB::table('erp_vault_objects')->where('id',$finished)->firstOrFail();
        Storage::disk('erp_vault')->assertExists($original->object_key);
        $this->finish($f,$id)->assertStatus(409);
        $this->assertDatabaseCount('erp_vault_objects',1);
    }
}
