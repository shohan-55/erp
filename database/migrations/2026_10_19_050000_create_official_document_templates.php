<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up():void
    {
        Schema::create('workspace_document_templates',function(Blueprint $t){
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $t->string('template_key',45);
            $t->unsignedSmallInteger('version');
            $t->string('document_title',180);
            $t->text('body_text');
            $t->string('footer_note',500)->nullable();
            $t->foreignId('approved_by')->constrained('memberships')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id','workspace_id','template_key','version'],'workspace_template_version_unique');
        });
    }
    public function down():void
    {
        Schema::dropIfExists('workspace_document_templates');
    }
};
