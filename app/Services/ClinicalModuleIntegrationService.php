<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\ClinicalInboundEvent;
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
     * @param  array<string, mixed>  $body
     */
    public function postToClinical(string $path, array $body, ?string $tenantId = null): void
    {
        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isConfiguredForOutbound()) {
            return;
        }

        $url = $settings->baseUrl().'/'.ltrim($path, '/');

        try {
            $response = Http::timeout(15)
                ->withHeaders(array_filter([
                    'X-Service-Key' => $settings->serviceKey(),
                    'X-Tenant-Id' => $tenantId,
                    'Accept' => 'application/json',
                ]))
                ->post($url, $body);

            if ($response->failed()) {
                Log::warning('Clinical Module request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Clinical Module request exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
