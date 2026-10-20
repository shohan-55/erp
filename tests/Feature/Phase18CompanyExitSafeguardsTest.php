<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\TenantExitPackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class Phase18CompanyExitSafeguardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('erp_exit');
        Storage::fake('erp_vault');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function firm(string $slug='exit-company',int $partners=2):array
    {
        $company=DB::table('companies')->insertGetId([
            'slug'=>$slug,'name'=>'Independent Firm','timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00','grace_minutes'=>10,
            'weekly_holidays'=>'[5,6]','active'=>true,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        for($i=1;$i<=$partners;$i++){
            $workspace=DB::table('workspaces')->insertGetId([
                'company_id'=>$company,'name'=>'Partner Practice '.$i,'slug'=>'partner-'.$i,
                'active'=>true,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $user=User::factory()->create();
            $member=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$user->id,'role'=>'partner','active'=>true,
                'monthly_allowance'=>'0.00',
                'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[]=(object)['user'=>$user,'id'=>$member,'workspace'=>$workspace];
        }
        $student=User::factory()->create();
        $studentMember=DB::table('memberships')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$people[0]->workspace,
            'user_id'=>$student->id,'role'=>'student','active'=>true,
            'monthly_allowance'=>'3000.00','created_at'=>now(),'updated_at'=>now(),
        ]);
        DB::table('leave_requests')->insert([
            'company_id'=>$company,'workspace_id'=>$people[0]->workspace,
            'membership_id'=>$studentMember,'leave_type'=>'casual',
            'date_from'=>'2026-10-22','date_to'=>'2026-10-22',
            'reason'=>'A statutory audit leave record required in exit backup.',
            'status'=>'approved','created_at'=>now(),'updated_at'=>now(),
        ]);
        return compact('company','slug','people','studentMember');
    }

    private function base(array $f): string
    {
        return '/c/'.$f['slug'].'/app/company-exit';
    }

    private function requestCase(array $f): int
    {
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/request',[
                'confirmation_slug'=>$f['slug'],
                'reason'=>'Preparing customer-owned data before terminating the ERP contract.',
                'retention_until'=>now()->addDays(45)->toDateString(),
            ])->assertRedirect()->assertSessionHasNoErrors();
        return (int)DB::table('tenant_exit_cases')->where('company_id',$f['company'])->value('id');
    }

    private function consent(array $f,int $id): void
    {
        foreach($f['people'] as $person){
            $this->actingAs($person->user)
                ->post($this->base($f).'/'.$id.'/vote',[
                    'decision'=>'approve',
                    'reason'=>'I independently authorize the scoped company export after review.',
                ])->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    private function prepare(array $f,int $id): void
    {
        $this->consent($f,$id);
        $result=Artisan::call('erp:exit:prepare',['--case'=>$id]);
        $this->assertSame(0,$result,Artisan::output());
        $this->assertDatabaseHas('tenant_exit_cases',[
            'id'=>$id,'company_id'=>$f['company'],'state'=>'prepared',
        ]);
    }

    public function test_unanimous_independent_partner_consents_are_required_and_private():void
    {
        $f=$this->firm();
        $foreign=$this->firm('foreign-entity');
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/request',[
                'confirmation_slug'=>'not-the-company',
                'reason'=>'Want to export all firm-owned records at contract exit.',
                'retention_until'=>now()->addDays(45)->toDateString(),
            ])->assertSessionHasErrors('confirmation_slug');
        $id=$this->requestCase($f);
        $this->assertDatabaseCount('tenant_exit_votes',2);
        $this->assertDatabaseHas('tenant_notifications',[
            'company_id'=>$f['company'],
            'workspace_id'=>$f['people'][1]->workspace,
            'recipient_membership_id'=>$f['people'][1]->id,
        ]);
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/'.$id.'/vote',[
                'decision'=>'approve','reason'=>'Reviewed and approved the scope of company data.',
            ])->assertRedirect();
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'awaiting_consent']);
        $this->assertSame(0,Artisan::call('erp:exit:prepare',['--case'=>$id]));
        $this->assertDatabaseCount('tenant_exit_artifacts',0);
        $this->actingAs($foreign['people'][0]->user)
            ->get($this->base($foreign).'/'.$id.'/purge-preflight')->assertNotFound();
        $this->actingAs($f['people'][1]->user)
            ->post($this->base($f).'/'.$id.'/vote',[
                'decision'=>'reject','reason'=>'Not yet satisfied with the proposed export and retention.',
            ])->assertRedirect();
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'rejected']);
        $this->assertDatabaseCount('tenant_exit_artifacts',0);
    }

    public function test_approved_export_encrypts_database_and_private_files_then_checks_hash():void
    {
        $f=$this->firm();
        $data="%PDF-1.4\nPrivate signed audit evidence.\n%%EOF\n";
        $path='practice-documents/'.$f['company'].'/'.$f['people'][0]->workspace.'/fixture.pdf';
        Storage::disk('local')->put($path,$data);
        DB::table('practice_documents')->insert([
            'company_id'=>$f['company'],'workspace_id'=>$f['people'][0]->workspace,
            'uploaded_by'=>$f['people'][0]->id,'title'=>'Private Statutory Audit File',
            'category'=>'audit','visibility'=>'partner','storage_path'=>$path,
            'original_filename'=>'fixture.pdf','sha256'=>hash('sha256',$data),
            'byte_size'=>strlen($data),'created_at'=>now(),'updated_at'=>now(),
        ]);
        $id=$this->requestCase($f);
        $this->prepare($f,$id);
        $artifacts=DB::table('tenant_exit_artifacts')->where('case_id',$id)->get();
        $this->assertGreaterThan(2,$artifacts->count());
        $manifest=$artifacts->firstWhere('kind','manifest');
        $file=$artifacts->firstWhere('kind','file');
        $this->assertNotNull($manifest);
        $this->assertNotNull($file);
        $this->assertTrue(TenantExitPackage::verify($id));
        $encrypted=Storage::disk('erp_exit')->get($file->storage_key);
        $this->assertStringNotContainsString('Private signed audit evidence',$encrypted);
        $this->assertSame($data,TenantExitPackage::content($file));
        $this->actingAs($f['people'][1]->user)
            ->get($this->base($f).'/'.$id.'/parts/'.$manifest->id)
            ->assertOk()->assertHeader('Content-Type','application/octet-stream');
        $this->post($this->base($f).'/'.$id.'/verify')
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'integrity_verified']);
        $this->assertDatabaseHas('practice_documents',['company_id'=>$f['company'],'storage_path'=>$path]);
        Storage::disk('local')->assertExists($path);
        $this->assertDatabaseHas('tenant_audit_events',['action'=>'exit.integrity.verified']);
    }

    public function test_corrupt_artifact_is_never_verified_or_acknowledged():void
    {
        $f=$this->firm();$id=$this->requestCase($f);
        $this->prepare($f,$id);
        $artifact=DB::table('tenant_exit_artifacts')->where('case_id',$id)->firstOrFail();
        Storage::disk('erp_exit')->put($artifact->storage_key,'tampered-ciphertext');
        $this->assertFalse(TenantExitPackage::verify($id));
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/'.$id.'/verify')->assertSessionHasErrors('integrity');
        $this->post($this->base($f).'/'.$id.'/acknowledge',[
            'manifest_sha256'=>str_repeat('0',64),'confirmation'=>'RECEIVED',
        ])->assertStatus(409);
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'prepared']);
    }

    public function test_independent_restore_attestation_needs_different_person_and_exact_manifest():void
    {
        $f=$this->firm();$id=$this->requestCase($f);
        $this->prepare($f,$id);
        $hash=DB::table('tenant_exit_cases')->where('id',$id)->value('manifest_sha256');
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/'.$id.'/verify')->assertRedirect();
        $this->post($this->base($f).'/'.$id.'/acknowledge',[
            'manifest_sha256'=>str_repeat('0',64),'confirmation'=>'RECEIVED',
        ])->assertSessionHasErrors('manifest_sha256');
        $this->post($this->base($f).'/'.$id.'/acknowledge',[
            'manifest_sha256'=>$hash,'confirmation'=>'RECEIVED',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $data=[
            'manifest_sha256'=>$hash,
            'restore_evidence'=>'Restored all NDJSON parts on an isolated database and reconciled row counts and file hash.',
            'confirmation'=>'INDEPENDENT_RESTORE_TESTED',
        ];
        $this->post($this->base($f).'/'.$id.'/restore-attestation',$data)->assertForbidden();
        $this->actingAs($f['people'][1]->user)
            ->post($this->base($f).'/'.$id.'/restore-attestation',$data)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('tenant_exit_cases',[
            'id'=>$id,'state'=>'restore_attested',
            'restore_attested_by'=>$f['people'][1]->id,
        ]);
        $preflight=$this->get($this->base($f).'/'.$id.'/purge-preflight')->assertOk();
        $preflight->assertJsonPath('physical_purge_enabled',false);
        $this->assertContains('Phase 18 deliberately has no irreversible purge implementation or release approval',
            $preflight->json('blocking_reasons'));
    }

    public function test_legal_holds_never_delete_data_even_after_release():void
    {
        $f=$this->firm();$id=$this->requestCase($f);
        $this->actingAs($f['people'][0]->user)
            ->post($this->base($f).'/'.$id.'/holds',[
                'reason'=>'Contractual preservation is required pending regulator inspection.',
            ])->assertRedirect();
        $hold=DB::table('tenant_exit_holds')->where('case_id',$id)->firstOrFail();
        $this->get($this->base($f).'/'.$id.'/purge-preflight')
            ->assertJsonPath('open_legal_holds',1)
            ->assertJsonPath('physical_purge_enabled',false);
        $this->post($this->base($f).'/'.$id.'/holds/'.$hold->id.'/release',[
            'reason'=>'The regulator has formally lifted this documented restriction.',
        ])->assertRedirect();
        $this->get($this->base($f).'/'.$id.'/purge-preflight')
            ->assertJsonPath('open_legal_holds',0)
            ->assertJsonPath('physical_purge_enabled',false);
        $this->assertDatabaseHas('leave_requests',['company_id'=>$f['company']]);
    }

    public function test_over_budget_export_stops_closed_and_preserves_originals():void
    {
        $f=$this->firm();
        config()->set('erp_exit.max_plaintext_bytes',200);
        $id=$this->requestCase($f);
        $this->consent($f,$id);
        $this->assertSame(1,Artisan::call('erp:exit:prepare',['--case'=>$id]));
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'blocked']);
        $this->assertDatabaseCount('tenant_exit_artifacts',0);
        $this->assertDatabaseHas('leave_requests',['company_id'=>$f['company']]);
        $this->actingAs($f['people'][0]->user)
            ->get($this->base($f).'/'.$id.'/purge-preflight')
            ->assertJsonPath('physical_purge_enabled',false);
    }

    public function test_students_and_unrelated_company_cannot_read_consent_export_or_purge_preflight():void
    {
        $a=$this->firm();
        $b=$this->firm('other-firm');
        $id=$this->requestCase($a);
        $student=User::findOrFail(DB::table('memberships')
            ->where('company_id',$a['company'])->where('role','student')->value('user_id'));
        $this->actingAs($student)
            ->get($this->base($a))->assertForbidden();
        $this->get($this->base($a).'/'.$id.'/purge-preflight')->assertForbidden();
        $this->actingAs($b['people'][0]->user)
            ->get($this->base($b).'/'.$id.'/purge-preflight')->assertNotFound();
        $this->post($this->base($b).'/'.$id.'/vote',[
            'decision'=>'approve','reason'=>'Trying to export a foreign company with invalid authority.',
        ])->assertNotFound();
        $this->assertDatabaseHas('tenant_exit_cases',['id'=>$id,'state'=>'awaiting_consent']);
    }
}
