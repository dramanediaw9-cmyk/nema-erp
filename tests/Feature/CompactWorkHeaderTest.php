<?php

namespace Tests\Feature;

use Tests\TestCase;

class CompactWorkHeaderTest extends TestCase
{
    public function test_approval_portal_uses_the_native_compact_work_layout(): void
    {
        $approvalView = file_get_contents(resource_path('views/approvals/index.blade.php'));

        $this->assertIsString($approvalView);
        $this->assertStringContainsString("@section('layout-mode', 'compact')", $approvalView);
        $this->assertLessThan(
            strpos($approvalView, "@section('content')"),
            strpos($approvalView, "@section('layout-mode', 'compact')")
        );
        $this->assertStringContainsString('class="approval-workbar"', $approvalView);
        $this->assertStringNotContainsString('class="grid stats-grid"', $approvalView);
    }
}
