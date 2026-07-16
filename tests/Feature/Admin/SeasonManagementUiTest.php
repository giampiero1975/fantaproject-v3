<?php

namespace Tests\Feature\Admin;

use Tests\TestCase;

class SeasonManagementUiTest extends TestCase
{
    public function test_season_management_exposes_country_funnel_filter_contract(): void
    {
        $view = file_get_contents(resource_path('views/admin/seasons/index.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('aria-label="Filtra competizioni per nazione"', $view);
        $this->assertStringContainsString('data-season-country-filter', $view);
        $this->assertStringContainsString('data-season-league-select', $view);
        $this->assertStringContainsString('data-season-registry-row', $view);
        $this->assertStringContainsString('data-season-registry-empty', $view);
        $this->assertStringContainsString('data-country-id="{{ $league->country_id }}"', $view);
        $this->assertStringContainsString("filter?.addEventListener('change', applyFilter)", $view);
    }
}
