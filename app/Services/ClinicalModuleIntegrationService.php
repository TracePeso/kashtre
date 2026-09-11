<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Client;
use App\Models\ClinicalInboundEvent;
use App\Models\InventoryFulfillmentLine;
use App\Models\InventoryHandoffToken;
use App\Models\Item;
use App\Models\KashtreClinicalModuleSetting;
use App\Models\PackageTracking;
use App\Models\PackageTrackingItem;
use App\Models\ServiceDeliveryQueue;
use App\Support\ItemStrengthParser;
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
                    ->orWhere('strength', 'like', "%{$query}%")
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

        // "Offer item" means the Clinical Module may order it: an active,
        // billable line in this business's catalogue. All four item types are
        // sellable (goods and services directly, package/bulk as composites),
        // so the only disqualifier is being retired — which for Item is a soft
        // delete, and those never reach this method through the default scope.
        $isOffer = $item->deleted_at === null;

        // Ingredient identity drives duplicate-therapy and allergy checking on
        // the Clinical side, and it must never be the SKU: two brands of the
        // same drug have different SKUs, so a SKU here makes every safety check
        // pass silently rather than fail loudly. Only a real generic name
        // yields a code — otherwise null, which Clinical reads as "check
        // unavailable" and surfaces to the clinician.
        $drugCode = $item->type === 'good' && filled($item->generic_name)
            ? Str::upper(Str::slug((string) $item->generic_name, '_'))
            : null;

        return [
            'sku' => $item->code,
            'item_name' => $item->name,
            'alternative_names' => array_values($alternatives),
            'strength_descriptor' => $item->strength ?: ItemStrengthParser::parse($item->name),
            'is_offer_item' => $isOffer,
            'service_code' => $item->type === 'service' ? $item->code : null,
            'drug_code' => $drugCode,
            'ingredient_codes' => $drugCode ? [$drugCode] : [],
            // Chemical class (e.g. PENICILLIN_CLASS) is not modelled in Main.
            // Empty means Clinical cannot block a class-level allergy — see the
            // Clinical Module checklist §2.4; needs a catalogue enrichment pass.
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
            'deceased_at' => $client->deceased_at?->toIso8601String(),
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

        // Clinical's published contract emits INFANT_REGISTRATION_REQUESTED
        // (their checklist §5.1); the shorter form is what this service
        // originally accepted. Accept both rather than reject a live delivery
        // over naming — their outbox would retry it forever.
        if (in_array($factToken, ['INFANT_REGISTRATION', 'INFANT_REGISTRATION_REQUESTED'], true)) {
            $response = $this->registerInfant($payload, $businessId);
        } elseif ($factToken === 'PATIENT_DECEASED') {
            $response = $this->recordPatientDeceased($payload, $businessId);
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

        $deliveryAt = $this->parseToAppTimezone($payload['delivery_at'] ?? null);

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
     * Clinical sends ISO-8601 instants in UTC ("...Z"). Laravel writes a
     * Carbon to a datetime column with format('Y-m-d H:i:s'), which drops the
     * offset instead of converting it — so a bare Carbon::parse() of 14:30Z
     * stores "14:30" and reads back as 14:30 Africa/Nairobi, three hours
     * before the event actually happened. Convert first.
     */
    private function parseToAppTimezone(mixed $value): Carbon
    {
        if ($value === null || $value === '') {
            return now();
        }

        return Carbon::parse($value)->setTimezone(config('app.timezone'));
    }

    /**
     * SRD §13.3 end-of-life kill-switch. Clinical halts everything clinical;
     * this side records the death so recurring billing can stop.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function recordPatientDeceased(array $payload, ?int $businessId): array
    {
        $patientId = (string) ($payload['patient_id'] ?? $payload['global_client_id'] ?? '');
        $client = $this->findClient($patientId, $businessId);

        if (! $client) {
            abort(404, 'Patient not found');
        }

        $deceasedAt = $this->parseToAppTimezone($payload['deceased_at'] ?? null);

        // Never move an already-recorded death: a redelivery or a correction
        // arriving out of order must not rewrite the original timestamp that
        // billing has already acted on.
        if ($client->deceased_at === null) {
            $client->forceFill(['deceased_at' => $deceasedAt])->save();
        }

        Log::info('Clinical PATIENT_DECEASED recorded', [
            'client_id' => $client->id,
            'deceased_at' => $client->deceased_at?->toIso8601String(),
        ]);

        return [
            'status' => 'recorded',
            'patient_id' => $client->uuid,
            'client_code' => $client->client_id,
            'deceased_at' => $client->deceased_at?->toIso8601String(),
        ];
    }

    /**
     * Tell Clinical what a package purchase entitles the patient to, so it can
     * decrement on clinical use and intercept orders beyond the allocation
     * (their checklist §6 / SRD §6.3).
     *
     * Called after the tracking rows are committed, never inside the sale's
     * transaction: a Clinical outage must not roll back a paid-for package.
     * postToClinical already swallows and logs transport failures.
     */
    public function notifyEntitlementsGranted(PackageTracking $tracking): void
    {
        $settings = KashtreClinicalModuleSetting::resolved();

        if (! $settings->isConfiguredForOutbound()) {
            return;
        }

        $tracking->loadMissing(['trackingItems.includedItem', 'client', 'packageItem']);

        $allocations = $tracking->trackingItems
            ->map(function (PackageTrackingItem $line) {
                $item = $line->includedItem;

                if (! $item) {
                    return null;
                }

                // Clinical's own endpoint requires service_code on every
                // allocation line (confirmed live 2026-08-26: a single
                // package with any non-service line — i.e. every real
                // package in this business, which all mix drugs/supplies
                // with a couple of billable services — got its *entire*
                // POST rejected 422, silently, because this used to send
                // every included line with service_code left null for any
                // 'good'/'bulk' item instead of dropping it). Only a
                // service-type line can ever be consumed against by a
                // clinical order anyway (CatalogueLookupController::present()
                // only reports service_code for type==='service'), so a
                // 'good' line here was always going to be rejected — it was
                // just taking every other line in the same package down
                // with it.
                if ($item->type !== 'service') {
                    return null;
                }

                return [
                    'service_code' => $item->code,
                    'sku' => $item->code,
                    'item_name' => $item->name,
                    'allocated_qty' => (float) $line->total_quantity,
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($allocations === []) {
            return;
        }

        $this->postToClinical('/api/v1/clinical/entitlements', [
            // Every other read/write against this patient elsewhere in the
            // integration (MAR, observations, EntitlementBalancesPanel's own
            // read via ApiEntitlementGateway::balancesFor()) keys off this
            // business-scoped client_id string, e.g. "ETJFA27HU" — never the
            // client's UUID. Clinical's own EntitlementController treats
            // patient_id as an opaque string with no cross-check against any
            // patient record, so a mismatch here was never going to surface
            // as an error; it silently registered every allocation under an
            // identifier nothing ever reads by (confirmed live 2026-08-26 —
            // a real package sale registered 3 real entitlements, findable
            // only by the client's uuid, invisible to this patient's own
            // balances panel).
            'patient_id' => $tracking->client?->client_id,
            'client_code' => $tracking->client?->client_id,
            'visit_id' => $tracking->client?->visit_id,
            'package_id' => $tracking->packageItem?->code ?: (string) $tracking->package_item_id,
            'package_name' => $tracking->packageItem?->name,
            'tracking_number' => $tracking->tracking_number,
            'valid_from' => $tracking->valid_from,
            'valid_until' => $tracking->valid_until,
            'allocations' => $allocations,
        ], (string) $tracking->business_id);
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
     * notify Clinical ward that a tote is staged for nurse collection.
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
     * validate nurse 5-digit code with Clinical Module.
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
                'client_space_id' => $token->client_space_id,
                'client_space_uuid' => $token->clientSpace?->uuid,
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
            'clientSpace:id,uuid,name',
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
            'client_space' => $token->clientSpace ? [
                'id' => $token->clientSpace->id,
                'uuid' => $token->clientSpace->uuid,
                'name' => $token->clientSpace->name,
            ] : null,
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
            // Returning silently here is how the encounter webhook and the
            // infant-registration callback disappear without a trace when the
            // settings row is half-filled. Say which half is missing.
            Log::warning('Clinical Module call skipped — outbound not configured', [
                'path' => $path,
                'has_url' => $settings->baseUrl() !== '',
                'has_service_key' => $settings->serviceKey() !== '',
            ]);

            return null;
        }

        $url = $settings->baseUrl().'/'.ltrim($path, '/');

        try {
            $pending = Http::timeout(10)
                ->withOptions(['connect_timeout' => 3])
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
