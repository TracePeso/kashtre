# Interface Control Document & Cros ~~s-~~ Module Al Integratione Blueprinte 

Integration Domain: Consuming Application Modules (CLINICA ~~L_~~ ORCHESTRATOR, LIM ~~S_~~ CORE, RI ~~S_~~ CORE, INVENTORY) Shared Al Services Gateway (Al GATEWAY) 

Specification Version: |CD ~~-A~~ l-v1. ~~0-~~ Productio ~~n-~~ Master 

Target Platform: Laravel Native v10+ / PHP 8.2+ / WebSockets (wss://) / REST JSON 

Compliant Standard Baseline: Al Gateway SRD v3.1 / Al Gateway Eng Spec v3.1 / Clinical SRD v6.0 / LIMS v2.6 / RIS v2.6 

Status: Approved Engineering Integration Blueprint 

##### Executive Architecture & Authentication Handshake 

Consuming application modules invoke Al capabilities exclusively through the Shared Al Gateway. Direct external API calls from modules to cloud LLM providers (e.g., direct OpenAl or Google Al calls) are strictly prohibited. 



<!-- Start of picture text -->
>—-—----sSS<br>ST _W_____,<br>| CONSUMING APPLICATION MODULES |<br>| (Clinical Orchestrator v6.0 | LIMS Core v2.6 | RIS/PACS Core v2.6) |<br>ey<br>Outbound API | Headers: | Inbound JSON<br>Dispatches | - Authorization: Bearer {MODULE _A PI_KEY} | Response Payload<br>| »X - Module - Code: CLINICAL | LIMS | RIS | (Extracted Intents,<br>| + X - Request-ID: {UUIDv4} | CDE Tokens, ICD -1 1)<br>v |<br>oo,<br>| SHARED Al SERVICES GATEWAY |<br>| (Auth Verification, ZDR Headers, Provider Router, Failover & Audit Logging) |<br><!-- End of picture text -->

~~ey~~ 

v | [ Hig ~~h-~~ Speed Local Edge GPU ] [ Encrypted Enterprise Cloud VNet ] (Local Whisper STT ~~-~~ Sub ~~-~~ 100ms) (Azure OpenAl - ZDR Enforced) 

###### Authentication Headers 

Every request dispatched from an application module to the Al Gateway must include: 

- e Authorization: Bearer {MODULE ~~_A~~ PI_KEY ~~_~~ HASH} 

- e X ~~-~~ Module ~~-~~ Code: {MODULE ~~_C~~ ODE} (e.g., CLINICA ~~L_~~ ORCHESTRATOR, LIM ~~S_~~ CORE, RI ~~S_~~ CORE) 

- e X ~~-~~ Request-ID: {UUIDv4} 

### 1. Clinical Orchestrator Integration Workflows 

###### 1.1 Bedside Speech ~~-t~~ o ~~-T~~ ext (STT) Voice Dictation 

- e Protocol: Persistent WebSocket (wss://gateway.kashtre.local/v1/stt/stream) 

- e Trigger: Clinician taps the microphone icon on mobile/tablet terminal during rounds. 

- e Execution: Streams raw PCM16 audio chunks; receives su ~~b-~~ 100ms streaming text tokens to render live on ~~-~~ screen text. 

- e Output: Populates the Bedside Rough Notes Scratchpad as DRAFT ~~_T~~ EXT. 



<!-- Start of picture text -->
—><br><!-- End of picture text -->

###### 1.2 e e Rough Notes Observation Extraction 

### e e —> e Extraction Atomic CDE 

###### Pre ~~-~~ Population 

- e REST Endpoint: POST /api/v1/ai/extra ~~ct~~ -observations e Trigger: Clinician clicks "Extract Observations" from the Rough Notes Scratchpad. 

###### Request Payload 

{ 

"module ~~_c~~ ode": "CLINICAL ~~_~~ ORCHESTRATOR", 

- "patient ~~_i~~ d": "CL ~~-~~ 00001234", 

- "visi ~~t_i~~ d": "VI ~~S-~~ 2026 ~~-~~ 001245", 

"text": "Temperature 39.2 Celsius. Pulse 120 beats per minute. BP 90 over 60." 

} 

###### Response Payload & System Action 

- { "intent ~~_i~~ d": "inten ~~t-~~ obs ~~-9~~ 912", 

“observations”: [ 

{"cde ~~_c~~ ode": "TEMP ~~_A~~ XILLARY", "dataElement": "Temperature", "value": 39.2, "unit": "°C" }, 

{"cde ~~_c~~ ode": "PULSE ~~_R~~ ATE", "dataElement": "Pulse", "value": 120, "unit": "bpm" }, 

{"cde ~~_c~~ ode": "BLOOD ~~_P~~ RESSURE", "dataElement": "Blood Pressure’, "systolic": 90, "diastolic": 60, "unit": "mmHg" } 

], 

"requiresValidation": true 

} 

- e Clinical Module Action: Pre- ~~f~~ ills values into active ward CDE observation templates with high- ~~v~~ isibility input badges. Requires nurse/doctor click to Validate & Commit. 



<!-- Start of picture text -->
—-<br><!-- End of picture text -->

## 1.3 Clinicale e Intent Extractione —- Clinicale e Translator Enginee 

###### Handshake 

- e REST Endpoint: POST /api/v1/ai/extrac ~~t-~~ intent 

- e Trigger: Clinician dictates or types free ~~-t~~ ext order notes ("Continue ceftriaxone 1g IV daily. Order CBC tomorrow"). 

###### Pipeline Sequence 

[ Free ~~-T~~ ext Dictation ] ~~—-~~ > [ Al Gateway Intent Extractor] 

Vv 

[ Extracted Intent JSON (Item + Strength) ] 

Vv 

[ Clinician Review & Approval ] 

v [ Clinical Translator Engine ] 

Vv 

[ Matches Active Master Catalog Brand SKU ] 

###### Response Payload 

{ "intentld": "intent ~~-~~ 8812", "medications": [ 

{ "action": "continue", "item": "ceftriaxone", 

"strength": "1g", "dosage": "1g IV daily", "route": "IV", "frequency": "QD" } , ] "laboratoryOrders": [ {"item": "CBC", "priority": "routine", "schedule": "tomorrow" } 1, 

"requiresReview": true 

} 

- e Clinical Module Action: Approved generic intents (ceftriaxone, 1g) pass into the Clinical Translator Engine to evaluate alternati ~~ve~~ _names arrays and resolve the active billable brand SKU (i ~~s_~~ offe ~~r_~~ item = TRUE). 

###### 1.4 Universal ICD ~~-1~~ 1 Diagnostic Coding Assistance 

- e REST Endpoint: POST /api/v1/ai/icd ~~11~~ -suggest 

###### Request Payload 

{ 

"module ~~_c~~ ode": "CLINICA ~~L_~~ ORCHESTRATOR", 

"diagnosisText": "Patient presenting with acute high fever, chills, and positive thick blood smear for Plasmodium falciparum" 

} 

###### Response Payload 

{ 

"diagnosis": "Malaria", 

“suggestions’: [ 

{"icd11Code": "1F40.0", "description": "Plasmodium falciparum malaria’ }, 

{ "icd11Code": "1F40.00", "description": "Severe falciparum malaria with cerebral complications" } 

1, 

"requiresClinicianApproval": true 

} 

###### 1.5 Multi ~~-~~ Specialty Shift Handover Summarization 

- e REST Endpoint: POST /api/v1/ai/summariz ~~e-~~ observations 

- e Consuming Profiles: ICU Organ ~~-S~~ ystem, Surgical Drains/Wounds, NICU Feeds, Physician I-PASS. 

###### Request Payload 

{ 

"module ~~_c~~ ode": "CLINICA ~~L_~~ ORCHESTRATOR", 

"patient ~~_i~~ d": "CL ~~-~~ 00001234", "timeRange": "24h", "handover_ ~~pr~~ ofile": "IC ~~U_~~ ORGAN ~~_S~~ YSTEM" 

} 

###### Response Payload 

{ 

###### "summary": { 

"hemodynamic_ ~~st~~ atus": "Improving on low ~~-~~ dose Noradrenaline", 

"respiratory ~~_s~~ tatus": "Weaned to CPAP SpO2 98%", 

“renal_status": "Net fluid balance positive +450mL, Urine output 1.2mL/kg/hr" 

}, 

"narrative": "2 ~~4-~~ hour ICU summary: Patient hemodynamically stabilizing. Inotrope requirements weaning. Urine output adequate. No panic alerts logged during night shift.", "requiresReview": true 

} 

## 2. Laboratory Information Management System (LIMS) Integration 

###### 2.1 Lab Bench Voice Notes Transcription 

- e Protocol: POST /api/v1/ai/speech ~~-~~ to ~~-t~~ ext 

- e Scope: Bench scientists dictating urine microscopy, gram stain notes, or gross pathology observations hands ~~-f~~ ree at laboratory stations. 

###### 2.2 Automated Culture & Microbiology Narrative Summarization 

- e REST Endpoint: POST /api/v1/ai/summariz ~~e-~~ observations 

- e Scope: Aggregates automated analyzer colony counts and antibiogram sensitivity raw numbers into structured microbiology diagnostic impressions. 

### 3. Radiology Information System (RIS/PACS) 

##### Integration 

###### 3.1 Radiologist Structured Report Dictation 

- e Protocol: Streaming WebSocket (wss://gateway.kashtre.local/v1/stt/stream) 

- e Scope: Radiologist dictates diagnostic findings into reporting templates (Findings, Technique, Impression). 

###### 3.2 Preliminary Impression & ICD ~~-1~~ 1 Candidate Extraction 

- e REST Endpoint: POST /api/v1/ai/extrac ~~t-~~ intent 

- e Scope: Parses radiologist's narrative findings (e.g., "77mm aperistaltic blind-ending tubular structure in RLQ with fat stranding") and returns preliminary diagnostic impression ("Acute appendicitis") + candidate ICD ~~-1~~ 1 code (DB10.0). 

### 4. Universal Laravel Al Gateway Proxy Client 

This reusable service class is included in the Clinical Module, LIMS, and RIS codebases to consume the Al Gateway smoothly. 

namespace App\Services\AiGateway; 

use Illuminate\Support\Facades\Http; use Exception; use Str; 

class AiGatewayClientService 

{ protected string $gatewayUrl; 

protected string $moduleCode; protected string $apiKey; 

public function ~~__c~~ onstruct() 

{ 

$thi ~~s-~~ >gatewayUrl = config(‘services.ai ~~_g~~ ateway.url’); 

$thi ~~s~~ ->moduleCode = config(‘services. ~~ai~~ _gateway.module ~~_c~~ ode’); // e.g. 

‘CLINICA ~~L_~~ ORCHESTRATOR' 

$thi ~~s-~~ >apiKey = config('services.a ~~i_~~ gateway.api_ ~~ke~~ y’); 

} 

[** 

* Dispatches a service request to the Shared Al Gateway. 

*/ public function callAiService(string $serviceEndpoint, array $payload): array 

{ 

$requestid = (string) Str::uuid(); 

$response = Http::withHeaders([ 

‘Authorization’ => ‘Bearer '. $thi ~~s-~~ >apiKey, ' ~~X-~~ Module ~~-~~ Code' => $thi ~~s~~ ->moduleCode, 

" ~~X-~~ Request-ID' => $requestld, ‘Content ~~-T~~ ype’ => ‘application/json’, }) ~~-~~ >timeout(10) ~~-~~ >retry(3, 500) 

~~-~~ >post($thi ~~s-~~ >gatewayUrl . ‘/api/v1/ai/' . $serviceEndpoint, arra ~~y_~~ merge($payload, [ ‘module ~~_c~~ ode' => $thi ~~s~~ ->moduleCode, ‘requested ~~_b~~ y ~~_u~~ ser ~~_i~~ d' => auth() ~~->~~ id() ?? 0, })); 

if ($response- ~~>f~~ ailed()) { throw new Exception("Al Gateway Error ({$serviceEndpoint}): HTTP ". $response- ~~>s~~ tatus()); } return $response- ~~>j~~ son(); } [** * Helper: Extract CDE Observations from Voice/Typed Draft */ public function extractObservations(string $draftText, string $patientld, string $visitld): array { return $this ~~-~~ >callAiService(‘extract ~~-~~ observations’, [ ‘text’ => $draftText, ‘patient ~~_i~~ d' => $patientld, ‘visi ~~t_i~~ d' => $visitld, ); } [** * Helper: Suggest ICD ~~-1~~ 1 Candidate Codes */ public function suggestlcd11(string $diagnosisText): array { return $thi ~~s-~~ >callAiService(‘icd1 ~~1-~~ suggest, [ ‘diagnosisText' => $diagnosisText, ); } } 

#### 5. Summary Al Microservice Routing Matrix 

|5.SummaryA<br>Al ServiceCode|l Microservic<br>GatewayAPI<br>Endpoint|Routing Mat<br>Primary<br>Consuming<br>Module|rix<br>PrimaryWorkflow<br>Action|
|---|---|---|---|
|SpeechToText|wss://gateway.../v1/s<br>tt/stream|Clinical /LIMS/ RIS|Live bedside audio<br>streaming to rough<br>notes scratchpad &<br>radiologist reports.|
|ObservationExtra<br>ction|POST<br>/api/v1/ai/extrac~~t-~~ob<br>servations|Clinical Module|Parses rough text<br>into structured CDE<br>numeric/unit<br>objects for<br>clic~~k-~~to~~-~~verify<br>pre-~~fi~~ll.|
|ClinicallntentExtra<br>ction|POST<br>/api/v1/ai/extract~~-i~~nt<br>ent|Clinical<br>/ LIMS/ RIS|Converts draft<br>order text to<br>generic drug intents<br>for the Clinical<br>Translator Engine.|
|ICD11Assistance|POST<br>/api/v1/ai/icd~~11~~-sugg<br>est|Clinical / RIS|Returns ranked<br>ICD~~-1~~1 candidate<br>codes requiring<br>clinician<br>confirmation.|
|ObservationSumm <br>arization|= POST<br>/api/v1/ai/summarize<br>~~-~~observations|Clinical Module|Generates narrative<br>shift handovers<br>acrossorgan<br>systems (ICU,<br>Surgical, NICU,<br>SBAR).|
|ClinicalProtocolRe<br>commend|POST<br>/api/v1/ai/recommen<br>d~~-p~~rotocol|Clinical Module|Analyzes real-time<br>CDEthresholdsto<br>suggest protocol|





