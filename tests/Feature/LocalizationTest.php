<?php

namespace Tests\Feature;

use App\Http\Controllers\LocaleController;
use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\SupportedLocale;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', SetLocale::class])
            ->get('/_test/localization', fn () => response()->json([
                'locale' => app()->getLocale(),
                'report_center' => __('reports.center.title'),
            ]));

        Route::middleware('web')
            ->get('/_test/localization/{locale}', LocaleController::class);
    }

    public function test_middleware_applies_indonesian_locale_from_session(): void
    {
        $response = $this
            ->withSession([SupportedLocale::sessionKey() => 'id'])
            ->get('/_test/localization');

        $response->assertOk()->assertExactJson([
            'locale' => 'id',
            'report_center' => 'Pusat Laporan',
        ]);
    }

    public function test_language_switch_is_saved_to_session_and_applied_to_next_request(): void
    {
        $switchResponse = $this
            ->withHeader('referer', url('/_test/localization'))
            ->get('/_test/localization/en');

        $switchResponse
            ->assertRedirect(url('/_test/localization'))
            ->assertSessionHas(SupportedLocale::sessionKey(), 'en')
            ->assertSessionHas('status', 'Language changed successfully.');

        $localizedResponse = $this->get('/_test/localization');

        $localizedResponse->assertOk()->assertExactJson([
            'locale' => 'en',
            'report_center' => 'Report Center',
        ]);
    }

    public function test_authenticated_user_uses_active_company_locale_when_session_has_no_preference(): void
    {
        $this->mock(CompanyContext::class)
            ->shouldReceive('current')
            ->once()
            ->andReturn((object) ['locale' => 'en']);

        $user = (new User)->forceFill([
            'id' => 1,
            'name' => 'Locale Test',
            'email' => 'locale-test@sams.local',
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/_test/localization');

        $response->assertOk()->assertExactJson([
            'locale' => 'en',
            'report_center' => 'Report Center',
        ]);
    }

    public function test_unsupported_language_is_rejected_without_changing_session(): void
    {
        $response = $this
            ->withSession([SupportedLocale::sessionKey() => 'id'])
            ->get('/_test/localization/fr');

        $response
            ->assertNotFound()
            ->assertSessionHas(SupportedLocale::sessionKey(), 'id');
    }

    public function test_invalid_session_locale_falls_back_to_supported_default(): void
    {
        config()->set('localization.default', 'id');

        $response = $this
            ->withSession([SupportedLocale::sessionKey() => 'fr'])
            ->get('/_test/localization');

        $response->assertOk()->assertExactJson([
            'locale' => 'id',
            'report_center' => 'Pusat Laporan',
        ]);
        $response->assertSessionMissing(SupportedLocale::sessionKey());
    }

    public function test_indonesian_and_english_catalogues_have_matching_keys(): void
    {
        foreach (['common', 'navigation', 'settings', 'reports'] as $catalogue) {
            $englishKeys = array_keys(Arr::dot(require lang_path("en/{$catalogue}.php")));
            $indonesianKeys = array_keys(Arr::dot(require lang_path("id/{$catalogue}.php")));

            sort($englishKeys);
            sort($indonesianKeys);

            $this->assertSame($englishKeys, $indonesianKeys, "Translation keys differ in {$catalogue}.");
        }

        $this->assertSame(['id', 'en'], SupportedLocale::all());
    }
}
