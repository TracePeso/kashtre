### Interface Control Document & Cros ~~s-~~ Module Integratione Blueprinte 



<!-- Start of picture text -->
7<br><!-- End of picture text -->

7 Integration Domain: Clinical Orchestrator (CLINICA ~~L_~~ ORCHESTRATOR) Radiology Information System (RI ~~S_~~ CORE) / Picture Archiving and Communication System (PACS) 

###### Specification Version: |CD ~~-v~~ 1. ~~0-~~ Production ~~-~~ Master 

Target Platform: Laravel Native v10+ / PHP 8.2+ / MySQL 8.0+ / Asynchronous Webhooks / DICOM C ~~-F~~ IND & C ~~-~~ STORE 

Compliant Standard Baseline: KashTre RIS SRD v2.5/ RIS Engineering Addendum v2.6 / Clinical SRD v6.0 

Status: Approved Engineering Integration Blueprint 

##### Executive Architectural Boundary Mandate 

To maintain strict domain isolation and protect business ledgers from radiology execution traffic: 

1. The Direct Communication Ban: The RIS/PACS module shall NEVER communicate directly with the Main Module or Inventory Module for any transactional, status, or stock depletion event. 

2. The Clinical Proxy Layer: All inbound imaging orders, status callbacks, DICOM readiness tokens, critical finding alerts, and radiology consumable facts pass asynchronously through the Clinical Module proxy layer. 

3. The Workflow Source of Truth: RIS execution is strictly workflow ~~-d~~ riven (RIS v2.6). The Clinical Module receives workflow step progress events and translates RIS internal workflow states to Main Module business statuses (PENDING, I ~~N.~~ PROGRESS, COMPLETED). 

~~<u>ss</u> __oo os~~ | CLINICAL ORCHESTRATOR | | (Central Operational Router, Proxy Gateway & Identity Anchor) | ~~$$ SsSsSsss~~ / ~~\——SSS~~ Inbound API | 1. Create Imaging Order (POST /api/v1/imaging/orders) | Outbound Webhooks 

Dispatches | 2. Cancel Imaging Order (POST /api/v1/imaging/cancel) —_| (MWL Ready, PACS C ~~-~~ STORE, | 3. Encounter Update (PATCH /api/v1/imaging/encounter) | Critical Findings, Consumables) v | 

~~<u>oo,</u> te~~ | RIS / PACS DIAGNOSTIC ENGINE | | (Modality Worklist C ~~-F~~ IND, Scanner Console, PACS Archiving, Radiologist Reports) | 

# ~~<u>Pe</u> —~~ 

#### 1. Identity Management & Structural System Hierarchy 

All diagnostic imaging transactions bind strictly to a 4 ~~-t~~ ier identity tree: 

|IdentityKey|Format Pattern|Owning Origin|Lifetime&Scope|
|---|---|---|---|
|global_client~~_i~~d|CL~~-~~00001234|Main Module|Permanent lifetime<br>profile key<br>anchoring patient<br>imaging history<br>across all visits.|
|visi~~t_i~~d|VI~~S-~~202~~6-~~001245|Main Module|Encounter<br>transaction key.<br>Expires at midnight<br>for outpatients;<br>locked for admitted<br>inpatients.|
|imaging~~_s~~tudy~~_u~~ui<br>d|IMGORD~~-2~~026~~-~~009 —<br>412|Clinical Module|Unique imaging<br>order reference<br>generated upon<br>order sign~~-o~~ff.|
|accessio~~n_~~number|ACC~~-2~~026~~-~~004810|RIS Module|Distinct<br>DICOM-~~c~~ompliant<br>lookup index|



matching physical machine consoles & PACS series. 

###### 1.1 Cross ~~-~~ Day Encounter Inheritance Protocol for Imaging 

When an outpatient has a pending imaging order (ORDER ~~_R~~ ECEIVED / PREPARATION ~~_~~ REQUIRED) ordered on Day 1, but returns on Day 2 with a fresh daily visi ~~t_i~~ d: 

- 1 ~~.~~ The Clinical Module detects encounter creation via EncounterObserver. 2 ~~.~~ The Clinical Module dispatches PATCH /api/v1/imaging/orders/{imagingStudyUuid}/updat ~~e-~~ encounter to RIS. 

- 3 ~~.~~ RIS updates the study's visi ~~t_i~~ d to today's active key without recreating the accession number or invalidating pre ~~-~~ scan preparation checklists. 



###### 2. Inbound REST API Contracts (Clinical Module — 

###### RIS) 

All inbound API requests from the Clinical Module to RIS include the Authorization: Bearer {RI ~~S_~~ SERVIC ~~E_~~ TOKEN} header and X ~~-~~ KashTre ~~-S~~ ignature HMAC ~~-S~~ HA256 token. 

###### 2.1 Create Imaging Order 

- e Endpoint: POST /api/v1/imaging/orders e Trigger: Clinician approves an imaging request in the Clinical Module chart workspace. 

###### Request Payload 

{ "$schema": 

"[https://js ~~o~~ n-schema.org/draft/202 ~~0-~~ 12/schema](https://js ~~on~~ -schema.org/draft/2020 ~~-~~ 12/schem a)", 

"title": "CreatelmagingOrderPayload", 

"type": "object", 

"required": ["tenant ~~_i~~ d", "global_client ~~_i~~ d", "visit ~~_i~~ d", "imaging ~~_s~~ tudy ~~_u~~ uid", "protocol_code", “ordering ~~_cl~~ inician ~~_i~~ d", "space_ ~~id~~ "], 

“oroperties": { 

- "tenant ~~_i~~ d": { "type": "string", "example": "FACILIT ~~Y_~~ ALPHA" }, 

- “global_client ~~_i~~ d": { "type": "string", "example": "CL ~~-~~ 00001234' }, 

- "visi ~~t_i~~ d": { "type": "string", "example": "VI ~~S-~~ 202 ~~6-~~ 001245" }, 

- “imaging ~~_s~~ tudy ~~_u~~ uid": { "type": "string", "example": "IMGORD ~~-2~~ 026 ~~-0~~ 09412" }, 

“protocol_code": {"type": "string", "example": "C ~~T_~~ AB ~~D_~~ CONT" }, 

“protocol_name": { "type": "string", "example": "CT Abdomen With Contrast" }, "modality": 1 "type": "string", "enum": ["CT", "MR", "XA" "US", "DX", "MG", "example": "CT" }, 

“ordering ~~_cl~~ inician ~~_i~~ d": {"type": "integer", "example": 104 }, 

"space ~~_id~~ ": { "type": "integer", "example": 12, "description": "Patient location space ID for room/store resolution" }, 

"urgency": { "type": "string", "enum": ["ROUTINE", "URGENT", "STAT"], "default": "ROUTINE" }, 

“clinical_indication": { "type": "string", "example": "Right lower quadrant pain, rule out acute appendicitis’ }, 

“creatinin ~~e_c~~ leared": { "type": "boolean", "default": false }, 

“pregnancy ~~s~~ tatus’: { "type": "string", "enum": ["NEGATIVE", "POSITIVE", "NOT ~~_A~~ PPLICABLE"], "default": "NEGATIVE" } 

}, 

"additionalProperties": false 

} 

###### Success Response (201 Created) 

{ 

"status": "SUCCESS", 

“imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", "“accessio ~~n_~~ number": "ACC ~~-2~~ 026 ~~-0~~ 04810", 

"ri ~~s_~~ status": "ORDER ~~_R~~ ECEIVED", “workflow ~~_v~~ ersion": 1, 

"“created ~~_a~~ t": "2026 ~~-~~ 07 ~~-~~ 25T16:00:00Z" 

} 

###### 2.2 Cross ~~-~~ Day Encounter Information Update 

e Endpoint: PATCH /api/v1/imaging/orders/{imagingStudyUuid}/updat ~~e-~~ encounter 

###### Request Payload 

{ 

“imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", 

"new_ ~~vi~~ sit ~~_i~~ d": "VI ~~S-~~ 2026 ~~-0~~ 01399", 

"updated ~~_t~~ imestamp": "2026 ~~-~~ 07 ~~-~~ 26T08:00:002" 

} 

###### 2.3 Cancel Imaging Order 

e Endpoint: POST /api/v1/imaging/orders/{{imagingStudyUuid}/cancel 

###### Request Payload 

{ 

“imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", 

"cancellatio ~~n_~~ reason ~~_c~~ ode": "CANCEL ~~_S~~ TRATEGY ~~_C~~ HANGE", 

"justificatio ~~n_~~ note": "Ultrasound findings already confirmed diagnosis", "cancelled ~~_b~~ y ~~_u~~ ser_ ~~id~~ ": 104 

} 



<!-- Start of picture text -->
—<br><!-- End of picture text -->

## 3. Outbound Webhook Contracts (RIS/PACS — Clinical Module Proxy) 

RIS dispatches webhooks to the Clinical Proxy Endpoint (POST /api/v1/clinical/imagin ~~g-~~ proxy/{eventType}). 

###### 3.1 Workflow Status & Main Module Mapping Webhook 

e Endpoint: POST /api/v1/clinical/imagi ~~ng~~ -proxy/statu ~~s-~~ update 

###### Workflow Step to System Status Mapping (RIS v2.6 Addendum) 

|RISWorkflow Step Code|Derived RIS Status|Derived Main Module<br>Status|
|---|---|---|
|PATIEN~~T _~~PREPARATION|PREPARATION~~_~~REQUIRED|PENDING|
|READY~~_~~FOR~~_S~~TUDY|READY~~_~~FOR~~_S~~TUDY|PENDING|
|SCAN~~_E~~XECUTION|I~~N_~~PROGRESS|I~~N_~~PROGRESS|
|IMAGE~~_~~QA|IMAGE~~_~~ACQUIRED|I~~N_~~PROGRESS|
|REPORTING|REPOR~~T_~~PENDING|I~~N_~~PROGRESS|
|CERTIFICATION|VERIFIED|COMPLETED|



###### Payload 

{ "“even ~~t_~~ type": "IMAGIN ~~G_~~ WORKFLOW_ ~~ST~~ ATUS_ ~~UP~~ DATE", 

- "tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", 

- “imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", 

"accessio ~~n_~~ number": "ACC ~~-2~~ 026 ~~-0~~ 04810", 

"global_client ~~_i~~ d": "CL ~~-~~ 00001234", 

- "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", 

"workflow ~~_s~~ tep ~~_c~~ ode": "SCAN ~~E~~ XECUTION", 

"ri ~~s_~~ status": "I ~~N_~~<sup>PROGRESS",</sup> 

"main ~~_m~~ odule_ ~~st~~ atus": "I ~~N.~~ PROGRESS", "executed ~~_b~~ y_ ~~us~~ er_ ~~id~~ ": 112, "timestamp": "2026 ~~-~~ 07 ~~-2~~ 5T16:20:00Z" 

} 

###### 3.2 DICOM Modality Worklist (MWL) & PACS C ~~-~~ STORE Completion Webhook 

- e Endpoint: POST /api/v1/clinical/imagi ~~n~~ g-proxy/pa ~~cs~~ -cstor ~~e~~ -complete 

- e Trigger: PACS receives DICOM image slices from gantry and validates checksum (PAC ~~S_C_~~ STORE ~~_C~~ OMPLETE). 

###### Payload 

{ 

"event ~~_t~~ ype": "PAC ~~S_C_~~ STORE ~~_C~~ OMPLETE", 

"tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", “imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", "“accessio ~~n_~~ number": "ACC ~~-2~~ 026 ~~-0~~ 04810", “global_client ~~_i~~ d": "CL ~~-~~ 00001234", "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", "serie ~~s_~~ instance ~~_u~~ id": "1.2.840.113619.2.55.3.28311512", 

“image ~~_c~~ ount": 420, "pacs ~~_v~~ iewer_ ~~ur~~ l": "[https://pacs.kashtre.local/viewer/viewer.html?studyUID=1.2.840.113619.2.55.3.28311512](https:// pacs.kashtre.local/viewer/viewer.html?studyUID=1.2.840.113619.2.55.3.28311512)", 

"timestamp": "2026 ~~-~~ 07 ~~-2~~ 5116:25:00Z" 

} 

###### 3.3 Critical Findings Alert Webhook 

- e Endpoint: POST /api/v1/clinical/imaging ~~-~~ proxy/critical-finding 

- e Trigger: Radiologist flags a lif ~~e~~ -threatening condition (e.g., Intracranial Bleed, Pneumothorax, Pulmonary Embolus). 

###### Payload 

{ 

"event ~~_t~~ ype": "CRITICA ~~L_~~ FINDING ~~_~~ ALERT", 

"tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", 

- "imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", 

"“accessio ~~n_~~ number": "ACC ~~-2~~ 026 ~~-0~~ 04810", 

"global_client ~~_i~~ d": "CL ~~-O~~ 00001234", 

"visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", 

"findin ~~g_~~ code": "INTRACRANIAL ~~_B~~ LEED", 

"finding_label": "Acute Epidural Hematoma Identified", 

“reporting ~~_r~~ adiologist ~~_id~~ ": 402, 

"radiologis ~~t_~~ name": "Dr. S. Bangura", 

"urgen ~~t_~~ acti ~~on~~ _recommended": "Immediate neurosurgical consultation required", "timestamp": "2026 ~~-~~ 07 ~~-2~~ 5T16:40:002" 

} 

###### 3.4 Validated Radiology Report Ingestion Webhook 

e Endpoint: POST /api/v1/clinical/imagin ~~g~~ -proxy/report ~~-~~ validated 

###### Payload 

{ 

“event ~~_t~~ ype": "RADIOLOGY ~~_R~~ EPORT ~~_ V~~ ALIDATED", 

"tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", 

"imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", 

"accessio ~~n_~~ number": "ACC ~~-2~~ 026 ~~-0~~ 04810", 

"global_client ~~_i~~ d": "CL ~~-~~ 00001234", 

"visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", "orotocol_code": "C ~~T~~ ABD ~~_C~~ ONT", "report ~~_v~~ ersion": 1, 

"structured ~~_p~~ ayload": { “clinical_history": "Right lower quadrant pain", "technique": "Axial CT images of abdomen and pelvis acquired with IV contrast", "findings": "Inflamed aperistaltic appendiceal structure measuring 11mm in diameter with surrounding fat stranding.", 

"impression": "Acute uncomplicated appendicitis.", 

"icd1 ~~1_~~ candidate ~~_c~~ ode": "DB10.0" 

}, “author ~~_r~~ adiologist ~~_id~~ ": 402, 

"digital_signature ~~_t~~ oken": "SI ~~G-~~ SHA256 ~~-f~~ 912c4b57e09", "timestamp": "2026 ~~-~~ 07 ~~-2~~ 5116:45:002" 

} 

###### 3.5 Radiology Consumption Fact Webhook (RADIOLOG ~~Y_~~ CONSUMPTION ~~_F~~ ACT) 

- e Endpoint: POST /api/v1/clinical/radiol ~~og~~ y-consumptio ~~n-~~ proxy 

- e Trigger: Imaging procedure completes its designated Consumption Attribution 

Workflow Step (e.g., CONTRAST ~~_A~~ DMINISTRATION, SCAN ~~_E~~ XECUTION, RECOVERY ~~_C~~ OMPLETED). 





e Routing Path: RIS ~~ Clinical Proxy ——* Inventory Module Stock Ledger. Resolution Hierarchy (RIS v2.6 Addendum) 

Logged-In Radiographer ID —> Selected Platform Room ID —+ Mapped Inventory Sub-Stoi 

###### Payload 

{ 

"event ~~_t~~ ype": "RADIOLOGY ~~_C~~ ONSUMPTION_ ~~FA~~ CT", “tenant ~~_i~~ d": "FACILIT ~~Y_~~ ALPHA", "imaging ~~_s~~ tudy ~~_u~~ uid": "IMGORD- ~~2~~ 026- ~~0~~ 09412", "protocol_code": "C ~~T~~ ABD ~~_C~~ ONT", "executed ~~_w~~ orkflow_ ~~st~~ ep": "CONTRAST ~~_A~~ DMINISTRATION", "radiographer ~~_u~~ ser_ ~~id~~ ": 112, "platfor ~~m_~~ room_ ~~id~~ ": 18, "originatin ~~g_~~ inventory ~~_s~~ tore_ ~~id~~ ": "STORE ~~-R~~ AD- ~~CT~~ - ~~SU~~ ITE- ~~1"~~ , “consumption_ ~~cla~~ ssification": "PATIEN ~~T.~~ PROCEDURE", "timestamp": "2026 ~~-0~~ 7- ~~2~~ 5T16:22:002Z", “recipe ~~_i~~ tems’: [ { "inventory ~~_s~~ ku": "CONT-IOPAMIDOL- ~~3~~ 00- ~~1~~ 00ML", “quantit ~~y_~~ deducted": 1.0000 }, { “inventory ~~_s~~ ku": "KIT- ~~IV~~ -CANNULA ~~-1~~ 8G", "quantit ~~y_~~ deducted": 1.0000 }, { "inventory ~~_s~~ ku": "SY ~~R-~~ HIGH ~~-~~ PRESSURE-INJECTOR’, "quantit ~~y_~~ deducted": 1.0000 } ] } 

##### 4. Production Service Implementations (Laravel 10+) 4.1 Inbound/Outbound Clinical Proxy Handler 

###### (RisIntegrationProxyService.php) 

namespace App\Services\Clinical\Integration; 

use Illuminate\Support\Facades\Http; use Illuminate\Support\Facades\DB; use App\Services\Clinical\ConsumptionEventBroker; use Exception; use Log; 

class RisIntegrationProxyService 

{ 

protected ConsumptionEventBroker $eventBroker; 

public function ~~_c~~ onstruct(ConsumptionEventBroker $eventBroker) 

{ 

$thi ~~s-~~ >eventBroker = $eventBroker; 

} 

/[** 

* 1. DISPATCH OUTBOUND IMAGING ORDER FROM CLINICAL TO RIS 

*/ 

public function dispatchOrderToRis(array $orderPayload): array 

{ 

$risUrl = config(‘services.ris.ur!’) . ‘/api/v1/imaging/orders’; $secret = config('services.ris.secret’); 

$body = jso ~~n_~~ encode($orderPayload); $signature = hash ~~_h~~ mac('sha256, $body, $secret); 

$response = Http::withHeaders([ 

‘Authorization’ => ‘Bearer '. config(‘services.ris.token’), 

' ~~X-~~ KashTre ~~-S~~ ignature’ => $signature, 

"X-Idempotency ~~-K~~ ey' => $orderPayload['imaging_ ~~st~~ udy_ ~~uu~~ id'], 

‘Content ~~-T~~ ype’ => ‘application/json’, 

}) ~~-~~ >timeout(5) ~~-~~ >retry(3, 1000) 

~~-~~ >post($risUrl, $orderPayload); 

###### if ($response- ~~>f~~ ailed()) { 

Log::error("RIS Order Dispatch Failed for UUID {$orderPayload|'imaging_ ~~st~~ udy_ ~~uui~~ d']}’, [ ‘status’ => $response- ~~>s~~ tatus(), 

‘response’ => $response ~~->~~ body() 

)); throw new Exception("RIS Gateway Communication Error: HTTP". $response- ~~>s~~ tatus()); 

} 

return $response- ~~>j~~ son(); 

} 

[** 

* 2. INGEST VALIDATED RADIOLOGY REPORT WEBHOOK FROM RIS */ 

public function processValidatedReportWebhook(array $payload): void 

{ 

DB::transaction(function () use ($payload) { 

$tenantid = $payload['tenant_ ~~id~~ ']; 

$patientld = $payload['global_client_ ~~id~~ ']; 

$visitld = $payload['visit_ ~~id~~ ']; 

// Store Dynamic Impression as CDE Observation Note 

DB::table(‘cde ~~_o~~ bservations')- ~~>~~ insert([ 

‘tenant ~~_i~~ d' => $tenantld, 

‘patient ~~_i~~ d' => $patientld, 

‘visi ~~t_i~~ d' => $visitld, 

‘cd ~~e_~~ code' => 'RAD_IMPRESSION _'. $payload|'protocol_code'], ‘captured ~~_v~~ alue ~~_t~~ ext' => $payload['structured ~~_p~~ ayload'][impression’], 

‘inpu ~~t_~~ uom ~~_i~~ d' => 1, // Default TEXT UOM 

‘pas ~~e_~~ uom_ ~~id~~ ' => 1, 

‘captur ~~e_~~ method' => 'IMPORTED ~~_D~~ ATA’, 

‘validatio ~~n_s~~ tatus' => 'VALIDATED’, ‘validate ~~d_b~~ y ~~_u~~ ser ~~_id~~ ' => $payload[‘author_ ~~ra~~ diologist_ ~~id~~ '], ‘captured ~~_a~~ t' => now(), 

}); 

// Complete Work Order 

DB::table(‘clinical_work ~~_o~~ rders’) 

- >where(‘tenant_ ~~id~~ ’, $tenantid) 

- >where(‘patient_ ~~id~~ ', $patientid) 

- >where(‘order ~~t~~ ype’, ‘RAD _'. $payload['protocol_code’']) 

- >update(['status' => ‘COMPLETED’, 'completed ~~_a~~ t' => now()]); 

}); 

} 

[** 

* 3. INGEST & FORWARD RADIOLOGY CONSUMPTION FACT TO INVENTORY 

*/ 

public function processRadiologyConsumptionProxy(array $payload): void 

{ 

foreach ($payload['recipe ~~_i~~ tems'] as $item) { 

$th ~~i~~ s->event ~~B~~ roker->emitConsumption ~~Fa~~ CONSUMPTIONct((RADIO ~~LO_~~ FACT;,GY_ [ 

‘patient ~~_i~~ d' => $payload['global_client_ ~~id~~ '] ?? null, 

‘visi ~~t_i~~ d' => $payload|'visit ~~_id~~ '] ?? null, 

‘jtem ~~_s~~ ku' => $item['inventory ~~_s~~ ku'], 

‘quantity’ => $item['quantit ~~y_~~ deducted'], ‘sub ~~_s~~ tore ~~_i~~ d' => $payload|'originating ~~_i~~ nventory_ ~~st~~ ore_ ~~id~~ '], 

1, $payload['tenant_ ~~id~~ ']); 

} 

} 

} 

###### 4.2 Webhook Receiving Controller (RisWebhookController.php) 

namespace App\Http\Controllers\Api\Wv1; 

use App\Http\Controllers\Controller; use Illuminate\Http\Request; use App\Services\Clinical\Integration\RisIntegrationProxyService; use Symfony\Component\HttpFoundation\Response; 

###### class RisWebhookController extends Controller 

{ 

protected RisIntegrationProxyService $risProxy; 

public function ~~_co~~ nstruct(RislntegrationProxyService $risProxy) 

{ 

$this ~~-~~ >risProxy = $risProxy; 

} 

[** 

* Route: POST /api/v1/clinical/imagin ~~g~~ -proxy/report ~~-~~ validated 

*/ 

public function handleValidatedReport(Request $request): Response 

{ 

$thi ~~s-~~ >verifyHmacSignature($request); 

$thi ~~s-~~ >risProx ~~y~~ ->processValidatedReportWebhook($request-> ~~al~~ l()); 

return response()- ~~>~~ json(['status' => ‘PROCESSED’, ‘timestamp’ => now() ~~->~~ tolso8601String()]): 

} 

[** 

* Route: POST /api/v1/clinical/radiol ~~og~~ y-consumptio ~~n-~~ proxy */ 

public function handleRadiologyConsumption(Request $request): Response 

{ 

$thi ~~s-~~ >verifyHmacSignature($request); 

$thi ~~s-~~ >risProx ~~y~~ ->processRadiologyConsumptionProxy($request->a ~~l~~ l()); 

return response()- ~~>~~ json(['status' => ‘PROCESSED’, ‘timestamp’ => now() ~~->~~ tolso8601String()]); 

} 

private function verifyHmacSignature(Request $request): void 

{ 

$providedSig = $request ~~->~~ header('X- ~~K~~ ashTre- ~~Si~~ gnature’); 

$calculatedSig = hash ~~_h~~ mac(‘sha25é6;, $request ~~->~~ getContent(), config('services.ris.secret’)); 

if (hash ~~_e~~ quals($calculatedSig, (string) $providedSig)) { 

abort(401, 'INVALI ~~D_~~ HMAC ~~_S~~ IGNATURE’); 

} 

} 

} 

##### 5. Summary Route & Webhook Endpoint Matrix 

|HTTP Method|Route Endpoint|Payload Direction|PrimaryService/<br>Action|
|---|---|---|---|
|POST|/api/v1/imaging/orde<br>rs|Clinical— RIS|Dispatchesnew)<br>diagnostic imaging<br>order with protocol<br>parameters.|
|PATCH|/api/v1/imaging/orde<br>.<br>rs/{uuid}/updat~~e-~~en<br>counter|we<br>——}<br>Clinical<br>RIS|Updates encounter<br>wu<br>.<br>visi~~t_i~~d for returning<br>outpatients.|



|POST|/api/v1/imaging/orde<br>.<br>rs/{uuid}/cancel|4<br>Clinical<br>RIS|Cancels studywith<br>reasoncodefrom<br>dictionary.|
|---|---|---|---|
|POST|/api/v1/clinical/imagi<br>ng-proxy/status-up<br>date|RIS<br>+ Clinical<br>Prox<br>¥|Receivesworkflow<br>stepprogress&<br>updatesMain<br>Module status.|
|POST|/api/v1/clinical/imagi<br>ng~~-p~~roxy/pacs~~-c~~sto<br>r~~e-~~complete|PACS<br>+ Clinical<br>Proxy|ReceivesHACE<br>C~~-~~STORE image<br>ee<br>availability<br>notification &<br>viewer URL.|
|POST|seo Helical)<br>ng-proxy/critical-fin<br>ding|RIS<br>+ Clinical<br>Prox<br>¥|Ingesiswget<br>criticalfindings&<br>triggerscallback<br>warning sirens.|
|POST|/api/v1/clinical/imagi<br>ng-proxy/report-val<br>idated|RIS<br>+ Clinical<br>Prox<br>¥|IngressLintelleste<br>diagnostic report<br>intopatient chart.|
|POST|/api/v1/clinical/radiol<br>ogy~~-~~consumption~~-~~<br>proxy|SAL.<br>in<br>Clinical<br>¥|Proxies<br>RADIOLOG~~Y_~~CONS<br>UMPTION~~_ ~~FACT to<br>Inventory ledger.|



