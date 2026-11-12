<?php

namespace Tests\Feature;

use Tests\TestCase;

class HomepageTest extends TestCase
{
    public function test_homepage_is_available(): void
    {
        $response = $this->get('/');
        $response->assertOk();
        $response->assertSee('Find a place');
    }
}
