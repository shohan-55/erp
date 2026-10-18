<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Blade;

class Phase05BladeCompiledDebugTest extends TestCase
{
    public function test_emit_compiled_calendar_template_for_diagnosis(): void
    {
        $source = file_get_contents(resource_path('views/erp/policies.blade.php'));
        $compiled = Blade::compileString($source);
        $lines = explode("\n", $compiled);
        for ($i = 75; $i < min(112, count($lines)); $i++) {
            fwrite(STDERR, "COMPILED_CALENDAR_LINE_".($i + 1).": ".$lines[$i]."\n");
        }
        $this->assertTrue(true);
    }
}
