KashTre Imaging Module (RIS) 

Engineering Addendum v2.6 

Workflow-Driven Execution Architecture 

Companion Engineering Specification for RIS Amendment Package v2.6 

### 1. Purpose 

This engineering addendum implements the RIS SRD Amendment Package v2.6. 

It introduces: 

1 Workflow Step Registry 2 3 Workflow Composition 4 5 Workflow Queues 6 7 Workflow Assignment 8 9 Workflow Completion Rules 10 11 Workflow ~~-~~ to ~~-~~ Status Mapping 12 13. Inventory Attribution 14 15 Consumption Exception Management 16 17. Workflow Versioning 18 <u>19 Study Ownership Controls</u> This document is intended for: 

1 Backend Developers 2 

> 3 System Architects 4 

5 Database Administrators 6 7 <u>API Engineers</u> 

### 2. Core Architectural Principle 

The workflow becomes the operational source of truth. 

1 Offer 2L 3 4Clinical Module 5J 6 7RIS Study 8L 9 10 Workflow 11 L 12 13. Workflow Step 14 L 15 16 Queue 17 L 18 19 Assigned User 20 4 21 22 Status Update 23 JL 24 25 <u>Inventory Attribution</u> 

3. Database Schema Additions 

3.1 Workflow Steps 

|2<br>3||id BIGINT UNSIGNED AUTO~~_I~~NCREMENT PRIMARY KEY,|
|---|---|---|
|4<br>5||tenant~~_i~~d VARCHAR(64)<br>NOT NULL,|
|6<br>7||ste~~p_c~~ode VARCHAR(64)<br>NOT NULL,|
|8<br>9||ste~~p_n~~ame VARCHAR(255)<br>NOT NULL,|
|10||description TEXT NULL,|
|11|||
|12||is~~a~~ctive BOOLEAN DEFAULT TRUE,|
|13|||
|14||created~~_a~~t TIMESTAMP DEFAULT CURRENT~~_T~~IMESTAMP,|
|15|||
|16||UNIQUE KEY ui~~d_~~workflow~~_s~~tep|
|17||(tenant~~_i~~d,<br>step~~_c~~ode)|
|18|)|;|



3.2 Workflow Step Users 1 CREATE TABLE workflow ~~_s~~ tep ~~_u~~ sers ( 2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 user ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 created ~~_a~~ t TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP, 9 10 FOREIGN KEY (workflow ~~_s~~ tep ~~_i~~ d) 11 REFERENCES tenant ~~_w~~ orkflow ~~_s~~ teps(id) <u>12)</u> Purpose: 

3.3 Protocol Workflow Versions 1 CREATE TABLE protocol ~~w~~ orkflows ( 3 ~~<u>se ewan wre ener omy Ke</u>~~ 

4 tenant ~~_i~~ d VARCHAR(64) NOT NULL, 5 6 protocol ~~c~~ ode VARCHAR(64) NOT NULL, 7 8 workflow ~~_n~~ ame VARCHAR(255) NOT NULL, 9 10 workflow ~~_v~~ ersion INT NOT NULL, 11 12 is ~~_~~ active BOOLEAN DEFAULT TRUE, 13 14 created ~~_a~~ t TIMESTAMP DEFAULT CURREN ~~T_~~ TIMESTAMP <u>15);</u> 

3.4 Protocol Workflow Steps 

1 CREATE TABLE protoco ~~l_~~ workflow ~~_s~~ teps ( 2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 protocol ~~_~~ workflow ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 sequence ~~_n~~ o INT NOT NULL, 9 10 ris ~~s~~ tatus VARCHAR(64) NOT NULL, 11 12 main ~~_s~~ tatus ENUM( 13 "PENDING', 14 "I ~~N_~~ PROGRESS', 15 "COMPLETED" 16 ) NOT NULL, 17 18 triggers ~~c~~ onsumption BOOLEAN DEFAULT FALSE, 19 20 created ~~_a~~ t TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP <u>21);</u> 

3.5 Study Workflow Execution 

2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 imaging study ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 workflow ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 current ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 9 10 status ENUM( 11 ‘ACTIVE’, 12 "COMPLETED', 13 "CANCELLED' 14 ) DEFAULT ‘ACTIVE’, 15 16 created ~~_a~~ t TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP <u>17);</u> 

###### 3.6 Workflow Step History 

1 CREATE TABLE workflow ~~_s~~ tep ~~_e~~ xecutions ( 2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 stud ~~y_~~ execution ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 executed ~~_b~~ y BIGINT UNSIGNED NOT NULL, 9 10 room ~~_i~~ d BIGINT UNSIGNED NULL, 11 12 started ~~_a~~ t TIMESTAMP NULL, 13 14 completed ~~_a~~ t TIMESTAMP NULL, 15 16 notes TEXT NULL, 17 18 created ~~_a~~ t TIMESTAMP DEFAULT CURREN ~~T_~~ TIMESTAMP <u>19);</u> 

###### 3.7 Study Ownership 

1 CREATE TABLE workflow ~~_c~~ laims ( 2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 stud ~~y_~~ execution ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 assigned ~~_u~~ ser ~~_i~~ d BIGINT UNSIGNED NOT NULL, 9 10 claimed ~~_a~~ t TIMESTAMP NOT NULL, 11 12 released ~~_a~~ t TIMESTAMP NULL <u>13)</u> 

###### 3.8 Completion Rule Definitions 

1 CREATE TABLE workflow ~~_s~~ tep ~~_c~~ ompletion ~~_r~~ ules ( 2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 5 6 rul ~~e_~~ type ENUM( 7 'FIELD', 8 "CHECKLIST', 9 "ATTACHMENT' , 10 "SIGNATURE' 11 ) NOT NULL, 12 13 rul ~~e_~~ key VARCHAR(255) NOT NULL, 14 15 is ~~r~~ equired BOOLEAN DEFAULT TRUE, 16 17 created ~~_a~~ t TIMESTAMP DEFAULT CURREN ~~T_~~ TIMESTAMP <u>18 )3</u> 

3.9 Consumption Exceptions 

2 id BIGINT UNSIGNED AUTO ~~_I~~ NCREMENT PRIMARY KEY, 3 4 imaging study ~~_~~ id BIGINT UNSIGNED NOT NULL, 5 6 workflow ~~_s~~ tep ~~_i~~ d BIGINT UNSIGNED NOT NULL, 7 8 exception ~~_r~~ eason VARCHAR(255) NOT NULL, 9 10 resolved BOOLEAN DEFAULT FALSE, 11 12 resolved ~~_b~~ y BIGINT UNSIGNED NULL, 13 14 resolved ~~_a~~ t TIMESTAMP NULL, 15 16 created ~~_a~~ t TIMESTAMP DEFAULT CURRENT ~~_T~~ IMESTAMP <u>17)</u> 

4. Laravel Services 

WorkflowEngineService 

Responsibilities: 

1 Load Workflow 2 3 Advance Workflow 4 5 Validate Progression 6 7 <u>Determine Next Step</u> 

Methods: 

1 startWorkflow() 2 3 completeStep() 4 5 getCurrentStep() 6 7 advanceToNextStep() 

###### WorkflowStatusMapperService 

Responsibilities: 

1 Map Workflow Step 2L 3RIS Status 4 5 Map Workflow Step 6 JL 7 Main Status Methods: 

1 updateRisStatus() 2 3 <u>updateMainStatus()</u> 

WorkflowOwnershipService 

Responsibilities: 

1 Claim Study 2 3 Release Study 4 5 <u>Transfer Study</u> Methods: 

1 claimStudy() 2 3 releaseStudy() 4 5 <u>transferStudy()</u> 

CompletionRuleService 

Responsibilities: 

1 Load Completion Rules 2 3 Validate Completion 4 5 Enforce Rules 

###### Methods: 

<u>1 validateStepCompletion()</u> 

Returns: 1 true 2 3 or 4 5 <u>ValidationErrors[]</u> 

###### ConsumptionAttributionService 

Responsibilities: 

1 Resolve Room 2 3 Resolve Store 4 5 Deduct Inventory 6 7 <u>Create Exception</u> Methods: 

1 triggerConsumption() 2 3 resolveStore() 4 5 <u>createException()</u> 

### 5. Workflow Execution Flow 

Start Study 

1 Order Created 24 3Workflow Loaded 4J <u>5Step 1 Queue</u> 

Claim Study 

1 User Opens Queue 2 JL 3 Claim Study 4 JL <u>5 Ownership Recorded</u> Complete Step 1 User Clicks Complete 2 JL 3 Completion Rules Checked 4 JL 5 Pass 6L 7Update Status 8L 9Trigger Consumption 10 Jl 11 Move To Next Queue 

6. Workflow Completion Rules 

Example 

Contrast Administration Required Rules: 

1 Contrast Agent 2 3 Volume 4 5 Time 6 7 <u>Operator</u> Configuration: 

~~1[~~ <u>2 {</u> 

3 "ruleType": "FIELD", 4 "ruleKey": "“contras ~~t_~~ agent" 5 hs 6{ 7 "ruleType": "FIELD", 8 "ruleKey": "contrast ~~_v~~ olume" 9 hs 10 { 11 "ruleType": "FIELD", 12 "ruleKey": "administration ~~_t~~ ime" 13 } 14. | 

## 7. RIS Status Mapping 

<u>|</u> <mark>|</mark> 

# 8. Main Module Status Mapping 



### 9. Workflow APIs 

Create Workflow Step 

<u>1 POST /api/v1/imaging/workflow</u> ~~<u>-</u>~~ <u>steps</u> Request: 

~~1{~~ 2 "stepCode": "SCAN EXECUTION", 3 "stepName": "Scan Execution" <u>4}</u> 

###### Assign Users To Step 

1 POST /api/v1/imaging/workflow ~~-~~ steps/{id}/users Request: 

~~1{~~ 2 "userIds": [10,11,12] <u>3}</u> 

Create Protocol Workflow 

1 <u>POST /api/v1/imaging/protocol</u> ~~<u>-</u>~~ <u>workflows</u> 

###### Claim Study 

1 <u>POST /api/v1/imaging/studies/{id}/claim</u> 

###### Complete Step 

1 <u>POST /api/v1/imaging/studies/{id}/complete</u> ~~<u>-s</u>~~ <u>tep</u> Request: 

~~1{~~ 2 "workflowStepId": 5, 3 "notes": "Completed successfully" <u>4}</u> 

### 10. Queue APIs 

#### My Queue 

<u>1 GET /api/v1/imaging/workflow</u> ~~<u>-</u>~~ <u>steps/{id}/queue</u> 

Response: 

~~1{~~ 2 "pendingStudies": 12, 3 "studies": [] <u>4}</u> 

## 11. Consumption Exception APIs 

###### View Exceptions 

1 GET <u>/api/v1/imaging/consumption</u> ~~<u>-</u>~~ <u>exceptions</u> 

###### Resolve Exception 

1 <u>POST /api/v1/imaging/consumption</u> ~~<u>-</u>~~ <u>exceptions/{id}/resolve</u> Request: 

~~1{~~ 2 "resolutionNotes": "Room mapping corrected" <u>3}</u> 

# 12. Room Resolution Logic 

RIS must never create its own room registry. 

Room shall always be obtained from: 

1 Current Authenticated Session 2L <u>3Selected</u> Room The selected room shall then be resolved to: 

1 Inventory Store 2 3 Workflow Audit Context 

## 13. Audit Requirements 

Every workflow action shall log: 

1 Study ID 2 3 Workflow Version 4 5 Workflow Step 6 7 User 8 9 Room 10 11 Device 12 13 IP Address 14 <u>15 Timestamp</u> Examples: 1 STE ~~P_~~ STARTED 2 3. STE ~~P_~~ COMPLETED 4 5 STUD ~~Y_~~ CLAIMED 6 7 STUD ~~Y_~~ RELEASED 8 9 CONSUMPTION ~~_T~~ RIGGERED 10 11 CONSUMPTION ~~_F~~ AILED 

##### 14. Version 1 Exclusions 

The following remain out of scope: 

2 

3 Peer Review Percentages 4 5 Blind Review Queues 6 7 Radiologist Scoring 8 9 <u>QA Analytics</u> 

To be implemented in a future: 

### Final Engineering Directive 

Implement RIS as a workflow-driven execution engine. Workflow steps are reusable system objects that act as queues, assignment points, status drivers, and inventory attribution triggers. Workflow completion is governed by configurable completion rules. All inventory attribution failures must be tracked through the Consumption Exception Framework. Workflow definitions shall be versioned and auditable. The RIS shall reuse the platform-wide room selection framework and shall not maintain a separate room registry. 

