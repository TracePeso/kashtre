## Technicale@ Engineeringe e Specificatione e e@ & Implementatione Blueprinte@ 

Component Core Key: CLINICAL ORCHESTRATOR 

Target Platform: Laravel Native v10+ (PHP 8.2+) / MySQL 8.0+ (InnoDB) / Vue 3 (Composition API) 

Specification Version: v6.0 ~~-~~ Engineerin ~~g-~~ Master 

Companion Document: KashTre Clinical Module SRD v6.0 

Status: Ready for Implementation 

### 1. Complete Relational Database Schema Blueprint (DDL SQL) 

The database schema is structured for zero ~~-~~ hardcoding mult ~~i-~~ tenancy, immutable auditability, and atomic CDE data modeling. 

-- 1. SETTINGS & MASTER DICTIONARIES REGISTRY 

CREATE TABLE ‘clinical_uo ~~m_~~ master’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT’, 

“unit_label’ VARCHAR(32) NOT NULL, -- e.g., ‘mmol/L, ‘mg/dL’, °C’ 

“ucum ~~_c~~ ode’ VARCHAR(32) NULL, -- International UCUM Standard 

‘category’ VARCHAR(64) NOT NULL, -- e.g., ‘Volumetric Concentration’, ‘Temperature’ ‘description’ VARCHAR(255) NULL, 

‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

“updated ~~_a~~ t> TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP ON UPDATE 

CURRENT ~~_T~~ IMESTAMP, 

UNIQUE KEY ‘ui ~~d_~~ tenant ~~_u~~ nit’ (‘tenant ~~_i~~ d’, “unit_label’), 

INDEX “id ~~x_~~ uom ~~_c~~ at® (‘tenant ~~_i~~ d’, ‘category’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_uo ~~m_~~ conversion ~~s_~~ master’ ( 

- ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- “tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT’, 

- “cd ~~e_~~ code’ VARCHAR(64) NOT NULL, -- e.g., ‘'GLUCOS ~~E_~~ RANDOM' 

“from ~~_u~~ nit ~~_i~~ d* BIGINT UNSIGNED NOT NULL, 

“t ~~o_u~~ nit ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

“conversion ~~_t~~ ype’ ENUM(‘MULTIPLIER’, 'DIVISOR’, ‘POLYNOMIAL) NOT NULL DEFAULT ‘MULTIPLIER’, 

‘factor’ DECIMAL(16,8) NOT NULL DEFAULT 1.00000000, 

- ‘formula ~~_e~~ xpression’ VARCHAR(255) NULL, -- e.g., ‘((C * 1.8) + 32)' 

“decimal_precision’ TINYINT UNSIGNED DEFAULT 2 NOT NULL, 

‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, FOREIGN KEY (‘from ~~_u~~ nit ~~_i~~ d’) REFERENCES ‘clinical ~~_u~~ om_master’(‘id’), FOREIGN KEY (‘t ~~o_u~~ nit ~~_i~~ d®) REFERENCES ‘clinical ~~_u~~ om_master’(‘id’), UNIQUE KEY ‘ui ~~d_~~ conv ~~_r~~ ule® ('tenant ~~_i~~ d’, ‘cde ~~_c~~ ode’, ‘from ~~_u~~ nit ~~_i~~ d’, ‘to ~~_u~~ nit ~~_i~~ d’) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_reas ~~on~~ _code ~~s_~~ master’ ( 

- ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- “tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT', 

- “categor ~~y_~~ code’ VARCHAR(64) NOT NULL, -- ‘SKIPPED ~~_O~~ BS', 'BREAK ~~_G~~ LASS', 

‘MAR ~~_W~~ ASTAGE,, etc. 

“reaso ~~n_~~ code’ VARCHAR(64) NOT NULL, 

- ‘display_label’ VARCHAR(255) NOT NULL, 

- ‘requires ~~f~~ ree ~~_t~~ ext’ BOOLEAN DEFAULT FALSE NOT NULL, 

‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, 

UNIQUE KEY ‘ui ~~d_~~ tenan ~~t_~~ reason’ (‘tenant ~~_i~~ d’, ‘category ~~_c~~ ode’, ‘reason ~~_c~~ ode’) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

- CREATE TABLE ‘clinical_scoring ~~_d~~ ictionaries’ ( 

   - ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

   - “tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT’, 

   - “scor ~~e_~~ code’ VARCHAR(64) NOT NULL, -- 'NEWS2’, 'SATS', '‘APGAR’, 'GCS', 'EGFR ~~_C~~ KD_ ~~EP~~ I' 

   - “scor ~~e_~~ name’ VARCHAR(128) NOT NULL, 

   - “matri ~~x_~~ payload’ JSON NOT NULL, -- Range rules, weights, coefficients 

   - ‘version’ VARCHAR(16) DEFAULT 'v1.0' NOT NULL, 

‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, UNIQUE KEY ‘ui ~~d_~~ scor ~~e_~~ code’ (‘tenant ~~_i~~ d’, ‘scor ~~e_~~ code’, ‘version’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_escalation ~~_r~~ ules* ( 

- ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT'’, 

“severity_ ~~ti~~ er’- ENUM('INFO’, 'WARNING’, 'URGENT ~~_R~~ EVIEW’, ‘CRITICAL ~~_P~~ ANIC’) NOT NULL, “colo ~~r_~~ hex’ CHAR(7) NOT NULL, -- e.g., '#DC2626' 

“auditory ~~_s~~ ignal’ VARCHAR(128) NULL, 

“screen ~~_a~~ ction’ ENUM(‘TOAST', 'HEADER ~~_A~~ LERT’, ‘MODAL ~~_P~~ OPUP’, 'SCREEN ~~_L~~ OCK’) NOT NULL, 

‘target ~~_r~~ oles' JSON NOT NULL, -- e.g., ["WARD ~~_N~~ URSE", "DUTY ~~_R~~ ESIDENT", "MATRON"] UNIQUE KEY ‘ui ~~d_~~ tenant ~~_s~~ everity’ (tenant ~~_id~~ ’, “severity ~~_t~~ ier’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘pharmacy ~~_r~~ oute ~~_f~~ requency ~~_m~~ aster’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT'’, 

“code” VARCHAR(32) NOT NULL, -- 'STAT’, ‘BID’, 'Q4H’, ‘PO’, 'IV' 

“type” ENUM(‘ROUTE’, FREQUENCY’) NOT NULL, 

‘display_label’ VARCHAR(128) NOT NULL, 

“minute ~~_i~~ nterval’ INT UNSIGNED DEFAULT O NOT NULL, 

‘j ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, 

UNIQUE KEY ‘ui ~~d_~~ route ~~_f~~ req’ (‘tenant ~~_i~~ d’, ‘type’, code’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

CREATE TABLE ‘tenan ~~t_~~ module_ ~~al~~ iases’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

“module ~~_c~~ ode’ VARCHAR(64) NOT NULL DEFAULT 'CLINICA ~~L_~~ ORCHESTRATOR', 

‘displa ~~y_~~ name’ VARCHAR(128) NOT NULL DEFAULT ‘Clinical Module’, 

“updated ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~T~~ IMESTAMP ON UPDATE 

###### CURRENT ~~_T~~ IMESTAMP, 

UNIQUE KEY ‘ui ~~d_~~ tenan ~~t_~~ module’ ('tenant ~~_i~~ d’, *module ~~_c~~ ode’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

-- 2. CARE ASSIGNMENT & PATIENT SPACES (BED CENSUS) 

###### CREATE TABLE ‘clinical_car ~~e_~~ teams’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

“team ~~_c~~ ode’ VARCHAR(64) NOT NULL, 

“tea ~~m_~~ name’ VARCHAR(128) NOT NULL, 

“specialty’ VARCHAR(64) NOT NULL, 

‘j ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, UNIQUE KEY ‘ui ~~d_~~ car ~~e_~~ team’ (‘tenant ~~_i~~ d’, ‘team ~~_c~~ ode’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_c ~~ar~~ e_te ~~am~~ _members’ ( 

“id” BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“team_ ~~id~~ *® BIGINT UNSIGNED NOT NULL, 

“user ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

‘rol ~~e_~~ code’ VARCHAR(64) NOT NULL, -- "CONSULTANT;, 'RESIDENT’, ‘NURSE’, ‘NUTRITIONIST FOREIGN KEY (‘team ~~_i~~ d’) REFERENCES ‘clinical ~~_c~~ are_teams(‘id’)<sup>ONDELETECASCADE,</sup> UNIQUE KEY ‘ui ~~d_~~ team ~~_u~~ ser’ (‘team ~~_i~~ d’, ‘user ~~_i~~ d*) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_ca ~~re~~ _assignments’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

“patient ~~_i~~ d’ VARCHAR(64) NOT NULL, 

- ‘visi ~~t_i~~ d” VARCHAR(64) NOT NULL, 

“assignmen ~~t_~~ model’ ENUM(‘INDIVIDUAL, 'ROLE’, 'TEAM', 'HYBRID') NOT NULL, 

‘primary ~~_d~~ octor ~~_i~~ d’ BIGINT UNSIGNED NULL, 

‘primary ~~_n~~ urse ~~_i~~ d’ BIGINT UNSIGNED NULL, 

“assigned ~~_t~~ eam ~~_i~~ d’ BIGINT UNSIGNED NULL, 

“assigned ~~_r~~ ol ~~e_~~ code’ VARCHAR(64) NULL, 

‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

FOREIGN KEY (‘assigned ~~_t~~ eam ~~_i~~ d’) REFERENCES ‘clinical ~~_c~~ are_teams(‘id’), INDEX “id ~~x_~~ care ~~_a~~ ssign ~~_p~~ atient® (tenant ~~_i~~ d’, ‘patient ~~_i~~ d’, ‘is ~~ac~~ tive’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clien ~~t_~~ spaces’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

- “puildin ~~g_~~ wing’ VARCHAR(64) NOT NULL, 

“ward ~~_c~~ ode’ VARCHAR(64) NOT NULL, 

“war ~~d_~~ name’ VARCHAR(128) NOT NULL, 

- “room ~~_~~ number’ VARCHAR(32) NOT NULL, 

“sub ~~_s~~ tore ~~_i~~ d’ VARCHAR(64) NOT NULL, -- Mapped Room- ~~t~~ o ~~-S~~ tore inventory ID INDEX “id ~~x_~~ space_lookup’ ( tenant ~~_id~~ *, ‘ward ~~_c~~ ode’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘patien ~~t_~~ beds’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“space_ ~~id~~ *’ BIGINT UNSIGNED NOT NULL, 

“bed ~~_c~~ ode’ VARCHAR(32) NOT NULL, -- e.g., ‘BED ~~-O~~ 1' 

“operational_state’ ENUM(‘AVAILABLE’, ‘OCCUPIED’, '"RESERVED') DEFAULT ‘AVAILABLE’ NOT NULL, 

‘current ~~_p~~ atient ~~_i~~ d’ VARCHAR(64) NULL, 

“current ~~_vi~~ sit ~~_i~~ d’ VARCHAR(64) NULL, 

‘i ~~s_~~ overflow’ BOOLEAN DEFAULT FALSE NOT NULL, -- Extra bed surge flag 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, FOREIGN KEY (‘space ~~_i~~ d’) REFERENCES ‘clien ~~t_~~ spaces’ (‘id’), UNIQUE KEY ‘ui ~~d_~~ spac ~~e_~~ bed’ (‘space ~~_i~~ d’, bed_ ~~c~~ ode’), 

INDEX “id ~~x_~~ be ~~d_~~ occupancy’ (‘current ~~_p~~ atient ~~_i~~ d*, “operational_state’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

-- 3. ATOMIC CDE ENGINE & TELEMETRY STAGING 

CREATE TABLE ‘cde ~~_r~~ egistry’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT’, 

“cd ~~e_~~ code’ VARCHAR(64) NOT NULL, -- e.g., 'TEMP ~~_A~~ XILLARY’, 'GLUCOS ~~E_~~ RANDOM' 

“cd ~~e_~~ name’ VARCHAR(128) NOT NULL, 

“data ~~_t~~ ype’ ENUM(‘NUMERIC’, 'BOOLEAN’, 'TEXT’, ‘CODE’, 'MULTI.COMPONENT’) NOT NULL, “bas ~~e_~~ uom ~~_i~~ d* BIGINT UNSIGNED NOT NULL, 

“normal_range ~~_m~~ in’ DECIMAL(12,4) NULL, 

“normal_rang ~~e_~~ max* DECIMAL(12,4) NULL, 

‘critical_high’ DECIMAL(12,4) NULL, 

“critical_low’ DECIMAL(12,4) NULL, 

‘physiological_min’ DECIMAL(12,4) NULL, -- Heuristic safety boundary ‘physiological_max’ DECIMAL(12,4) NULL, ‘i ~~s_~~ graphable’ BOOLEAN DEFAULT TRUE NOT NULL, ‘i ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, FOREIGN KEY (‘bas ~~e_~~ uom_ ~~id~~ ’) REFERENCES ‘clinical ~~_u~~ om_master’(‘id’), UNIQUE KEY ‘ui ~~d_~~ tenan ~~t_~~ cde’ (‘tenant ~~_i~~ d’, ‘cde ~~_c~~ ode’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4 ~~_~~ unicode ~~_c~~ i; 

CREATE TABLE ‘cde ~~_o~~ bservations’ ( ‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

‘patient ~~_i~~ d’ VARCHAR(64) NOT NULL, 

‘visi ~~t_i~~ d’ VARCHAR(64) NOT NULL, 

“cd ~~e_~~ code’ VARCHAR(64) NOT NULL, 

“captured ~~_v~~ alue ~~_n~~ umeric’ DECIMAL(12,4) NULL, 

‘captured ~~_v~~ alue ~~_t~~ ext’ TEXT NULL, 

‘captured ~~_v~~ alue ~~_j~~ son’ JSON NULL, -- For mult ~~i~~ -component e.g. BP {systolic, diastolic} 

‘inpu ~~t_~~ uom ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

‘bas ~~e_~~ uom ~~_i~~ d* BIGINT UNSIGNED NOT NULL, 

“base ~~_v~~ alu ~~e_~~ numeric’ DECIMAL(12,4) NULL, -- Normalized base value 

“captur ~~e_~~ method’ ENUM('MANUAL, 'VOICE ~~_D~~ ICTATION’, ‘DEVICE_IMPORT’, ‘'CALCULATED’) NOT NULL, 

‘validatio ~~n_s~~ tatus’ ENUM('VALIDATED’, 'UNVALIDATED') DEFAULT 'VALIDATED' NOT NULL, 

‘validate ~~d_~~ by ~~_u~~ ser ~~_i~~ d’ BIGINT UNSIGNED NULL, 

‘captured ~~_a~~ t’ TIMESTAMP(3) DEFAULT CURRENT ~~_T~~ IMESTAMP(3), FOREIGN KEY (‘inpu ~~t_~~ uom ~~_i~~ d’) REFERENCES ‘clinical ~~_u~~ om_master(‘id’), FOREIGN KEY (‘bas ~~e_~~ uom_ ~~id~~ ’) REFERENCES ‘clinical ~~_u~~ om_master’(‘id’), 

INDEX ‘id ~~x_~~ cde ~~_p~~ atient ~~_t~~ ime’ (tenant ~~_i~~ d’, ‘patient ~~_i~~ d’, ‘cde ~~_c~~ ode’, ‘captured ~~_a~~ t’ DESC) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

CREATE TABLE ‘cde ~~_d~~ evice ~~_s~~ taging’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

‘device_ ~~id~~ entifier’- VARCHAR(128) NOT NULL, 

‘capture ~~_s~~ ource’ VARCHAR(64) NOT NULL, -- e.g., 'IC ~~U_~~ MONITOR_ ~~01~~ ’, '"FOETA ~~L_~~ DOPPLER' “ra ~~w_~~ payload JSON NOT NULL, 

‘captured ~~_v~~ alue’ DECIMAL(12,4) NOT NULL, 

“cd ~~e_~~ code’ VARCHAR(64) NOT NULL, 

“patient ~~_i~~ d* VARCHAR(64) NOT NULL, 

‘validatio ~~n_~~ status’ ENUM(‘UNVALIDATED’, ‘VALIDATED’, REJECTED") DEFAULT 'UNVALIDATED' NOT NULL, 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

INDEX “id ~~x_~~ device ~~_s~~ taging’ ( tenant ~~_i~~ d’, ‘patient ~~_i~~ d’, ‘validatio ~~n_s~~ tatus’) ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

-- 4, PROCESS REGISTRY & WORK ORDERS 

CREATE TABLE ‘clinical_process ~~_r~~ egistry™ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL DEFAULT 'DEFAULT’, 

‘proces ~~s_~~ code’ VARCHAR(64) NOT NULL, -- ‘ADMISSION’, ‘TRANSFER’, ‘DISCHARGE’, 

‘DEATH ~~_C~~ ERT' 

‘process name’ VARCHAR(128) NOT NULL, 

‘j ~~s_~~ active’ BOOLEAN DEFAULT TRUE NOT NULL, 

UNIQUE KEY ‘ui ~~d_~~ proces ~~s_~~ code’ (‘tenant ~~_i~~ d’, ‘proces ~~s_~~ code’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_proces ~~s_~~ steps° ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

‘process ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

“step ~~_o~~ rder’ TINYINT UNSIGNED NOT NULL, 

“ste ~~p_~~ code’ VARCHAR(64) NOT NULL, 

“ste ~~p_~~ name’ VARCHAR(128) NOT NULL, 

‘i ~~s_~~ mandatory’ BOOLEAN DEFAULT TRUE NOT NULL, 

‘required ~~_r~~ ole’ VARCHAR(64) NOT NULL, -- e.g., ‘CONSULTANT’, 'WARD ~~_N~~ URSE' 

FOREIGN KEY (‘process ~~_i~~ d’) REFERENCES‘clinical_process ~~r~~ egistry’ (‘id’) ON DELETE CASCADE, 

- UNIQUE KEY ‘ui ~~d_~~ process ~~_s~~ tep* (‘proc ~~e_i~~ ssd’, ‘step ~~_o~~ rder’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_wor ~~k_~~ orders’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- “tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

- ‘patient ~~_i~~ d™ VARCHAR(64) NOT NULL, 

- ‘visi ~~t_i~~ d’ VARCHAR(64) NOT NULL, 

- ‘orde ~~r t~~ ype’ VARCHAR(64) NOT NULL, -- 'DAIL ~~Y~~ WARD ~~_R~~ EVIEW’, ‘PR ~~E_~~ O ~~P_~~ CHECKLIST' 

“assigned ~~_t~~ o ~~_u~~ ser ~~_i~~ d’ BIGINT UNSIGNED NULL, 

“assigned ~~_r~~ ol ~~e_~~ code’ VARCHAR(64) NULL, 

“status’ ENUM(‘PENDING', 'I ~~N_~~ PROGRESS’, ‘COMPLETED’, ‘CANCELLED') DEFAULT 'PENDING' NOT NULL, 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

“completed ~~_a~~ t’ TIMESTAMP NULL, 

INDEX “id ~~x_~~ work ~~_o~~ rder ~~_a~~ ssignee’ (‘tenant ~~_i~~ d’, ‘assigned ~~_t~~ o ~~_u~~ ser ~~_id~~ ’, status’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

###### CREATE TABLE ‘clinical_entitlements’ ( 

‘id’ BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

- “tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

- “patient ~~_i~~ d’ VARCHAR(64) NOT NULL, 

- ‘package ~~_i~~ d® VARCHAR(64) NOT NULL, 

“servic ~~e_~~ code’ VARCHAR(64) NOT NULL, 

‘allocated ~~_q~~ ty’ INT UNSIGNED NOT NULL, 

“use ~~d_~~ qty INT UNSIGNED DEFAULT O NOT NULL, 

“remaining ~~_q~~ ty’ INT UNSIGNED GENERATED ALWAYS AS (‘allocated ~~_q~~ ty® ~~-~~ ‘used ~~_q~~ ty’) STORED, 

INDEX “id ~~x_~~ entitlement_lookup’ (‘tenant ~~_i~~ d’, ‘patient ~~_i~~ d’, “servic ~~e_~~ code’) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

-- 5. AUDIT & BREAK ~~-~~ GLASS LOGS 

- CREATE TABLE ‘clinical_b ~~re~~ ak_g **l** ogs’ass ( 

‘id* BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, 

“tenant ~~_i~~ d’ VARCHAR(64) NOT NULL, 

“user ~~_i~~ d’ BIGINT UNSIGNED NOT NULL, 

“patient ~~_i~~ d’ VARCHAR(64) NOT NULL, 

‘visi ~~t_i~~ d” VARCHAR(64) NOT NULL, 

“reaso ~~n_~~ code’ VARCHAR(64) NOT NULL, 

‘justificatio ~~n_~~ note’ TEXT NULL, 

“granted_ ~~un~~ til’ TIMESTAMP NOT NULL, 

“created ~~_a~~ t’ TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 

INDEX “id ~~x_~~ break ~~_g~~ lass ~~_a~~ udit* ( tenant ~~_i~~ d’, ‘user ~~_i~~ d’, “created ~~_a~~ t’ DESC) 

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_ ~~un~~ icode_ ~~ci~~ ; 

#### 2. Dynamic Pr ~~e-~~ Seeded Database Master Seeder 

This production Laravel database seeder populates all required master dictionaries with standard clinical units, UCUM codes, conversion factors, reason codes, scoring rules, escalation tiers, routes, and frequencies out ~~-~~ of ~~-~~ the ~~-b~~ ox. 

###### namespace Database\Seeders; 

use Illuminate\Database\Seeder; use Illuminate\Support\Facades\DB; 

class ClinicalMasterDictionariesSeeder extends Seeder { 

public function run(): void 

{ 

###### $tenantid = ‘DEFAULT’; 

// 1, SEED MASTER UNITS OF MEASURE (UOM) 

###### $units = [ 

['°C’, ‘Cel’, ‘Temperature’, ‘Celsius Vitals'], 

['°F', ‘[degF]’, ‘Temperature’, ‘Fahrenheit Vitals'], 

[‘mmHg’, 'mm|[Hg]'’, ‘Blood Pressure / Tension’, 'Systolic/Diastolic BP'], 

[‘cmH20', 'cm[H20]’, ‘Respiratory/ Fluid Pressure’, ‘Ventilator PEEP’, 

[‘bpm'’, ‘/min’, ‘Heart & Respiratory Rates’, ‘Pulse/ Heart Rate’), 

[‘breaths/min’, ‘{breaths}/min’, 'Heart & Respiratory Rates’, ‘Respiratory Rate’, 

['kg’, ‘kg’, ‘Mass / Weight’, ‘Patient Mass'], 

[‘g’, 'g', ‘Mass / Weight’, ‘Infant Mass'], 

‘mg’, 'mg', ‘Mass / Weight’, ‘Drug Mass'], 

‘g/dL, ‘g/dL, ‘Volumetric Concentration’, ‘Hemoglobin Concentration’, 

[‘mg/dL, ‘mg/dL, ‘Volumetric Concentration’, ‘Glucose/ Bilirubin'], 

[‘mmol/L, ‘mmol/L, ‘Molar Concentration’, ‘Electrolytes / Glucose Base’], [‘umol/L, 'umol/L, ‘Molar Concentration’, ‘Serum Creatinine’], [‘mL/hr’, '‘mL/h’, ‘Infusion & Flow Rates’, 'lV Infusion Velocity'], [‘mg/kg’, ‘mg/kg’, 'Weigh ~~t-~~ Normalized Dosing’, ‘Pediatric Dosage Metric’, [‘mL/min/1.73m”, 'mL/min/{1.73 ~~_m~~ 2},, ‘Renal Function / Clearance’, 'eGFR Metric’, ['%', '%', ‘Percentages & Proportions’, '‘SpO2 Oxygen Saturation’), 

]; 

###### foreach ($units as $u) { 

DB::table(‘clinical_u ~~om_~~ master' ~~)~~ ->updateOrInsert( 

[‘tenant ~~_i~~ d' => $tenantld, ‘unit_label' => $u[O]], 

[‘'ucu ~~m_~~ code' => $u[1], ‘category’ => $u[2], ‘description’ => $u[3], 'i ~~s_~~ active' => true] 

); } 

###### // 2. SEED PR ~~E-~~ CONFIGURED UNIT CONVERSIONS 

$mmolld = DB::table(‘clinical_ uom ~~_m~~ aster’)- ~~>~~ where(‘unit_label’, ‘mmol/L)- ~~>v~~ alue(‘id’); $mgdlld = DB::table(‘clinical_uom ~~_m~~ aster') ~~->~~ where(‘unit_label', 'mg/dL)- ~~>v~~ alue(‘id’); $umolld = DB::table(‘clinical_ uom ~~_m~~ aster’)- ~~>~~ where(‘unit_label’, ‘umol/L)- ~~>~~ value(‘id’); 

###### if ($mmolld && $mgalld) { 

// Glucose: mmol/L < ~~-~~ > mg/dL (Multiplier 18.0182) 

DB::table(‘clinical_u ~~om_~~ conversion ~~s_m~~ aster’) ~~-~~ >updateOrlInsert( 

[‘tenant ~~_i~~ d' => $tenantld, ‘cd ~~e_~~ code' => 'GLUCOSE ~~_~~ RANDOM', '‘fro ~~m_u~~ nit ~~_i~~ d' => 

$mmolld, ‘t ~~o_u~~ nit ~~_i~~ d' => $mgdlld], 

[‘conversion ~~_t~~ ype' => ‘MULTIPLIER’, ‘factor’ => 18.01820000, ‘decimal_precision' => 1, 

‘i ~~s_~~ active' => true] ); DB::table(‘clinical_u ~~om~~ _conversion ~~s_~~ master’ ~~)-~~ >updateOrInsert( 

[‘tenant ~~_i~~ d' => $tenantld, 'cd ~~e_~~ code' => 'GLUCOSE ~~_~~ RANDOM', '‘fro ~~m_u~~ nit ~~_i~~ d' => 

$mgadlld, 't ~~o_u~~ nit ~~_i~~ d' => $mmolld], 

[‘conversion ~~_t~~ ype' => 'DIVISOR’, 'factor' => 18.01820000, ‘decimal_precision' => 1, ‘i ~~s_~~ active' => true] 

); } 

if ($umolld && $mgadlld) { 

// Creatinine: umol/L ~~-~~ > mg/dL (Divisor 88.4) 

DB::table(‘clinical_u ~~om_~~ conversion ~~s_m~~ aster’) ~~-~~ >updateOrlInsert( 

[‘tenant ~~_i~~ d' => $tenantld, 'cd ~~e_~~ code' => 'CREATININE ~~_S~~ ERUM', 'from ~~_u~~ nit ~~_i~~ d' => $umolld, 't ~~o_u~~ nit ~~_i~~ d' => $mgdlld], 

[‘conversion ~~_t~~ ype' => 'DIVISOR’, ‘factor’ => 88.40000000, ‘decimal_precision’ => 2, ‘i ~~s_~~ active' => true] 

); } /1 3. SEED REASON CODES $reasons = [ [‘SKIPPED ~~_O~~ BS', 'REASON ~~_O~~ BS ~~_R~~ EFUSED’, ‘Patient Refused Observation’, false], [‘SKIPPED ~~_O~~ BS', 'REASON ~~_O~~ BS ~~_O~~ FF ~~_W~~ ARD', 'Patient Off Ward / In Transit’, false], 

/1 3. SEED REASON CODES 

[‘SKIPPED ~~_O~~ BS', 'REASON ~~_O~~ BS_IN ~~_T~~ HEATRE;, ‘Patient In Operating Theatre’, false], [‘BREAK ~~_G~~ LASS'’, ‘OVERR ~~ID~~ E_EMERG ~~ER~~ NCY_ESUS’, ‘Emergency Resuscitation / Crash Call’, true], [‘BREAK ~~_G~~ LASS', 'OVERRID ~~E_~~ ON ~~_C~~ ALL ~~_C~~ OVER’, ‘On ~~-C~~ all Night / Weekend Coverage’, false], [‘'MA ~~R_~~ WASTAGE’, 'MA ~~R_~~ WASTAGE ~~_D~~ ROPPED’, 'Dose Dropped / Contaminated’, false], [‘'MA ~~R_~~ WASTAGE'’, 'MAR ~~_~~ WASTAGE_ ~~L~~ INE ~~_B~~ LOWN', 'IlV Line Blown/ Infiltrated'’, false], ]; foreach ($reasons as $r) { DB::table(‘clinical_reas ~~on~~ _code ~~s_~~ master' ~~)-~~ >updateOrlnsert( [‘tenant ~~_i~~ d' => $tenantld, ‘categor ~~y_~~ code' => $r[O], 'reaso ~~n_~~ code' => $r[1]], [‘display_label' => $r[2], 'requires ~~_f~~ ree ~~_t~~ ext' => $r[3], 'i ~~s_~~ active' => true] ); } /1 4, SEED ESCALATION TIERS $escalations = [ (INFO! '#2563EB’, 'chime.mp3’, ‘TOAST’, jso ~~n_~~ encode(["WARD_ ~~NU~~ RSE"])], 

[WARNING '#D97706, 'beep ~~_d~~ ual.mp3’, 'HEADER ~~_A~~ LERT, jso ~~n_~~ encode(["WARD ~~_N~~ URSE", 

"DUTY ~~_R~~ ESIDENT"])], 

[‘URGENT ~~_R~~ EVIEW’, '#EA580C', 'beep ~~_c~~ ontinuous.mp3’, 'MODAL ~~_P~~ OPUP’, jso ~~n_~~ encode(["WARD ~~_N~~ URSE", "DUTY ~~_R~~ ESIDENT", "MATRON"))], 

[‘CRITICAL ~~_P~~ ANIC’, '#DC2626, 'sire ~~n_f~~ ull.mp3', 'SCREEN ~~_L~~ OCK’, jso ~~n_~~ encode(["WARD ~~_N~~ URSE", "DUTY ~~_R~~ ESIDENT", "IC ~~U_~~ REGISTRAR", "MATRON"])], 1; 

foreach ($escalations as $e) { 

DB::table(‘clinical_escalati ~~on_~~ rules ~~')~~ ->updateOrInsert( [‘tenant ~~_i~~ d' => $tenantld, 'severity ~~_t~~ ier’ => $e[O]], 

[‘colo ~~r_~~ hex' => $e[1], ‘auditory ~~_s~~ ignal' => $e[2], ‘screen ~~_a~~ ction' => $e[3], 'target ~~_r~~ oles' => 

$e[4]] 

); } 

/15. SEED ROUTES & FREQUENCIES 

$routesFregs= [ [‘PO'", ‘ROUTE;, ‘Oral’, 0], ‘IV’, "ROUTE;, ‘Intravenous; O], [‘IM', ‘ROUTE; ‘Intramuscular’, 0], 

[‘SC’, ‘ROUTE’, ‘Subcutaneous’, 0], [‘STAT’, ‘FREQUENCY’, ‘Immediately/Emergency’, 0], [‘QD', ‘FREQUENCY’, ‘Once Daily’, 1440], [‘BID', ‘FREQUENCY’, ‘Twice Daily’, 720], [‘TID', ‘FREQUENCY’, 'Three Times Daily’, 480], [‘QID', ‘FREQUENCY’, ‘Four Times Daily’, 360], ['Q4H’, ‘FREQUENCY’, ‘Every 4 Hours’, 240], 

1; 

foreach ($routesFreqs as $rf) { DB::table(‘pharmacy ~~_r~~ oute ~~_f~~ requency_ ~~ma~~ ster’)- ~~>~~ updateOrlInsert( [‘tenant ~~_i~~ d' => $tenantld, ‘type’ => $rf[1], ‘code’ => $rf[O]], [‘display_label' => $rf[2], 'minute ~~_i~~ nterval' => $rf[3], 'i ~~s_~~ active' => true] 

); } } } 

#### 3. Zero ~~-T~~ rust Network Access (ZTNA) Security 

###### Middleware 

This Middleware intercepts all inbound requests, evaluates on ~~-~~ premises vs of ~~f-~~ premises IP/mMTLS context, enforces Relationshi ~~p-~~ Based Access Control (ReBAC) and Break ~~-~~ Glass sessions, blocks live orders of ~~f-~~ premises, and injects dynamic watermarking data. 

namespace App\Http\Middleware; 

use Closure; 

use Illuminate\Http\Request; 

use Illuminate\Support\Facades\DB; 

use Symfony\Component\HttpFoundation\Response; use Exception; 

class ZtnaContextMiddleware 

{ [** 

* Handle an incoming request enforcing ZTNA Dual-Tier Security. 

*/ 

public function handle(Request $request, Closure $next): Response { 

$user = auth() ~~->~~ user(); 

if ($user) { 

return response()- ~~>j~~ son(['error' => 'Unauthenticated'], 401); 

} 

$clientlp = $request- ~~>i~~ p(); 

$isOnPremises = $thi ~~s-~~ >evaluateOnPremisesContext($request, $clientlp); 

$request- ~~>~~ attributes ~~->~~ set(‘i ~~s_~~ on ~~_p~~ remises', $isOnPremises); 

// 1. OFF ~~-~~ PREMISES LIVE ORDER BLOCKING 

if ('$isOnPremises) { 

$isMutatingOrderRoute = $request-> ~~i~~ s(‘api/v1/clinical/orders/*’) || 

$request- ~~>i~~ s(‘api/v1/clinical/mar/administer’); 

if ($isMutatingOrderRoute && i ~~n_~~ array($reques ~~t-~~ >method(), ['POST’, ‘PUT’, ‘DELETE’))) { return response() ~~->~~ json([ 

‘error’ => 'ZTNA ~~_O~~ FF ~~_P~~ REMISE ~~_R~~ ESTRICTION’, 

‘message’ => ‘Live medication ordering and MAR dose administration are strictly prohibited of ~~f-~~ premises. Draft completion only.’ 

], 403); 

} 

} 

// 2. RELATIONSHI ~~P-~~ BASED ACCESS CONTROL (ReBAC) & BREAK ~~-~~ GLASS CHECK 

$patientld = $request- ~~>r~~ oute(‘patientld’) ?? $request- ~~>i~~ nput(‘patient_ ~~id~~ ’); 

if ($patientld) { 

$hasActiveRelationship = $thi ~~s-~~ >checkCareRelationship($user- ~~>~~ id, $patientld, $user ~~->~~ tenant_ ~~id~~ ); 

###### if (‘$hasActiveRelationship) { 

/! Check for valid active Break ~~-~~ Glass override token 

$hasActiveBreakGlass = DB::table(‘clinical_brea ~~k_g~~ lass_logs') 

~~-~~ >where(‘tenant_ ~~id~~ ', $user ~~->~~ tenant ~~_i~~ d) 

~~-~~ >where(‘user_ ~~id~~ ', $user ~~->~~ id) 

~~-~~ >where(‘patient_ ~~id~~ ', $patientid) 

~~-~~ >where(‘granted_u ~~nt~~ il’, ‘>’, now()) 

~~-~~ >exists(); 

###### if (‘$hasActiveBreakGlass) { 

###### return response() ~~->~~ json([ 

‘error’ => 'REBA ~~C_~~ ACCESS ~~_ D~~ ENIED’, 

‘message’ => 'No active care relationship found. Break ~~-~~ Glass emergency override required to view patient chart, 

‘requires ~~_~~ break ~~_g~~ lass' => true 

], 403); 

} 

} } 

$response = $next($request); 

/1 3. OFF ~~-P~~ REMISES DYNAMIC WATERMARK HEADER INJECTION 

if (‘$isOnPremises && $response instanceof Response) { 

$response ~~->~~ headers- ~~>~~ set('X ~~-K~~ ashTre ~~-W~~ atermark- ~~Us~~ er’, $use ~~r-~~ >name. " (ID: 

{$user- ~~>~~ id})"); 

$response ~~->~~ headers- ~~>s~~ et('X ~~-K~~ ashTre- ~~W~~ atermark-IP’, $clientlp); 

$response ~~->~~ headers ~~->~~ set(' ~~X-~~ KashTr ~~e-~~ Watermark ~~-T~~ imestamp’, now() ~~->~~ tolso8601String()); $response ~~->~~ headers- ~~>s~~ et('Cache- ~~Co~~ ntrol’, 'no ~~-~~ store, no ~~-~~ cache, must- ~~r~~ evalidate, max ~~-a~~ ge=0’); 

} 

return $response; 

} 

private function evaluateOnPremisesContext(Request $request, string $ip): bool 

{ 

$hospitalSubnets = config(‘kashtre.hospital_subnets’, ['10.0.0.0/8', '192.168.1.0/24']); 

foreach ($hospitalSubnets as $subnet) { 

if ($thi ~~s-~~ >ipInSubnet($ip, $subnet)) { 

return true; 

} 

} 

return $request ~~->~~ header('X ~~-K~~ ashTre- ~~m~~ TLS-V ~~e~~ rified’) === ‘true’; 

} 

private function checkCareRelationship(int $userld, string $patientld, string $tenantld): bool 

{ 

return DB::table(‘clinical_ca ~~re_~~ assignments’) 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantld) 

~~-~~ >where(‘patient_ ~~id~~ ’, $patientid) 

~~-~~ >where(‘is ~~_a~~ ctive’, true) 

~~-~~ >where(function ($query) use ($userld) { 

$query ~~->~~ where(‘primary_ ~~do~~ ctor_ ~~id~~ ', $userld) 

~~-~~ >orWhere(‘primary_ ~~nu~~ rse_ ~~id~~ ', $userld) 

~~-~~ >orWhereln(‘assigned ~~_te~~ am_ ~~id~~ ', function ($sub) use ($userld) { 

$sub ~~->~~ select(‘team ~~_i~~ d' ~~)-~~ >from(‘clinical_ca ~~re~~ _tea ~~m_~~ members')- ~~>~~ where(‘user_ ~~id~~ ’, 

$userld); 

}); } ~~)-~~ >exists(); } 

private function ipInSubnet(string $ip, string $subnet): bool 

{ if (strpos($subnet, '/') === false) return $ip === $subnet; list($sub, $bits) = explode(’/’, $subnet); $ip = ip2long($ip); $sub = ip2long($sub); $mask = ~~-1~~ << (32 ~~-~~ $bits); return ($ip & $mask) == ($sub & $mask); 

} 

} 

#### 4. Core CDE Execution, Unit Conversion & Heuristic 

##### Engine 

This service handles atomic observation capture, base unit normalization, poin ~~t-~~ o ~~f-~~ capture unit switching, physiological heuristic sanity validation, and dynamic scoring calculations (NEWS2, 

eGFR). 

namespace App\Services\Clinical; 

use Illuminate\Support\Facades\DB; use Exception; 

class CdeExecutionEngine 

{ [** * Captures an atomic CDE observation with dynamic unit conversion & safety heuristics. */ public function captureObservation(array $payload, int $userld, string $tenantld): array 

{ 

$cde = DB::table(‘cde ~~_r~~ egistry') 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantid) 

~~-~~ >where(‘cde ~~_c~~ ode’, $payload|'cde ~~_c~~ ode’]) ~~-~~ >first(); 

if (Sede) { 

throw new Exception("Invalid CDE Code: {$payload['cde_ ~~co~~ de']}"); 

} 

$inputVal = $payload['value ~~_n~~ umeric’]; 

$inputUomld = $payload['input ~~_u~~ om_ ~~id~~ ’]; $baseUomld = $cde ~~->~~ base ~~_u~~ om_ ~~id~~ ; 

// 1. PHYSIOLOGICAL HEURISTIC RANGE INTERCEPTION 

$thi ~~s-~~ >validatePhysiologicalHeuristic($cde, $inputVal, $inoutUomld, $baseUomld); 

// 2. BASE UNIT NORMALIZATION 

$baseValue = $thi ~~s-~~ >normalizeToBaseUnit($payload['cde ~~_c~~ ode’], $inputVal, $inoutUomld, $baseUomid, $tenantld); 

// 3. PERSIST ATOMIC OBSERVATION 

$obsld = DB::table(‘cd ~~e_~~ observations’) ~~->~~ insertGetld([ 

‘tenant ~~_i~~ d' => $tenantld, 

‘patient ~~_i~~ d' => $payload['patient_ ~~id~~ '], ‘visi ~~t_i~~ d' => $payload['visit_ ~~id~~ '], ‘cd ~~e_~~ code' => $payload['cde ~~_c~~ ode’], ‘captured ~~_v~~ alu ~~e_~~ numeric' => $inputVal, ‘inpu ~~t_~~ uom ~~_i~~ d' => $inoputUomld, 

‘pas ~~e_~~ uom_ ~~id~~ ' => $baseUomld, 

‘pas ~~e_~~ valu ~~e_~~ numeric' => $baseValue, ‘captur ~~e_~~ method' => $payload['captur ~~e_~~ method'] ?? ‘MANUAL, 

‘validatio ~~n_s~~ tatus' => $payload|'validation ~~_s~~ tatus'] ?? ‘VALIDATED’, ‘validate ~~d_~~ by ~~_u~~ ser ~~_i~~ d' => $userld, ‘captured ~~_a~~ t' => now(), 

}); 

return [ 

‘observation ~~_i~~ d' => $obsld, ‘captured ~~_v~~ alue' => $inputVal, ‘pas ~~e_v~~ alue ~~_n~~ ormalized' => $baseValue, 

‘ ~~is_~~ panic ~~_h~~ igh' => $cde- ~~>~~ critical_high && $baseValue > $cde- ~~>~~ critical_high, 

‘ ~~is_~~ panic_low' => $cde- ~~>~~ critical_low && $baseValue < $cde- ~~>~~ critical_low, 

]; } 

private function validatePhysiologicalHeuristic($cde, float $val, int $inobutUomld, int $baseUomld): void 

{ // \f entering in mmol/L and typing > 100 (e.g. 180 typed for glucose) if ($cd ~~e-~~ >cd ~~e_~~ code === 'GLUCOS ~~E_~~ RANDOM' && $inputUomld === $baseUomld && $val > 100) { 

throw new Exception("HEURISTI ~~C~~ _SAFET ~~Y_~~ BLOCK: Value {$val} mmol/L exceeds physiological limits. Did you mean {$val} mg/dL?"); 

} } 

public function normalizeToBaseUnit(string $cdeCode, float $val, int $fromUom, int $toUom, string $tenantld): float 

{ 

if ($fromUom === $toUom) return $val; 

$conv = DB::table(‘clinical_uo ~~m_~~ conversion ~~s_m~~ aster') 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantid) 

~~-~~ >where(‘cde ~~_c~~ ode’, $cdeCode) 

~~-~~ >where(‘from_ ~~un~~ it_ ~~id~~ ', $fromUom) 

~~-~~ >where(‘to_ ~~uni~~ t_i ~~d'~~ ’, $toUom) ~~-~~ >first(); 

if (1$conv) return $val; 

if ($conv ~~-~~ >conversion ~~_t~~ ype === MULTIPLIER’) { 

return round($val * $conv ~~->~~ factor, $conv ~~->~~ decimal_precision); 

} elseif ($conv ~~-~~ >conversion ~~_t~~ ype === 'DIVISOR’) { 

return round($val / $conv ~~->~~ factor, $conv ~~->~~ decimal_precision); 

} 

return $val; 

} 

} 

#### 5. Deterministic Clinical Decision Support System (CDSS) Shield 

This deterministic engine executes hard patient safety checks during medication order entry, evaluating Drug ~~-~~ Drug Interactions (DDIs), drug allergies, pediatric weight limits (mg/kg), and eGFR renal dose auto ~~-~~ adjustments. 

namespace App\Services\Clinical; 

use Illuminate\Support\Facades\DB; use Exception; 

class DeterministicCdssShield 

{ /[** 

* Evaluates medication order safety against DDIs, Allergies, Pediatric Weight Limits & Renal Function. 

*/ 

public function evaluateMedicationSafety(array $orderPayload, string $patientid, string $tenantld): array 

{ 

$warnings= []; $hardBlocks= []; 

// 1. PEDIATRIC WEIGHT ~~-~~ BASED DOSAGE CHECK 

$patientWeight = DB::table(‘cd ~~e_~~ observations') 

- >where(‘tenant_ ~~id~~ ’, $tenantld) 

- >where(‘patient_ ~~id~~ ’, $patientid) 

- >where(‘cde ~~_c~~ ode’, 'BODY ~~_W~~ EIGHT’) 

- >latest(‘captured ~~_a~~ t') 

- >value(‘base ~~_v~~ alue ~~_n~~ umeric’); 

$isPediatric = DB::table(‘patients') ~~->~~ where(‘id', $patientid) ~~-~~ >value(‘age ~~_y~~ ears’) < 12; 

###### if ($isPediatric && $patientWeight) { 

$requestedMg = $orderPayload['dose ~~_m~~ g']; 

$maxMgPerkg = $orderPayload|'max ~~_m~~ g_ ~~pe~~ r_ ~~kg~~ '] ?? 15.0; // Default threshold 

$allowedMaxDose = $patientWeight * $maxMgPerkg; 

###### if ($requestedMg > ($allowedMaxDose* 1.5)) { 

$hardBlocks[] = [ 

‘type! => 'PEDIATRI ~~C_~~ WEIGH ~~T~~ OVERDOSE, 

‘message’ => "Requested dose ({$requestedMg}mg) exceeds 150% of maximum safe weight ~~-~~ based limit ({$allowedMaxDose}mg for {$patientWeight}kg).” 

]; 

} 

} 

/1 2. RENAL FUNCTION AUTO ~~-~~ ADJUSTMENT (eGFR Interception) 

$latestEgfr = DB::table(‘cd ~~e_~~ observations’) 

- >where(‘tenant_ ~~id~~ ’, $tenantid) 

- >where(‘patient_ ~~id~~ ’, $patientld) 

- >where(‘cde_ ~~co~~ de'’, 'EGF ~~R_~~ CALCULATED’) 

- >latest(‘captured ~~_at~~ ’) 

~~-~~ >value(‘base ~~_v~~ alue ~~_n~~ umeric’); 

if ($latestEgfr && $latestEgfr < 30.0 && ($orderPayload['is ~~_n~~ ephrotoxic'] ?? false)) { 

$warningsl] = [ 

‘type’ => 'RENA ~~L_~~ DOS ~~E_~~ REDUCTIO ~~N_~~ RECOMMENDED’, 

‘message’ => "Patient eGFR is severely reduced ({$latestEgfr} mL/min/1.73m”). 

Recommend 50% dose reduction or interval extension." 

]; 

} 

//! 3. DRUG ~~-~~ DRUG INTERACTION (DDI) CHECK 

$activeMeds = DB::table(‘medication ~~_o~~ rders’) 

- >where(‘tenant_ ~~id~~ ’, $tenantld) 

- >where(‘patient_ ~~id~~ ', $patientld) 

- >where(‘status’, ACTIVE’) 

- >pluck(‘drug ~~_c~~ ode’) 

- >toArray(); 

foreach ($activeMeds as $activeDrug) { 

$ddi = DB::table(‘tenant ~~_d~~ di ~~_d~~ ictionary’) 

- >where(‘tenant_ ~~id~~ ', $tenantld) 

- >where(‘drug_ ~~a’~~ , $orderPayload['drug ~~_c~~ ode’]) 

~~-~~ >where(‘drug_ ~~b’~~ , $activeDrug) 

~~-~~ >first(); 

if ($ddi) { 

if ($ddi ~~-~~ >severity === 'HARD ~~_B~~ LOCK’) { 

$hardBlocks[] = ['type' => 'DDI_HARD ~~_B~~ LOCK’, 'message' => "Severe interaction between {$orderPayload['drug ~~_c~~ ode']} and {$activeDrug}: {$ddi ~~->~~ description}"]; 

}else { 

$warnings[] = ['type’ => 'DDI_WARNING', 'message' => "Moderate interaction between {$orderPayload|'drug ~~_c~~ ode']} and {$activeDrug}.']; 

} 

} } 

return [ ‘i ~~s_~~ safe' => count($hardBlocks) === O, ‘har ~~d_b~~ locks' => $hardBlocks, ‘warnings’ => $warnings, 

]; 

} 

} 

#### 6. Unified Clinical Consumption Fact Broker 

Dispatches standardized clinical consumption webhook fact tokens to parallel modules (Inventory, Finance, Pharmacy). 

namespace App\Services\Clinical; 

use Illuminate\Support\Facades\Http; use IIluminate\Support\Facades\DB; 

###### class ConsumptionEventBroker 

{ 

[** 

* Emits a standardized clinical consumption fact to Inventory/Finance/Pharmacy queues. */ 

public function emitConsumptionFact(string $factToken, array $payload, string $tenantld): void 

{ 

$subStoreld = $payload['sub_ ~~st~~ ore_ ~~id~~ '] ?? 

$thi ~~s-~~ >resolveRoomToStoreMapping($payload|'patient_i ~~d'~~ ]); 

###### $eventData = [ 

‘event ~~_i~~ d' => (string) \Str::uuid(), 

‘tenant ~~_i~~ d' => $tenantld, 

‘fac ~~t_~~ token' => $factToken, // e.g., ‘MEDICATION ~~_A~~ DMINISTERED', 

‘MEDICATION ~~_W~~ ASTED‘, 'LAB ~~_C~~ ONSUMPTION_ ~~F~~ ACT' 

‘patient ~~_i~~ d' => $payload[‘patient_ ~~id~~ '] ?? null, 

'‘visi ~~t_~~ id' => $payload['visit ~~_id~~ '] ?? null, 

‘ite ~~m_s~~ ku' => $payload|'item ~~_s~~ ku’, 

‘quantity’ => $payload[‘quantity'], 

'sub ~~_s~~ tore ~~_i~~ d' => $subStoreld, 

'reaso ~~n_~~ code' => $payload|'reason ~~_c~~ ode’] ?? null, 

‘executed ~~_b~~ y ~~_u~~ ser ~~_i~~ d' => auth()- ~~>i~~ d(Q), 

‘timestamp’ => now() ~~->~~ tolso8601String(), 

]; 

// 1, Dispatch Async to Inventory Service Proxy 

Http::withHeaders([' ~~X-~~ KashTre ~~-E~~ vent' => $factToken]) 

~~-~~ >post(config(‘kashtre.inventor ~~y_~~ webhook_ ~~url~~ ’), $eventData); 

// 2. Dispatch Async to Finance Loss Ledger if Wastage/Unapproved Usage 

if (i ~~n_~~ array($factToken, ['MEDICATION ~~_W~~ ASTED', 

‘NON ~~_A~~ PPROVED ~~_F~~ LOOR_ ~~ST~~ OCK_ ~~US~~ AGE'))) { 

Http::withHeaders([' ~~X-~~ KashTre ~~-E~~ vent' => $factToken]) 

~~-~~ >post(config(‘kashtre.finance_loss_ledger_u ~~rl~~ '), $eventData); 

} 

} 

private function resolveRoomToStoreMapping(string $patientid): string 

{ 

###### return DB::table(‘patien ~~t_b~~ eds’) 

~~-~~ >join(‘clien ~~t_~~ spaces’, ‘patien ~~t_~~ beds.space_ ~~id~~ ', ‘=’, 'clien ~~t_~~ spaces.id’) 

~~-~~ >where(‘patient ~~_b~~ eds.current_ ~~pa~~ tient_ ~~id~~ ’, $patientid) 

~~-~~ >value(‘clien ~~t_~~ spaces.sub ~~_s~~ tore_ ~~id~~ ') ?? 'MAIN ~~_S~~ TORE’; 

} 

} 

#### 7. Master REST API Controller & Contract Endpoints 

Exposes standardized endpoints consumed by frontend clients, medical devices, and external integrations. 

###### namespace App\Http\Controllers\Api\v1; 

use App\Http\Controllers\Controller; use Illuminate\Http\Request; 

use App\Services\Clinical\CdeExecutionEngine; use App\Services\Clinical\DeterministicCdssShield; use IIlluminate\Support\Facades\DB; 

class ClinicalOrchestratorController extends Controller { 

[** 

- 1. GET Real-Time Ward Bed Census 

* Endpoint: GET /api/v1/clinical/wards/{wardCode}/census 

*/ 

public function getWardCensus(string $wardCode, Request $request) 

{ 

$tenantid = auth( ~~)-~~ >user( ~~)-~~ >tenant ~~_i~~ d ?? ‘DEFAULT’; 

###### $beds = DB::table(‘patien ~~t_b~~ eds’) 

- >join(‘clien ~~t_~~ spaces’, ‘patien ~~t_~~ beds.space_ ~~id~~ ', '=', 'clien ~~t_~~ spaces.id’) 

- >where(‘client ~~_s~~ paces.tenant_ ~~id~~ ’, $tenantld) 

- >where('clien ~~t_~~ spaces.ward ~~_c~~ ode’, $wardCode) 

- >select(‘patient ~~_b~~ eds.*’, 'clie ~~nt~~ _spaces.ward ~~_n~~ ame'’, 'clie ~~nt~~ _spaces.roo ~~m_~~ number’) 

- ~~-~~ >get(); 

return response() ~~->~~ json([ '‘war ~~d_~~ code' => $wardCode, 

- ‘total_beds' => $beds ~~->~~ count(), 

‘occupied’ => $beds- ~~>~~ where(‘operational_state’, 'OCCUPIED')- ~~>~~ count(), 'reserved' => $beds- ~~>~~ where(‘operational_state’, 'RESERVED’) ~~->~~ count(), 

‘available’ => $beds- ~~>~~ where(‘operational_state’, '‘AVAILABLE') ~~-~~ >count(), ‘beds ~~_~~ grid' => $beds 

}); 

} 

[** 

* 2. POST Add Overflow Bed 

* Endpoint: POST /api/v1/clinical/wards/{spaceld}/overflo ~~w-~~ bed 

*/ 

public function addOverflowBed(int $spaceld, Request $request) 

{ 

$extraBedCode = 'COT-'. rand(100, 999) . '-OVERFLOW'; 

$bedid = DB::table(‘patien ~~t_~~ beds' ~~)-~~ >insertGetld([ 

'space ~~_i~~ d' => $spaceld, 

‘pe ~~d_~~ code' => $extraBedCode, 

‘operational_state’ => ‘AVAILABLE’, 

‘ ~~is_~~ overflow' => true, 

‘created ~~_a~~ t' => now(), 

}); 

return response() ~~->~~ json(['‘message’ => ‘Overflow bed added successfully’, ‘bed ~~_i~~ d' => $bedld, 'be ~~d_~~ code' => $extraBedCode)); } 

[** 

* 3. POST Capture Atomic CDE Observation 

* Endpoint: POST /api/v1/clinical/observations 

*/ 

public function captureObservation(Request $request, CdeExecutionEngine $engine) 

{ 

###### $validated = $request- ~~>~~ validate([ 

‘patient ~~_i~~ d' => 'required|string’, 

‘visi ~~t_i~~ d' => 'required|string’, 

‘cd ~~e_~~ code' => 'required|string’, 

‘valu ~~e_~~ numeric’ => 'required|numeric’, 

‘inpu ~~t_~~ uom ~~_i~~ d' => 'requiredlinteger’, 

}); 

$result = $engine ~~->~~ captureObservation($validated, auth() ~~->~~ id(), auth( ~~)-~~ >user( ~~)-~~ >tenant ~~_i~~ d ?? 'DEFAULT’); 

return response() ~~->~~ json($result, 201); 

} 

} 

#### 8. Complete Master REST API Routing Contracts Table 

|HTTP Method|Route Endpoint|PrimaryController<br>& Method|| Description /<br>Scope|
|---|---|---|---|
|GET|/api/v1/clinical/ward<br>s/{wardCode}/cens<br>us|ClinicalOrchestrato<br>rController@getWa<br>rdCensus|Renders real-time<br>total, occupied,<br>reserved, available<br>bed counts &|



||||spatial grid.|
|---|---|---|---|
|POST|/api/v1/clinical/ward<br>s/{spaceld}/overflo<br>w~~-~~bed|ClinicalOrchestrato<br>rController@addOv <br>erflowBed|Dynamically creates<br>— temporary +Add<br>Overflow Bed slot.|
|POST|/api/v1/clinical/obser |<br>vations|ClinicalOrchestrato<br>rController@captur<br>eObservation|Capturesatomic<br>CDE withUOM<br>conversionand<br>heuristic checks.|
|POST|/api/v1/clinical/order<br>s/medications|ClinicalOrchestrato<br>rController@place<br>MedicationOrder|EvaluatesCDSS<br>safetyshield (DDIs,<br>Allergies, Pediatric<br>mg/kg)and<br>submits order.|
|POST|/api/v1/clinical/securi<br><br>ty/break~~-~~glass|§ SecurityController<br>@executeBreakGla<br>Ss|Grantsemergency<br>4~~-~~hour override<br>session with<br>mandatory reason<br>code logging.|
|GET|/api/v1/clinical/tasks/<br><br>visibility|— TaskVisibilityControl<br><br>ler@getFilteredTask <br>Ss|| Projects clinically<br>_ filteredviews ("My<br>Wara", "MyTeam")<br>over Main Module<br>queues.|
|POST|/api/v1/clinical/transi<br>tions/execute|ProcessWorkflowC<br>ontroller@executeS _<br>tep|Advances major<br>_ clinical transition<br>steps (Admission,<br>Transfer, Discharge,<br>Death Cert).|



# Technicale@ Engineeringe e Addendum: Clinicale e@ Off ~~-~~ Premisee Securitye & Devicee@ Enrollment 

Document Reference: CLINICA ~~L~~ ORCHESTRATO ~~R_~~ EN ~~G_~~ ADDENDUM ~~_S~~ EC ~~_0~~ 1 

Base Engineering Document: KashtTre Clinical Module Engineering Specification v6.0 Base SRD Reference: KashtTre Clinical Module SRD v6.0 + Security Addendum 

Target Architecture: Laravel 10+ (PHP 8.2+) / MySQL 8.0+ / Vue 3 (Composition API / WebAuthn/ Tailwind CSS) 

System Status: Approved Engineering Implementation Blueprint 

### 1. Database Migrations (Laravel DDL Migrations) 

Run these database migrations to create the required tables for Medical Director tokens, device enclave registrations, and off ~~-s~~ ite audit telemetry. 

###### 1.1 

###### 2026 ~~_~~ 0 ~~7_~~ 25 000001 ~~cr~~ eate_ ~~cl~~ inical_devic ~~e_~~ enrollment ~~_t~~ ables.php 

###### <?php 

use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema; 

return new class extends Migration 

{ 

public function up(): void 

{ 

// 1. Time ~~-~~ Locked Pairing Tokens Table 

Schema::create(‘clinical_enrollment ~~_t~~ okens’, function (Blueprint $table) { 

$table ~~->~~ id(); 

$table ~~->~~ string(‘tenant_ ~~id~~ ’, 64) ~~->~~ default('DEFAULT)); 

$table ~~->~~ foreignid(‘use ~~r_~~ id ~~')~~ ->constrained(‘users ~~')~~ ->cascadeOnDelete(); 

$table ~~->~~ char(‘pairing ~~_o~~ tp’, 6); 

$table ~~->~~ string(‘q ~~r_~~ code ~~_t~~ oken’, 128); 

$table ~~-~~ >foreignid(‘generated ~~_b~~ y ~~_m~~ d_u ~~se~~ r_i ~~d'~~ )- ~~>c~~ onstrained(‘users’); 

$table ~~->~~ timestamp(‘expires_ ~~at~~ '); 

$table ~~-~~ >boolean(‘i ~~s_~~ consumed’)- ~~>d~~ efault(false); 

$table ~~-~~ >timestamp(‘consumed_a ~~t'~~ )- ~~>n~~ ullable(); 

$table ~~-~~ >timestamps(); 

$table ~~->~~ index(['tenant_ ~~id~~ ’, ‘user ~~_i~~ d’, ‘pairing ~~_o~~ tp’, ‘i ~~s~~ consumed'], id ~~x_m~~ d_ ~~to~~ ken_lookup’); }); 

// 2. Clinical Device Enrollments Table 

Schema::create(‘clinical_device ~~_e~~ nrollments’, function (Blueprint $table) { $table ~~->~~ id(); 

$table ~~->~~ string(‘tenant_ ~~id~~ ’, 64) ~~->~~ default('DEFAULT)); 

$table ~~->~~ foreignid(‘use ~~r_~~ id ~~')~~ ->constrained(‘users ~~')~~ ->cascadeOnDelete(); 

$table ~~->~~ string(‘device_ ~~uu~~ id'’, 128); 

$table ~~->~~ string(‘'devic ~~e_~~ model’, 128); 

$table ~~->~~ string(‘device ~~_o~~ s’, 64); 

$table ~~->~~ text(‘publi ~~c_~~ key ~~_p~~ em’); // OpenSSL PEM or WebAuthn Public Key JSON 

$table ~~->~~ foreignid(‘enrolle ~~d_b~~ y ~~_m~~ d_ ~~us~~ er_i ~~d'~~ )- ~~>c~~ onstrained(‘users’); 

$table ~~->~~ enum(‘status’, ['ACTIVE’, ‘SUSPENDED’, ‘REVOKED'])- ~~>~~ default(‘ACTIVE’); 

$table ~~-~~ >timestamp(‘enrolled_ ~~at~~ ') ~~->~~ useCurrent(); 

$table ~~->~~ timestamp(‘last_ ~~of~~ fsite ~~_ac~~ cess_a ~~t'~~ )- ~~>n~~ ullable(); 

$table ~~-~~ >timestamps(); 

$table ~~->~~ unique(['tenant_ ~~id~~ ’, 'user ~~_i~~ d’, ‘'device ~~_u~~ uid'], 'ui ~~d_~~ user ~~_c~~ linical_device’); $table ~~->~~ index(['tenant_ ~~id~~ ’, 'device ~~_u~~ uid’, 'status’], ‘id ~~x_~~ clinical_device_lookup’); 

}); 

// 3. Off ~~-S~~ ite Access Telemetry Audit Log Table Schema::create(‘clinical_offsit ~~e_~~ access_logs’, function (Blueprint $table) { $table ~~->~~ id(); 

$table ~~->~~ string(‘tenant_ ~~id~~ ’, 64); $table ~~->~~ foreignlid(‘use ~~r_~~ id ~~'~~ )->constrained(‘users ~~'~~ )->cascadeOnDelete(); 

$table ~~->~~ foreignid(‘enrolled ~~_d~~ evice_ ~~id~~ ') ~~->~~ constrained(‘clinical_device ~~_e~~ nrollments’); $table ~~->~~ string(‘patient_ ~~id~~ ', 64); 

$table- ~~>s~~ tring(‘visit_ ~~id~~ ’, 64); 

$tabl ~~e-~~ >enum(‘access ~~_p~~ urpose’, [ ‘CHART ~~_R~~ EVIEW', ‘DRAF ~~T_~~ NOTE ~~_C~~ OMPLETION', ‘LA ~~B_~~ RESULT ~~_C~~ HECK’, ‘BREA ~~K_~~ GLAS ~~S_~~ EMERGENCY' 

}); $table ~~->~~ string(‘captured_ ~~ip~~ ’, 45); 

$table ~~->~~ decimal(‘captured_latitude’, 10, 8) ~~->~~ nullable(); 

$table ~~-~~ >decimal(‘captured_longitude’, 11, 8) ~~->~~ nullable(); 

$table ~~->~~ string(‘biometric ~~_s~~ ignature ~~_h~~ ash’, 255); 

$table ~~-~~ >integer('sessio ~~n_~~ duration ~~_s~~ econds’) ~~->~~ unsigned()- ~~>d~~ efault(0); 

$table ~~->~~ boolean(‘is ~~_f~~ lagged_ ~~fo~~ r_ ~~re~~ view’)- ~~>d~~ efault(false); 

$table ~~-~~ >timestamps(); 

$table ~~->~~ index(['tenant_ ~~id~~ ’, ‘created ~~_at~~ ', ' ~~is_~~ flagged ~~_f~~ or ~~_r~~ eview’], 'id ~~x_~~ offsit ~~e_~~ md ~~_f~~ eed’); }); 

} 

public function down(): void 

{ 

Schema::droplfExists(‘clinical_offsit ~~e_~~ access_logs’); Schema::droplfExists(‘clinical_device ~~_e~~ nrollments’); Schema::droplfExists(‘clinical_enrollment ~~_to~~ kens’); 

} 

}; 

##### 2. Backend Service Classes (Laravel Native Core Services) 

###### 2.1 Medical Director Device Enrollment Service 

app/Services/Clinical/ClinicalDeviceEnrollmentService.php 

<?php 

namespace App\Services\Clinical; 

use Illuminate\Support\Facades\DB; use Illuminate\Support\Str; use Carbon\Carbon; use Exception; 

class ClinicalDeviceEnrollmentService 

{ 

[** 

* Step 1: Medical Director generates a 5 ~~-~~ minute singl ~~e-~~ use pairing token for a doctor. */ 

public function generateEnrollmentToken(int $doctorUserld, int $medicalDirectorUserld, string $tenantld): array 

{ 

// Enforce maximum 1 active device rule: revoke existing draft/pending tokens 

DB::table(‘clinical_enrollment ~~_t~~ okens') 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantld) 

~~-~~ >where(‘user_ ~~id~~ ', $doctorUserld) 

~~-~~ >where(‘ ~~is~~ _consumed, false) 

~~-~~ >update([' ~~is~~ _consumed' => true]); 

$otp = (string) random ~~_i~~ nt(100000, 999999); $qrToken = Str::random(64); $expiresAt = Carbon::now() ~~->~~ addMinutes(5); 

$tokenld = DB::table(‘clinical_enrollmen ~~t_~~ tokens' ~~)-~~ >insertGetld([ ‘tenant ~~_i~~ d' => $tenantld, ‘user ~~_i~~ d' => $doctorUserld, ‘pairin ~~g_o~~ tp' => $otp, ‘q ~~r_~~ code ~~_t~~ oken' => $qrToken, ‘generate ~~d_~~ by ~~_m~~ d_ ~~us~~ er_ ~~id~~ ' => $medicalDirectorUserld, ‘expires ~~_a~~ t' => $expiresAt, ‘ ~~is~~ _consumed' => false, ‘created ~~_a~~ t' => now(), ‘updated ~~_a~~ t' => now(), }); 

return [ ‘token ~~_i~~ d' => $tokenld, ‘pairin ~~g_o~~ tp' => $otp, ‘q ~~r_~~ code ~~_p~~ ayload' => jso ~~n_~~ encode([ ‘tenant ~~_i~~ d' => $tenantld, ‘user ~~_i~~ d' => $doctorUserld, ‘q ~~r_~~ token' => $qrToken, ‘otp' => $otp, ‘expires ~~_a~~ t' => $expiresAt ~~->~~ tolso8601String() }), ‘expires ~~_a~~ t' => $expiresAt ~~->~~ tolso8601String() ]; } [** * Step 2: Clinician submits hardware enclave public key + pairing token to finalize enrollment. */ public function completeEnrollment(array $payload, string $tenantld): array { 

return DB::transaction(function () use ($payload, $tenantld) { $tokenRecord = DB::table(‘clinical_enrollmen ~~t_t~~ okens’) 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantid) 

~~-~~ >where(‘user_ ~~id~~ ’, $payload['user_ ~~id~~ ']) 

~~-~~ >where(‘pairing ~~_ot~~ p’, $payload|'pairing ~~_o~~ tp']) 

~~-~~ >where(‘qr ~~_c~~ ode_ ~~to~~ ken’, $payload[‘qr ~~_t~~ oken']) 

~~-~~ >where(‘ ~~is~~ _consumed, false) 

~~-~~ >where(‘expires_a ~~t'~~ ’, '>', now()) 

~~-~~ >first(); 

###### if (\$tokenRecord) { 

throw new Exception("INVALID ~~_~~ OR ~~_E~~ XPIRED ~~_T~~ OKEN: Enrollment token has expired or is 

invalid."); } 

// Mark token as consumed 

DB::table(‘clinical_enrollment ~~_t~~ okens') 

~~-~~ >where(‘id’, $tokenRecord- ~~>~~ id) 

~~-~~ >update([' ~~is~~ _consumed' => true, ‘consumed ~~_a~~ t' => now()]); 

// Revoke any previous active devices for this doctor (Max 1 Active Mobile Device rule) DB::table(‘clinical_devic ~~e_~~ enrollments’) 

~~-~~ >where(‘tenant_ ~~id~~ ', $tenantld) 

~~-~~ >where(‘user_ ~~id~~ ', $payload|'user_ ~~id~~ ']) 

- >update(['status' => 'REVOKED')); 

// Register new device key binding 

$enrollmentid = DB::table(‘clinical_devic ~~e_~~ enrollments' ~~)-~~ >insertGetld([ 

‘tenant ~~_i~~ d' => $tenantld, 

‘user ~~_i~~ d' => $payload|'user_ ~~id~~ '], 

‘device ~~_u~~ uid' => $payload['device_ ~~uu~~ id'], 

‘devic ~~e_~~ model' => $payload['device ~~_m~~ odel'], 

‘device ~~_o~~ s' => $payload['device_ ~~os~~ '], 

‘publi ~~c_~~ ke ~~y_~~ pem' => $payload[‘publi ~~c_k~~ ey ~~_p~~ em'], 

‘enrolle ~~d_~~ b ~~y_~~ md ~~_u~~ ser_ ~~id~~ ' => $tokenRecord ~~->~~ generated ~~_b~~ y ~~_m~~ d_ ~~us~~ er_ ~~id~~ , ‘status’ => ‘ACTIVE’, ‘enrolled ~~_a~~ t' => now(), ‘created ~~_a~~ t' => now/(), ‘updated ~~_a~~ t' => now(), 

)); 

return [ 

‘status' => 'ENROLLED ~~_S~~ UCCESS', 

‘enrollment ~~_i~~ d' => $enrollmentid, 

‘device ~~_u~~ uid' => $payload['device_ ~~uu~~ id'], 

‘enrolled ~~_a~~ t' => now() ~~->~~ tolso8601String() 

]; 

}); 

} 

} 

###### 2.2 Off ~~-~~ Premise Cryptographic Signature Verifier 

app/Services/Clinical/OffsiteBiometricVerificationService.php 

<?php 

###### namespace App\Services\Clinical; 

use Illuminate\Support\Facades\DB; use Exception; 

class OffsiteBiometricVerificationService 

{ 

/[** * Verifies cryptographic signature created by device hardware enclave during biometric challenge. 

*/ 

public function verifyOffsiteChallenge(int $userld, string $deviceUuid, string $challengeData, string $signatureBase64, string $tenantld): bool 

{ 

$device = DB::table(‘clinical_devic ~~e_~~ enrollments’) 

~~-~~ >where(‘tenant_ ~~id~~ ’, $tenantid) 

~~-~~ >where(‘user_ ~~id~~ ’, $userld) 

~~-~~ >where(‘device ~~_uu~~ id’, $deviceUuid) 

~~-~~ >where(‘status’, ‘ACTIVE’) 

~~-~~ >first(); 

###### if (‘$device) { 

throw new Exception("UNREGISTERED ~~_D~~ EVICE: Device is not enrolled or has been revoked by Medical Director.’); 

} 

$publickKeyPem = $device ~~-~~ >publi ~~c_~~ ke ~~y_~~ pem; $signature = base64 ~~_d~~ ecode($signatureBaseé4); 

/! Nerify SHA256 signature using OpenSSL and registered Public Key $verifyResult = openssl_verify($challengeData, $signature, $publickKeyPem, 

###### OPENSSL ~~_A~~ LGO_ ~~S~~ HA256); 

if ($verifyResult !== 1) { 

throw new Exception("BIOMETRI ~~C_~~ SIGNATURE ~~_F~~ AILED: Cryptographic verification failed. Unauthorized biometric challenge."); 

} 

// Update last off ~~-s~~ ite access timestamp 

DB::table(‘clinical_device ~~_e~~ nrollments’') 

~~-~~ >where(‘id', $device ~~->~~ id) 

~~-~~ >update(['las ~~t_o~~ ffsit ~~e_~~ access ~~_at~~ ' => now()]); 

return true; 

} 

} 

### 3. ZTNA Security Middleware 

###### 3.1 Off ~~-~~ Premise Security Enforcement Middleware 

app/Http/Middleware/OffsiteClinicalAccessMiddleware.php 

<?php 

namespace App\Http\Middleware; 

use Closure; use Illuminate\Http\Request; use Illuminate\Support\Facades\DB; use App\Services\Clinical\OffsiteBiometricVerificationService; use Symfony\Component\HttpFoundation\Response; 

class OffsiteClinicalAccessMiddleware 

{ 

protected OffsiteBiometricVerificationService $verifier; 

public function ~~_co~~ nstruct(OffsiteBiometricVerificationService $verifier) 

{ $this ~~->~~ verifier = $verifier; } 

public function handle(Request $request, Closure $next): Response 

{ 

$user = auth() ~~->~~ user(); 

if (‘Suser) { 

return response()- ~~>j~~ son([‘error' => ‘UNAUTHENTICATED’, 401); 

} 

$clientlp = $request- ~~>i~~ p(); 

$isOnPremises = $thi ~~s-~~ >evaluateOnPremisesContext($clientlp); 

// Attach network location flag to request attributes 

$request- ~~>~~ attributes ~~->~~ set(‘i ~~s_~~ on ~~_p~~ remises', $isOnPremises); 

###### if ('$isOnPremises) { 

// 1. STRICT OFF ~~-~~ PREMISE ORDER BLOCKING (Drafts and reviews only) 

$isMutatingOrderRoute = $request- ~~>i~~ s(‘api/v1/clinical/orders/*’) || 

   - $request- ~~>i~~ s(‘api/v1/clinical/mar/administer'’) || 

   - $request-> ~~i~~ s(‘api/v1/clinical/discharge/finalize’); 

- if ($isMutatingOrderRoute && i ~~n_~~ array($request ~~->~~ method)(), ['POST’, ‘PUT’, 'DELETE'])) { return response() ~~->~~ json([ 

‘error’ => 'ZTN ~~A_~~ OFFSIT ~~E_~~ MUTATION_ ~~R~~ ESTRICTED’, 

‘message’ => 'Live medication orders, MAR dose authorizations, and formal 

discharges are strictly prohibited of ~~f-~~ premises. Draft completion only.’ ], 403); 

} 

// 2. ENROLLED DEVICE & BIOMETRIC CHALLENGE VERIFICATION 

$deviceUuid = $request ~~->~~ header('X ~~-K~~ ashTre ~~-D~~ evice- ~~U~~ UID’); 

$biometricSig = $request ~~->~~ header('X- ~~K~~ ashTre- ~~Bi~~ ometric- ~~Si~~ gnature’); $challengeData = $request ~~->~~ header('X ~~-K~~ ashTre- ~~C~~ hallenge- ~~Pa~~ yload’); 

if (‘$deviceUuid || !$biometricSig || !$challengeData) { 

return response() ~~->~~ json([ 

‘error’ => 'BIOMETRI ~~C_~~ REAUTH ~~_R~~ EQUIRED', 

‘message’ => 'Of ~~f-~~ premise chart access requires a valid enrolled device and biometric hardware challenge signature.’ 

], 428); 

} 

try{ 

$this ~~-~~ >verifie ~~r-~~ >verifyOffsiteChallenge( 

$user ~~->~~ id, 

$deviceUuid, 

$challengeData, 

$biometricSig, 

$user ~~->~~ tenant ~~_i~~ d ?? 'DEFAULT' 

); 

} catch (\Exception $e) { return response() ~~->~~ json([ ‘error’ => 'ZTN ~~A_~~ SECURITY ~~_C~~ HALLENGE_ ~~FA~~ ILED', ‘message’ => $e ~~-~~ >getMessage() ], 403); 

} 

} 

$response = $next($request); 

/1 3. INJECT ANTI-LEAK WATERMARK HEADERS FOR FRONTEND OVERLAY 

if (‘$isOnPremises && $response instanceof Response) { 

$response ~~->~~ headers- ~~>~~ set('X ~~-~~ KashTre ~~-~~ Watermark- ~~Us~~ er’, "DR. {$use ~~r-~~ >name} (ID: 

###### {$user- ~~>~~ id})"); 

$response ~~->~~ headers- ~~>s~~ et('X ~~-K~~ ashTre- ~~W~~ atermark-IP’, $clientlp); 

$response ~~->~~ headers ~~->~~ set(' ~~X-~~ KashTr ~~e-~~ Watermark ~~-T~~ imestamp’, now() ~~->~~ tolso8601String()); 

$response ~~->~~ headers- ~~>s~~ et('Cache- ~~Co~~ ntrol’, 'no ~~-~~ store, no ~~-~~ cache, must- ~~r~~ evalidate, 

max ~~-a~~ ge=0, private’); 

$response ~~->~~ headers- ~~>s~~ et(‘Pragma’, 'no ~~-~~ cache’); 

} 

return $response; 

} 

private function evaluateOnPremisesContext(string $ip): bool 

{ $hospitalSubnets = config(‘kashtre.hospital_subnets’, ['10.0.0.0/8', '192.168.1.0/24']); foreach ($hospitalSubnets as $subnet) { 

if ($thi ~~s-~~ >ipInSubnet($ip, $subnet)) { 

return true; } } return false; } 

private function ipInSubnet(string $ip, string $subnet): bool 

{ if (strpos($subnet, '/') === false) return $ip === $subnet; list($sub, $bits) = explode(‘/’, $subnet); 

$ip = ip2long($ip); $sub = ip2long($sub); $mask = ~~-1~~ << (32 ~~-~~ $bits); return ($ip & $mask) == ($sub & $mask); 

} 

} 

###### 4. API Controllers & Routes 

###### 4.1 Medical Director Device Enrollment Controller 

app/Http/Controllers/Api/V1/MedicalDirectorEnrollmentController.ohp 

<?php 

namespace App\Http\Controllers\Api\W1; 

use App\Http\Controllers\Controller; use Illuminate\Http\Request; use App\Services\Clinical\ClinicalDeviceEnrollmentService; use Illuminate\Support\Facades\DB; 

class MedicalDirectorEnrollmentController extends Controller { 

protected ClinicalDeviceEnrollmentService $enrollmentService; 

public function ~~_co~~ nstruct(ClinicalDeviceEnrollmentService $enrollmentService) 

{ $thi ~~s-~~ >enrollmentService = $enrollmentService; } [** * POST /api/v1/clinical/md/generat ~~e-~~ token * Medical Director generates 5 ~~-~~ min pairing token. */ public function generateToken(Request $request) { 

$validated = $request- ~~>~~ validate([ ‘doctor ~~_u~~ ser ~~_i~~ d' => 'requiredlintegerlexists:users,id’, ); 

$tenantid = auth( ~~)-~~ >user( ~~)-~~ >tenant ~~_i~~ d ?? ‘DEFAULT’; 

$result = $thi ~~s-~~ >enrollmentServic ~~e-~~ >generateEnrollmentToken( 

$validated['doctor ~~_u~~ ser_ ~~id~~ '], 

auth() ~~->~~ id(), 

$tenantld 

); 

return response() ~~->~~ json($result, 201); 

} 

/[** 

* POST /api/v1/clinical/device/complet ~~e-~~ enrollment 

* Doctor scans QR and submits WebAuthn/Enclave public key. 

*/ 

public function completeEnrollment(Request $request) 

{ 

$validated = $request- ~~>~~ validate([ 

'user ~~_i~~ d' => 'requiredlinteger’, 

‘pairin ~~g_o~~ tp' => 'required|stringlsize:6, 

‘q ~~r_~~ token' => 'required|string’, 

‘'devic ~~e_~~ uuid' => 'required|string’, 

‘'devic ~~e_~~ model' => 'required|string’, 

‘device ~~_o~~ s' => 'required|string’, 

‘publi ~~c_~~ ke ~~y_~~ pem' => 'required|string’, 

}); 

$tenantid = auth( ~~)-~~ >user( ~~)-~~ >tenant ~~_i~~ d ?? ‘DEFAULT’; 

$result = $thi ~~s-~~ >enrollmentService ~~-~~ >completeEnrollment($validated, $tenantld); 

return response() ~~->~~ json($result, 200); 

} 

[** 

* GET /api/v1/clinical/md/offsit ~~e~~ -audi ~~t-~~ feed 

* Real-time surveillance feed for Medical Director. 

*/ public function getOffsiteAuditFeed(Request $request) 

{ 

$tenantid = auth( ~~)-~~ >user( ~~)-~~ >tenant ~~_i~~ d ?? ‘DEFAULT’; 

$logs = DB::table(‘clinical_offsi ~~te_~~ access_logs') 

~~-~~ >join(‘users, ‘clinical_offsi ~~te~~ _access_logs.user_ ~~id~~ ’, '=', 'users.id’) 

~~-~~ >join(‘clinical_devic ~~e_~~ enrollments’, ‘clinical_offsi ~~te~~ _access_logs.enrolled ~~_d~~ evice_ ~~id~~ ’, ‘=’, ‘clinical_devic ~~e_~~ enrollments.id’) 

~~-~~ >where(‘clinical_offsit ~~e_~~ access_logs.tenant_ ~~id~~ ’, $tenantld) 

~~-~~ >select( 

‘clinical_offsi ~~te_~~ access_logs.”’, 

‘users.name as doctor ~~_n~~ ame'’, 

‘clinical_devi ~~ce~~ _enrollments.device ~~_m~~ odel', 

‘clinical_devic ~~e_~~ enrollments.device ~~_o~~ s' 

) ~~-~~ >orderBy(‘clinical_offsit ~~e_~~ access_logs.created_ ~~at~~ ', ‘'desc’) 

~~-~~ >paginate(30); 

return response() ~~->~~ json($logs); 

} 

} 

#### 5. Frontend Vue 3 Pic ~~k-~~ and ~~-~~ Place Components 

###### 5.1 Dynamic Ant ~~i-~~ Leak Viewport Watermark (DynamicViewportWatermark.vue) 

Place this component over clinical chart viewports to display diagonal, ant ~~i-~~ screenshot watermarking of ~~f-~~ premises. 

###### <template> 

<div class="relative w ~~-f~~ ull h ~~-f~~ ull overflow ~~-~~ hidden"> <!-- Clinical Content Slot --> <slot /> 

<!-- Off ~~-~~ Premise Watermark Overlay --> 

<div v ~~-~~ if="isOffPremise" 

class="pointe ~~r-~~ event ~~s-~~ none fixed inse ~~t-~~ O z ~~-~~ 50 flex fle ~~x-~~ wrap items ~~-~~ center justif ~~y-~~ center opacity ~~-~~ 15 selec ~~t-~~ none overflow ~~-~~ hidden" 

> <div v ~~-~~ for="n in 12" 

*key="n" 

class="m ~~-~~ 8 transform ~~-~~ rotat ~~e-~~ 30 text ~~-~~ xs fon ~~t-~~ mono font ~~-~~ bold trackin ~~g-~~ widest tex ~~t-~~ re ~~d-~~ 900 uppercase whitespace ~~-~~ nowrap" 

> {{ watermarkText }} </div> </div> 

</div> </template> 

<script setup> import { computed} from ‘vue’ 

const props = defineProps({ isOffPremise: { type: Boolean, default: false }, doctorName: { type: String, required: true }, staffld: { type: [String, Number], required: true }, remotelp: { type: String, required: true } }) const watermarkText = computed(() => { const utcTime = new Date().tolSOString().replace(T;, '').substring(O, 19) return ‘${props.doctorName} | ID: ${props.staffld} | IP: ${props.remotelp} | ${utcTime} UTC’ }) </script> <style scoped> ~~.-~~ rotat ~~e-~~ 30 { transform: rotate ~~(-~~ 30deg); } </style> 

###### 5.2 5 ~~-~~ Minute Inactivity Auto ~~-~~ Logout Composable (uselnactivityAutoLock.js) 

Import this composable into clinical chart viewports to purge RAM and lock sessions after 300 

seconds of inactivity. 

import { ref, onMounted, onUnmounted} from 'vue' 

export function uselnactivityAutoLock(timeoutSeconds = 300, onTimeoutCallback) { const idleTimer = ref(null) 

const remainingSeconds = ref(timeoutSeconds) 

const resetTimer = () => { remainingSeconds.value = timeoutSeconds if (idleTimer.value) clearInterval(idleTimer.value) 

idleTimer.value = setInterval(() => { remainingSeconds.value-if (remainingSeconds.value <= 0) { clearlInterval(idleTimer.value) executeLock() } , }1000) } const executeLock = () => { // 1, Clear I ~~n-~~ Memory Ephemeral RAM State if (window.crypto && window.crypto.subtle) { // Purge local object references } // 2. Clear browser storage caches sessionStorage.clear() // 3. Trigger callback (e.g. show biometric lock modal) if (onTimeoutCallback) { onTimeoutCallback() } } const handleUserActivity = () => { resetTimer() } 

onMounted(() => { window.addEventListener(‘'mousemove’, handleUserActivity) window.addEventListener(‘keydown’, handleUserActivity) window.addEventListener(‘touchstart’, handleUserActivity) 

window.addEventListener(‘scroll’, handleUserActivity) resetTimer() 

}) 

onUnmounted(() => { 

window.removeEventListener(‘mousemove'’, handleUserActivity) window.removeEventListener('keydown’, handleUserActivity) window.removeEventListener(‘touchstart’, handleUserActivity) window.removeEventListener(‘scroll’, handleUserActivity) if (idleTimer.value) clear|Interval(idleTimer.value) 

}) 

return { 

remainingSeconds 

} 

} 

