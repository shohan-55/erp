<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BrandIdentity;
use App\Support\PortableDocx;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandIdentityOfficialDocumentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp():void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-19 14:00:00','Asia/Dhaka'));
        Storage::fake('local');
    }
    protected function tearDown():void
    {
        $this->travelBack();
        parent::tearDown();
    }
    private function fixture(string $slug='brand-firm'):array
    {
        $company=DB::table('companies')->insertGetId([
            'name'=>'Test Legal Firm '.$slug,'slug'=>$slug,'timezone'=>'Asia/Dhaka',
            'office_start'=>'10:00:00','office_end'=>'18:00:00',
            'grace_minutes'=>10,'weekly_holidays'=>'[5,6]',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $workspace=DB::table('workspaces')->insertGetId([
            'company_id'=>$company,'slug'=>'team','name'=>'Audit Team',
            'active'=>true,'created_at'=>now(),'updated_at'=>now(),
        ]);
        $people=[];
        foreach(['partner','manager','student'] as $role){
            $u=User::factory()->create();
            $id=DB::table('memberships')->insertGetId([
                'company_id'=>$company,'workspace_id'=>$workspace,
                'user_id'=>$u->id,'role'=>$role,'active'=>true,
                'manager_membership_id'=>$role==='student'?$people['manager']['id']:null,
                'monthly_allowance'=>0,'created_at'=>now(),'updated_at'=>now(),
            ]);
            $people[$role]=['id'=>$id,'user'=>$u];
        }
        return [$company,$workspace,$people];
    }
    private function validProfile(array $override=[]):array
    {
        return array_replace([
            'public_name'=>'Corporate Demo & Partners',
            'office_address'=>'Audit Tower, Uttara, Dhaka',
            'email'=>'office@example.test',
            'phone'=>'+8800000000',
            'website'=>'https://example.test',
            'accent_color'=>'#22B98D',
            'sidebar_color'=>'#17191F',
            'signatory_name'=>'Authorized Principal',
            'signatory_title'=>'Partner',
        ],$override);
    }
    private function leave(int $company,int $workspace,array $p,string $status='approved'):int
    {
        return DB::table('leave_requests')->insertGetId([
            'company_id'=>$company,'workspace_id'=>$workspace,
            'membership_id'=>$p['student']['id'],
            'leave_type'=>'casual','day_portion'=>'full',
            'date_from'=>'2026-11-02','date_to'=>'2026-11-02','charge_days'=>1,
            'reason'=>'A formal family appointment requiring authorized leave',
            'status'=>$status,
            'reviewed_by'=>$status==='approved'?$p['partner']['id']:null,
            'reviewed_at'=>$status==='approved'?now():null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
    }

    public function test_brand_is_partner_editable_manager_read_only_and_company_legal_name_is_unchanged():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $this->actingAs($p['student']['user'])
            ->get('/c/brand-firm/app/settings/brand-studio')->assertForbidden();
        $this->actingAs($p['manager']['user'])
            ->get('/c/brand-firm/app/settings/brand-studio')->assertOk()
            ->assertSeeText('Read-only');
        $this->post('/c/brand-firm/app/settings/brand-studio',$this->validProfile())
            ->assertForbidden();

        $this->actingAs($p['partner']['user'])
            ->post('/c/brand-firm/app/settings/brand-studio',$this->validProfile())
            ->assertRedirect();
        $this->assertDatabaseHas('workspace_brand_profiles',[
            'company_id'=>$company,'workspace_id'=>$workspace,
            'public_name'=>'Corporate Demo & Partners','accent_color'=>'#22B98D',
        ]);
        $this->assertDatabaseHas('companies',[
            'id'=>$company,'name'=>'Test Legal Firm brand-firm',
        ]);
        $this->get('/c/brand-firm/app')->assertOk()
            ->assertSee('erp-dynamic-brand-palette',false)
            ->assertSee('Corporate Demo & Partners')
            ->assertSee('style',false);
    }

    public function test_brand_blocks_inaccessible_sidebar_color_and_untrusted_website():void
    {
        [,,$p]=$this->fixture();
        $this->actingAs($p['partner']['user'])
            ->post('/c/brand-firm/app/settings/brand-studio',
                $this->validProfile(['sidebar_color'=>'#FEFEFE']))
            ->assertSessionHasErrors('sidebar_color');
        $this->post('/c/brand-firm/app/settings/brand-studio',
            $this->validProfile(['website'=>'javascript:alert(1)']))
            ->assertSessionHasErrors('website');
        $this->assertDatabaseCount('workspace_brand_profiles',0);
    }

    public function test_letterhead_assets_are_private_immutable_and_student_can_only_get_current_logo():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jWeEAAAAASUVORK5CYII=');
        $this->actingAs($p['partner']['user'])
            ->post('/c/brand-firm/app/settings/brand-studio/assets',[
                'kind'=>'logo','file'=>UploadedFile::fake()->createWithContent('logo.png',$png),
            ])->assertRedirect();
        $asset=DB::table('workspace_brand_assets')->first();
        $this->assertNotNull($asset);
        Storage::disk('local')->assertExists($asset->storage_path);
        $this->assertDatabaseHas('workspace_brand_profiles',['workspace_id'=>$workspace,'logo_asset_id'=>$asset->id]);
        $this->actingAs($p['student']['user'])
            ->get('/c/brand-firm/app/settings/brand-studio/assets/'.$asset->id)->assertOk();
        $this->actingAs($p['partner']['user'])
            ->post('/c/brand-firm/app/settings/brand-studio/assets',[
                'kind'=>'letterhead','file'=>UploadedFile::fake()->createWithContent(
                    'letterhead.pdf',"%PDF-1.4\n1 0 obj\n<<>>\nendobj\n%%EOF"
                ),
            ])->assertRedirect();
        $this->assertDatabaseCount('workspace_brand_assets',2);
        $letterhead=DB::table('workspace_brand_assets')->where('kind','letterhead')->first();
        $this->actingAs($p['student']['user'])
            ->get('/c/brand-firm/app/settings/brand-studio/assets/'.$letterhead->id)->assertNotFound();
        $this->actingAs($p['manager']['user'])
            ->get('/c/brand-firm/app/settings/brand-studio/assets/'.$letterhead->id)->assertNotFound();
    }

    public function test_only_approved_leave_can_be_issued_and_student_cannot_download_others():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $pending=$this->leave($company,$workspace,$p,'pending');
        $this->actingAs($p['student']['user'])
            ->get('/c/brand-firm/app/documents/approved-leave/'.$pending.'/print')->assertNotFound();
        $this->assertDatabaseCount('official_document_issues',0);

        $approved=$this->leave($company,$workspace,$p);
        $this->get('/c/brand-firm/app/documents/approved-leave/'.$approved.'/print')
            ->assertOk()->assertSeeText('Approved Leave Application')
            ->assertSee('Print / Save as PDF')
            ->assertSee('LEAVE-'.$company.'-'.$workspace.'-');
        $this->assertDatabaseCount('official_document_issues',1);
        $this->get('/c/brand-firm/app/documents/approved-leave/'.$approved.'/print')
            ->assertOk();
        $this->assertDatabaseCount('official_document_issues',1);
        $word=$this->get('/c/brand-firm/app/documents/approved-leave/'.$approved.'/word')->assertOk();
        $word->assertHeader('Content-Type',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $content=$word->getContent();
        $this->assertStringStartsWith('PK', $content);
        $this->assertStringContainsString('word/document.xml',$content);
        $this->assertDatabaseCount('official_document_issues',1);
        $this->actingAs($p['manager']['user'])
            ->get('/c/brand-firm/app/documents/approved-leave/'.$approved.'/word')->assertOk();
        $this->actingAs($p['student']['user'])
            ->get('/c/brand-firm/app/documents/official-register/export')->assertForbidden();
        $this->actingAs($p['partner']['user'])
            ->get('/c/brand-firm/app/documents/official-register/export')->assertOk();
    }

    public function test_cross_company_and_cross_workspace_isolation_for_official_letters():void
    {
        [$company,$workspace,$p]=$this->fixture();
        $approved=$this->leave($company,$workspace,$p);
        [,,$other]=$this->fixture('second-brand');
        $this->actingAs($other['student']['user'])
            ->get('/c/second-brand/app/documents/approved-leave/'.$approved.'/word')->assertNotFound();
        $this->actingAs($other['partner']['user'])
            ->get('/c/second-brand/app/documents/approved-leave/'.$approved.'/print')->assertNotFound();
        $this->assertDatabaseCount('official_document_issues',0);
    }

    public function test_word_file_is_valid_zip_with_escaped_unicode_document_text():void
    {
        $bytes=PortableDocx::make([
            ['text'=>'বাংলা Company <Confidential> & Co.', 'bold'=>true],
            ['text'=>'Approved "Leave" record'],
        ]);
        $this->assertSame('PK',substr($bytes,0,2));
        $this->assertStringContainsString('word/document.xml',$bytes);
        $this->assertStringContainsString('&lt;Confidential&gt;', $bytes);
        $this->assertStringNotContainsString('<Confidential>',$bytes);
        if(class_exists(\ZipArchive::class)){
            $tmp=tempnam(sys_get_temp_dir(),'docx').'.docx';
            file_put_contents($tmp,$bytes);
            $zip=new \ZipArchive();
            $this->assertSame(true,$zip->open($tmp));
            $xml=$zip->getFromName('word/document.xml');
            $this->assertNotFalse($xml);
            $this->assertStringContainsString('বাংলা',$xml);
            $zip->close();unlink($tmp);
        }
    }
}
