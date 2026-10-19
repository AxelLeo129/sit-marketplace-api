<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiDocsTest extends TestCase
{
    public function test_docs_are_available_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->get('/docs/api')->assertOk();
        $this->get('/docs/api.json')->assertOk()->assertJsonPath('info.title', 'SIT Marketplace API');
    }

    public function test_docs_are_hidden_outside_local(): void
    {
        $this->app['env'] = 'production';

        $this->get('/docs/api')->assertForbidden();
        $this->get('/docs/api.json')->assertForbidden();
    }
}
