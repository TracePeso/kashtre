<?php

namespace App\Console\Commands;

use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalEndpointCatalog;
use Illuminate\Console\Command;
use Throwable;

/**
 * Walks the documented Clinical Module API surface and reports what the
 * deployed service actually honours.
 *
 * The question this answers is "how much of the contract is real?" — which
 * routes exist, which are missing, which exist but are blocked on a dependency
 * that was never configured. That is a different question from "do my
 * credentials work", and the classification below is built to keep the two
 * apart.
 *
 * **Only read-only endpoints are called.** The catalog marks each entry `safe`,
 * and this never invokes a write. Probing a hospital's live API by
 * administering a MAR dose is not a test, it is an incident.
 */
class ClinicalProbeCommand extends Command
{
    protected $signature = 'clinical:probe
        {--group= : Only probe one §16 group (partial match, case-insensitive)}
        {--patient= : A real global_client_id, to unlock patient-scoped endpoints}
        {--ward= : A real ward code, to unlock ward endpoints}
        {--cde=PULSE_RATE : A CDE code for the registry lookups}
        {--score=NEWS2 : A score code for the scoring-model lookup}
        {--template= : A CDE template code}
        {--show-skipped : List endpoints skipped for want of a sample id}
        {--json= : Also write the full result set to this path}';

    protected $description = 'Probe every documented Clinical Module endpoint and report coverage gaps';

    /**
     * What a response tells us about the endpoint, as opposed to about our
     * request. The distinction is the whole point of the command.
     */
    private const CLASSIFICATIONS = [
        'OK' => 'Endpoint is live and answered',
        'GATED' => 'Endpoint exists; an access gate refused (expected for P/Z routes)',
        'NO_RECORD' => 'Endpoint exists; the sample id matched nothing',
        'MISSING' => 'Route does not exist on the deployed service',
        'NOT_IMPLEMENTED' => 'Route exists but is explicitly unimplemented',
        'DEPENDENCY' => 'Endpoint exists; a dependency it needs is unconfigured',
        'AUTH' => 'Credentials rejected — check CLINICAL_SERVICE_KEY',
        'ERROR' => 'Server error',
        'SKIPPED' => 'Needs a sample id that was not supplied',
    ];

    public function handle(ClinicalApiClient $client): int
    {
        if (! $client->isConfigured()) {
            $this->error('CLINICAL_MODULE_URL is empty — nothing to probe.');
            $this->line('Set it in .env, along with CLINICAL_SERVICE_KEY.');

            return self::FAILURE;
        }

        $this->line('Probing '.config('services.clinical.url'));

        $health = $client->health();
        $this->line('Health: '.($health['ok'] ? '<fg=green>ok</>' : "<fg=red>{$health['status']}</>"));

        if (! $health['ok']) {
            // Everything downstream would report MISSING, which would be a lie
            // — the routes may well exist behind an unreachable service.
            $this->warn('Service is not healthy; endpoint results below would be misleading. Stopping.');

            return self::FAILURE;
        }

        $this->newLine();

        $endpoints = $this->endpointsToProbe();
        $results = [];
        $bar = $this->output->createProgressBar(count($endpoints));
        $bar->start();

        foreach ($endpoints as $endpoint) {
            $results[] = $this->probe($client, $endpoint);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->report($results);

        if ($path = $this->option('json')) {
            file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->line("Full results written to {$path}");
        }

        // A missing or unimplemented route is a genuine contract gap and should
        // fail CI. A gated or record-less response is normal.
        $gaps = array_filter($results, fn (array $r) => in_array($r['status'], ['MISSING', 'NOT_IMPLEMENTED'], true));

        return $gaps === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function endpointsToProbe(): array
    {
        $endpoints = ClinicalEndpointCatalog::safe();

        if ($group = $this->option('group')) {
            $endpoints = array_values(array_filter(
                $endpoints,
                fn (array $e) => str_contains(strtolower($e['group']), strtolower($group)),
            ));
        }

        return $endpoints;
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return array<string, mixed>
     */
    private function probe(ClinicalApiClient $client, array $endpoint): array
    {
        $path = $this->substitute($endpoint['path']);

        if ($path === null) {
            return $this->result($endpoint, 'SKIPPED', null, 'no sample id supplied');
        }

        $hasSampleId = $path !== $endpoint['path'];

        try {
            $started = microtime(true);

            // FHIR answers a different content type and does not use the
            // standard envelope, so it goes through the raw path.
            str_starts_with($path, 'fhir/')
                ? $client->getRaw($path)
                : $client->get($path);

            return $this->result($endpoint, 'OK', 200, null, (int) ((microtime(true) - $started) * 1000));
        } catch (ClinicalApiException $e) {
            return $this->result(
                $endpoint,
                $this->classify($e, $hasSampleId),
                $e->status(),
                $e->errorCode() ?? $e->getMessage(),
            );
        } catch (Throwable $e) {
            return $this->result($endpoint, 'ERROR', null, $e->getMessage());
        }
    }

    /**
     * Turns a status code into a statement about the *endpoint*.
     *
     * The subtle one is 404. On a path with no substituted id it means the
     * route is absent — a real gap. On a path where we supplied a sample id it
     * almost certainly means that id matched nothing, which says nothing about
     * whether the endpoint exists. Conflating the two produces a gap report
     * full of false alarms.
     */
    private function classify(ClinicalApiException $e, bool $hasSampleId): string
    {
        return match (true) {
            $e->status() === 401 => 'AUTH',
            $e->status() === 403, $e->status() === 428 => 'GATED',
            $e->status() === 404 => $hasSampleId ? 'NO_RECORD' : 'MISSING',
            $e->status() === 405 => 'MISSING',
            $e->status() === 501 => 'NOT_IMPLEMENTED',
            $e->status() === 503 => 'DEPENDENCY',
            $e->status() === 422, $e->status() === 400 => 'OK',
            $e->status() >= 500 => 'ERROR',
            default => 'OK',
        };
    }

    /**
     * @param  array<string, mixed>  $endpoint
     * @return array<string, mixed>
     */
    private function result(array $endpoint, string $status, ?int $httpStatus, ?string $detail = null, ?int $ms = null): array
    {
        return [
            'method' => $endpoint['method'],
            'path' => $endpoint['path'],
            'group' => $endpoint['group'],
            'auth' => $endpoint['auth'],
            'status' => $status,
            'http_status' => $httpStatus,
            'detail' => $detail,
            'ms' => $ms,
            'note' => $endpoint['note'] ?? null,
        ];
    }

    /**
     * Fills {placeholders} from the command's options. Returns null when any
     * placeholder is unfillable — guessing an id would test the 404 handler
     * rather than the endpoint.
     */
    private function substitute(string $path): ?string
    {
        $samples = array_filter([
            'patientId' => $this->option('patient'),
            'wardCode' => $this->option('ward'),
            'cdeCode' => $this->option('cde'),
            'scoreCode' => $this->option('score'),
            'templateCode' => $this->option('template'),
        ]);

        if (! preg_match_all('/\{(\w+)\}/', $path, $matches)) {
            return $path;
        }

        foreach ($matches[1] as $placeholder) {
            if (! isset($samples[$placeholder])) {
                return null;
            }

            $path = str_replace('{'.$placeholder.'}', (string) $samples[$placeholder], $path);
        }

        return $path;
    }

    /**
     * @param  array<int, array<string, mixed>>  $results
     */
    private function report(array $results): void
    {
        $counts = array_count_values(array_column($results, 'status'));

        $this->info('Summary');

        foreach (self::CLASSIFICATIONS as $status => $meaning) {
            if (! isset($counts[$status])) {
                continue;
            }

            $this->line(sprintf(
                '  %s %-16s %-4d %s',
                $this->badge($status),
                $status,
                $counts[$status],
                $meaning,
            ));
        }

        $this->newLine();

        // The gaps are the reason the command exists, so they are printed in
        // full rather than summarised.
        $gaps = array_filter($results, fn (array $r) => in_array($r['status'], ['MISSING', 'NOT_IMPLEMENTED', 'ERROR', 'AUTH'], true));

        if ($gaps !== []) {
            $this->error('Contract gaps');
            $this->table(
                ['Status', 'Method', 'Path', 'HTTP', 'Detail'],
                array_map(fn (array $r) => [
                    $r['status'], $r['method'], $r['path'], $r['http_status'] ?? '-', $r['detail'] ?? '',
                ], $gaps),
            );
        }

        $dependencies = array_filter($results, fn (array $r) => $r['status'] === 'DEPENDENCY');

        if ($dependencies !== []) {
            $this->warn('Blocked on an unconfigured dependency (see guide §14)');
            $this->table(
                ['Method', 'Path', 'Detail'],
                array_map(fn (array $r) => [$r['method'], $r['path'], $r['detail'] ?? ''], $dependencies),
            );
        }

        if ($this->option('show-skipped')) {
            $skipped = array_filter($results, fn (array $r) => $r['status'] === 'SKIPPED');

            if ($skipped !== []) {
                $this->line('Skipped for want of a sample id — rerun with --patient / --ward to cover these:');

                foreach ($skipped as $r) {
                    $this->line("  {$r['method']} {$r['path']}");
                }
            }
        }

        $probed = count($results);
        $total = ClinicalEndpointCatalog::count();
        $this->newLine();
        $this->line(sprintf(
            'Probed %d of %d documented endpoints (%d are writes and are never called).',
            $probed,
            $total,
            $total - count(ClinicalEndpointCatalog::safe()),
        ));
    }

    private function badge(string $status): string
    {
        return match ($status) {
            'OK' => '<fg=green>●</>',
            'GATED', 'NO_RECORD', 'SKIPPED' => '<fg=blue>●</>',
            'DEPENDENCY' => '<fg=yellow>●</>',
            default => '<fg=red>●</>',
        };
    }
}
