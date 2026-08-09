<?php

namespace App\Console\Commands;

use App\Services\Clinical\Api\ClinicalApiClient;
use App\Services\Clinical\Api\Exceptions\ClinicalApiException;
use App\Support\Clinical\ClinicalEndpointCatalog;
use Illuminate\Console\Command;

/**
 * Calls a single Clinical Module endpoint ad hoc, for exploring the API by
 * hand while integrating.
 *
 *   php artisan clinical:call GET clinical/security/context
 *   php artisan clinical:call GET clinical/patients/CL-00001234/observations --data='{"limit":5}'
 *   php artisan clinical:call POST clinical/orders/translate --data='{"requested_term":"ceftriaxone"}'
 *   php artisan clinical:call --list=orders
 *
 * Writes require --confirm, because the difference between exploring an API
 * and administering a medication is one typed word and should stay that way.
 */
class ClinicalCallCommand extends Command
{
    protected $signature = 'clinical:call
        {method? : GET, POST, PATCH, PUT or DELETE}
        {path? : Path relative to /api/v1/, e.g. clinical/security/context}
        {--data= : JSON body (or query string for GET)}
        {--idempotency-key= : Sent as X-Idempotency-Key}
        {--no-identity : Send as module traffic, with no clinician identity}
        {--confirm : Required for any non-GET request}
        {--list= : Instead of calling, list catalog endpoints matching this term}
        {--raw : Print the whole response body rather than just data}';

    protected $description = 'Call one Clinical Module endpoint, or list the documented surface';

    public function handle(ClinicalApiClient $client): int
    {
        if ($term = $this->option('list')) {
            return $this->list($term);
        }

        $method = strtoupper((string) $this->argument('method'));
        $path = ltrim((string) $this->argument('path'), '/');

        if ($method === '' || $path === '') {
            $this->error('Both a method and a path are required (or use --list=<term>).');

            return self::FAILURE;
        }

        if (! $client->isConfigured()) {
            $this->error('CLINICAL_MODULE_URL is empty.');

            return self::FAILURE;
        }

        if ($method !== 'GET' && ! $this->option('confirm')) {
            // Deliberately not an interactive prompt: this command gets run in
            // scripts, and a prompt that can be answered by a stray newline is
            // not a safeguard.
            $this->error("{$method} would modify clinical data. Re-run with --confirm if that is what you intend.");

            return self::FAILURE;
        }

        $data = $this->parseData();

        if ($data === null) {
            return self::FAILURE;
        }

        $options = array_filter([
            'idempotency_key' => $this->option('idempotency-key'),
            'with_identity' => $this->option('no-identity') ? false : null,
        ], fn ($value) => $value !== null);

        try {
            $response = match ($method) {
                'GET' => str_starts_with($path, 'fhir/') || $this->option('raw')
                    ? $client->getRaw($path, $data, $options)
                    : $client->get($path, $data, $options),
                'POST' => $client->post($path, $data, $options),
                'PATCH' => $client->patch($path, $data, $options),
                'PUT' => $client->put($path, $data, $options),
                'DELETE' => $client->delete($path, $data, $options),
                default => throw new \InvalidArgumentException("Unsupported method [{$method}]."),
            };
        } catch (ClinicalApiException $e) {
            $this->error("HTTP {$e->status()}".($e->errorCode() ? " {$e->errorCode()}" : ''));
            $this->line($e->getMessage());

            if ($e->errors() !== []) {
                $this->line(json_encode($e->errors(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            }

            // Always surface this: it is on every Clinical log line and is the
            // first thing their support will ask for.
            $this->line("request_id: {$e->requestId()}");

            return self::FAILURE;
        }

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseData(): ?array
    {
        $raw = $this->option('data');

        if (! $raw) {
            return [];
        }

        $decoded = json_decode((string) $raw, true);

        if (! is_array($decoded)) {
            $this->error('--data must be a JSON object.');

            return null;
        }

        return $decoded;
    }

    private function list(string $term): int
    {
        $term = strtolower($term);
        $all = ClinicalEndpointCatalog::all();

        // Path matches win. Falling straight through to group matching meant
        // `--list=maternity` also returned every scratchpad and recall route,
        // because they share the group "AI, interop, maternity, recall" —
        // technically correct and completely unhelpful.
        $matches = array_values(array_filter(
            $all,
            fn (array $e) => str_contains(strtolower($e['path']), $term),
        ));

        if ($matches === []) {
            $matches = array_values(array_filter(
                $all,
                fn (array $e) => str_contains(strtolower($e['group']), $term),
            ));
        }

        if ($matches === []) {
            $this->warn("No documented endpoint matches [{$term}].");
            $this->line('Groups: '.implode(' · ', ClinicalEndpointCatalog::groups()));

            return self::SUCCESS;
        }

        $this->table(
            ['Method', 'Path', 'Auth', 'Safe', 'Note'],
            array_map(fn (array $e) => [
                $e['method'],
                $e['path'],
                $e['auth'],
                $e['safe'] ? 'read' : 'write',
                $e['note'] ?? '',
            ], $matches),
        );

        $this->line(count($matches).' of '.ClinicalEndpointCatalog::count().' documented endpoints.');

        return self::SUCCESS;
    }
}
