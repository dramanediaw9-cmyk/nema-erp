<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_public_landing_page_is_available(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Nema');
    }
}
