<?php

namespace Tests\Feature\Clinical;

use App\Services\Clinical\Api\ClinicalApi;
use App\Support\Clinical\ClinicalEndpointCatalog;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;
use Tests\TestCase;

/**
 * Proves the typed resource layer actually reaches every endpoint the catalog
 * documents.
 *
 * The method is blunt on purpose: reflect over every public method on every
 * resource, invoke it against a faked HTTP client with sentinel arguments,
 * record which paths were hit, and compare that set against the catalog.
 *
 * A hand-maintained checklist of 194 routes would drift within a week. This
 * cannot — if someone adds a catalog entry without a resource method, or
 * fat-fingers a path in a resource, the comparison fails and names the route.
 */
class ClinicalApiCoverageTest extends TestCase
{
    /** Distinctive so they survive into the URL and can be normalised back out. */
    private const SENTINEL_STRING = 'ZZSTRZZ';

    private const SENTINEL_ID = 'ZZIDZZ';

    /**
     * Methods that take the resource name as an argument, so a sentinel
     * produces a wildcard path rather than a documented one.
     *
     * `InteropResource::fhirSearch()` is the only case: it is the generic form
     * of the eight documented FHIR searches, each of which also has a named
     * wrapper that the coverage check does exercise concretely.
     *
     * @var array<int, string>
     */
    private const GENERIC_ACCESSORS = ['GET fhir/{}'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.clinical.url' => 'https://clinical.kashtre.test',
            'services.clinical.service_key' => 'test-service-key',
            'services.clinical.default_tenant' => 'FACILITY_ALPHA',
            'services.clinical.retry_times' => 0,
            // WebhooksResource refuses to send unsigned, so the HMAC secrets
            // have to be present for those methods to issue a request at all.
            'services.module_endpoints.lims.secret' => 'lims-secret',
            'services.module_endpoints.ris.secret' => 'ris-secret',
        ]);

        Cache::flush();
    }

    public function test_the_catalog_matches_the_documented_endpoint_count(): void
    {
        // The guide states 194 endpoints. If this drifts, either the guide was
        // revised or the catalog gained a typo — both are worth noticing.
        $this->assertSame(194, ClinicalEndpointCatalog::count());
    }

    public function test_the_catalog_has_no_duplicate_routes(): void
    {
        $keys = array_map(
            fn (array $e) => $e['method'].' '.$e['path'],
            ClinicalEndpointCatalog::all(),
        );

        $duplicates = array_keys(array_filter(array_count_values($keys), fn (int $n) => $n > 1));

        $this->assertSame([], $duplicates, 'Duplicate catalog entries: '.implode(', ', $duplicates));
    }

    public function test_every_catalog_entry_is_well_formed(): void
    {
        $validMethods = ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'];
        $validAuth = ['-', 'P', 'Z', 'S', 'H'];

        foreach (ClinicalEndpointCatalog::all() as $entry) {
            $this->assertContains($entry['method'], $validMethods, "Bad method on {$entry['path']}");
            $this->assertContains($entry['auth'], $validAuth, "Bad auth class on {$entry['path']}");
            $this->assertStringStartsNotWith('/', $entry['path'], 'Catalog paths are relative to /api/v1/');
            $this->assertNotEmpty($entry['group']);
        }
    }

    public function test_only_read_only_endpoints_are_marked_safe(): void
    {
        foreach (ClinicalEndpointCatalog::safe() as $entry) {
            // clinical:probe calls everything marked safe. A write slipping
            // into this set would mean probing a live hospital API mutates it.
            $this->assertSame(
                'GET',
                $entry['method'],
                "{$entry['method']} {$entry['path']} is marked safe but is not a GET.",
            );
        }
    }

    public function test_every_documented_endpoint_is_reachable_from_a_resource(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        $called = $this->invokeEveryResourceMethod();

        $documented = array_map(
            fn (array $e) => $e['method'].' '.$this->normalise($e['path']),
            ClinicalEndpointCatalog::all(),
        );

        $missing = array_values(array_unique(array_diff($documented, $called)));

        $this->assertSame(
            [],
            $missing,
            "Documented endpoints with no resource method:\n  ".implode("\n  ", $missing),
        );
    }

    public function test_no_resource_method_calls_an_undocumented_endpoint(): void
    {
        Http::fake(['clinical.kashtre.test/*' => Http::response(['data' => []])]);

        $called = $this->invokeEveryResourceMethod();

        $documented = array_map(
            fn (array $e) => $e['method'].' '.$this->normalise($e['path']),
            ClinicalEndpointCatalog::all(),
        );

        // A resource hitting a path the guide does not document is either a
        // typo or an endpoint someone invented. Both are worth catching before
        // they reach a running service.
        $undocumented = array_values(array_diff($called, $documented, self::GENERIC_ACCESSORS));

        $this->assertSame(
            [],
            $undocumented,
            "Resource methods calling undocumented endpoints:\n  ".implode("\n  ", $undocumented),
        );
    }

    /**
     * Invokes every public method on every resource and returns the normalised
     * "METHOD path" set that was actually requested.
     *
     * @return array<int, string>
     */
    private function invokeEveryResourceMethod(): array
    {
        $api = app(ClinicalApi::class);

        // Lives on the facade rather than a resource — it is the one endpoint
        // that is neither authenticated nor domain-scoped.
        $api->health();

        $resources = [
            $api->chart(), $api->orders(), $api->mar(), $api->wards(),
            $api->transitions(), $api->diagnostics(), $api->ai(),
            $api->interop(), $api->maternity(), $api->security(),
            $api->settings(), $api->webhooks(),
        ];

        foreach ($resources as $resource) {
            foreach ($this->publicMethodsOf($resource) as $method) {
                try {
                    $method->invokeArgs($resource, $this->argumentsFor($method));
                } catch (\Throwable $e) {
                    $this->fail(sprintf(
                        '%s::%s() threw during coverage invocation: %s',
                        $resource::class,
                        $method->getName(),
                        $e->getMessage(),
                    ));
                }
            }
        }

        $called = [];

        foreach (Http::recorded() as [$request]) {
            /** @var Request $request */
            $called[] = strtoupper($request->method()).' '.$this->normalise($this->pathOf($request));
        }

        return array_values(array_unique($called));
    }

    /**
     * @return array<int, ReflectionMethod>
     */
    private function publicMethodsOf(object $resource): array
    {
        return array_values(array_filter(
            (new ReflectionClass($resource))->getMethods(ReflectionMethod::IS_PUBLIC),
            fn (ReflectionMethod $m) => ! $m->isStatic()
                && ! $m->isConstructor()
                // Declared on the resource itself, not inherited plumbing.
                && $m->getDeclaringClass()->getName() === $resource::class,
        ));
    }

    /**
     * Builds sentinel arguments from parameter types.
     *
     * @return array<int, mixed>
     */
    private function argumentsFor(ReflectionMethod $method): array
    {
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                // Stop at the first optional parameter: defaults are what a
                // real caller would use, and they exercise the same path.
                break;
            }

            $arguments[] = $this->sentinelFor($parameter->getType(), $parameter->getName());
        }

        return $arguments;
    }

    private function sentinelFor(?object $type, string $name): mixed
    {
        $names = match (true) {
            $type instanceof ReflectionUnionType => array_map(
                fn ($t) => $t instanceof ReflectionNamedType ? $t->getName() : '',
                $type->getTypes(),
            ),
            $type instanceof ReflectionNamedType => [$type->getName()],
            default => ['string'],
        };

        // int|string identifiers keep their own sentinel so a path segment can
        // be told apart from a free-text argument during normalisation.
        if (in_array('int', $names, true) && in_array('string', $names, true)) {
            return self::SENTINEL_ID;
        }

        return match (true) {
            in_array('array', $names, true) => [],
            in_array('float', $names, true) => 1.0,
            in_array('int', $names, true) => 1,
            in_array('bool', $names, true) => false,
            default => self::SENTINEL_STRING,
        };
    }

    private function pathOf(Request $request): string
    {
        $path = parse_url($request->url(), PHP_URL_PATH) ?: '';

        return ltrim(str_replace('/api/v1/', '', $path), '/');
    }

    /**
     * Collapses every identifier — catalog placeholders and sentinels alike —
     * to a single token, so `clinical/beds/{bed}` and `clinical/beds/ZZIDZZ`
     * compare equal.
     */
    private function normalise(string $path): string
    {
        $path = rawurldecode($path);
        $path = preg_replace('/\{\w+\}/', '{}', $path);
        $path = str_replace([self::SENTINEL_ID, self::SENTINEL_STRING], '{}', $path);

        return trim((string) $path, '/');
    }
}
