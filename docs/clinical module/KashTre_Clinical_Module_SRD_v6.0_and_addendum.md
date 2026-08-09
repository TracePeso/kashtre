##### Systems Requirements Document (SRD) 

Module Core Key: CLINICA ~~L~~ _ORCHESTRATOR (Dynamic Display Alias Configurable per Tenant) 

Document Version: v6 ~~.0-~~ Master Consolidated Evolutionary Architecture (Care Assignment, Task Visibility & Major Transition Process Engine) 

Target Architecture: Zer ~~o-~~ Code Mult ~~i-~~ Tenant Microservice Framework (Laravel Native) 

Scope: Complete OPD/IPD Patient Journey, Atomic CDE Engine, Dynamic Care Assignment, Task Visibility Framework, Major Transition Process Registry, Bed Census, Deterministic CDSS, HL7 FHIR Interoperability, Clinical Entitlements, Order Automation, Mult ~~i-~~ Unit Conversion & Toggle, Pr ~~e-~~ Seeded Settings Dictionaries, Device Telemetry & Hybrid Validation, ZTNA Security, & Al Gateway Integration 

System Status: Approved Specification 

###### Executive Architectural Summary & Evolutionary Timeline 

The KashTre Clinical Orchestrator specification represents a mult ~~i-~~ stage evolutionary design: 

1. July 20th Baseline (Core Purpose & Orchestration): Established the Clinical Module as the central decision, workflow, and event broker engine sitting between Main (Business Truth), Inventory (Physical Truth), LIMS, RIS, PACS, and HR. Introduced the Clinical Translator Engine, Package Entitlement tracking, Order Automation, and Clinical Consumption Event Broker ~~.~~ 

2. July 21st Amendment (Data ~~-F~~ irst Atomic CDE Paradigm): Eliminated static, hardcoded forms in favor of atomic Clinical Data Elements (CDEs), Observation Groups, Dynamic Templates, Schedules, Protocols, Device Telemetry, Hybrid Validation, and Bedside Rough Notes. 

3. July 25th Consolidation Iteration A (Journey, Safety & Settings Framework): Unified the OPD/IPD patient journey, added Outpatient MDT shared care, Ward Bed Census with Overflow capacity (+ Add Overflow Bed), ZTNA Remote Access, Nomenclature Abstraction, Deterministic CDSS, HL7 FHIR R4/R5 Interoperability, Universal Settings Dictionaries with pr ~~e-~~ seeded master data, Multi ~~-~~ Unit Display Toggles ( mmol/L - mg/dL ), and Poin ~~t-~~ o ~~f-~~ Capture Input Safety Shields ~~.~~ 

4 ~~.~~ July 25th Consolidation Iteration B (Advanced Addendum ~~-~~ Version 6 ~~.~~ 0 Master): Introduced the Dynamic Care Assignment Framework (Individual, Role, Team, and Hybrid models), Clinical Task Visibility Framework (clinical context projections over Main Module enterprise queues), and the Clinical Process Registry (configurable step ~~-~~ by ~~-~~ step workflows strictly for Major Transitions: Admission, Transfer, Discharge, Referral, and 

Mortality). 

###### 1 ~~.~~ Universal Zero ~~-~~ Hardcoding Mandate & Dynamic Pr ~~e-~~ Seeded Settings Framework 

To guarantee immediate operational readiness while maintaining total mult ~~i-~~ tenant flexibility, the system strictly prohibits hardcoding any clinical literal, unit of measure, numeric threshold, score formula, drop ~~-~~ down option, reason code, or severity level inside application source code. 

All operational parameters across all clinical workflows are driven by dynamic records stored in the Clinical Settings & Master Dictionary Registry. The system ships with a comprehensive Pr ~~e-~~ Seeded Master Data Package out ~~-~~ of ~~-~~ the ~~-~~ box. System administrators in any health facility do not need to invent units or reason categories from scratch; they simply review, toggle, customize, or append to the pr ~~e-~~ seeded defaults. 



<!-- Start of picture text -->
a | CLINICAL SETTINGS & DICTIONARY REGISTRY | |<br>| | (Pr e- Seeded Defaults + Tenant Admin Configuration) |<br>a|<br>v v Vv Vv v<br>a i, | OT<br>a|<br>| Unitsof | | Reason Codes | | Scoring | | Escalation | | Medication<br>| Measure | | Dictionary | | Formulas & | | Rules& | | Routes &<br>| Dictionary | | (Audited — | | Weight | | Severity | | Frequency |<br>| (uom _ma ster’)| | Overrides) | | Matrices | | Tiers | | Routines<br>v Vv Vv Vv Vv<br>CCooo<br>EE<br>| DYNAMIC CLINICAL EXECUTION ENGINE & CDE REGISTRY<br><!-- End of picture text -->

| (Consumes Settings Dictionaries for Input, Validation, & Render) | ~~<u>be</u> ee~~ 

###### 1.1 Master Units of Measure (UOM) Registry (clinical_uo ~~m_~~ master) 

All clinical measurement units are pr ~~e-~~ seeded in the system database (Settings — Clinical Dictionaries — Units of Measure) ~~.~~ When an administrator configures a Clinical Data Element (CDE), laboratory indicator, or MAR dosage field, the UI forces selection exclusively from this active clinical_uo ~~m_~~ master table ~~.~~ Direct manual typing of free ~~-t~~ ext unit strings is programmatically disabled ~~.~~ 

###### Pr ~~e-~~ Seeded Default Units Catalog (Categorized) 

The platform ships with the following standard pr ~~e-~~ seeded UOM records (including mapping to international UCUM ~~-~~ Unified Code for Units of Measure standards): 

|Category|Display Unit Label|UCUM Code|Common Clinical<br>Use Case|
|---|---|---|---|
|Temperature|°C (Celsius), °F<br>(Fahrenheit)|Cel, [degF]|Vitals, Incubation,<br>Hypothermia<br>Protocol|
|Blood Pressure/<br>Tension|mmHg|mm[Hg]|Systolic/Diastolic<br>BP, CVP, MAP, ICP|
|Respiratory / Fluid<br>Pressure|cmH20, kPa, bar,<br>psi|cm[H20], kPa, bar,<br>[psi]|Ventilator PEEP,<br>Airway Pressure,<br>ABG|
|Heart&<br>Respiratory Rates|bpm (beats/min),<br>breaths/min|/min, {oreaths}/min|Pulse Rate, Heart<br>Rate, Respiration<br>Rate|
|Mass /Weight|kg, g, mg, mcg (ug),<br><br>ng|| kg, g, mg, ug, ng|Patient Weight,<br>Drug Dosages|
|Volumetric<br>Concentration|g/dL, mg/dL, g/L,<br>mg/L|g/dL, mg/dL, g/L,<br>mg/L|Hemoglobin, Blood<br>Glucose, Protein|
|Molar|mmol/L, umol/L,|mmol/L, umol/L,|Electrolytes,|



|Concentration|mol/L|mol/L|Creatinine, Bilirubin|
|---|---|---|---|
|Electrolyte<br>Equivalents|mEq/L, Eq/L|medq/L, eq/L|Sodium, Potassium,<br>Chloride,<br>Bicarbonate|
|Enzyme &<br>Biological Activity|U/L, IU/L, IU/mL,<br>mlU/mL, IU|U/L, iU/L, [iU}/mL,<br>m[iU}/mL|AST, ALT, Insulin,<br>Hormones (hCG,<br>TSH)|
|Volume & Fluid<br>Velocity|mL, L, dL, ub|mL, L, dL, uL|Intake/Output,<br>Urine, Drain Volume|
|Infusion & Flow<br>Rates|mL/hr, L/min,<br>drops/min (gtt/min)|mL/h, L/min,<br>{drop}/min|IV Infusions,<br>Oxygen Flow,<br>Inotropes|
|Weight~~-~~Normalize<br>d Dosing|mg/kg, mg/kg/hr,<br>mL/kg, mL/kg/hr|mg/kg, mg/kg/h,<br>mL/kg, mL/kg/h|Pediatric Dosing,<br>Fluid Resuscitation|
|Cellular Counts &<br>Hematology|cells/uL, x1049/L,<br>x10412/L, /hpf, /lpf|10*3/uL, 10*9/L,<br>10*12/L, /[HPF]|WBC, Platelets,<br>RBC, Urine<br>Microscopy|
|Renal Function/<br>Clearance|mL/min/1~~.~~73m?,<br>mL/min|mL/min/{1.7~~3_m~~2},<br>mL/min|eGFR, Creatinine<br>Clearance|
|Linear& Surface<br>Measurements|cm, mm, m, cm?, in|cm, mm, m, cm2,<br>[i~~n_i~~]|Height, Cervical<br>Dilatation, Wound<br>Area|
|Percentages&<br>Proportions|%, fraction, ratio|%, 1, 1|SpO,<br>oy<br>~<br>Ejection<br>Fraction, Hematocrit|
|Energy &<br>Metabolism|kcal, kJ, kcal/day|kcal, kJ, kcal/d|Parenteral Nutrition,<br>Caloric Intake|
|TimeWindows &<br>Intervals|sec, min, hr, days,<br>weeks, months|s, min, h, d, wk, mo|Contraction<br>Duration, Dosing<br>Intervals|



###### 1 ~~.~~ 2 Master Reason Code Dictionary (clinical_reaso ~~n_~~ code ~~s_m~~ aster) 

Every dro ~~p-~~ down list requiring a user to document an operational or clinical reason is pre ~~-~~ populated from settings ~~.~~ Administrators can activate, deactivate, or add custom codes per operational category ~~.~~ Pr ~~e-~~ Seeded Default Reason Codes Catalog 

- [ CLINICAL REASON CODES MASTER DICTIONARY] ~~|t—~~ 1 ~~.~~ MISSING / SKIPPED OBSERVATION REASONS | ~~|-—~~ -REASO ~~N_~~ OB ~~S_~~ REFUSED _ : Patient Refused Observation | ~~J~~ REASON ~~_~~ OBS ~~_~~ OF ~~F_~~ WARD_ : Patient Off Ward / In Transit/ Diagnostics | ~~[|-~~ —REASON ~~_O~~ BS_I ~~N_~~ THEATRE : Patient In Operating Theatre / PACU | ~~po~~ REASON ~~_O~~ BS ~~_E~~ QUIPMENT _ : Equipment Failure / Calibration Pending | ~~[--~~ REASON ~~_O~~ BS ~~_S~~ LEEP ~~_L~~ OCK : Clinical Exemption / Undisturbed Sleep Order | ~~|-—~~ -REASON ~~_~~ OBS ~~_U~~ NCOOPERATIVE: Patient Uncooperative / Combative | ~~L_~~ REASON ~~_O~~ BS ~~P~~ HYSICIAN _ : Attending Physician Hold Order | ~~|—~~ 2. BREAK ~~-~~ GLASS EMERGENCY OVERRIDES | ~~[|-~~ —-OVERRI ~~DE~~ _EMERGENC ~~Y_~~ RESUS: Emergency Resuscitation / Crash Call | ~~[-—~~ OVERRIDE ~~_~~ ON ~~_C~~ AL ~~L_~~ COVER : On ~~-C~~ all Night / Weekend Cross ~~-~~ Coverage | ~~|—~~ OVERRIDE ~~_S~~ PECIALIS ~~T_~~ CONS : Unassigned Urgent Specialist Consultation | ~~[|-~~ —-OVERRID ~~E_~~ CROS ~~S_~~ DEPT ~~_C~~ RIS : Cross ~~-~~ Departmental Surge / Crisis Response | ~~|—~~ OVERRIDE ~~_P~~ RIMARY ~~_U~~ NAVAIL: Primary Attending Clinician Unavailable | ~~L—O~~ VERRIDE ~~_P~~ RE ~~_P~~ OST ~~_O~~ P _ : Pr ~~e-~~ Op/ Pos ~~t-~~ Op Rapid Stabilization | ~~[—~~ 3. MAR ADMINISTRATION REFUSALS, DELAYS & WASTAGE | ~~[/—~~ MAR ~~_R~~ EFUSED ~~_P~~ ATIENT_ : Patient Refused Dose | ~~|—~~ MAR ~~_R~~ EFUSED ~~_N~~ AUSEA _ : Patient Nauseated / Active Vomiting | ~~[|-~~ -MA ~~R_~~ WASTAG ~~E_~~ DROPPED_ : Dose Dropped / Contaminated | ~~—~~ MAR ~~_W~~ ASTAGE ~~_L~~ IN ~~E_~~ BLOWN: IV Line Blown / Infiltrated During Admin | ~~—~~ MAR ~~_W~~ ASTAGE_ ~~VI~~ AL ~~_S~~ POILED: Vial Cracked / Contaminated / Expired | ~~J~~ MAR ~~_H~~ OLD ~~_P~~ ARAMETER ~~_O~~ UT: Vital Sign Parameter Out of Bounds (Holding 

- Dose) | ~~I~~ MAR ~~_~~ HOL ~~D_~~ NP ~~O_~~ ORDER__ : Patient NPO (Nothing By Mouth) | ~~-—M~~ AR_ ~~E~~ RROR_ ~~D~~ ISPENSED_ : Incorrect Drug/Dose Dispensed by Pharmacy | ~~|—~~ 4. ORDER CANCELLATIONS & MODIFICATIONS | ~~J~~ CANCEL ~~_D~~ UPLICATE _ : Duplicate Order Entered In Error | ~~J~~ CANCE ~~L_~~ STRATEG ~~Y_~~ CHANGE: Clinical Treatment Strategy Changed | ~~J~~ CANCEL ~~_D~~ ISCHARGED __ : Patient Discharged / Transferred | ~~[—~~ CANCEL ~~_C~~ ONTRAINDICATION<sup>:Allergy/ContraindicationIdentifiedPost</sup><sup>~~-~~Order</sup> | ~~[-—~~ CANCEL ~~_S~~ AMPLE ~~_R~~ EJECTED : Lab Sample Hemolyzed / Specimen Rejected | l ~~L —C~~ ANCEL_ ~~CL~~ INICIA ~~N_~~ STOP : Attending Clinician Discontinued Order | ~~L_~~ 5. BED RELEASE & TRANSFER REJECTIONS 

- ~~|—~~ REJECT ~~_~~ BE ~~D_~~ DIRTY : Target Bed Dirty/Housekeeping Pending ~~—~~ REJECT ~~_S~~ PECIALTY ~~_~~ MISMAT: Specialty/ Isolation Requirement Mismatch ~~|—~~ REJECT ~~S~~ TAFFING RATIO: Ward Nurse ~~-t~~ o ~~-P~~ atient Staffing Ratio Exceeded ~~|—~~ REJECT ~~_P~~ ATIEN ~~T_~~ UNSTABLE: Patient Clinically Unstable for Transit ~~L—~~ REJECT ~~_~~ ATTENDIN ~~G_~~ HOLD : Transfer Hold Placed by Attending Physician 

###### 1.3 Configurable Score Formula & Weight Matrix Engine (clinical_scoring ~~_d~~ ictionaries) 

All clinical calculations, risk indexes, and score parameters are pre ~~-~~ configured as structured JSON matrices in settings ~~.~~ 

###### Pr ~~e-~~ Seeded Clinical Scoring Models 

- 1 ~~.~~ NEWS2 (National Early Warning Score 2) Matrix: Pre ~~-~~ seeded value bounds for Respiratory Rate, SpO, , Supplemental O2 , Systolic BP, Pulse, Consciousness (CVPU), and Temperature. Pre ~~-~~ configured composite risk tiers ( Score 0 — 4 —+> Low/Green . Score 5 — 6 —+ Medium/Yellow . 



<!-- Start of picture text -->
Score 0 — 4 —+> Low/Green<br><!-- End of picture text -->



<!-- Start of picture text -->
Score 5 — 6 —+ Medium/Yellow<br><!-- End of picture text -->

   - Score > 7 —> High/Red 

2. SATS (South African Triage Scale) Matrix: Pre ~~-~~ seeded triage acuity mapping combining Mobility (Walking, Wheelchair, Stretcher), Vital Sign Score, and Emergency Trauma Identifiers to set intake queue color priorities (RED - Immediate, ORANGE ~~-~~ Very Urgent, YELLOW ~~-~~ Urgent, GREEN ~~-~~ Non ~~-U~~ rgent, BLUE ~~-~~ Deceased) ~~.~~ 

3. APGAR Scoring Matrix: Pre ~~-~~ seeded 1, 5, and 10 ~~-~~ minute newborn assessment grids ( 



<!-- Start of picture text -->
0,1,2<br><!-- End of picture text -->

- 0,1,2 points each for Appearance, Pulse, Grimace, Activity, Respiration) ~~.~~ 



<!-- Start of picture text -->
(i —4 —4<br><!-- End of picture text -->

4. Glasgow Coma Scale (GCS) Matrix: Pre ~~-~~ seeded options for Eye Opening (i —4 —4 ), 



<!-- Start of picture text -->
( 1—5<br><!-- End of picture text -->



<!-- Start of picture text -->
( 1—6 )<br><!-- End of picture text -->

   - Verbal Response ( 1—5 ), and Motor Response ( 1—6 ) ~~.~~ eGFR CKD ~~-E~~ PI (2021 Race ~~-F~~ ree) Formula Variables: Pre ~~-~~ seeded coefficients ( k = 0.7 female/0.9 male | a = —0.241 female/ — 0.302 male - gender multipliers 1.012 female/1.000 male ) stored as settings records to allow seamless updates if clinical guidelines change ~~.~~ 

5. eGFR CKD ~~-E~~ PI (2021 Race ~~-F~~ ree) Formula Variables: Pre ~~-~~ seeded coefficients ( 

6. BMI & Pediatric Growth Percentiles: Pre ~~-~~ seeded height/weight ratio formulas and WHO pediatric Z ~~-~~ score calculation matrices ~~.~~ 

###### 1 ~~.~~ 4 Dynamic Escalation & Alert Severity Registry 

###### (clinical_escalatio ~~n_r~~ ules) 

The system ships with 4 pre ~~-~~ configured escalation tiers. Facility administrators can alter color 

hex codes, sound siren files, or target roles per tier: 

|Severity Tier|Default Color|Auditory<br>Signal|Screen Action|Pre~~-~~Seeded<br>Target Role<br>Routing|
|---|---|---|---|---|
|INFO|#2563EB<br>(Blue)|Subtle Chime|Passive Toast<br>Banner|Assigned Ward<br>Nurse|
|WARNING|#D97706<br>(Yellow)|Dual Beep|Sticky Header<br>Alert|Assigned Ward<br>Nurse + Duty<br>Resident|
|URGENT~~_R~~EV =<br>IEW|#EA580C<br>(Orange)|Continuous<br>Beep|Modal Popup|Assigned<br>Nurse + Duty<br>Resident +<br>Ward Matron|
|CRITICA~~L_~~PA<br>NIC|#DC2626<br>(Red)|Full Alarm<br>Siren|Screen<br>Overlay<br>& Lock|Ward Nurse +<br>Duty Resident<br>+1CU<br>Registrar +<br>Matron|



###### 1 ~~.~~ 5 Medication Route & Frequency Registry (pharmacy ~~_r~~ oute ~~_f~~ requency ~~_m~~ aster) 

###### Pr ~~e-~~ Seeded Administration Routes 

Oral (PO), Intravenous (IV), Intramuscular (IM), Subcutaneous (SC/SubQ), Inhalation (INH), Topical (TOP), Sublingual (SL), Rectal (PR), Nasal (NAS), Ophthalmic (OU/OD/OS), Otic (AD/AS/AU), Intrathecal (IT), Transdermal (TD) ~~.~~ 

###### Pr ~~e-~~ Seeded Dosing Frequencies & MAR Scheduler Intervals 

|Frequency Code|Description|Pr~~e-~~Seeded Minute<br>Interval||MAR Dose<br>Execution Routine|
|---|---|---|---|
|STAT|Immediately / Single<br>Emergency Dose|=Omins|Immediate<br>singl~~e-~~dose MAR<br>trigger|
|ONCE|Single Routine|0 mins|Scheduled once at|



||Dose||specific target<br>timestamp|
|---|---|---|---|
|QD / Q24H|Once Daily|1440 mins|Generates 1 dose<br>every 24 hours<br>(default 08:00)|
|BID / Q12H|Twice Daily|720 mins|Generates 2 doses<br>perday (e~~.~~g~~.,~~ 08:00,<br>20:00)|
|TID /Q8H|Three Times Daily|480 mins|Generates 3 doses<br>perday (e~~.~~g~~.,~~ 06:00,<br>14:00, 22:00)|
|QID / Q6H|Four Times Daily|360 mins|Generates 4 doses<br>perday (e~~.~~g~~.,~~ 06:00,<br>12:00, 18:00, 00:00)|
|Q4H|Every 4 Hours|240 mins|Generates 6 doses<br>perday every 4<br>hours|
|Q2H|Every 2 Hours|120 mins|Generates 12<br>doses per day<br>(Intensive<br>Monitoring)|
|Q1H|Hourly|60 mins|Generates 24<br>doses per day (ICU<br>Continuous<br>Infusion)|
|PRN|As Needed|Enforces Min<br>Interval|Displayed on MAR<br>with min retry<br>window lock|
|QHS|At Bedtime|1440 mins|Scheduled daily at<br>bedtime (default<br>21:00)|



###### 1 ~~.~~ 6 Dynamic Uni ~~t-~~ Conversion, Multi ~~-~~ Unit Toggle, & Input Safety 

###### Safeguards Engine 

To solve the clinical hazard of misinterpreting laboratory values or vital signs due to conflicting unit preferences across Clinicians (e.g ~~.,~~ mmol/L vs ~~.~~ mg/dL for Blood Glucose; °C vs ~~.~~ °F for Temperature; g/dL vs ~~.~~ g/L for Hemoglobin), the system implements a Dual-Unit Storage & Dynamic Conversion Engine coupled with an Input Safety Shield. 

## ~~<u>oOo</u>~~ 



<!-- Start of picture text -->
| RAW CAPTURED CAPTURED CLINICAL OBSERVATION OBSERVATION<br>| (Stored Invariant in Tenant Standard Base Unit, e . g ., 7 . 0 mmol/L)<br><!-- End of picture text -->

RAW CAPTURED CAPTURED CLINICAL OBSERVATION OBSERVATION | (Stored Invariant in Tenant Standard Base Unit, e ~~.~~ g ~~.,~~ 7 ~~.~~ 0 mmol/L) | ~~SSCS~~ v Vv [ CLINICIAN DISPLAY VIEWA ] [ CLINICIAN DISPLAY VIEWB ] Active Toggle: mmol/L (Default) Active Toggle: mg/dL Renders: 7 ~~.~~ 0 mmol/L Renders: 126 ~~.1~~ mg/dL (Auto ~~-~~ Converted) Panic High: > 15.0 mmol/L Panic High: > 270 ~~.~~ 3 mg/dL (Aut ~~o-~~ Rescaled) 

###### ~~SSCS~~ 

###### 1 ~~.~~ 6 ~~.~~ 1 Tenant Standard Base Unit vs. Interactive Clinician Display Toggle 

- 1 ~~.~~ Invariant Storage in Base Units: Every quantitative CDE defines a single Tenant Base Unit in settings (e.g ~~.,~~ Blood Glucose base unit = mmol/L). All numeric observation facts are stored in the database in this invariant base unit to preserve analytical consistency. 

2. Pre ~~-~~ Configured Conversion Formulas: The UOM registry (clinical_uo ~~m_~~ master) stores bidirectional conversion formulas and conversion factors for alternate display units: © Blood Glucose: Valuemnejaz = Valuegmoi, < 18.0182 > Creatinine: Valuene/at = Valueymoi/t /88.4 o Temperature: Value-p = (Value-c x 1.8) + 32 9 Hemoglobin: Value,<sup>;, =Value,;azx10</sup> 

3. Interactive On ~~-t~~ he ~~-F~~ ly Ul Unit Toggle Button: Every clinical value display, trend graph, laboratory result table, and observation flowsheet provides an interactive Unit Toggle Badge (e.g., [ mmol/L | mg/dL ]). Clicking the badge instantly recalculates and r ~~e-~~ renders all on ~~-~~ screen values, historical time ~~-~~ series points, and chart axes into the clinician's preferred view unit without modifying underlying database records. 

###### 1 ~~.~~ 6 ~~.~~ 2 Dynamic Alert Threshold & Panic Range Re ~~-S~~ caling 

Whena clinician switches display units, all alert boundaries, critical panic limits, and normal reference ranges automatically re ~~-~~ scale in real-time to match the active view unit: 

- e Example: A patient's Glucose panic high threshold is configured in tenant settings as 

   - 15.0 mmol/L 



<!-- Start of picture text -->
16.0 mmol/L mmol/L<br><!-- End of picture text -->

- If viewed in default mode (mmol/L), the patient's result of 16.0 mmol/L mmol/L highlights in red as CRITICA ~~L_~~ PANIC (> 1°-9La mmol/L ) ~~.~~ 

- 0 If the clinician clicks the unit toggle to mg/dL, the UI automatically r ~~e-~~ scales the display result to 288.3 mg/dL and r ~~e-~~ scales the visual panic boundary banner to > 270.3 mg/dL 



<!-- Start of picture text -->
> 270.3 mg/dL<br><!-- End of picture text -->

. The alert classification (CRITICA ~~L_P~~ ANIC) remains perfectly synchronized. 

###### 1 ~~.~~ 6 ~~.~~ 3 Input Context Safety Shield & Physiological Heuristic Interception 

To eliminate catastrophic data entry errors caused by unit confusion during bedside observation 



<!-- Start of picture text -->
180<br><!-- End of picture text -->



<!-- Start of picture text -->
mg/dL<br><!-- End of picture text -->



<!-- Start of picture text -->
mmol/L<br><!-- End of picture text -->

entry (e ~~.~~ g., a nurse typing 180 assuming mg/dL into an input field expecting mmol/L ): 

- 1 ~~.~~ High- ~~V~~ isibility Input Unit Badges: Every CDE entry field features an un ~~-e~~ ditable, high ~~-c~~ ontrast, colo ~~r-~~ coded Active Input Unit Badge prominently anchored inside the righ ~~t-~~ hand edge of the input text box (e ~~.~~ g ~~.,~~ [ Input Unit: mmol/L ]) ~~.~~ 

2 ~~.~~ Explicit Unit Switcher at Point of Capture: If a nurse receives a bedside fingerstick device reading in mg/dL while the field default is mmol/L , the entry box includes a single ~~-c~~ lick "Enter in mg/dL instead" helper button ~~.~~ The system accepts the entered value in mg/dL , displays the instant calculated conversion ( 180 mg/dL 10.0 mmol/L ), and prompts the nurse to click Confirm 

Conversion before saving ~~.~~ 

- 3 ~~.~~ Physiological Range Heuristic Interception: The CDSS continuously monitors numeric input values against physiological sanity limits configured in UOM settings: 



<!-- Start of picture text -->
180<br><!-- End of picture text -->

o Out ~~-o~~ f ~~-~~ Unit Range Warning: If a user inputs 180 into a field set to mmol/L (a value that would represent 3240 mg/dL ~~—ph~~ ysiologically incompatible with life), the system immediately blocks entry and pops up an urgent safety dialog: 

*Input Value (180 mmol/L) is outside viable physiological bounds for mmol/L.” 

”Did you mean 180 mg/dL? (Converts to 10.0 mmol/L)” 

| Click to Confirm 10.0 mmol/L} | | Re-enter Value | 

###### 2 ~~.~~ Dynamic Care Assignment Framework 

To establish clear operational ownership and power Relationshi ~~p-~~ Based Access Control (ReBAC) security, the system supports a configurable Dynamic Care Assignment Engine (Settings — Clinical Care Assignments) ~~.~~ 

[ CLINICAL CARE ASSIGNMENT MODELS ] ~~|—~~ 1. INDIVIDUAL MODEL<sup>:PrimaryConsultant(Dr</sup><sup>~~.~~Kamara)+PrimaryNurse(Nurse</sup> Kargbo) ~~|—~~ 2 ~~.~~ ROLE MODEL : Duty Resident Shift + Assigned Ward Shift Nurse ~~t—~~ 3 ~~.~~ TEAMMODEL __ : ICU Team A (Consultant + Resident + Nurse + Nutritionist) ~~L—~~ 4 HYBRID MODEL _ : Primary Consultant (Dr. Kamara) + Supporting Medical Team A 

###### 2 ~~.~~ 1 Care Assignment Models 

Facilities configure and assign patient ownership according to local operational structures: 

- e Individual Assignment: Direct 1 ~~-~~ to ~~-~~ 1 link between a Patient ID and a named Primary Doctor/ Primary Nurse. 

- e Role ~~-~~ Based Assignment: Binds patient care ownership to active shift duty roles (e.g ~~.,~~ On-Call Paediatric Registrar) ~~.~~ 

- e Team ~~-~~ Based Assignment: Assigns a multidisciplinary clinical team (e.g., Surgical Team B, ICU Team 1) containing multiple staff roles to a patient file simultaneously. 

- e Hybrid Assignment: Blends individual primary accountability (e.g ~~.,~~ Primary Attending Consultant) with broader team coverage (Medical Team A) ~~.~~ 

###### 2 ~~.~~ 2 Operational Scope & System Integration 

Care ownership records establish the legal and operational framework for: 

- e ReBAC Context Validation: Enforces zero ~~-t~~ rust chart access rules based on active team/patient linkages ~~.~~ 

- e Handover Task Routing: Auto ~~-~~ compiles shift handover summaries based on team assignment filters. 

- e Break ~~-~~ Glass Audit Bounds: Determines when an unassigned clinician triggers a break ~~-~~ glass emergency override. 

- e MDT Collaboration Workspaces: Enables parallel c ~~o-~~ management across multiple 

specialists without location transfers. 

###### 3 ~~.~~ Clinical Task Visibility & Contextual Queue Projection Engine 

To avoid duplicating enterprise service queues generated by the Main Module (e ~~.~~ g., Main Module Wound Dressing Queue tracking 50 patients facilit ~~y-~~ wide), the Clinical Module projects a Clinically Filtered Task View ~~.~~ 

[ MAIN MODULE ENTERPRISE QUEUE ] ~~—>~~ (Wound Dressing Queue: 50 Patients Hospital-Wide) 



<!-- Start of picture text -->
Vv<br>©_ oo<br>ooo<br>| CLINICAL TASK VISIBILITY PROJECTION ENGINE |<br>| Filtered by Active User Context: Ward = "Gynaecology Ward" |<br>| Renders: "Gynaecology Ward — 5 Patients Waiting For Wound Dressing" |<br><!-- End of picture text -->



<!-- Start of picture text -->
Pe<br>—_—SSSSSSS—SFsFssSSSSSSSSSSSSsSd<br><!-- End of picture text -->

###### 3 ~~.~~ 1 Contextual Filter Engine 

Clinicians interact with enterprise tasks filtered through local clinical boundaries: 

- e Client Space / Building / Ward / Room / Bed 

- e Primary Doctor/ Primary Nurse / Assigned Care Team 

- e Specialty / Care Pathway 

###### 3.2 Staff Workspace Alignment 

Clinical users see localized views ("My Patients", "My Ward", "My Team", "My Current Work") rather than overwhelming hospital-wide administrative lists, aligning directly with Bed Management, Ward Census, and Shift Handovers ~~.~~ 

###### 4. Complete OPD Process Registry 



###### IPD Patient Journey & Major 

The system provides a continuous clinical chart model spanning Outpatient Department (OPD) visits, acute inpatient admissions (IPD), ward transfers, and post ~~-~~ discharge chronic recall loops ~~.~~ 

[ OUTPATIENT (OPD) JOURNEY ] 

~~<u>| |</u>~~ OPD Check ~~-i~~ n & Triage (SATS/NEWS2 Color Code) | 

Vv 

~~|~~ ~~<u>|</u>~~ Consultation, CDE Entry & Al Voice Dictation Scratchpad | 



<!-- Start of picture text -->
Vv<br><!-- End of picture text -->

~~|~~ ~~<u>|</u>~~ Diagnostic Ordering / Outpatient MDT Shared Care | 

V (Clinical Intent: Decision to Admit) 

[ INPATIENT (IPD) JOURNEY] | 

~~|~~ ~~<u>|</u>~~ Inpatient Handshake Transfer & Bed/Space Allocation | 

~~|~~ ~~<u>|</u>~~ Major Clinical Transitions Engine (Configurable Workflows) | 

Vv 

Vv 

~~<u>| |</u>~~ Mult ~~i-~~ Day Care, MAR Administration, & Shift Handovers | 

~~<u>| |</u>~~ Clinical Discharge Summary & ICD ~~-1~~ 1 Finalization | 

Vv (Clinical Transition: Post ~~-~~ Discharge Recall) 

###### [ CHRONIC RECALL & SURVEILLANCE ] 

Vv 

~~<u>| |</u>~~ Macro Surveillance Engine (30/90/36 ~~5-~~ Day Recall Alerts) | 

# ~~Pe~~ 

###### 4 ~~.~~ 1 Outpatient Entry & Consultation Phase 

1. Intake & Triage Acuity: The patient arrives at OPD ~~.~~ Triage captures vital CDEs 



<!-- Start of picture text -->
SpO, ) and<br><!-- End of picture text -->

(Temperature, BP, Pulse, SpO, ) and aut ~~o-~~ computes SATS/NEWS2 scores using settings matrices to push a priority color code to the universal queue ~~.~~ 

2. Consultation & MDT Shared Care: The physician opens the active encounter chart. For complex cases (e.g., Diabetic or Oncology clinics), multiple care providers (Physician, Nutritionist, Podiatrist) access and co ~~-~~ sign distinct sections of the same active encounter workspace concurrently without forcing location transfers ~~.~~ 

- 3 ~~.~~ Bedside Scratchpad & Al Voice Dictation: The clinician uses voice or typed rough notes ~~.~~ The Al Gateway streams sub ~~-~~ second transcription and extracts structured CDE tokens directly into the chart view for clinician verification ~~.~~ 

###### 4.2 The "Decision to Admit" Clinical Bridge 

When an outpatient requires hospital admission: 

1. Clinical Intent Trigger: The attending OPD clinician issues a DECISION ~~_~~ T ~~O_~~ ADMIT clinical order, attaching a target specialty/ward (e ~~.~~ g ~~.,~~ Paediatric Ward) and an initial 

Admission Note. 

2. Administrative Handshake Dispatch: The system dispatches an asynchronous event notification to the target ward's bed management queue to reserve an active patient bed ~~.~~ 

3. Continuous Chart Persistence: The client's active clinical file transitions from OPD mode to Inpatient mode seamlessly, preserving all OPD vitals, active prescriptions, and pending diagnostic orders on the longitudinal chart timeline ~~.~~ 

###### 4.3 Configurable Major Clinical Transitions Engine (clinical_process ~~_r~~ egistry) 

To accommodate varied institutional policies without code changes, major clinical state transitions are governed by a configurable Clinical Process Registry (Settings — Clinical Process Registry) ~~.~~ 

Important Boundary: Routine bedside care is NOT governed bya rigid workflow engine; it remains dynamic, observation ~~-d~~ riven, order ~~-d~~ riven, protocol-driven, and task ~~-d~~ riven. The workflow engine applies strictly to major state transitions: 

###### Pre ~~-~~ Configured Transition Workflows Catalog 

- [ MAJOR CLINICAL TRANSITIONS WORKFLOWS ] 

- ~~|—~~ 1. ADMISSION WORKFLOW | ~~|—~~ Step 1 [Mandatory]: Admission Assessment & Triage Verification | ~~[|~—~~ Step 2 [Mandatory]: Initial Nursing Assessment | ~~|—~~ Step 3 [Mandatory]: Clinical Risk Assessment (Fall, Pressure Sore, VTE) | ~~[|-—~~ Step 4 [Mandatory]: Initial Care Plan Initialization | ~~/—~~ Step 5 [Mandatory]: Bed Allocation & Room- ~~t~~ o ~~-S~~ tore Mapping | ~~L—~~ Step 6 [Mandatory]: Ward Nurse Acceptance Handshake | ~~[—~~ 2. INTER ~~-~~ SPACE TRANSFER WORKFLOW | ~~—~~ Step 1 [Mandatory]: Transfer Request & Destination Selection | ~~|—~~ Step 2 [Mandatory]: Medical Review & Transit Stability Sign ~~-o~~ ff | ~~|—~~ Step 3 [Mandatory]: Active Medication Reconciliation | ~~/—~~ Step 4 [Mandatory]: Transfer Clinical Summary Generation (CDE Snapshot) | ~~|—~~ Step 5 [Mandatory]: Receiving Ward Acceptance Handshake | ~~4—~~ Step 6 [Mandatory]: Physical Bed Custody Transfer | ~~|—~~ 3 ~~.~~ DISCHARGE WORKFLOW | ~~|—~~ Step 1 [Mandatory]: Consultant Discharge Sign ~~-O~~ ff | ~~|—~~ Step 2 [Mandatory]: Nursing Discharge Review & Line/Drain Removal Check | ~~/—~~ Step 3 [Mandatory]: Discharge Medication Reconciliation | ~~|—~~ Step 4 [Mandatory]: Outstanding Laboratory & Radiology Results Review | ~~[|—~~ Step 5 [Mandatory]: Clinical Discharge Summary & ICD ~~-1~~ 1 Finalization | ~~|—~~ Step 6 [Mandatory]: Discharge Medication Issue / External Voucher | ~~—~~ Step 7 [Optional ]: Main Module Financial & Clearance Verification 

| ~~L—~~ Step 8 [Mandatory]: Clinical Encounter Closure 

- ~~|—~~ 4. EXTERNAL REFERRAL WORKFLOW | ~~|—~~ Step 1 [Mandatory]: Referral Justification & Target Specialty Selection | ~~|—~~ Step 2 [Mandatory]: Inte ~~r-~~ Hospital Transfer Summary Generation | ~~L—~~ Step 3 [Mandatory]: Clinical Sign ~~-o~~ ff & IPS / C ~~-~~ CDA Export Package 

###### ~~L—~~ 5 ~~.~~ DEATH CERTIFICATION WORKFLOW 

- Step 1 [Mandatory]: Verification of Death (Clinical Assessment) 

- ~~|—~~ Step 2 [Mandatory]: System End- ~~o~~ f ~~-L~~ ife Kill-Switch Execution (Halts MAR/Orders) ~~|~~ Step 3 [Mandatory]: Death Cause Documentation (ICD ~~-1~~ 1 Mortality Coding) ~~|—~~ Step 4 [Mandatory]: Mortuary Custody Handshake ~~L_~~ Step 5 [Mandatory]: Formal Death Certificate Sign ~~-o~~ ff & Chart Lock 

Each workflow step supports configurable Mandatory/Optional flags, Role Ownership, Completion Rules, Override Authorization Rules, and Immutable Audit Trails ~~.~~ 

###### 5. Bed Management, Real-Time Ward Census, & Surge Capacity Engine 

Bed management gives ward staff (nurses, ward matrons) instant operational clarity over physical bed status and patient distribution without unnecessary administrative overhead ~~.~~ 

###### 5 ~~.~~ 1 Core Bed Operational States 

Beds within any client space (Ward, Bay, Room) maintain three simple operational states: 

- e AVAILABLE: The bed is vacant and ready for immediate patient assignment. 

- e OCCUPIED: The bed is actively assigned to a patient ~~.~~ The UI displays an interactive Client Card showing the occupant'’s Name, Age/Gender, Visit ID, Triage Acuity Color Code, and Primary Diagnosis. 

- e RESERVED: The bed is temporarily locked for an incoming admission or inte ~~r-~~ ward transfer (DECISION ~~_~~ T ~~O_~~ ADMIT or REQUEST ~~_T~~ RANSFER). 

###### 5 ~~.~~ 2 Real-Time Ward Census & Space Dashboard 

Every ward interface features a persistent Real-Time Ward Census Header Widget: 

Total Beds: Nyotar | Occupied: Noccupica | Reserved: Npeservea | Available: Nayaitabie 

e 

Visual Spatial Grid: Renders an interactive bed map for the ward. Clicking any OCCUPIED bed opens the client's active chart viewport; clicking an AVAILABLE bed allows 

###### instant assignment of incoming or reserved patients ~~.~~ 

###### 5 ~~.~~ 3 Overflow & Surge Capacity Engine ("Extra Bed" Creation) 

To handle hig ~~h-~~ demand periods when a ward reaches 100% capacity: 

- 1 ~~.~~ Dynamic Extra Bed Creation: Ward nurses or managers can click + Add Overflow Bed on the ward dashboard ~~.~~ 

- 2 ~~.~~ Temporary Identifier Assignment: The system generates an active extra bed slot (e.g ~~.,~~ BED ~~-~~ 1 ~~2-~~ EXTRA or COT ~~-~~ 0 ~~8-~~ OVERFLOW) ~~.~~ 

3. Full System Integration: The extra bed immediately inherits the ward's CDE templates, observation schedules, MAR rules, and Room- ~~t~~ o ~~-S~~ tore inventory mappings, allowing full clinical care and charting for overflow patients ~~.~~ 

4. Auto ~~-R~~ etirement on Discharge: Once the overflow client is transferred or discharged, the system prompts the nurse to retire the extra bed slot, resetting the ward's baseline capacity. 

###### 6. Clinical Translator Engine, Entitlement Tracking & Order Automation 

###### 6 ~~.~~ 1 Mult ~~i-~~ Parameter Clinical Translator Engine 

To shield medical practitioners from searching warehouse SKU numbers during order entry, clinicians prescribe using standard generic chemical terms ~~.~~ The Clinical Translator Engine intercepts the clinician's intent and evaluates it against the master inventory catalog using a three ~~-t~~ ier matcher: 

- 1 ~~.~~ Generic/Chemical Match (alternati ~~ve~~ _names Tag Array): Scans a JSON tag array tracking all generic synonyms, trade names, and active chemical compound codes (e.g., matching "Paracetamol", "Acetaminophen", or "Panadol" to the same master entry). 

2. Strength Descriptor Verification: Validates the therapeutic concentration text descriptor attached to the inventory item (e.g ~~.,~~ matching "500mg per tablet", "250mg/5mL suspension", or "1g vial") ~~.~~ 

3. Active Brand Selection Output: Maps the matched generic intent to the specific active, billable brand SKU (i ~~s_~~ offe ~~r_~~ item = TRUE) available at the facility's active Pharmacy service point ~~.~~ 

###### 6.2 External Match Fallback & External Referral Generator 

Doctors are never restricted by internal hospital inventory offerings ~~.~~ When the Clinical Translator Engine determines that an ordered item is not available in the internal Master Catalog (i ~~s_~~ offe ~~r_~~ item = FALSE or no matching SKU exists): 

[ CLINICAL ORDER INTENT ] ~~—-~~ > [ CLINICAL TRANSLATOR ENGINE ] 

Vv 

###### [ NO INTERNAL OFFER MATCH ] 

v 

[ CLINICIAN CONFIRMS: "EXTERNAL FULFILLMENT" ] 

v 



<!-- Start of picture text -->
ss<br>oo<br>| AUTOMATED EXTERNAL REFERRAL GENERATOR |<br>| ¢ External Prescription (Outpatient Pharmacy Retail Voucher) |<br>| ¢ External Laboratory Referral (Third - Party Pathology Reference) |<br>| » External Imaging Referral (External PACS / Radiology Center) |<br>| ¢ External Specialist/Physiotherapy Referral Letter |<br><!-- End of picture text -->



<!-- Start of picture text -->
Pe<br><!-- End of picture text -->

#### ~~ss~~ 

1. Clinician Confirmation Prompt: The UI displays: "/tem not available in internal offering catalog. Proceed with External Fulfillment?" 

2. Automated Document Generation: Upon clinician sign ~~-o~~ ff, the system formats an audited, print/PD ~~F-~~ ready external document containing clinician credentials, digital signature stamp, patient identifiers, clinical indication, and structured order parameters ~~.~~ 

###### 6.3 Clinical Entitlement & Package Consumption Framework 

The Clinical Module owns clinical entitlement tracking for bundled care packages (e.g ~~.,~~ Antenatal Packages, Surgical Procedure Packages, Wellness Screening Bundles, Executive Health Checks) ~~.~~ 

[ PREPAID CARE PACKAGE ] ~~——P~~ > (Allocated Balances: e ~~.~~ g ~~.,~~ 9 Consults, 3 CBCs, 2 Ultrasounds) 

Vv 



<!-- Start of picture text -->
|<br>| ENTITLEMENT CONSUMPTION ENGINE |<br>| Tracks per Client ID & Active Package ID: |<br>| + Allocated Quantity |<br>| » Used Quantity |<br><!-- End of picture text -->



<!-- Start of picture text -->
| - Remaining Balance |<br>{<br>|<br>-——\TJ_.1___<br>Vv v<br>[ WITHIN ALLOCATED LIMIT] [EXCESS CONSUMPTION ]<br>| |<br>Vv Vv<br>(Deducts 1 Entitlement Unit; (Generates Additional Order;<br>Bypasses Billing Clearance) Routes to Billing Workflow)<br><!-- End of picture text -->

1. Prepaid Package Registration: When a patient purchases a care package (managed in Main Module), the Clinical Module receives a package entitlement token mapping allocated quantities per service code (e ~~.~~ g., ANC Package = 9 Consultations, 3 CBCs, 2 Obstetric Ultrasounds) ~~.~~ 

2. Real-Time Balance Decrementation: Upon clinician order entry, the engine checks active entitlements. If a matching entitlement exists (Remaining > 0), the system deducts 1 unit from Remaining, logs the Used counter, and immediately releases the order to the fulfillment queue without triggering a payment cashier hold ~~.~~ 

3. Excess Consumption Billing Interception: If the allocated package units are exhausted (Remaining = 0) and the clinician orders a 4th CBC test: o The Clinical Module consumes 0 entitlement units. o Automatically marks the 4th request as an Excess Package Order ~~.~~ o Routes the unbundled request straight to the standard commercial billing workflow ~~.~~ 

###### 6.4 Clinical Order Automation Engine 

To eliminate manual t ~~o-~~ do lists and fragmented task management, the Clinical Module automatically generates structured Clinical Work Orders anchored to specific clinical events and care pathways ~~.~~ 

###### [ CLINICAL EVENT TRIGGER ] 

~~|—~~ 1 ~~.~~ Daily Inpatient Stay ~~—-~~ » Auto ~~-~~ Generates: "Daily Morning Ward Round Review" ~~|—~~ 2 ~~.~~ Surgical Admission ~~——~~ P Auto ~~-~~ Generates: "Pre ~~-~~ Operative Assessment" & "Anesthetic Review" 

~~L_~~ 3 ~~.~~ Discharge Sign ~~-o~~ ff ~~——~~ » Auto ~~-~~ Generates: "Medication Reconciliation" & "Discharge Summary" 

1. Work Order Lifecycle States: All automated work orders progress through a standardized lifecycle: 

   - \text {PENDING} \longrightarrow \text {INPROGRESS}<sup>\longrightarrow</sup> \text<sup>{COMPLETED}</sup> 

2. Daily Morning Ward Round Reviews: Every morning at a configurable clock tick (e.g., 06:00 AM), the system evaluates all active admitted patients (i ~~s_~~ admitted = TRUE) and automatically injects a DAIL ~~Y_~~ WAR ~~D_~~ REVIE ~~W_~~ ORDER into the assigned attending doctor's shift worklist. 

3. Perioperative Task Bundles: Assigning a patient to a surgical care pathway automatically generates linked work items for PRE ~~_O~~ P ~~_N~~ URSING ~~_C~~ HECKLIST, ANESTHETIC ~~_P~~ RE ~~_E~~ VALUATION, and SURGICAL ~~_C~~ ONSENT_ ~~VE~~ RIFICATION. Completing each task advances the patient's surgical readiness indicator on the ward dashboard ~~.~~ 

###### 7 ~~.~~ Deterministic Clinical Decision Support System (CDSS) & Safety Guards 

While probabilistic Al models handle transcription, summarization, and coding suggestions, all patient safety enforcement is executed by a Hard Deterministic Rule Engine ~~.~~ This engine intercepts order entry, prescribing, and CDE captures in real-time ~~.~~ 

[ CLINICIAN ORDER ENTRY ] ~~—-~~ ® (Medication / Lab / CDE Event) 

###### v 



<!-- Start of picture text -->
rr<br>| DETERMINISTIC CDSS SAFETY SHIELD |<br>| 1. Drug - Drug Interaction (DDI) Matcher |<br>| 2. Drug -A llergy Cross - Checker |<br>| 3. Pediatric Weight - Based Dose Limits _ |<br>| 4. eGFR Renal Dose Adjustment Rules |<br>| 5. Unit Safety & Heuristic Range Checker |<br>Vv Vv<br>[ PASS / VALID ] [ WARNING / BLOCK]<br>Vv v<br>(Proceed to Chart) (Clinician Override/<br>Reason Code Required)<br><!-- End of picture text -->

###### 7.1 Drug ~~-~~ Drug Interaction (DDI) & Drug ~~-A~~ llergy Safety Shield 

1. Real-Time DDI Interception: When a clinician orders a drug via the Clinical Translator Engine, the CDSS scans all active medications in the client's chart against a standardized 

interaction dictionary configured in settings (tenant ~~_d~~ di ~~_d~~ ictionary) ~~.~~ 

   - Severity Tiers (Configurable in Settings): INFO, WARNING, HARD ~~_B~~ LOCK. 

   - Hard blocks require explicit senior consultant override authorization with audited clinical justification selected from the clinical_reaso ~~n_~~ codes ~~_m~~ aster ~~.~~ 

2. Drug ~~-A~~ llergy Cross ~~-~~ Checking: Cross ~~-r~~ eferences active allergy CDEs (e ~~.~~ g ~~.,~~ Penicillin Class, Sulfa Drugs) against active ingredients and chemical class tags ~~.~~ Triggers visual alerts prior to order sign ~~-o~~ ff ~~.~~ 



<!-- Start of picture text -->
( mg/kg<br><!-- End of picture text -->

###### 7 ~~.~~ 2 Pediatricwae Weight. ~~-~~ Based Dosage Validationas ( mg/kg Engine). 

- 1 ~~.~~ Weight ~~-~~ Dependent Order Validation: For pediatric patients, the CDSS evaluates prescribed dosages against recorded weight CDEs (mg/kg/dose or mg/kg/day ). 

- 2 ~~.~~ Out ~~-~~ o ~~f-~~ Bounds Interception: If a prescribed dose exceeds maximum singl ~~e-~~ dose or 24 ~~-~~ hour weight limits configured in settings: o The system highlights the variance in red, showing recommended vs ~~.~~ entered dosage ~~.~~ o Blocks order execution if variance exceeds configurable safety thresholds (e ~~.~~ g., > 150% calculated maximum) ~~.~~ 

###### 7.3 Renal Function Auto ~~-~~ Adjustment & Lab ~~-D~~ riven Alerts 

1. eGFR & Serum Creatinine Interception: Evaluates active eGFR CDE scores (calculated automatically via the setting ~~s-~~ configured CKD ~~-E~~ PI formula) prior to committing orders for renally cleared or nephrotoxic agents ~~.~~ 



<!-- Start of picture text -->
lf eGFR eGFR < 50 mL/min/1.73m mL/min/1.73m , the CDSS<br><!-- End of picture text -->

2. Dose Reduction Guidance: lf eGFR eGFR < 50 mL/min/1.73m mL/min/1.73m , the CDSS displays recommended dose adjustments or frequency interval shifts defined in settings (e ~~.~~ g ~~.,~~ recommending Metformin dose reduction or Vancomycin interval extension) ~~.~~ 

###### 7.4 Evidence ~~-~~ Based Order Sets & Clinical Pathways 

Provides pre ~~-~~ configured, single ~~-c~~ lick clinical ordering pathways defined in Settings that bundle standard diagnostics, medications, IV fluids, and observation schedules: 

- e Sepsis 3-Hour Resuscitation Bundle Order Set 

- e Diabetic Ketoacidosis (DKA) Protocol Order Set 

- e Acute Asthma Exacerbation Order Set 

- e Pre-Eclampsia Management Protocol 

###### 8 ~~.~~ Global Interoperability, HL7 FHIR R4/R5, & Health Information Exchange (HIE) 

To allow seamless data exchange with external healthcare networks, national registries, laboratory analyzers, and thir ~~d-~~ party systems, the Clinical Module implements native HL7 FHIR (Fast Healthcare Interoperability Resources) R4/R5 transformations ~~.~~ 

###### [ KASHTRE CLINICAL MODULE ] 

~~|—~~ (Internal CDEs / JSON Contracts) 

Vv ~~|~~ ~~<u>|</u>~~ FHIR R4/R5 MAPPING & ADAPTER LAYER | 

v Vv Vv [ FHIR REST APIs] [IPS /C ~~-~~ CDA Export] [ External HIE / HIN ] (JSON / XML) (Clinical Summaries) (National Registries) 

###### 8.1 Core FHIR Resource Mapping Schema 

Internal atomic Clinical Data Elements (CDEs), diagnoses, orders, and patient records dynamically map to standard FHIR resources: 

|KashTre Internal Data<br>Domain|Target HL7 FHIR<br>Resource|Primary Profile / Code<br>System|
|---|---|---|
|Client Profile & Registration|FHIR Patient|Unique Identifier,<br>Demographics|
|Active Encounter / Visit ID|FHIR Encounter|Class (AMB/IMP), Period,<br>Status|
|Atomic CDE Capture<br>(Vitals, Lab Value)|FHIR Observation|LOINC /SNOMEDCT/<br>UCUM Mapping|
|Confirmed ICD~~-1~~1<br>Diagnosis|FHIR Condition|ICD~~-1~~1 Coding Standard|
|Prescribed Medication /<br>Intent|FHIR MedicationRequest|RxNorm /ATC / Local<br>Brand SKU|
|Diagnostic Order<br>(Lab/Imaging)|FHIR ServiceRequest|LOINC / CPT Procedure<br>Codes|
|Completed Lab/Radiology|FHIR DiagnosticReport|LOINC / Structured|



Result 

Observation 

###### 8 ~~.~~ 2 International Patient Summary (IPS) & C ~~-~~ CDA Export 

- 1 ~~.~~ Cross-Institutional Transfer Continuity: Generates standardized International Patient Summary (IPS) documents and C ~~-~~ CDA (Consolidated Clinical Document Architecture) XML/JSON packages upon patient transfer or referral to external hospital networks ~~.~~ 

- 2 ~~.~~ Inbound IPS Import Engine: Supports importing external FHIR/ ~~C-~~ CDA documents into a staging view, allowing clinicians to selectively merge historical allergies, immunizations, and chronic conditions into the client's active KashTre chart ~~.~~ 

###### 9 ~~.~~ Unified Clinical Consumption Event Broker Matrix 

The Clinical Module serves as the central operational event broker for the KashTre platform. It records physical clinical consumption facts at the bedside or service point and emits structured webhook event tokens to parallel modules (Inventory, Finance, Pharmacy) ~~.~~ 

~~<u>Oo</u> —————————~~ | CENTRAL CLINICAL EVENT BROKER | —_—SSSSSSSSSSSSFSFSSSSSSSSSSSSsS 

| CENTRAL CLINICAL EVENT BROKER | —_—SSSSSSSSSSSSFSFSSSSSSSSSSSSsS — Vv Vv Vv [ INVENTORY MODULE ] [ FINANCE / LOSS LEDGER] [ PHARMACY SERVICE POINT] * Physical Decrements * Cost Write ~~-o~~ ffs * Escrow Release ¢ Sub ~~-S~~ tore Restocks ¢ Account Balancing * Verification Logs 

###### 9 ~~.~~ 1 Standardized Consumption Fact Tokens Catalog 

|Consumption Fact<br>Token|Trigger Event<br>Source|Data Payload<br>Contents|Target Parallel<br>Receiver|
|---|---|---|---|
|MEDICATIO~~N_~~AD|Nurse clicks|Patient ID, Visit ID,|Inventory Module|
|MINISTERED|"Administer" on|Drug SKU,|(decrements stock)|
||MAR following|Batch/Lot, Dose|& Finance (settles|



||barcode scan &<br>5~~-~~Rights check|Administered,<br>Executing User ID,<br>Room-~~t~~o~~-S~~tore<br>Sub~~-s~~tore ID|escrow)|
|---|---|---|---|
|MEDICATIO~~N_~~WA<br>STED|Nurse documents<br>dropped, refused,<br>or cracked<br>medication dose on<br>MAR|Patient ID, Visit ID,<br>Drug SKU, Wasted<br>Dose Qty, Reason<br>Code from<br>clinical_reaso~~n_~~cod<br>es~~_m~~aster,<br>Room-~~t~~o~~-S~~tore<br>Sub~~-s~~tore ID|Inventory Module<br>(decrements stock)<br>& Finance (routes<br>cost write~~-o~~ff to<br>Hospital Loss<br>Account)|
|LAB~~_~~CONSUMPTI<br>ON~~_F~~ACT|Lab analyzer lane<br>drops fluid / runs<br>assay (via LIMS<br>proxy)|Order ID, Test<br>Code, Reagent<br>SKU, Consumed<br>Volume/Count,<br>Instrument ID,<br>Executing<br>Sub~~-s~~tore ID|Inventory Module<br>(decrements<br>reagent store<br>balance)|
|RADIOLOG~~Y_~~CON<br>SUMPTION~~_F~~ACT|Radiology<br>procedure<br>execution<br>completed (via<br>RIS/PACS proxy)|Order ID, Modality<br>Procedure Code,<br>Contrast SKU,<br>Film/Consumable<br>Qty, Executing<br>Sub~~-s~~tore ID|Inventory Module<br>(decrements<br>radiology<br>consumable<br>balance)|
|NON~~_A~~PPROVED_~~_~~<br>FLOOR~~_S~~TOCK~~_U~~<br>SAGE|Nurse logs<br>un~~-~~prescribed floor<br>stock usage during<br>acute ward care|Patient ID, Visit ID,<br>Item SKU, Quantity<br>Used, Justification<br>Note, Ward<br>Sub~~-s~~tore ID|Inventory Module<br>(decrements ward<br>floor stock) &<br>Finance (applies<br>post~~-~~paid charge<br>line)|
|CRASH~~_C~~ART~~_~~CO _<br>NSUMPTION|Post~~-s~~tabilization<br>reconciliation<br>logged following<br>emergency crash<br>call|Patient ID, Visit ID,<br>Item SKUs array,<br>Quantities Used<br>array, Resuscitation<br>Event ID, Crash<br>Cart Store ID|Inventory Module<br>(decrements crash<br>cart store & triggers<br>restock alert) &<br>Finance|



###### 10 ~~.~~ Security, Remote Access Gateway, & Of ~~f-~~ Premises Enforcement Engine 

To allow clinicians to review charts or complete notes from home while enforcing strict data privacy and regulatory compliance, the system operates under a Zero ~~-T~~ rust Network Access (ZTNA) Security Framework ~~.~~ 



<!-- Start of picture text -->
|<br>| AUTHENTICATED USER REQUEST |<br><!-- End of picture text -->

| ZTNA/ MULTI-TENANT SECURITY GATEWAY | (Context Evaluator & Device Fingerprinter) | 



<!-- Start of picture text -->
|<br><!-- End of picture text -->

### ~~| v~~ 

Vv 

[ ON ~~-~~ PREMISES NETWORK (Hospital IP / mTLS)] (Home / Mobile)] 

###### [ OFF ~~-~~ PREMISES / REMOTE 

- ¢ Full Operational Scope Enabled 

- Contex ~~t-~~ Aware Restricted Scope Enabled * Read ~~-O~~ nly Chart Review & Draft Notes 

¢ Live Order Execution & Signing Allowed 

Completion 

- ¢ Direct Device Ingestion Active 

- ¢ Unrestricted High ~~-~~ Resolution Image Rendering 

- Downloads Disabled 

¢ Dynamic Screen Watermarking Enforced 

- Local Browser Storage/ File 

###### 10 ~~.~~ 1 Dual Access Security Tiers 

- e Tier 1: On ~~-P~~ remises Access (Hospital Network/Dedicated Terminals): Unrestricted operational authorizatio ~~n—p~~ lace live orders, administer MAR medications, sign discharge documents, execute inte ~~r-~~ ward transfers, and view high ~~-r~~ esolution PACS image streams ~~.~~ 

- e Tier 2: Of ~~f-~~ Premises Access (Home / Remote Mobile Access): Restricted Clinical View & Draft Completion Scope ~~.~~ Clinicians can review charts, dictate rough notes, complete draft SOAP notes, and review pending lab/radiology results ~~.~~ Of ~~f-~~ premise users are strictly barred from placing live medication orders, executing MAR dose administrations, or authorizing financial transactions ~~.~~ 

###### 10 ~~.~~ 2 Relationshi ~~p-~~ Based Access Control (ReBAC) & Break ~~-~~ Glass 

###### Protocol 

1. Care ~~-~~ Team Relationship Rule: Access relies on active care ownership records defined in the Dynamic Care Assignment Framework (Individual, Role, Team, or Hybrid). 

- 2 ~~.~~ Break ~~-~~ Glass Emergency Protocol: Unassigned clinicians accessing emergency files must click Break ~~-~~ Glass Override, select a mandatory reason code from clinical_reas ~~on~~ _code ~~s_~~ master (Emergency Resuscitation, On-Call Coverage), receive a time-limited 4 ~~-~~ hour window, and trigger an automated security audit alert ~~.~~ 

###### 10 ~~.~~ 3 Data Loss Prevention (DLP) & Remote Watermarking Framework 

- 1 ~~.~~ Dynamic Viewport Watermarking: Overlays a transparent watermark rendering User ID, Full Name, Remote IP, and UTC Timestamp across of ~~f-~~ premise viewports. 

- 2 ~~.~~ Zero Local Storage: All data resides strictly in ephemeral memory and is wiped upon tab closure or 15 ~~-~~ minute inactivity ~~.~~ Local browser storage, copy ~~-~~ paste clipboards, and file downloads are programmatically disabled of ~~f-~~ premises ~~.~~ 

###### 11 ~~.~~ Nomenclature Abstraction & Dynamic Identity Framework 

Decouples internal software identity from user ~~-f~~ acing naming conventions to support customization across diverse healthcare entities ~~.~~ 

###### 11 ~~.~~ 1 Immutable Internal Core Keys vs ~~.~~ Dynamic Tenant Aliases 

All backend source code, microservice routes, database schemas, and API payloads bind strictly to an Immutable Core Code ~~.~~ The user ~~-i~~ nterface display name is rendered dynamically from tenant-level meta ~~-c~~ onfiguration parameters: 

Immutable Backend Key: CLINICAL_ORCHESTRATOR == Dynamic UI Display Alias: Tenant 

|Internal Immutable<br>Code|Default Display<br>Alias|Example Tenant<br>Custom Alias 1|Example Tenant<br>Custom Alias 2|
|---|---|---|---|
|CLINICA~~L_~~ORCHE<br>STRATOR|Clinical Module|EMR Module|Patient Care Engine|
|PATIENT ~~_~~SPACE|Patient Space|Bed / Bay|Room Unit|
|VISIT_ID|Visit ID|Encounter Key|Episode Number|
|CDE~~_R~~EGISTRY|Data Element<br>Registry|Clinical Metrics<br>Catalog|Observation<br>Dictionary|





<!-- Start of picture text -->
MAR _E NGINE MAR eMAR Workspace Medication Tracker<br><!-- End of picture text -->

###### 11 ~~.~~ 2 Schema & API Contract Decoupling 

Database DDL schemas, internal webhook endpoints, and microservice payload contracts use invariant keys to prevent code breakage when labels are renamed: 

CREATE TABLE “tenan ~~t_~~ module ~~_a~~ liases™ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, “‘tenan ~~t_i~~ d’ VARCHAR(64) NOT NULL, 

*module ~~_c~~ ode’ VARCHAR(64) NOT NULL DEFAULT 'CLINICA ~~L_~~ ORCHESTRATOR', ‘displa ~~y_~~ name’ VARCHAR(128) NOT NULL DEFAULT 'Clinical Module’, 

“updated ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP ON UPDATE CURRENT ~~_T~~ IMESTAMP, 

UNIQUE KEY ‘ui ~~d_~~ tenan ~~t_~~ module’ (‘tenant ~~_i~~ d*, “module ~~_c~~ ode’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### 12. Atomic CDE Registry, Shift Handovers, Device Layer, & Al Gateway Integrations 

###### 12 ~~.~~ 1 Atomic Clinical Data Element (CDE) Architecture & Dynamic Documentation 

All clinical charts, flowsheets, forms, and reports are generated dynamically from atomic CDE definitions: 



<!-- Start of picture text -->
Define CDEs —+ Group CDEs —+ Build Templates —+ Apply Schedules —+> Capture Datz<br><!-- End of picture text -->

Define CDEs —+ Group CDEs —+ Build Templates —+ Apply Schedules —+> Capture Datz 

e 

Calculated Clinical Indicators: System automatically calculates composite scores (NEWS2, SATS, APGAR, GCS, eGFR, BMI, Fluid Balance Totals) using dynamic formula configuration dictionaries from Settings. 

###### 12 ~~.~~ 2 Al Gateway v3 ~~.~~ 1 Integration 

The module interfaces natively with the KashTre Shared Al Gateway v3 ~~.~~ 1 over standardized JSON contracts: 

- e SpeechToText: Live bedside audio streaming via WebSocket (wss://gateway.kashtre.local/v1/stt/stream). 

- e ObservationExtraction: Parses free ~~-~~ text dictation into structured CDE numeric/unit objects (selecting units strictly from clinical_ uom ~~_m~~ aster) to pre- ~~fi~~ ll observation forms. 



- e |ICD11Assistance: Suggests candidate ICD ~~-1~~ 1 diagnostic codes (e.g., query "Malaria" — code "1F40") requiring explicit human clinician validation. 

- e ObservationSummarization: Generates automated shift handover narratives across organ systems or care plans ~~.~~ 

###### 12.3 Device Integration Layer & Hybrid Validation Workflow 

Ingests direct telemetry streams from bedside equipment (Patient Monitors, Foetal Monitors, ICU Monitors, CVP Sensors, Temperature Probes): 

- 1 ~~.~~ Unvalidated Staging Ledger Schema: Raw device readings commit to an unvalidated staging table: 

Payload: {timestamp, capture_source, device_identifier, captured_value, validation_status} 

2. Hybrid Validation Protocol: High ~~-r~~ isk telemetry remains flagged as UNVALIDATED until an attending nurse clicks Validate & Commit on the ward flowsheet to sign off before it becomes a permanent legal CDE chart fact ~~.~~ 

###### 12.4 Observation Compliance Engine & Missing Observation Rules 

Actively monitors configured observation schedules (q15m, qth, q4h, Daily): 

- 1 ~~.~~ State Machine Transitions: Scheduled observations transition across three compliance states: 

DUE —+ OVERDUE —-+> MISSING 

2. Mandatory Audited Reason Logging: Skipped observations require a mandatory reason code selected from clinical_reas ~~on~~ _code ~~s_~~ master (R ~~EAS_~~ OBS ~~_ R~~ EFUSED,ON REASON ~~_O~~ BS ~~_O~~ FF ~~_W~~ ARD, REASON ~~_O~~ BS_IN ~~_T~~ HEATRE, REASON ~~_O~~ BS ~~_ E~~ QUIPMENT, REASON ~~_O~~ BS ~~_S~~ LEEP ~~_L~~ OCk) ~~.~~ 

###### 13. Specialized Clinical Workflows 

###### 13 ~~.~~ 1 Maternity Portal (The Birth Event) 

Captures delivery details, maternal CDE observations, APGAR scores, and birth events. Triggers an automated notification hook to register linked infant records that inherit primary maternal coverage keys ~~.~~ 

###### 13 ~~.~~ 2 Emergency Crash Cart Pos ~~t-~~ Consumption Reconciliation 

Clinical staff utilize crash cart supplies immediately during lif ~~e~~ -threatening crises without prior 

authorization holds ~~.~~ Nurses execute a post ~~-s~~ tabilization reconciliation workflow to log used items, update clinical charts, decrement inventory, and synchronize charge ledgers in a single step. 

###### 13 ~~.~~ 3 End- ~~o~~ f ~~-L~~ ife System Kill-Switch 

Validating a patient status as Deceased executes an immediate system kill-switch: halts active MAR orders, cancels pending lab/radiology worklists, freezes recurring billing streams, and locks the chart for mortality audit preservation ~~.~~ 



<!-- Start of picture text -->
A,<br><!-- End of picture text -->

###### A, OUT ~~-~~ OF ~~-~~ SCOPE BOUNDARY ITEMS (PARALLEL MODULE RESPONSIBILITIES) 

To preserve domain isolation, the following functional areas are strictly Out ~~-~~ o ~~f-~~ Scope for this module and must be managed by their respective application engines: 

###### 1. Main Module Developers 

- e Encounter Visit ID Lifecycle: Generating Visit ID strings, managing standard outpatient midnight expirations, and locking i ~~s_~~ admitted = TRUE persistent IDs during inpatient stays ~~.~~ 

- e Master Inventory Catalog: Managing global product catalogs and enforcing the i ~~s_~~ offe ~~r_~~ item boolean bitmask (exposing items to clinical search when TRUE and hiding raw warehouse parts when FALSE) ~~.~~ 

- e Post ~~-~~ Paid Credit Limits: Enforcing financial credit ceilings (max ~~_po~~ stpaid_limit) and commercial hold flags. 

- e Enterprise Service Queues: Generating enterprise service point queues (filtered views are projected by the Clinical Task Visibility Framework). 

###### 2. Inventory Module Developers 

- e Physical Stock Decrements: Executing physical inventory write ~~-o~~ ffs across sub ~~-s~~ tores upon receiving proxied REAGEN ~~T _~~ CONSUMPTION ~~_F~~ ACT webhooks. 

- e Forecasting Models: Computing moving ~~-~~ average inventory replenishment thresholds. 

###### 3. Finance & Pharmacy Service Point Developers 

- e Point ~~-~~ of ~~-~~ Sale Invoicing: Processing payments, checking insurance policy rules, and clearing financial claims ~~.~~ 

- e Loss Ledger Adjustments: Processing financial write ~~-o~~ ffs for documented MAR dose wastage ~~.~~ 

###### 4 ~~.~~ HR Module Developers 

- e Productivity Credit Accounting: Processing session participant IDs to track staff workload metrics. 

###### Official Security Addendum to Master Clinical Module SRD v6.0 

Document Reference: CLINICA ~~L~~ ORCHESTRATOR ~~_A~~ DDENDUM ~~_S~~ EC ~~_0~~ 1 

Base Document: KashTre Master Clinical Module Systems Requirements Document v6.0 

Target Architecture: Zer ~~o-~~ Code Mult ~~i-~~ Tenant Microservice Framework (Laravel Native) 

Scope: Medical Director I ~~n-~~ Person Device Enrollment, Of ~~f-~~ Premise Biometric Re ~~-A~~ uthentication Gate, 5 ~~-~~ Minute Inactivity Auto ~~-L~~ ogout, Dynamic Ant ~~i-~~ Leak Viewport Watermarking, & Medical Director Audit Surveillance Feed 

System Status: Approved Specification Addendum 

###### Executive Summary & Architectural Intent 

This Addendum extends Section 10 (Security & Remote Access Gateway) of the KashTre Master Clinical Module SRD v6.0. 

It establishes a Zero ~~-T~~ rust Network Access (ZTNA) Security Framework governed by the Medical Director/Chief Medical Officer (CMO). It solves the operational challenge of allowing of ~~f-~~ duty doctors to review patient charts, check diagnostic results, or finalize draft SOAP notes from home while enforcing strict patient privacy, anti-leak safeguards, and regulatory compliance. ~~|~~ ~~<u>|</u>~~ AUTHENTICATED CLINICIAN REQUEST | | ZTNA/MULTI-TENANT SECURITY GATEWAY | | (Evaluates IP Subnet, Device UUID & mTLS) | 

~~a~~ | v v 

[ ON ~~-~~ PREMISES NETWORK (Hospital IP / mTLS)] [ OFF ~~-~~ PREMISES / REMOTE (Home / Mobile)] 

- ¢ Full Operational Scope Enabled 

¢ Live Order Execution & Signing Allowed 

* ZTNA Restricted Scope Enabled ¢ Medical Director Device Enrollment 

###### Enforced 

¢ Direct Device Ingestion Active View 

¢ Unrestricted High ~~-~~ Resolution Image Rendering Enforced 

¢ Mandatory Biometric Re ~~-A~~ uth Per Chart 

¢ 5 ~~-~~ Minute Inactivity Auto ~~-~~ Logout 

- Dynamic Ant ~~i-~~ Leak Screen Watermarking 

- Read ~~-O~~ nly Chart Review & Draft Completion Only 

- ¢ Real-Time Feed to Medical Director Audit Log 

###### 1. I ~~n-~~ Person Medical Director Device Registration Protocol (clinical_devic ~~e_~~ enrollments) 

Before any doctor or clinician can access patient medical records of ~~f-~~ premises on a mobile phone, tablet, or personal laptop, the device must undergo an i ~~n-~~ person registration handshake at the Medical Director's office. 

###### 1 ~~.~~ 1 Four ~~-~~ Step Enrollment Workflow 

- 1 ~~.~~ I ~~n-~~ Person Physical Identity Verification: The clinician presents physically at the Medical Director / Chief Medical Officer (CMO) office with official national ID and medical staff credentials ~~.~~ 

- 2 ~~.~~ Time ~~-~~ Locked Pairing Token Generation: The Medical Director opens their administrative terminal and clicks "Enroll Clinical Device", generating a single ~~-~~ use, 5 ~~-~~ minute encrypted QR code and 6 ~~-d~~ igit OTP (clinical_enrollmen ~~t_t~~ okens) ~~.~~ 

- 3 ~~.~~ Hardware Enclave & WebAuthn Key Binding: 

   - The clinician scans the QR code inside the KashTre mobile/web application ~~.~~ 

   - The device's internal Hardware Security Enclave (Apple Secure Enclave / Android StrongBox) generates a unique device ~~_u~~ uid, hardware signature, and cryptographic Public/Private Key pair ~~.~~ 

   - Prompts the clinician to complete a local biometric scan (Fingerprint / FacelD) to bind the enclave keys to their physical identity. 

4. Registration Commitment: The backend stores the Public Key linked to user ~~_i~~ d and enrolle ~~d_~~ b ~~y_~~ md ~~_u~~ ser ~~_id.~~ The system enforces a strict rule: Maximum 1 active mobile Clinical device per clinician ~~.~~ 

###### 1.2 Database Schema DDL for Clinical Device Registration 

CREATE TABLE ‘clinical_enrollmen ~~t_~~ tokens’ ( 

- ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- ‘tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT', 

- “‘use ~~r_i~~ d° BIGINT UNSIGNED NOT NULL, -- Clinician being enrolled 

- ‘pairing ~~_~~ otp’ CHAR(6) NOT NULL, “q ~~r_~~ code ~~_t~~ oken’ VARCHAR(128) NOT NULL, 

“generate ~~d_~~ b ~~y_m~~ d ~~_us~~ er ~~_id~~ ° BIGINT UNSIGNED NOT NULL, -- Medical Director User ID ‘expi ~~rea~~ st’TIMESTAMP NOT NULL, -- 5 ~~-~~ minute time lock ‘is ~~c~~ onsumed’ BOOLEAN DEFAULT FALSE NOT NULL, 

“consumed ~~_a~~ t’ TIMESTAMP NULL, 

FOREIGN KEY (‘user ~~_i~~ d’) REFERENCES‘users’ (‘id’), 

FOREIGN KEY (‘generate ~~d_b~~ y ~~_m~~ d_ ~~us~~ er_ ~~id*~~ ’) REFERENCES ‘users’(‘id*), INDEX *id ~~x_~~ md ~~_t~~ oken_lookup* (‘tenant ~~_i~~ d’, ‘user ~~_i~~ d*, ‘pairing ~~_~~ otp’, ‘i ~~s~~ consumed’) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

CREATE TABLE ‘clinical_devic ~~e_~~ enrollments™ ( 

- ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- ‘tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT', 

“user ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

- ‘device ~~_u~~ uid’ VARCHAR(128) NOT NULL, 

- “devic ~~e_~~ model’ VARCHAR(128) NOT NULL, 

- ‘device ~~_os~~ *’ VARCHAR(64) NOT NULL, 

‘publi ~~c_~~ ke ~~y_~~ pem’ TEXT NOT NULL, -- Hardware Security Enclave Public Key 

‘enrolle ~~d_~~ b ~~y_~~ md ~~_u~~ ser ~~_id~~ ’ BIGINT UNSIGNED NOT NULL, -- Medical Director Sign ~~-~~ Off Anchor 

‘status’ ENUM(‘ACTIVE’, ‘SUSPENDED’, 'REVOKED') DEFAULT 'ACTIVE' NOT NULL, 

- ‘enrolled ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, ‘la ~~st~~ _offs ~~it~~ e_acc ~~esa~~ s_t’TIMESTAMP NULL, FOREIGN KEY (‘user ~~_i~~ d’) REFERENCES‘users’ (‘id’), 

FOREIGN KEY (‘enroll ~~ed_~~ b ~~y_~~ md ~~_u~~ ser ~~_id~~ *) REFERENCES‘users’ (‘id’), UNIQUE KEY ‘ui ~~d_u~~ ser ~~_c~~ linical_device’ (tenant ~~_id~~ *, ‘user ~~_i~~ d’, “device ~~_u~~ uid’), INDEX *“i ~~dx_~~ clinical_device_lookup* (‘tenant ~~_i~~ d’, “device ~~_u~~ uid’, ‘status’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### 2 ~~.~~ Of ~~f-~~ Premise Biometric Re ~~-A~~ uthentication Gate ("Clinical Access Challenge") 

When an authenticated clinician attempts to open a patient chart, view lab results, or edit draft notes while connected of ~~f-~~ premises (outside registered hospital IP subnets): 

1. Enrolled Device Check: The ZTNA Gateway verifies that the incoming request originates from a device with status ACTIVE in clinical_devic ~~e_e~~ nrollments. 

2. Biometric Challenge Prompt: The app triggers the phone's native hardware scanner (Fingerprint/FacelD) ~~.~~ Passing the scan unlocks the hardware ~~-s~~ tored Private Key to cryptographically sign the chart view payload. 

3. Contextual Scope Lock (Drafts & Reviews Only): o Permitted Actions: Reviewing patient history, checking lab/radiology reports, dictating rough notes, completing draft SOAP notes ~~.~~ 

- Prohibited Actions: Of ~~f-~~ premise users are strictly barred from placing live medication orders, authorizing MAR dose administrations, releasing official diagnostic reports, or clearing financial holds (unless executing an audited Break ~~-~~ Glass Emergency Override) ~~.~~ 

###### 3 ~~.~~ 5 ~~-~~ Minute Inactivity Auto ~~-~~ Logout & Session Lock Engine 

To prevent unauthorized viewing if an of ~~f-~~ duty clinician leaves their personal device unattended at home or in a public space: 

1. Precision Inactivity Timer: The of ~~f-~~ premises clinical viewport initializes a local JavaScript inactivity tracker monitoring user inputs (touch, click, keypress, scroll). 



<!-- Start of picture text -->
(E = = 300 seconds<br><!-- End of picture text -->

- 2 ~~.~~ Automatic Session Termination (E = = 300 seconds ): If no user interaction is detected for 5 continuous minutes (300 seconds), the system automatically locks the viewport, purges all i ~~n~~ -memory chart data, and redirects to a locked authentication screen ~~.~~ 

- 3 ~~.~~ Re ~~-A~~ uthentication Requirement: Re ~~-o~~ pening the active chart requires a fresh biometric scan (Fingerprint/FacelD) to unlock the hardware enclave ~~.~~ 

###### 4. Data Loss Prevention (DLP) & Dynamic Viewport Watermarking 

To prevent unauthorized screen captures, photography, or data caching on of ~~f-~~ premise devices: 

- 1 ~~.~~ Dynamic Ant ~~i-~~ Leak Viewport Watermark: The UI overlays a transparent, diagonal ant ~~i-~~ screenshot watermark across the entire chart rendering: 

Watermark Overlay: [DR. KAMARA| STAFF ID: 104 | REMOTE IP: 197.214.12.88 | 2026-07-25 22:15:00 uTC| 

2. Zero Local Storage (Ephemeral RAM Only): Local browser localStorage, sessionStorage, and IndexedDB are programmatically cleared. Chart data resides strictly in ephemeral RAM and is wiped immediately upon tab closure or session timeout ~~.~~ 

3. OS Clipboard & Screenshot Interception: Disables copy ~~-~~ paste clipboard buffers and triggers OS-level window obscuring when screen recording or screenshotting is attempted ~~.~~ 

###### 5. Medical Director Audit Surveillance Feed 

###### (clinical_ offsi ~~te~~ _access_logs) 

Every of ~~f~~ -campus chart view, draft update, or result review event dispatches an immutable transaction record directly to the Medical Director's security console. 

###### 5 ~~.~~ 1 Schema for Off ~~-~~ Site Access Telemetry 

- CREATE TABLE ‘clinical_of ~~fs~~ ite_acceslog **s** ’_ ( 

   - ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

   - ‘tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

   - “user ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, -- Clinician User ID 

   - “enrolle ~~d_d~~ evice ~~_i~~ d° BIGINT UNSIGNED NOT NULL, 

   - *patient ~~_i~~ d* VARCHAR(64) NOT NULL, 

   - ‘visi ~~t_i~~ d” VARCHAR(64) NOT NULL, 

   - “access ~~p~~ urpose’ ENUM('CHART ~~_R~~ EVIEW', 'DRAF ~~T_~~ NOTE ~~_C~~ OMPLETION', 

- ‘LA ~~B_~~ RESULT ~~_C~~ HECK', 'BREA ~~K_~~ GLASS ~~_~~ EMERGENCY’) NOT NULL, ‘captured ~~_i~~ p’ VARCHAR(45) NOT NULL, 

   - “captured_latitude’ DECIMAL(10,8) NULL, 

   - ‘captured_longitude’ DECIMAL(11,8) NULL, 

- ‘biometri ~~c_~~ signatur ~~e_~~ hash* VARCHAR(255) NOT NULL, -- Signed via Hardware Enclave 

- Private Key 

*sessio ~~n_~~ duratio ~~n_~~ seconds’ INT UNSIGNED DEFAULT 0 NOT NULL, 

- ‘i ~~s_~~ flagge ~~d_~~ fo ~~r_~~ review BOOLEAN DEFAULT FALSE NOT NULL, -- Medical Director audit 

- flag 

   - ‘created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

FOREIGN KEY (‘user ~~_i~~ d’) REFERENCES‘users’ (‘id’), 

FOREIGN KEY (enrolled ~~_d~~ evice ~~_i~~ d’) REFERENCES ‘clinical_de ~~vi~~ ce_enrollments(‘id’), INDEX ‘id ~~x_~~ offsi ~~te~~ _md ~~_f~~ eed* (‘tenant ~~_i~~ d*, ‘created ~~_a~~ t’ DESC, ‘is ~~fl~~ agged ~~_f~~ o ~~r_~~ review) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### 5 ~~.~~ 2 Medical Director Dashboard Features 

- e Real-Time Surveillance Stream: Displays live of ~~f~~ -campus access events, flagging afte ~~r-~~ hours access spikes, prolonged sessions, or unassigned chart reviews. 

- e 1 ~~-~~ Click Administrative Override: Allows the Medical Director to click any log row to inspect exact duration, edited draft text, and GPS/IP maps, with 1 ~~-~~ click authority to Suspend Device Access or Demand Official Audit Justification. 

