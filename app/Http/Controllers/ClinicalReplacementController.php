<?php

namespace App\Http\Controllers;

use App\Services\Clinical\Api\ClinicalRequestContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/** UI/session bridge only. Clinical owns all task/evidence/permission decisions. */
class ClinicalReplacementController extends Controller
{
    private const ASSETS = ['main-workflow-page.mjs', 'workflow-application-gateway.mjs', 'task-view.mjs', 'task-workspace.mjs'];
    private const OPERATIONS = ['tasks.mine', 'tasks.open', 'tasks.execute', 'tasks.lookup', 'tasks.mainProgress'];

    public function __construct(private readonly ClinicalRequestContext $identity) {}

    public function show(Request $request): Response
    {
        $this->requireAccess($request);
        return response()->view('clinical.replacement')->header('Cache-Control', 'no-store');
    }

    public function workflow(Request $request): Response
    {
        $this->requireAccess($request);
        if (! $request->isJson() || strlen($request->getContent()) > 2097152) {
            return $this->failure(400, 'REQUEST_INVALID', true);
        }
        try { $input = json_decode($request->getContent(), true, 64, JSON_THROW_ON_ERROR); }
        catch (Throwable) { return $this->failure(400, 'REQUEST_INVALID', true); }
        if (! is_array($input) || array_diff(array_keys($input), ['operation', 'value'])
            || ! in_array($input['operation'] ?? null, self::OPERATIONS, true) || ! is_array($input['value'] ?? null)) {
            return $this->failure(400, 'REQUEST_INVALID', true);
        }
        try {
            // No automatic retry: a lost response may already have committed. The UI keeps its operation ID.
            $upstream = $this->transport($request)->post($this->origin().'/api/v1/replacement/workflow', $input);
            if (in_array($upstream->status(), [401, 403], true)) {
                return $this->failure(403, 'ACCESS_REVOKED', false);
            }
            if ($upstream->status() !== 200 || strlen($upstream->body()) > 4194304) {
                return $this->failure(503, 'WORKFLOW_REQUEST_UNAVAILABLE', false);
            }
            $body = $upstream->json();
            if (! is_array($body) || ($body['ok'] ?? null) !== true || ! array_key_exists('result', $body)) {
                return $this->failure(503, 'WORKFLOW_REQUEST_UNAVAILABLE', false);
            }
            return response()->json(['ok' => true, 'result' => $body['result']])->header('Cache-Control', 'no-store');
        } catch (Throwable) {
            return $this->failure(503, 'WORKFLOW_REQUEST_UNAVAILABLE', false);
        }
    }

    public function asset(Request $request, string $asset): Response
    {
        $this->requireAccess($request);
        abort_unless(in_array($asset, self::ASSETS, true), 404);
        try {
            $upstream = $this->transport($request)->get($this->origin().'/api/v1/replacement/assets/'.$asset);
            if ($upstream->status() !== 200 || strlen($upstream->body()) > 524288
                || ! str_starts_with(strtolower($upstream->header('Content-Type')), 'text/javascript')) {
                return $this->failure(503, 'ASSET_UNAVAILABLE', true);
            }
            return response($upstream->body(), 200, ['Content-Type' => 'text/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff']);
        } catch (Throwable) { return $this->failure(503, 'ASSET_UNAVAILABLE', true); }
    }

    private function requireAccess(Request $request): void
    {
        abort_unless(config('clinical_replacement.enabled') === true, 404);
        $user = $request->user();
        abort_unless($user && $user->status === 'active' && $user->business_id && $user->branch_id
            && in_array('View Ward Census', (array) ($user->permissions ?? []), true), 403);
    }

    private function transport(Request $request): \Illuminate\Http\Client\PendingRequest
    {
        $key = (string) config('services.clinical.service_key');
        if ($key === '' || preg_match('/[\r\n\x00]/', $key)) { throw new \RuntimeException('Unavailable'); }
        return Http::withHeaders(['Accept' => 'application/json', 'X-Service-Key' => $key,
            'X-Tenant-Id' => $this->identity->tenantId((int) $request->user()->business_id)]
            + $this->identity->identityHeaders($request->user()))
            ->withOptions(['allow_redirects' => false])->connectTimeout(2)->timeout(10)->asJson();
    }

    private function origin(): string
    {
        $origin = (string) config('services.clinical.url'); $u = parse_url($origin);
        if ($u === false || ($u['scheme'] ?? null) !== 'https' || empty($u['host'])
            || isset($u['user']) || isset($u['pass']) || isset($u['query']) || isset($u['fragment'])
            || ! in_array($u['path'] ?? '', ['', '/'], true)) { throw new \RuntimeException('Unavailable'); }
        return rtrim($origin, '/');
    }

    private function failure(int $status, string $code, bool $notCommitted): Response
    {
        return response()->json(['ok' => false, 'code' => $code, 'definitelyNotCommitted' => $notCommitted], $status)
            ->header('Cache-Control', 'no-store');
    }
}
