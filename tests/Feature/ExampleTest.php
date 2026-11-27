<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_public_landing_page_and_login_load(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Uptoware Audit')
            ->assertSee('Try Live Demo');

        $this->get(route('login'))->assertOk();
    }
}
