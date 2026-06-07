<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompactWorkHeaderTest extends TestCase
{
    public function test_work_header_styles_prioritize_operational_space(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertIsString($css);
        $this->assertStringContainsString('Compact work header', $css);
        $this->assertStringContainsString('margin-bottom: 10px;', $css);
        $this->assertStringContainsString('padding: 9px 12px;', $css);
        $this->assertStringContainsString('.topbar-leading > .workspace', $css);
        $this->assertStringContainsString('.topbar .identity-card span', $css);
    }
}
