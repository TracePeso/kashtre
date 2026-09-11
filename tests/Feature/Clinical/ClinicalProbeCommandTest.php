<?php

namespace Tests\Feature\Clinical;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The probe's value is entirely in its classification: telling a route that is
 * *absent* from one that is merely *gated*, or from one whose sample id simply
 * matched nothing. Get that wrong and the gap report is noise.
 */
class ClinicalProbeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clinical.url' => 'https://clinical.kashtre.test',
            'services.clinical.service_key' => 'test-service-key',
            'services.clinical.default_tenant' => 'FACILITY_ALPHA',
            'services.clinical.retry_times' => 0,
        ]);

        Cache::flush();
    }

    public function test_it_refuses_to_run_when_the_module_is_not_configured(): void
    {
        config(['services.clinical.url' => null]);

        $this->artisan('clinical:probe')
            ->expectsOutputToContain('CLINICAL_MODULE_URL is empty')
            ->assertExitCode(1);
    }

    public function test_it_stops_early_when_the_service_is_unhealthy(): void
    {
        Http::fake([
            'clinical.kashtre.test/api/v1/health' => Http::response([
                'data' => ['status' => 'degraded', 'checks' => ['database' => false]],
            ], 503),
        ]);

        // Every endpoint would report MISSING against a dead service, which
        // would be a lie — the routes may exist perfectly well behind it.
        $this->artisan('clinical:probe')
            ->expectsOutputToContain('would be misleading')
            ->assertExitCode(1);
    }

    public function test_a_healthy_service_answering_everything_reports_no_gaps(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/health')
                ? Http::response(['data' => ['status' => 'ok', 'checks' => []]])
                : Http::response(['data' => []]);
        });

        $this->artisan('clinical:probe', ['--group' => 'Settings'])
            ->assertExitCode(0);
    }

    public function test_a_missing_route_is_reported_as_a_gap_and_fails_the_command(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/health')) {
                return Http::response(['data' => ['status' => 'ok', 'checks' => []]]);
            }

            if (str_contains($request->url(), 'settings/cde-groups')) {
                return Http::response(['message' => 'Not Found'], 404);
            }

            return Http::response(['data' => []]);
        });

        // 404 on a path with no substituted id means the route is genuinely
        // absent — a contract gap, and a non-zero exit so CI notices.
        $this->artisan('clinical:probe', ['--group' => 'Settings'])
            ->expectsOutputToContain('Contract gaps')
            ->expectsOutputToContain('settings/cde-groups')
            ->assertExitCode(1);
    }

    public function test_a_gated_endpoint_is_not_reported_as_a_gap(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/health')) {
                return Http::response(['data' => ['status' => 'ok', 'checks' => []]]);
            }

            return Http::response([
                'message' => 'No care relationship.',
                'errors' => ['error_code' => 'REBAC_ACCESS_DENIED'],
            ], 403);
        });

        // A 403 proves the route exists and its gate is working. That is a
        // pass, not a gap.
        $this->artisan('clinical:probe', ['--group' => 'Settings'])
            ->assertExitCode(0);
    }

    public function test_an_unconfigured_dependency_is_flagged_separately_from_a_gap(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/health')) {
                return Http::response(['data' => ['status' => 'ok', 'checks' => []]]);
            }

            return Http::response([
                'message' => 'The AI gateway is not configured.',
                'errors' => ['error_code' => 'AI_GATEWAY_UNAVAILABLE'],
            ], 503);
        });

        // §14 items answer 503. The endpoint exists; something it needs was
        // never configured. Worth surfacing, but not a contract gap.
        $this->artisan('clinical:probe', ['--group' => 'Settings'])
            ->expectsOutputToContain('Blocked on an unconfigured dependency')
            ->assertExitCode(0);
    }

    public function test_it_never_calls_a_write_endpoint(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/health')
                ? Http::response(['data' => ['status' => 'ok', 'checks' => []]])
                : Http::response(['data' => []]);
        });

        $this->artisan('clinical:probe')->assertExitCode(0);

        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            // Probing a live hospital API by administering a MAR dose is not a
            // test. Nothing but GET may ever leave this command.
            $this->assertSame(
                'GET',
                strtoupper($request->method()),
                'clinical:probe issued a non-GET request to '.$request->url(),
            );
        }
    }

    public function test_patient_scoped_endpoints_are_skipped_without_a_sample_id(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/health')
                ? Http::response(['data' => ['status' => 'ok', 'checks' => []]])
                : Http::response(['data' => []]);
        });

        $this->artisan('clinical:probe', ['--group' => 'Patient chart', '--show-skipped' => true])
            ->expectsOutputToContain('clinical/patients/{patientId}/observations')
            ->assertExitCode(0);

        // Nothing should have been called with a literal brace — inventing an
        // id would test the 404 handler rather than the endpoint.
        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            $this->assertStringNotContainsString('%7B', $request->url());
            $this->assertStringNotContainsString('{', $request->url());
        }
    }

    public function test_supplying_a_patient_id_unlocks_the_patient_scoped_endpoints(): void
    {
        Http::fake(function (Request $request) {
            return str_contains($request->url(), '/health')
                ? Http::response(['data' => ['status' => 'ok', 'checks' => []]])
                : Http::response(['data' => []]);
        });

        $this->artisan('clinical:probe', [
            '--group' => 'Patient chart',
            '--patient' => 'CL-00001234',
        ])->assertExitCode(0);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'CL-00001234'));
    }

    public function test_the_call_command_lists_documented_endpoints(): void
    {
        $this->artisan('clinical:call', ['--list' => 'maternity'])
            ->expectsOutputToContain('clinical/maternity/birth-events')
            ->assertExitCode(0);
    }

    public function test_the_call_command_refuses_a_write_without_confirmation(): void
    {
        Http::fake();

        $this->artisan('clinical:call', [
            'method' => 'POST',
            'path' => 'clinical/observations',
        ])
            ->expectsOutputToContain('--confirm')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }
}
