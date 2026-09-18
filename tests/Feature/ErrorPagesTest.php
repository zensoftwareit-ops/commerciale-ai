<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    public function test_all_custom_error_pages_render_without_application_or_tenant_context(): void
    {
        foreach (['401', '403', '404', '419', '422', '429', '500', '503', '4xx', '5xx'] as $code) {
            $html = view('errors.'.$code)->render();

            $this->assertStringContainsString('Daria', $html, 'Pagina errori '.$code);
            $this->assertStringContainsString('Errore '.$code, $html, 'Pagina errori '.$code);
            $this->assertStringContainsString('Torna a Daria', $html, 'Pagina errori '.$code);
        }
    }
}
