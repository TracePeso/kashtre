## Interface Control Document & 

## Cros ~~s-~~ Module Integratione Blueprinte 

Integration Domain: Clinical Orchestrator (CLINICA ~~L_~~ ORCHESTRATOR) & Poin ~~t-~~ o ~~f-~~ Care Ecosystem (INVENTORY ~~_~~ CORE) 



<!-- Start of picture text -->
7<br><!-- End of picture text -->

7 Core Inventory 

Specification Version: |CD-INV ~~-v~~ 1.0 ~~-~~ Production ~~-~~ Master 

Target Platform: Laravel Native v10+ / PHP 8.2+ / MySQL 8.0+ / Asynchronous Webhooks 

Compliant Standard Baseline: Inventory SRD v6.0 / Inventory Eng Spec v6.0 / Clinical SRD v6.0 / LIMS v2.6 / RIS v2.6 

Status: Approved Engineering Integration Blueprint 

#### Executive Architectural Boundary & Domain Isolation Mandate 

To maintain strict domain isolation and adhere to The Floor Principle and The Domain Isolation Principle: 

- 1 ~~.~~ Physical Truth vs. Financial Truth: The Inventory Module owns physical asset balances and stock movement; the Main Module owns financial ledgers and billing. The Clinical Module acts as the central operational event broker and routing proxy between clinical staff, diagnostic engines (LIMS/RIS), and Inventory. 

- 2 ~~.~~ The Clinical Event Broker: All bedside medication administrations, floor stock usages, crash cart reconciliations, LIMS reagent drops, and RIS contrast usages emit standardized Clinical Consumption Facts through the Clinical Module proxy layer. 

3. No Direct LIMS/RIS Write Access: LIMS and RIS engines do not write directly to Inventory store ledgers. They emit proxied events (REAGEN ~~T~~ CONSUMPTION_ ~~F~~ ACT / RADIOLOGY ~~_C~~ ONSUMPTION_ ~~F~~ ACT) containing their mapped Platform Room ID, which the Clinical Proxy resolves to the active Inventory Sub ~~-S~~ tore. 

~~<u>EE</u> St,~~ | CLINICAL ORCHESTRATOR | | (Central Operational Event Broker, Proxy Gateway & Identity Anchor) | 



<!-- Start of picture text -->
1<br><!-- End of picture text -->



<!-- Start of picture text -->
J<br><!-- End of picture text -->



<!-- Start of picture text -->
| 2. Token Verification | 3. Consumption Facts<br><!-- End of picture text -->

1.Demand 



<!-- Start of picture text -->
| 4. Ward Ready<br><!-- End of picture text -->

Intent | Handshake Callback | (MAR, Floor Stock, Crash | Alerts Payloads | (POST /api/vi/clinical/ | Cart, LIMS/RIS Reagents) | (WebSocket) (POST) | handshake/validate ~~-~~ token) | (POST /api/v1/inventory/ | Vv | _consumption/emit) Vv 

__oo ~~os~~ | INVENTORY CORE ENGINE | | (Stock Ledgers, Approved Pools, Usage Reconciliation, Ward Totes, Demand Ledgers) 

# ~~<u>Pe</u> —~~ 

#### 1. Core Identity & Reconciliation Routing Framework 

All consumption transactions bind to the platform's 4 ~~-t~~ ier identity hierarchy and run through the Usage Reconciliation Engine. 

###### 1.1 The 3 Poin ~~t-~~ o ~~f-~~ Care Reconciliation Scenarios 

When a usage event is logged at the point of care, the system executes one of three backend reconciliation workflows: 

|System<br>Scenario|Operating<br>Condition|Physical<br>Inventory<br>Reduced?|Digital<br>Approved<br>Pool<br>Reduced?|Triggers Main<br>Module<br>Billing?|
|---|---|---|---|---|
|End Store<br>Counter<br>Handoff|Prepaid<br>prescription<br>dispensed at<br>pharmacy<br>counter|YES|NO (Populates<br>Pool)|YES (Closes<br>Paid Ticket)|
|Scenario A:<br>Approved<br>Patient Usage|Item exists<br>within Patient<br>Digital<br>Approved Pool|NO|YES|NO|
|Scenario B:<br>Non~~-~~Approve|Item<br>consumed|YES|NO|YES<br>(Dispatches|





<!-- Start of picture text -->
d Floor Stock from satellite Postpaid<br>store (not in Event)<br>Approved<br>Pool)<br>Scenario C: Operational YES NO NO<br>Administrativ expense<br>e Usage (cleaning,<br>spills, training)<br>Scenario D: Emergency YES NO YES<br>Crash Cart resuscitation (Dispatches<br>Usage reconciliation Postpaid<br>Event)<br><!-- End of picture text -->



##### 2. Inbound REST API Contracts (Clinical Module — Inventory) 

###### 2.1 Parallel Demand Intent Capture (Addendum A ~~-0~~ 2) 

- e Endpoint: POST /api/v1/inventory/demand/capture ~~-i~~ ntent 

- e Trigger: The Clinical Translator Engine intercepts a clinician's order intent (prescriptions, diagnostic requests), completely blind to current shelf balances. 

- e Purpose: Logs raw clinical demand into inventor ~~y_~~ demand_ledgers to expose unfulfilled deficits for procurement forecasting. 

###### Request Payload 

{ 

"$schema": 

"[https://js ~~o~~ n-schema.org/draft/202 ~~0-~~ 12/schema](https://js ~~on~~ -schema.org/draft/2020 ~~-~~ 12/schem a)", 

"title": "ClinicalDemandIintentPayload", 

"type": "object", 

“required”: ["tenant ~~_i~~ d", "global_client ~~_i~~ d", "originatin ~~g_~~ space_ ~~id~~ ", "inventory ~~_s~~ ku", “quantity ~~_r~~ equested’", "timestamp’], 

“oroperties”: { 

“tenant ~~_i~~ d": { "type": "string", "example": "FACILIT ~~Y_~~ ALPHA" }, 

- "global_client ~~_i~~ d": { "type": "string", "example": "CL ~~-~~ 00001234" }, 

“originatin ~~g_~~ space ~~_id~~ ": { "type": "string", "example": "WARD ~~-G~~ YNAE ~~-B~~ ED ~~-0~~ 4" }, “inventory ~~_s~~ ku": { "type": "string", "example": "DRUG ~~-C~~ EFTRIAXONE ~~-1~~ G" }, “quantit ~~y_~~ requested": { "type": "integer", "minimum: 1, "example": 2 }, 

- “urgency”: { "type": "string", "enum": ["ROUTINE", "URGENT", "STAT"], "default": "ROUTINE" }, 

"timestamp": { "type": "string", "format": "date ~~-~~ time" } 

}, 

"additionalProperties": false 

} 

###### Success Response (201 Created) 

{ 

"status": "DEMAND ~~_L~~ OGGED", 

"demand_ledger_ ~~id~~ ": 88412, "timestamp": "2026 ~~-~~ 07 ~~-2~~ 5T17:00:00Z" 

} 

###### 2.2 Poin ~~t-~~ o ~~f-~~ Care Unified Consumption Fact Emission 

- e Endpoint: POST /api/v1/inventory/consumption/emit 

- e Trigger: Nurse administers dose on MAR, logs non ~~-~~ approved floor stock, reconciles a crash cart, or LIMS/RIS proxy relays reagent/contrast usage. 

###### Request Payload 

{ 

"$schema": 

“[https://js ~~o~~ n-schema.org/draft/202 ~~0-~~ 12/schema|(https://js ~~on~~ -schema.org/draft/2020 ~~-~~ 12/schem a)", 

"title": "PointOfCareConsumptionFactPayload", 

"type": "object", 

“required”: ["tenant ~~_i~~ d", "fac ~~t_t~~ oken", "usage ~~_c~~ ontext", "originatin ~~g_~~ sub ~~_s~~ tore_ ~~id~~ ’, “inventory ~~_s~~ ku", "quantit ~~y_~~ consumed'", "executed ~~_b~~ y_ ~~us~~ er_ ~~id~~ ", "timestamp, 

“oroperties”: { 

“tenant ~~_i~~ d": { "type": "string", "example": "FACILIT ~~Y_~~ ALPHA" }, 

“fac ~~t_t~~ oken": { 

"type": "string", "enum": [ "MEDICATION ~~_A~~ DMINISTERED", "MEDICATION ~~_W~~ ASTED", 

"NON ~~_A~~ PPROVED ~~_F~~ LOOR ~~_S~~ TOCK_ ~~U~~ SAGE", 

"CRASH ~~_~~ CART ~~_~~ CONSUMPTION", 

"LA ~~B_~~ CONSUMPTION_ ~~FA~~ CT", "RADIOLOG ~~Y_~~ CONSUMPTION ~~_F~~ ACT" 

] 

}, “usage ~~_c~~ ontext": { 

“enum”: ["PATIENT’, "ADMINISTRATIVE", "CRASH ~~_C~~ ART", "WASTAGE ~~_O~~ PERATIONAL', "WASTAGE ~~_E~~ XPIRED"] 

}, “global_client ~~_i~~ d": { "type": "string", "nullable": true, "example": "CL ~~-~~ 00001234" }, "visi ~~t_i~~ d": { "type": "string", "nullable": true, "example": "VI ~~S-~~ 202 ~~6-~~ 001245" }, “originating sub ~~_s~~ tore_ ~~id~~ ": {"type": "string", "example": "STORE-ICU ~~-S~~ ATELLITE ~~-1~~ " }, “inventory ~~_s~~ ku": { "type": "string", "example": "DRUG ~~-~~ PARACETAMOL ~~-~~ 500MG' }, “quantit ~~y_~~ consumed": { "type": "number", "minimum": 0.0001, "example": 2 }, “administrativ ~~e_~~ purpose": { "type": "string', "nullable": true, "example": "SPIL ~~L_~~ CLEANUP" }, "reason ~~_c~~ ode": { "type": "string", "nullable": true, "example": "MAR ~~_~~ WASTAGE ~~_D~~ ROPPED" }, “executed ~~_b~~ y_ ~~us~~ er_ ~~id~~ ": {"type": "integer’, "example": 108 }, "timestamp": { "type": "string", "format": "date ~~-~~ time" } 

}, 

"additionalProperties": false 

} 

###### Success Response (200 Ok) 

{ 

"status": "RECONCILED", 

"reconciliatio ~~n_s~~ cenario": "A", "physical_stoc ~~k_~~ reduced": false, “approved ~~_p~~ ool_reduced": true, “billin ~~g_~~ triggered”: false, "audi ~~t_~~ node ~~_h~~ ash": "e3b0c44298fc1c149afbf4c8996fb92427ae41e46496934ca495991b7852b855" 

} 

#### 3. Token ~~-~~ Exchange Ward Handshake Protocol (Section 4.5) 

To maintain absolute security and prevent ward nurses from accessing pharmacy inventory interfaces, the handoff of staged Ward Delivery Totes follows a 5 ~~-d~~ igit verification handshake. 

[ END STORE PHARMACY ] [ CLINICAL WARD TERMINAL ] Items Staged into Ward Tote ~~—-~~ P Transmits Ready Alert (WS) ~~—P~~ Nurse clicks "Collect Medications" 

Pharmacist prompts for code < ~~——~~ Nurse inspects physical tote < ~~——~~ Receives 5 ~~-D~~ igit Token: "84921" 

| & states 5 ~~-d~~ igit code 

(Bound to shift session, 15m expiry) 

v 

Pharmacist inputs "84921" | v 

POST /api/v1/clinical/nandshake/validat ~~e-~~ token | ~~[—[~~ MATCH VALID ] ~~——~~ P Tote Released; Stock decremented from End Store balance. ~~L_[~~ DISCREPANCY] ~~—~~ > Partial Acceptance: Error line isolated, rolled back to PENDING. 

###### 3.1 Token Validation Handshake Endpoint 

- e Endpoint: POST /api/v1/clinical/nandshake/validat ~~e-~~ token 

- e Trigger: Pharmacist inputs the 5 ~~-d~~ igit code provided verbally by the collecting nurse at the End Store counter. 

###### Request Payload 

{ 

"$schema": 

"[https://js ~~o~~ n-schema.org/draft/202 ~~0-~~ 12/schema](https://js ~~on~~ -schema.org/draft/2020 ~~-~~ 12/schem a)", 

"title": "TokenHandshakeCallback’, 

"type": "object", 

"required": ["tote ~~_i~~ dentifier", "nurse ~~_s~~ ession ~~_k~~ ey", "verificatio ~~n_t~~ oken", “dispensing ~~_p~~ harmacist_ ~~id~~ "], 

“properties”: { 

"tote ~~_i~~ dentifier": { "type": "string", "example": "TOT ~~E-~~ WARD ~~-~~ GYNAE ~~-0~~ 04" }, 

"nurse ~~_s~~ ession ~~_k~~ ey": { "type": "string", "example": "SES ~~S-~~ NURS ~~E-~~ KARGBO- ~~9~~ 912" }, 

"verificatio ~~n_t~~ oken": { "type": "string", "pattern": "*[0 ~~-9~~ ]{5}$", "example": "84921" }, 

"dispensing ~~_p~~ harmacist_ ~~id~~ ": { "type": "integer", "example": 204 }, 

"flagged ~~_d~~ iscrepancy ~~_s~~ kus’: { 

"type": "array", 

"items": { "type": "string" }, "example": ["DRUG ~~-~~ AMOXICILLIN ~~-~~ 250MG"] 

} 

}, 

"additionalProperties": false 

} 

###### Success Response (200 OK) 

{ 

"status": "HANDSHAKE ~~V~~ ERIFIED", 

“tote ~~_i~~ dentifier": "TOT ~~E-~~ WARD ~~-G~~ YNAE- ~~0~~ 04", 

"release ~~d_i~~ tems ~~_c~~ ount": 14, 

"discrepancy ~~_i~~ tems ~~_r~~ olled ~~_b~~ ack": 1, "released ~~_a~~ t": "2026 ~~-~~ 07 ~~-2~~ 5T17:10:00Z" 

} 

### 4. Emergency Crash Cart Workflow (Section 6) 

Crash carts are treated as locked Satellite Store nodes operating under a strict emergency state machine. 

[ READY STATUS ] ~~—P~~ Action: "Deploy Cart / Open Box" ~~—®~~ [ DEPLOYED STATUS ] (All Ul inputs locked during crisis) 

[ READY STATUS ] ~~<—~~ Restocked & Sealed ~~<—~~ [ RECONCILING STATUS ] ~~<——1~~ (Staff click "Record Usage" post ~~-c~~ risis) 

###### 4.1 Post ~~-C~~ risis Reconciliation Execution 

When the emergency stabilizes, the nurse switches the cart state to RECONCILING and submits the used items array in a single batch. 

###### Programmatic System Actions: 

1. Physical stock is decremented from the specific Crash Cart Satellite Store node. 2. CRASH ~~_C~~ AR ~~T~~ CONSUMPTION fact is emitted to the Inventory audit log. 3. Asynchronous postpaid billing payload is dispatched dispatched upstream to the Main Module. 4. Auto ~~-g~~ enerates an urgent internal replenishment ticket to restock the cart back to maximum capacity. 

3. Asynchronous postpaid billing payload is dispatched dispatched upstream to the Main Module. 

##### 5. Production Service Implementations (Laravel 10+) 

###### 5.1 Clinical Inventory Event Router (ClinicallnventoryBridgeService.php) 

namespace App\Services\Clinical\Integration; 

use Illuminate\Support\Facades\Http; use Illuminate\Support\Facades\DB; use App\Services\Inventory\UsageReconciliationEngine; 

use Exception; use Log; 

class ClinicallnventoryBridgeService 

{ 

###### protected UsageReconciliationEngine $reconciliationEngine; 

public function ~~_c~~ onstruct(UsageReconciliationEngine $reconciliationEngine) 

{ 

$thi ~~s-~~ >reconciliationEngine = $reconciliationEngine; 

} 

[** 

* 1. INGEST CLINICAL CONSUMPTION FACT & EXECUTE RECONCILIATION 

*/ 

public function processConsumptionFact(array $payload): array 

{ 

###### return DB::transaction(function () use ($payload) { 

/! Map sub ~~-s~~ tore from Platform Room if not explicitly passed 

$storeld = $payload[‘originating ~~_s~~ ub ~~_s~~ tore_ ~~id~~ '] 

2? $thi ~~s-~~ >resolveSubStoreFromRoom($payload|'global_client_ ~~id~~ '] ?? null); 

$reconciliationPayload = [ 

‘usage ~~_c~~ ontext' => $payload['usage_ ~~co~~ ntext'], 

‘originatin ~~g_s~~ tore ~~_i~~ d' => $storeld, 

‘client ~~_i~~ d' => $payload['global_client_ ~~id~~ '] ?? null, 

‘administrativ ~~e_~~ purpose' => $payload['administrativ ~~e_~~ purpose’] ?? null, 

‘inventory ~~_s~~ ku' => $payload['inventory ~~_sk~~ u'], 

‘quanti ~~ty~~ _consumed' => $payload[‘quantit ~~y_~~ consumed'], ‘user ~~_i~~ d' => $payload['execu ~~te~~ d ~~_b~~ y_ ~~usi~~ d’,er 

]; 

// Execute Inventory Module Core Reconciliation Logic 

$result = $thi ~~s-~~ >reconciliationEngin ~~e-~~ >recordUsage($reconciliationPayload); 

/! Log Cross ~~-~~ Module Handshake Audit 

Log::info("Clinical Consumption Fact Processed [Token: {$payload|'fact_ ~~to~~ ken']}]", [ ‘client ~~_i~~ d' => $payload['global_client_ ~~id~~ '] ?? ‘N/A’, 

'sku' => $payload|'inventory ~~_sk~~ u'], 

‘scenario’ => $result['scenario’] 

}); 

return $result; 

}); 

} 

[** 

* 2. RESOLVE SUB ~~-~~ STORE ID FROM PLATFORM ROOM / BED LOCATION */ 

private function resolveSubStoreFromRoom(?string $clientld): string { 

if (!$clientld) return 'MAIN ~~_~~ DISPENSARY ~~_S~~ TORE’; 

###### return DB::table(‘patien ~~t_~~ beds’) 

- >join(‘clien ~~t_~~ spaces’, ‘patien ~~t_~~ beds.space_ ~~id~~ ', ‘=’, 'clien ~~t_~~ spaces.id’) 

- >where(‘patient ~~_b~~ eds.current_ ~~pa~~ tient_ ~~id~~ ’, $clientld) 

- >value(‘clien ~~t_~~ spaces.sub ~~_s~~ tore_ ~~id~~ ') ?? 'MAIN ~~_~~ DISPENSARY ~~_S~~ TORE’; 

} 

} 

###### 5.2 Token Verification Handshake Service 

###### (WardHandshakeService.php) 

namespace App\Services\Clinical\Integration; 

use Illuminate\Support\Facades\DB; use Exception; 

###### class WardHandshakeService 

{ 

[** 

* Validates 5 ~~-D~~ igit verification code and releases staged delivery tote. */ 

public function validateHandshakeToken(array $payload): array 

{ 

###### return DB::transaction(function () use ($payload) { 

$toteld = $payload[‘tote_ ~~id~~ entifier’]; 

$token = $payload['verification ~~_t~~ oken’]; 

/! Query active verification token session in Clinical Module 

$activeToken = DB::table(‘ward ~~_c~~ ollection ~~_t~~ okens’) 

- >where(‘tote_ ~~id~~ entifier’, $toteld) 

- >where(‘verification ~~_t~~ oken’, $token) 

- >where(‘is ~~_u~~ sed’, false) 

- >where(‘expires_a ~~t'~~ ’, '>', now()) 

~~-~~ >first(); 

###### if (‘SactiveToken) { 

throw new Exception("INVAL ~~ID~~ _.HANDSHAK ~~E_~~ TOKEN: Token has expired or does not match staged tote."); 

} 

// Mark token as used 

DB::table(‘ward ~~_c~~ ollection ~~_t~~ okens') 

~~-~~ >where(‘id', $activeToken ~~->~~ id) 

~~-~~ >update(['i ~~s_~~ used' => true, 'used ~~_a~~ t' => now()]); 

// Handle Partial Acceptance Discrepancies if any items were flagged 

if (tempty($payload['flagged ~~_d~~ iscrepancy_ ~~sk~~ us'])) { 

foreach ($payload["flagged ~~_d~~ iscrepancy ~~_s~~ kus'] as $discrepantSku) { 

// Duplicate row, strip tote reference, and roll back to PENDING state 

DB::table(‘inventor ~~y_~~ queue ~~_i~~ tems’) 

~~-~~ >where(‘tote_ ~~id~~ entifier’, $toteld) 

- >where(‘inventory ~~_s~~ ku', $discrepantSku) 

~~-~~ >update([ 

‘status’ => ‘PENDING’, 

‘tote ~~_i~~ dentifier' => null, 

‘discrepancy ~~_n~~ ote' => 'Flagged during counter collection handshake’ 

)); 

} 

} 

// Update remaining Tote items to COMPLETED and decrement End Store Physical Balances 

DB::table(‘inventor ~~y_~~ queue ~~_i~~ tems’) 

~~-~~ >where(‘tote_ ~~id~~ entifier’, $toteld) 

~~-~~ >where(‘status’, STAGED’) 

~~-~~ >update(['status' => ‘COMPLETED’, 'completed ~~_a~~ t' => now()]); 

return [ 

‘status’ => 'HANDSHAKE_ ~~VE~~ RIFIED’, ‘tote ~~_i~~ dentifier' => $toteld, ‘verified ~~_a~~ t' => now() ~~->~~ tolso8601String() 

]; 

}); 

} 

} 

#### 6. Summary Route & Webhook Endpoint Matrix 

|6.Summary <br>HTTP Method|Route &Webho<br>Route Endpoint|ok Endpoint <br>Payload Direction|Matrix<br>PrimaryService/<br>Action|
|---|---|---|---|
|POST|/api/v1/inventory/de<br>mand/capture-inten<br>t|Clinical—<br>imeniony|LogsrawClinical<br>intentto<br>invento~~ry~~_demand_<br>ledgers (Addendum<br>A~~-0~~2).|
|POST|KDI MEMEOVASE<br>nsumption/emit|Clinical Proxy—+<br>Inventory|SLs unified<br>point~~-~~of~~-~~care<br>.<br>consumption facts<br>(MAR, Floor Stock,<br>Crash Cart, LIMS,<br>RIS).|
|POST|/api/v1/clinical/nand<br>shake/validate-toke<br>n|Inventory (End<br>Store) —> Clinical|Validates 5~~-d~~igit<br>tokentorelease<br>staged Ward<br>Delivery Totes<br>(Section 4.5).|
|POST|fapiivUbilling/postp<br>ai~~d-~~event|Tventiony+<br>.<br>Main Module Proxy|Dispatches<br>non~~-~~approved floor<br>stockorcrashcart<br>consumption facts<br>for postpaid billing.|
|GET|/api/v1/inventory/co<br>nsumption~~-e~~xcepti<br>ons|Inventory Settings<br>Ul|Renders unresolved<br>store/room<br>mapping or offline<br>deduction failures<br>for supervisor<br>action.|



