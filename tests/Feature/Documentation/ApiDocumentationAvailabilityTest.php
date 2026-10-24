<?php

namespace Tests\Feature\Documentation;

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;
use Tests\TestCase;

class ApiDocumentationAvailabilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(RestrictedDocsAccess::class);
    }

    public function test_exported_openapi_document_is_available_without_live_database_generation(): void
    {
        $response = $this->get('/docs/api.json');

        $response
            ->assertOk()
            ->assertHeader('content-type', 'application/json');

        $response->assertJsonStructure([
            'openapi',
            'info',
            'paths',
        ]);
    }

    public function test_api_documentation_ui_uses_the_exported_openapi_document(): void
    {
        $response = $this->get('/docs/api');

        $response
            ->assertOk()
            ->assertSee('<elements-api', false)
            ->assertSee('apiDescriptionDocument', false)
            ->assertSee('openapi', false);
    }
}
