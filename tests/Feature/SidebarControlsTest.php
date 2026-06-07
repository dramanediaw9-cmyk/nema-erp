<?php

namespace Tests\Feature;

use Tests\TestCase;

class SidebarControlsTest extends TestCase
{
    public function test_main_javascript_initializes_desktop_and_mobile_sidebar_controls(): void
    {
        $javascript = file_get_contents(resource_path('js/app.js'));

        $this->assertIsString($javascript);
        $this->assertStringContainsString('[data-sidebar-toggle]', $javascript);
        $this->assertStringContainsString('[data-sidebar-collapse-toggle]', $javascript);
        $this->assertStringContainsString('[data-focus-toggle]', $javascript);
        $this->assertStringContainsString('cycleDesktopSidebar', $javascript);
        $this->assertStringContainsString('nema.erp.sidebar.state', $javascript);
    }
}
