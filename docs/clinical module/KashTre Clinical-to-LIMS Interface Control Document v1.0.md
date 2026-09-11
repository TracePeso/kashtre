### Interface Control Document & 

### Cros ~~s-~~ Module Integratione@ Blueprinte 



<!-- Start of picture text -->
7<br><!-- End of picture text -->

7 Integration Domain: Clinical Orchestrator (CLINICA ~~L_~~ ORCHESTRATOR) Laboratory Information Management System (LIM ~~S_~~ CORE) 

Specification Version: |CD ~~-v~~ 1. ~~0-~~ Production ~~-~~ Master 

Target Platform: Laravel Native v10+ / PHP 8.2+ / MySQL 8.0+ / Asynchronous Webhooks 

Compliant Standard Baseline: KashTre LIMS SRD v2.5 / LIMS Engineering Addendum v2.6 / Clinical SRD v6.0 

Status: Approved Engineering Integration Blueprint 

##### Executive Architectural Boundary Mandate 

To maintain strict domain isolation and protect business ledgers from diagnostic execution traffic: 

1. The Direct Communication Ban: The LIMS module shall NEVER communicate directly with the Main Module or Inventory Module for any transactional, status, or stock depletion event. 

2. The Clinical Proxy Layer: All inbound orders, status callbacks, specimen alerts, critical panic notifications, and reagent consumption facts pass asynchronously through the Clinical Module proxy layer. 

3. The Workflow Source of Truth: LIMS execution is strictly workflow ~~-d~~ riven (LIMS v2.6). The Clinical Module receives workflow step progress events and translates LIMS internal workflow states to Main Module business statuses (PENDING, I ~~N_~~ PROGRESS, COMPLETED). 

~~<u>a</u> Oe~~ | CLINICAL ORCHESTRATOR ORCHESTRATOR | | (Central Operational Router, Proxy Gateway & Gateway & & Identity Anchor) Anchor) | 

| CLINICAL ORCHESTRATOR ORCHESTRATOR | | (Central Operational Router, Proxy Gateway & Gateway & & Identity Anchor) Anchor) | ~~ee)QSQS~~ Inbound API | 1. Create Lab Order (POST /api/v1/lab/orders) | Outbound Webhooks Dispatches | 2. Cancel Lab Order (POST /api/v1/lab/orders/cancel) | (Specimen Events, Results, 

## ~~ee)QSQS~~ 

Reagents) 

| 3. Encounter Update (PATCH /api/v1/lab/orders/encounter) | Critical Panics, v | 

~~<u>ooo</u> dt~~ | LIMS DIAGNOSTIC ENGINE | | (Workflow Step Queues, Analyzer Integration, Delta Checks & Results) | 

# ~~<u>Pe</u> SSS~~ 

#### 1. Identity Management & Cros ~~s-~~ Day Encounter Inheritance Mapping 

All transactions bind to a strict 4 ~~-t~~ ier identity hierarchy. 

|IdentityKey|Format Pattern|Owning Origin|Lifetime&Scope|
|---|---|---|---|
|global_client~~_i~~d|CL~~-~~00001234|Main Module|Permanent lifetime<br>profile key<br>anchoring patient<br>historyacross all<br>visits.|
|visi~~t_~~id|VI~~S-~~202~~6-~~001245|Main Module|Encounter<br>transaction key.<br>Expires at midnight<br>for outpatients;<br>locked for admitted<br>inpatients.|
|la~~b_~~order~~_u~~uid|LABORD~~-2~~026~~-~~008 —<br>SS)|Clinical Module|Unique<br>investigation order<br>reference<br>generated upon<br>order sign~~-o~~ff.|
|specimen~~_i~~d|SPEC~~-~~2026~~-~~00098<br>7|LIMS Module|Unique2D barcode<br>container identifier<br>printed at|



phlebotomy/collecti on point. 

###### 1.1 Cross ~~-~~ Day Encounter Inheritance Protocol 

When an outpatient has an outstanding pending lab order (ORDER ~~_R~~ ECEIVED / WAITING COLLECTION) ordered on Day 1, but returns on Day 2 with a fresh daily visi ~~t_i~~ d: 

- 1 ~~.~~ The Clinical Module detects the new encounter session creation via EncounterObserver. 2 ~~.~~ The Clinical Module fires a PATCH /api/v1/lab/orders/{labOrderUuid}/updat ~~e~~ -encounter request to LIMS. 

- 3 ~~.~~ LIMS updates the pending order's visi ~~t_i~~ d to today's active key without recreating the order or invalidating barcodes. 



###### 2. Inbound REST API Contracts (Clinical Module — LIMS) 

All inbound API requests from the Clinical Module to LIMS include the Authorization: Bearer {LIM ~~S_~~ SERVIC ~~E_~~ TOKEN} header and X ~~-~~ KashTre ~~-S~~ ignature HMAC ~~-S~~ HA256 token. 

###### 2.1 Create Laboratory Order 

- e Endpoint: POST /api/v1/lab/orders e Trigger: Clinician approves a laboratory order in the Clinical Module chart session. 

###### Request Payload 

- { 

"$schema': 

- “[https://js ~~o~~ n-schema.org/draft/202 ~~0-~~ 12/schemal(https://js ~~on~~ -schema.org/draft/2020 ~~-~~ 12/schem a)", 

"title": "CreateLabOrderPayload", 

“required”: ["tenant ~~_i~~ d", "global_client ~~_i~~ d", "visit ~~_i~~ d”, "la ~~b_~~ order ~~_u~~ uid", “ordering ~~_cl~~ inician ~~_i~~ d", 

"space_ ~~id~~ ", "tes ~~t_~~ requests'], 

“properties: { 

- “tenant ~~_i~~ d": { "type": "string", "example": "FACILIT ~~Y_~~ ALPHA" }, 

- "global_client ~~_i~~ d": { "type": "string", "example": "CL ~~-~~ 00001234' }, 

- "visi ~~t_i~~ d": { "type": "string", "example": "VI ~~S-~~ 202 ~~6-~~ 001245" }, 

"la ~~b_~~ order ~~_u~~ uid": { "type": "string", "example": "LABORD ~~-2~~ 026 ~~-0~~ 08551" }, 

   - “ordering ~~_cl~~ inician ~~_i~~ d": {""type": "integer’, "example": 104 }, 

- "space ~~_id~~ ": { "type": "integer", "example": 12, "description": "Patient bed/bay space ID for 

- Room- ~~t~~ o ~~-S~~ tore resolution" }, 

   - "urgency": { "type": "string", "enum": ["ROUTINE", "URGENT", "STAT"], "default": "ROUTINE" }, 

“clinical_indication": { "type": "string", "example": "Fever of unknown origin, rule out Malaria and Sepsis’ }, 

“package ~~_e~~ ntitlement_ ~~id~~ ": { "type": "string", "nullable": true, "example": "PKG ~~-~~ ANC ~~-0~~ 012" }, “tes ~~t_~~ requests: { "type": "array", “minitems": 1, "items": { "type": "object", "required": ["tes ~~t_~~ code", "tes ~~t_~~ name’], “properties: { "tes ~~t_~~ code": { "type": "string", "example": "CBC" }, “tes ~~t_~~ name": { "type": "string", "example": "Complete Blood Count" } } } } }, "additionalProperties": false } 

###### Success Response (201 Created) 

{ 

"status": "SUCCESS", "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", "lim ~~s_s~~ tatus": "ORDER ~~_R~~ ECEIVED", “workflow ~~_v~~ ersion": 1, "created ~~_a~~ t": "2026 ~~-0~~ 7 ~~-2~~ 5T14:30:002Z" 

} 

###### 2.2 Cross ~~-~~ Day Encounter Information Update 

- e Endpoint: PATCH /api/v1/lab/orders/{labOrderUuid}/updat ~~e~~ -encounter e Trigger: Returning outpatient receives a new daily visi ~~t_i~~ d at registration desk. 

###### Request Payload 

{ 

"la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", 

"new_ ~~vi~~ sit ~~_i~~ d": "VI ~~S-~~ 2026 ~~-0~~ 01399", "updated ~~_t~~ imestamp": "2026 ~~-~~ 07 ~~-~~ 26T08:00:002" 

} 

###### Success Response (200 OK) 

{ 

"status": "UPDATED", 

"la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", 

"active ~~_vi~~ sit ~~_i~~ d": "VI ~~S-~~ 202 ~~6-~~ 001399" 

} 

###### 2.3 Cancel Laboratory Order 

e Endpoint: POST /api/v1/lab/orders/{labOrderUuid}/cancel 

###### Request Payload 

{ 

- "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", 

"cancellatio ~~n_~~ reason ~~_c~~ ode": "CANCEL ~~_S~~ TRATEGY ~~_C~~ HANGE", 

"justificatio ~~n_~~ note": "Clinician changed treatment plan following ultrasound findings", "cancelled ~~_b~~ y ~~_u~~ ser_ ~~id~~ ": 104 

} 



##### 3. Outbound Webhook Contracts (LIMS — Clinical Module Proxy) 

LIMS dispatches webhooks to the Clinical Proxy Endpoint (POST /api/v1/clinical/l ~~ab~~ -proxy/{eventType}). 

###### 3.1 Workflow Status & Main Module Mapping Webhook 

e Endpoint: POST /api/v1/clinical/l ~~a~~ b-proxy/stat ~~us~~ -update 

###### Workflow Step to System Status Mapping 

|LIMSWorkflow Step Code|Derived LIMS Status|Derived Main Module<br>Status|
|---|---|---|
|PHLEBOTOMY|WAITIN~~G_~~COLLECTION|PENDING|
|SPECIMEN~~_R~~ECEPTION|COLLECTED|PENDING|
|ANALYZER~~_~~RUN /<br>MICROSCOPY|PROCESSING|I~~N_~~PROGRESS|



RESUL ~~T_~~ ENTRY PROVISIONAL I ~~N_~~ PROGRESS VALIDATION VALIDATED I ~~N_~~ PROGRESS RESUL ~~T_~~ RELEASE ARCHIVED COMPLETED 

###### Payload 

{ “event ~~_t~~ ype": "LA ~~B_~~ WORKFLOW_ ~~ST~~ ATUS_ ~~UP~~ DATE", 

"tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", “global_client ~~_i~~ d": "CL ~~-~~ O0001234", "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", “workflow ~~_s~~ tep ~~_c~~ ode": "ANALYZER ~~_R~~ UN", "lim ~~s_s~~ tatus": "PROCESSING", "main ~~_m~~ odule_ ~~st~~ atus": "I ~~N~~ PROGRESS", "executed ~~_b~~ y_ ~~us~~ er_ ~~id~~ ": 208, "timestamp": "2026 ~~-0~~ 7 ~~-2~~ 5T15:10:002Z" 

} 

###### 3.2 Critical Result Panic Alert Webhook 

- e Endpoint: POST /api/v1/clinical/la ~~b-~~ proxy/critical-result 

- e Trigger: Analyzer or scientist logs an extreme result breaching the tenant's critical threshold. 

###### Payload 

{ 

“event ~~_t~~ ype": "CRITICA ~~L_~~ RESULT ~~_P~~ ANIC ~~_A~~ LERT", "tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", "global_client ~~_i~~ d": "CL ~~-~~ 00001234", "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", "cde ~~_c~~ ode": "POTASSIUM ~~_S~~ ERUM", "tes ~~t_~~ name": "Serum Potassium", "“observed ~~_v~~ alue": 6.8, “unit_label": "mmol/L’, “critical_type": "HIGH ~~_P~~ ANIC", "tenan ~~t_f~~ inding ~~s_~~ code": "CRI ~~T_K_~~ HIGH ~~_6_5~~ ", “authorizing ~~_p~~ athologist_ ~~id~~ ": 305, "timestamp": "2026 ~~-~~ 07 ~~-2~~ 5T15:25:00Z" 

} 

###### 3.3 Result Validated Webhook (Final Lab Results Ingestion) 

e Endpoint: POST /api/v1/clinical/l ~~ab~~ -proxy/resul ~~t-~~ validated 

Payload 

{ 

"event ~~_t~~ ype": "LAB ~~R~~ ESUL ~~T~~ VALIDATED", "tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", "global_client ~~_i~~ d": "CL ~~-~~ 00001234", "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", "tes ~~t_~~ code": "CBC", “results”: [ { "cde ~~_c~~ ode": "HEMOGLOBIN", "tes ~~t_~~ name": "Hemoglobin", “valu ~~e_~~ numeric": 11.2, “unit_label": "g/dL, “uom_ ~~id~~ ": 10, "reference ~~_m~~ in": 12.0, "referenc ~~e_~~ max": 16.0, "i ~~s_~~ abnormal”: true, "has ~~_d~~ elta ~~_al~~ ert": false }, { "cde ~~_c~~ ode": "WBC ~~_C~~ OUNT", "tes ~~t_~~ name": "White Blood Cell Count", "valu ~~e_~~ numeric": 14.5, “unit_label": "x10*9/L", “uom_ ~~id~~ ": 13, "reference ~~_m~~ in": 4.0, "referenc ~~e_~~ max": 11.0, "i ~~s_~~ abnormal": true, "has ~~_d~~ elta ~~_al~~ ert": true } 1, "validated ~~_b~~ y ~~_u~~ ser ~~_id~~ ": 305, “digital_signature ~~_t~~ oken": "SI ~~G-~~ SHA256 ~~-a~~ 8f9e12c4b57", "timestamp": "2026 ~~-0~~ 7 ~~-2~~ 5T15:40:002Z" } 

###### 3.4 Reagent Consumption Fact Webhook 

###### (REAGEN ~~T_~~ CONSUMPTION ~~_F~~ ACT) 

- e Endpoint: POST /api/v1/clinical/l ~~a~~ b-reagen ~~t-~~ proxy 

- e Trigger: Laboratory test completes its designated Consumption Attribution Workflow Step (e.g., ANALYZER ~~_R~~ UN, CROSSMATCH). 



<!-- Start of picture text -->
——”<br><!-- End of picture text -->



<!-- Start of picture text -->
——’<br><!-- End of picture text -->

- e Routing Path: LIMS ——” Clinical Proxy ——’ Inventory Module Stock Ledger. 

- Resolution Hierarchy (LIMS v2.6 Addendum) 

Logged-In Scientist ID —+ Selected Platform Room ID —+ Mapped Inventory Sub-Store ID 

###### Payload 

{ 

"event ~~_t~~ ype": "REAGEN ~~T~~ CONSUMPTION_ ~~FA~~ CT", 

"tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", "la ~~b_~~ order ~~_u~~ uid": "LABORD- ~~2~~ 026- ~~0~~ 08551", "tes ~~t_~~ code": "CBC", “executed ~~_w~~ orkflow_ ~~st~~ ep": "ANALYZER ~~_R~~ UN", "scientis ~~t_u~~ ser ~~_id~~ ": 208, 

"platfor ~~m_~~ room_ ~~id~~ ": 14, “originatin ~~g_~~ inventory ~~_s~~ tore_ ~~id~~ ": "STOR ~~E-~~ LA ~~B-~~ HEMATOLOGY- ~~A"~~ , "“consumption_ ~~cl~~ assification": "PATIEN ~~T~~ TEST", "timestamp": "2026 ~~-0~~ 7 ~~-2~~ 5T15:15:00Z", “recipe ~~_i~~ tems’: [ { “inventory ~~_s~~ ku": "REAG ~~-S~~ YS ~~-L~~ YSE ~~-5~~ 00ML', "microlit ~~er~~ _volum ~~e_~~ deducted": 250 }, { “inventory ~~_s~~ ku": "REAG- ~~S~~ YS- ~~DI~~ LUENT-2 ~~0~~ L'’, "microlit ~~er~~ _volum ~~e_~~ deducted": 1200 

} ] 

} 

##### 4. Production Service Implementations (Laravel 10+) 

###### 4.1 Inbound/Outbound Clinical Proxy Handler (LimsIntegrationProxyService.php) 

namespace App\Services\Clinical\Integration; 

use Illuminate\Support\Facades\Http; use Illuminate\Support\Facades\DB; use App\Services\Clinical\CdeExecutionEngine; use App\Services\Clinical\ConsumptionEventBroker; use Exception; use Log; 

###### class LimsIntegrationProxyService 

{ 

protected CdeExecutionEngine $cdeEngine; 

protected ConsumptionEventBroker $eventBroker; 

public function ~~_c~~ onstruct(CdeExecutionEngine $cdeEngine, ConsumptionEventBroker $eventBroker) 

{ 

$thi ~~s-~~ >cdeEngine = $cdeEngine; 

$thi ~~s-~~ >eventBroker = $eventBroker; 

} 

[** 

* 1. DISPATCH OUTBOUND ORDER FROM CLINICAL TO LIMS */ 

public function dispatchOrderToLims(array $orderPayload): array 

{ $limsUrl = config(‘services.lims.url’) . '/api/v1/lab/orders'; $secret = config(‘services.lims.secret’); 

$body = jso ~~n_~~ encode($orderPayload); 

$signature = hash ~~_h~~ mac(‘sha256;, $body, $secret); 

$response = Http::withHeaders([ 

‘Authorization’ => ‘Bearer ' . config(‘services.lims.token’), 

' ~~X-~~ KashTre ~~-S~~ ignature' => $signature, 'X-ldempotency ~~-K~~ ey' => $orderPayload['lab ~~_o~~ rder_ ~~uu~~ id'], ‘Content ~~-T~~ ype’ => ‘application/json’, 

}) ~~-~~ >timeout(5) ~~-~~ >retry(3, 1000) 

~~-~~ >post($limsUrl, $orderPayload); 

if ($response- ~~>f~~ ailed()) { 

Log::error("LIMS Order Dispatch Failed for UUID {$0rderPayload['lab_ ~~or~~ der_ ~~uui~~ d']}’, [ ‘status’ => $response- ~~>s~~ tatus(), ‘response’ => $response ~~->~~ body() 

}); 

throw new Exception("LIMS Gateway Communication Error: HTTP ". $response- ~~>s~~ tatus()); 

} 

return $response- ~~>j~~ son(); 

} 

[** 

* 2. INGEST VALIDATED RESULT WEBHOOK FROM LIMS */ 

public function processValidatedResultWebhook(array $payload): void 

{ 

DB::transaction(function () use ($payload) { 

$tenantid = $payload['tenant_ ~~id~~ ’]; $patientld = $payload['global_client_ ~~id~~ ']; $visitld = $payload|'visit_ ~~id~~ ’]; 

foreach ($payload['results'] as $res) { 

// Map LIMS Unit Label to Master VOM ID $uomld = DB::table(‘clinical_ uom ~~_m~~ aster’) 

~~-~~ >where(‘tenant_ ~~id~~ ', $tenantld) 

~~-~~ >where(‘unit_label'’, $res['unit_label']) 

~~-~~ >value(‘id') ?? $res['uom_ ~~id~~ ']; 

// Execute Atomic CDE Observation Capture 

$thi ~~s-~~ >cdeEngine ~~-~~ >captureObservation([ 

‘patient ~~_i~~ d' => $patientld, 

'visi ~~t_i~~ d' => $visitld, 

‘cd ~~e_~~ code' => $res['cde ~~_c~~ ode'], 

‘valu ~~e_~~ numeric’ => $res['valu ~~e_n~~ umeric’], 

‘inpu ~~t_~~ uom ~~_i~~ d' => $uomld, ‘captur ~~e_~~ method' => 'IMPORTED ~~_D~~ ATA’, 

‘validatio ~~n_s~~ tatus' => 'VALIDATED' 

], $payload['validated ~~_b~~ y_ ~~us~~ er_ ~~id~~ '], $tenantld); 

} 

// Advance Clinical Order Tracking Status 

DB::table(‘clinical_work ~~_o~~ rders’) 

- >where(‘tenant_ ~~id~~ ', $tenantld) 

- >where(‘patient_ ~~id~~ ’, $patientld) 

- >where(‘order ~~_ty~~ pe’, ‘LAB _'. $payload['test ~~_c~~ ode’]) 

- >update(['status' => ‘COMPLETED’, 'completed ~~_a~~ t' => now()]); 

}); 

} 

/[** 

* 3. INGEST & FORWARD REAGENT CONSUMPTION FACT TO INVENTORY 

*/ 

public function processReagentConsumptionProxy(array $payload): void 

{ 

foreach ($payload|['recipe ~~_i~~ tems'] as $item) { 

$th ~~i~~ s->even ~~tB~~ roker->emitConsum ~~p~~ tionFact(CONSUMPTION ~~‘LF~~ ACT’,AB_ [ 

- ‘patient ~~_i~~ d' => $payload['global_client_ ~~id~~ '] ?? null, 

- ‘visi ~~t_i~~ d' => $payload['visit ~~_id~~ '] ?? null, 

- ‘item ~~_s~~ ku' => $item['inventory ~~_s~~ ku'], 

‘quantity’ => $item['microlit ~~er~~ _volum ~~e_~~ deducted'], 

‘sub ~~_s~~ tore ~~_i~~ d' => $payload|'originating ~~_i~~ nventory ~~_s~~ tore ~~_id~~ ', 

], $payload['tenant_ ~~id~~ ']); 

} 

} 

} 

###### 4.2 Webhook Receiving Controller (LimsWebhookController.php) 

namespace App\Http\Controllers\Api\v1; 

use App\Http\Controllers\Controller; use Illuminate\Http\Request; use App\Services\Clinical\Integration\LimsIntegrationProxyService; use Symfony\Component\HttpFoundation\Response; 

class LimsWebhookController extends Controller 

{ 

protected LimsIntegrationProxyService $limsProxy; 

public function ~~_c~~ onstruct(LimsIntegrationProxyService $limsProxy) 

{ $thi ~~s-~~ >limsProxy = $limsProxy; 

} 

[** 

* Route: POST /api/v1/clinical/l ~~ab~~ -proxy/resul ~~t-~~ validated */ 

public function handleValidatedResult(Request $request): Response { 

$thi ~~s-~~ >verifyHmacSignature($request); 

$thi ~~s-~~ >limsProx ~~y-~~ >processValidatedResultWebhook($request-> ~~al~~ l()); 

return response()- ~~>~~ json(['status' => ‘PROCESSED’, 'timestamp' => now() ~~->~~ tolso8601String()]); 

} 

[** 

* Route: POST /api/v1/clinical/l ~~a~~ b-reagen ~~t-~~ proxy */ 

public function handleReagentConsumption(Request $request): Response 

{ 

$thi ~~s-~~ >verifyHmacSignature($request); 

$thi ~~s-~~ >limsProx ~~y-~~ >processReagentConsumptionProxy($request-> ~~al~~ l()); 

return response()- ~~>~~ json(['status' => ‘PROCESSED’, ‘timestamp’ => now() ~~->~~ tolso8601String()]); 

} 

private function verifyHmacSignature(Request $request): void 

{ 

$providedSig = $request ~~->~~ header('X- ~~K~~ ashTre- ~~Si~~ gnature’); 

$calculatedSig = hash ~~_h~~ mac('sha25é6, $request ~~->~~ getContent(), 

config(‘services.lims.secret'’)); 

if (hash ~~_e~~ quals($calculatedSig, (string) $providedSig)) { abort(401, 'INVALI ~~D_~~ HMAC ~~_S~~ IGNATURE’); 

} 

} 

} 

##### 5. Summary Route & Webhook Endpoint Matrix 

HTTP Method Route Endpoint Payload Direction Primary Service/ 

|||||Action|
|---|---|---|---|---|
|POST|/api/v1/lab/orders|Clinical|+ LIMS|Dispatchesnew<br>diagnostic order<br>with CDE requests.|
|PATCH|fapilvWlablordersiu<br>uid}/updat~~e-~~encoun<br>ter|Clinical|> LIMS|[SeesUSSU<br>visi~~t_i~~d for returning<br>outpatients.|
|POST|fapiNWlablordersiu<br>uid}/cancel|Clinical|—> LIMS|Cancelsorderwith<br>reasoncodefrom<br>dictionary.|
|POST|/api/v1/clinical/lab-p<br>roxy/statu~~s-~~update|LIMS<br>Proxy|+ Clinical|Receivesworkflow<br>step progress &<br>.<br>updates Main<br>Module status.|
|POST|fapiiviclinical/lab-p<br>roxy/critical-result|LIMS<br>Proxy|+ Clinical|ingestspanic result<br>&triggers<br>.<br>.<br>visual/auditory<br>sirens.|
|POST|sejeLWMelimes IE S-f9<br>roxy/result-validate<br>d|LIMS<br>Prox<br>y|+ Clinical|GES Velleeiesel<br>results intoatomic<br>CDE database.|
|POST|/api/v1/clinical/lab~~-~~r<br>eagent~~-~~proxy|LIMS —*<br>oe<br>¥|SAR.<br><br>Cl<br>|<br>Clinical|Proxies<br>REAGEN~~T ~~CONSU<br>MPTION~~_F~~ACTto<br>Inventory ledger.|



