<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\ClinicalInboundEvent;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\Item;
use App\Models\KashtreClinicalModuleSetting;
use App\Models\ServiceDeliveryQueue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ClinicalModuleIntegrationService
{
    public function resolveBusinessId(?string $tenantId): ?int
    {
        if ($tenantId === null || trim($tenantId) === '') {
            return null;
        }

        $tenantId = trim($tenantId);

        if (ctype_digit($tenantId)) {
            return (int) $tenantId;
        }

        $business = Business::query()
            ->where('uuid', $tenantId)
            ->orWhere('entity_code', $tenantId)
            ->orWhere('account_number', $tenantId)
            ->first();

        return $business?->id;
    }

    public function tenantIdFromRequest(Request $request): ?string
    {
        return $request->header('X-Tenant-Id')
            ?: $request->query('tenant_id')
            ?: $request->input('tenant_id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchCatalogue(string $query, ?int $businessId, int $limit = 50): array
    {
        $items = Item::query()
            ->when($businessId, fn ($q) => $q->where('business_id', $businessId))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('generic_name', 'like', "%{$query}%")
                    ->orWhere('code', 'like', "%{$query}%")
                    ->orWhere('other_names', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return $items->map(fn (Item $item) => $this->catalogueItemPayload($item))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogueItemPayload(Item $item): array
    {
        $alternatives = [];
        if (filled($item->generic_name)) {
            $alternatives[] = $item->generic_name;
        }
        if (filled($item->other_names)) {
            foreach (preg_split('/[,|;]+/', (string) $item->other_names) ?: [] as $name) {
                $name = trim($name);
                if ($name !== '' && ! in_array($name, $alternatives, true)) {
                    $alternatives[] = $name;
                }
            }
        }

        $isOffer = in_array($item->type, ['package', 'bulk'], true);
        $drugCode = $item->type === 'good'
            ? Str::upper(Str::slug((string) ($item->generic_name ?: $item->code), '_'))
            : null;

        return [
            'sku' => $item->code,
            'item_name' => $item->name,
            'alternative_names' => array_values($alternatives),
            'strength_descriptor' => $item->strength,
            'is_offer_item' => $isOffer,
            'service_code' => $item->type === 'service' ? $item->code : null,
            'drug_code' => $drugCode,
            // Ingredient / class codes are not yet modelled in Main — empty until catalogue enrichment.
            'ingredient_codes' => $drugCode ? [$drugCode] : [],
            'drug_class_codes' => [],
            'uuid' => $item->uuid,
            'type' => $item->type,
            'business_id' => $item->business_id,
        ];
    }

    public function findClient(string $identifier, ?int $businessId): ?Client
    {
        $query = Client::query()->with(['business:id,uuid,name', 'branch:id,uuid,name']);

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        return $query->where(function ($q) use ($identifier) {
            $q->where('uuid', $identifier)
                ->orWhere('client_id', $identifier);
            if (ctype_digit($identifier)) {
                $q->orWhere('id', (int) $identifier);
            }
        })->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function clientPayload(Client $client): array
    {
        $addressParts = array_filter([
            $client->village,
            $client->county,
        ]);

        return [
            'global_client_id' => $client->uuid,
            'client_code' => $client->client_id,
            'full_name' => $client->name ?: trim(($client->surname ?? '').' '.($client->first_name ?? '')),
            'date_of_birth' => $client->date_of_birth?->toDateString(),
            'gender' => $client->sex,
            'national_id' => $client->nin,
            'phone' => $client->phone_number,
            'email' => $client->email,
            'address' => $addressParts !== [] ? implode(', ', $addressParts) : null,
            'village' => $client->village,
            'county' => $client->county,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
            'visit_id' => $client->visit_id,
            'visit_expires_at' => $client->visit_expires_at?->toIso8601String(),
            'status' => $client->status,
            'business' => $client->business ? [
                'id' => $client->business->id,
                'uuid' => $client->business->uuid,
                'name' => $client->business->name,
            ] : null,
            'branch' => $client->branch ? [
                'id' => $client->branch->id,
                'uuid' => $client->branch->uuid,
                'name' => $client->branch->name,
            ] : null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function queuePayload(?int $businessId, ?string $wardCode = null): array
    {
        $query = ServiceDeliveryQueue::query()
            ->with([
                'client:id,uuid,client_id,visit_id,name',
                'servicePoint:id,uuid,name,room_id',
                'servicePoint.room:id,name',
            ])
            ->whereIn('status', ['pending', 'in_progress', 'partially_done'])
            ->orderBy('queued_at');

        if ($businessId) {
            $query->where('business_id', $businessId);
        }

        $rows = $query->get();

        return $rows
            ->filter(function (ServiceDeliveryQueue $row) use ($wardCode) {
                if (! $wardCode) {
                    return true;
                }
                $ward = $row->servicePoint?->room?->name;

                return $ward && Str::upper(Str::slug($ward, '_')) === Str::upper(Str::slug($wardCode, '_'));
            })
            ->map(function (ServiceDeliveryQueue $row) {
                $wardName = $row->servicePoint?->room?->name;

                return [
                    'queue_code' => $row->servicePoint?->uuid ?: 'SP-'.$row->service_point_id,
                    'queue_name' => $row->servicePoint?->name,
                    'global_client_id' => $row->client?->uuid,
                    'client_code' => $row->client?->client_id,
                    'visit_id' => $row->client?->visit_id,
                    'ward_code' => $wardName ? Str::upper(Str::slug($wardName, '_')) : null,
                    'ward_name' => $wardName,
                    'item_name' => $row->item_name,
                    'status' => $row->status,
                    'waiting_since' => $row->queued_at?->toIso8601String(),
                    'business_id' => $row->business_id,
                    'branch_id' => $row->branch_id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function handleInboundEvent(array $payload, ?int $businessId): array
    {
        $eventId = (string) ($payload['event_id'] ?? '');
        $factToken = (string) ($payload['fact_token'] ?? '');

        if ($eventId === '') {
            abort(422, 'event_id is required');
        }

        $existing = ClinicalInboundEvent::query()->where('event_id', $eventId)->first();
        if ($existing) {
            return $existing->response ?? ['status' => 'duplicate', 'event_id' => $eventId];
        }

        if ($factToken === 'INFANT_REGISTRATION') {
            $response = $this->registerInfant($payload, $businessId);
        } else {
            abort(422, 'Unsupported fact_token');
        }

        ClinicalInboundEvent::query()->create([
            'event_id' => $eventId,
            'fact_token' => $factToken,
            'business_id' => $businessId,
            'payload' => $payload,
            'response' => $response,
        ]);

        return $response;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function registerInfant(array $payload, ?int $businessId): array
    {
        $motherId = (string) ($payload['mother_patient_id'] ?? '');
        $mother = $this->findClient($motherId, $businessId);

        if (! $mother) {
            abort(404, 'Mother patient not found');
        }

        $business = $mother->business ?: Business::find($mother->business_id);
        $branch = $mother->branch;

        if (! $business || ! $branch) {
            abort(422, 'Mother business/branch incomplete');
        }

        $sex = strtoupper((string) ($payload['sex'] ?? 'UNKNOWN'));
        $sexMapped = match ($sex) {
            'MALE', 'M' => 'male',
            'FEMALE', 'F' => 'female',
            default => 'other',
        };

        $deliveryAt = isset($payload['delivery_at'])
            ? Carbon::parse($payload['delivery_at'])
            : now();

        $order = (int) ($payload['birth_order'] ?? 1);
        $surname = $mother->surname ?: $mother->name;
        $firstName = 'Infant'.($order > 1 ? ' '.$order : '');

        $infant = Client::query()->create([
            'uuid' => (string) Str::uuid(),
            'business_id' => $mother->business_id,
            'branch_id' => $mother->branch_id,
            'client_id' => Client::generateClientId($business, (string) $surname, $firstName, $deliveryAt->toDateString()),
            'name' => trim($firstName.' '.($surname ?? '')),
            'surname' => $surname,
            'first_name' => $firstName,
            'sex' => $sexMapped,
            'date_of_birth' => $deliveryAt->toDateString(),
            'phone_number' => $mother->phone_number,
            'village' => $mother->village,
            'county' => $mother->county,
            'nationality' => $mother->nationality,
            'status' => 'active',
            'client_type' => 'individual',
            'insurance_company_id' => ! empty($payload['inherit_maternal_coverage'])
                ? $mother->insurance_company_id
                : null,
            'policy_number' => ! empty($payload['inherit_maternal_coverage'])
                ? $mother->policy_number
                : null,
        ]);

        $infant->issueNewVisitId();
        $infant->refresh();

        $callbackPath = (string) ($payload['callback_path'] ?? '');
        $callbackBody = [
            'infant_patient_id' => $infant->uuid,
            'infant_visit_id' => $infant->visit_id,
            'birth_record_id' => $payload['birth_record_id'] ?? null,
            'event_id' => $payload['event_id'] ?? null,
        ];

        if ($callbackPath !== '') {
            $this->postToClinical($callbackPath, $callbackBody, (string) $mother->business_id);
        }

        return [
            'status' => 'registered',
            'infant_patient_id' => $infant->uuid,
            'infant_visit_id' => $infant->visit_id,
            'client_code' => $infant->client_id,
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function notifyEncounterCreated(
        Client $client,
        string $visitId,
        ?string $previousVisitId = null
    ): void {
        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isEncounterWebhookEnabled() || ! $settings->isConfiguredForOutbound()) {
            return;
        }

        $this->postToClinical('/api/v1/clinical/encounters/created', [
            'global_client_id' => $client->uuid,
            'visit_id' => $visitId,
            'previous_visit_id' => $previousVisitId,
            'business_id' => $client->business_id,
            'branch_id' => $client->branch_id,
        ], (string) $client->business_id);
    }

    /**
     * SRD §4.5 step 1 — notify Clinical ward that a tote is staged for nurse collection.
     */
    public function notifyToteStaged(InventoryHandoffToken $token): void
    {
        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isConfiguredForOutbound()) {
            Log::info('Clinical tote-staged alert skipped (Clinical Module not configured)', [
                'handoff_ref' => $token->uuid,
            ]);

            return;
        }

        $token->loadMissing([
            'store:id,uuid,name',
        ]);

        $payload = $this->toteChecklistPayload($token);

        $response = $this->requestClinical(
            'POST',
            '/api/v1/clinical/pharmacy/totes/staged',
            $payload,
            (string) $token->business_id
        );

        if ($response === null) {
            return;
        }

        if ($response->failed()) {
            Log::warning('Clinical tote-staged alert failed', [
                'handoff_ref' => $token->uuid,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return;
        }

        $sessionId = data_get($response->json(), 'data.clinical_session_id')
            ?? data_get($response->json(), 'clinical_session_id');

        $token->clinical_notified_at = now();
        if (is_string($sessionId) && $sessionId !== '') {
            $token->clinical_session_id = $sessionId;
        }
        $token->save();
    }

    /**
     * Allow EndStore release without Clinical when outbound integration is not configured.
     */
    public function handoffBypassEnabled(): bool
    {
        if (! (bool) config('services.clinical_module.handoff_bypass_enabled', false)) {
            return false;
        }

        return ! KashtreClinicalModuleSetting::resolved()->isConfiguredForOutbound();
    }

    public function handoffBypassCode(): string
    {
        $digits = preg_replace('/\D+/', '', (string) config('services.clinical_module.handoff_bypass_code', '00000')) ?? '';

        return str_pad(substr($digits, 0, 5), 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{valid: bool, message: ?string, clinical_session_id: ?string}|null
     */
    public function tryHandoffBypass(string $code): ?array
    {
        if (! $this->handoffBypassEnabled()) {
            return null;
        }

        $normalized = preg_replace('/\D+/', '', $code) ?? '';
        if ($normalized !== $this->handoffBypassCode()) {
            return null;
        }

        Log::info('Inventory handoff release accepted via bypass (Clinical Module not configured).');

        return [
            'valid' => true,
            'message' => null,
            'clinical_session_id' => 'inventory-bypass',
        ];
    }

    /**
     * SRD §4.5 step 4 — validate nurse 5-digit code with Clinical Module.
     *
     * @return array{valid: bool, message: ?string, clinical_session_id: ?string}
     */
    public function validateHandoffCode(string $code, InventoryHandoffToken $token): array
    {
        $bypass = $this->tryHandoffBypass($code);
        if ($bypass !== null) {
            return $bypass;
        }

        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isConfiguredForOutbound()) {
            return [
                'valid' => false,
                'message' => $this->handoffBypassEnabled()
                    ? 'Enter the dev bypass code ('.$this->handoffBypassCode().') or configure Clinical Module.'
                    : 'Clinical Module is not configured. Cannot validate handoff code.',
                'clinical_session_id' => null,
            ];
        }

        $response = $this->requestClinical(
            'POST',
            '/api/v1/clinical/pharmacy/handoff/validate',
            [
                'code' => $code,
                'handoff_ref' => $token->uuid,
                'clinical_session_id' => $token->clinical_session_id,
                'store_id' => $token->store_id,
                'store_uuid' => $token->store?->uuid,
                'client_space_id' => null,
                'client_space_uuid' => null,
                'basket_key' => $token->basket_key,
                'business_id' => $token->business_id,
            ],
            (string) $token->business_id
        );

        if ($response === null) {
            return [
                'valid' => false,
                'message' => 'Unable to reach Clinical Module to validate the handoff code.',
                'clinical_session_id' => null,
            ];
        }

        if ($response->failed()) {
            $message = data_get($response->json(), 'message')
                ?? data_get($response->json(), 'error')
                ?? 'Clinical Module rejected the handoff code.';

            return [
                'valid' => false,
                'message' => is_string($message) ? $message : 'Clinical Module rejected the handoff code.',
                'clinical_session_id' => null,
            ];
        }

        $json = $response->json() ?? [];
        $valid = (bool) (data_get($json, 'data.valid') ?? data_get($json, 'valid') ?? true);
        $sessionId = data_get($json, 'data.clinical_session_id')
            ?? data_get($json, 'clinical_session_id');

        return [
            'valid' => $valid,
            'message' => $valid
                ? null
                : (data_get($json, 'data.message') ?? data_get($json, 'message') ?? 'Invalid handoff code.'),
            'clinical_session_id' => is_string($sessionId) ? $sessionId : null,
        ];
    }

    /**
     * Checklist payload for Clinical ward dashboard / Collect Medications.
     *
     * @return array<string, mixed>
     */
    public function toteChecklistPayload(InventoryHandoffToken $token): array
    {
        $token->loadMissing([
            'store:id,uuid,name',
        ]);

        $lineIds = array_values(array_map('intval', $token->fulfillment_line_ids ?? []));

        $lines = InventoryFulfillmentLine::query()
            ->with(['client:id,uuid,client_id,name,visit_id', 'item:id,uuid,code,name,strength'])
            ->whereIn('id', $lineIds)
            ->orderBy('id')
            ->get();

        return [
            'handoff_ref' => $token->uuid,
            'clinical_session_id' => $token->clinical_session_id,
            'expires_at' => $token->expires_at?->toIso8601String(),
            'business_id' => $token->business_id,
            'store' => $token->store ? [
                'id' => $token->store->id,
                'uuid' => $token->store->uuid,
                'name' => $token->store->name,
            ] : null,
            'client_space' => null,
            'basket_key' => $token->basket_key,
            'tote_barcode' => $token->tote_barcode,
            'lines' => $lines->map(function (InventoryFulfillmentLine $line) {
                $remaining = max(0, (float) $line->quantity - (float) $line->quantity_fulfilled);

                return [
                    'fulfillment_line_uuid' => $line->uuid,
                    'fulfillment_line_id' => $line->id,
                    'global_client_id' => $line->client?->uuid,
                    'client_code' => $line->client?->client_id,
                    'client_name' => $line->client?->name,
                    'visit_id' => $line->visit_id ?: $line->client?->visit_id,
                    'sku' => $line->item?->code,
                    'item_uuid' => $line->item?->uuid,
                    'item_name' => $line->item_name ?: $line->item?->name,
                    'strength' => $line->item?->strength,
                    'quantity' => $remaining,
                    'status' => $line->status,
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function postToClinical(string $path, array $body, ?string $tenantId = null): void
    {
        $this->requestClinical('POST', $path, $body, $tenantId);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function requestClinical(
        string $method,
        string $path,
        array $body = [],
        ?string $tenantId = null
    ): ?\Illuminate\Http\Client\Response {
        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isConfiguredForOutbound()) {
            return null;
        }

        $url = $settings->baseUrl().'/'.ltrim($path, '/');

        try {
            $pending = Http::timeout(15)
                ->withHeaders(array_filter([
                    'X-Service-Key' => $settings->serviceKey(),
                    'X-Tenant-Id' => $tenantId,
                    'Accept' => 'application/json',
                ]));

            $response = strtoupper($method) === 'GET'
                ? $pending->get($url, $body)
                : $pending->send($method, $url, ['json' => $body]);

            if ($response->failed()) {
                Log::warning('Clinical Module request failed', [
                    'method' => $method,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            Log::warning('Clinical Module request exception', [
                'method' => $method,
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
