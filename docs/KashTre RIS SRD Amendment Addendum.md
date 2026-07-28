







8 9 Cannula Placement 10 11 Contrast Administration 12 13. Scan Execution 14 15 Image QA 16 17 Reporting 18 19 Certification 20 <u>21 Recovery Clearance</u> Workflow steps shall not be hardcoded. 

Administrators shall be able to: 

1 Create 2 3 Edit 4 5 Disable 6 7 Rename 

workflow steps without source code changes. 

# Amendment 2: Workflow Step User Pools & Queues 

Every workflow step shall maintain an assigned user pool. 

Example: 

Assigned Users: 

1 Dr. A 2 3 Dr. B 4 

The workflow step automatically becomes a work queue. 

Users assigned to the step shall see: 

1 Patients Waiting 2 3 Studies Waiting 4 5 Active Work for that step. 

### Queue Principle 

Queues belong to workflow steps. 

Example: 

1 <u>Reporting Queue</u> may serve: 1 CT 2 3 MRI 4 5 X ~~-~~ Ray 6 7 Ultrasound 

without duplication. 

Amendment 3: Protocol Workflow Composition Framework 

Protocols shall be composed using reusable workflow steps. 

Example: 

CT Abdomen With Contrast 

1 Consent Verification 2 3 Creatinine Verification 

4 5 Cannula Placement 6 7 Contrast Administration 8 9 Scan Execution 10 11 Image QA 12 13 Reporting 14 15 Certification Administrators shall be able to: 

1 Add Steps 2 3 Remove Steps 4 5 <u>Reorder Steps</u> within protocol configuration. 

Amendment 4: Workflow-Driven Status Mapping Workflow becomes the operational source of truth. 

Each workflow step shall support mapping to: 

RIS Status Example: 

1 Consent Verification 24 3PREPARATIO ~~N_~~ REQUIRED 4 5 Scan Execution 64 <u>7I</u> ~~N_~~ PROGRESS 8 9 Image QA 10 J 

11 IMAG ~~E_~~ ACQUIRED 12 13 Reporting 14 L 15 REPOR ~~T_~~ PENDING 16 17. Certification 18 L 19 VERIFIED 

Main Module Status 

Example: 1 Consent Verification 24 3PENDING 4 5 Scan Execution 64 <u>7IN</u> ~~_~~ PROGRESS 8 9 Reporting 10 4 11 COMPLETED 

Workflow progression shall update RIS and Main Module statuses automatically. 

Amendment 5: Workflow-Based Inventory Attribution The existing inventory attribution framework remains but becomes workflow-driven. Each protocol shall define: 

<u>1 Consumption Attribution Step</u> Example: 

1 Contrast Administration When the workflow reaches that step: 

1 Logged ~~-~~ In User 2 4 





















These capabilities shall be implemented separately in a future: 

<u>1 Radiology Quality Assurance Framework</u> 

Amendment 11: Workflow Step Completion Rules Framework 

##### Objective 

Workflow steps shall not be completed merely because a user clicks a button. 

The system must know what constitutes successful completion. 

#### Workflow Step Configuration 

Each workflow step shall support: 

|RequirementType||Checklist, Field, Signature, Attachment|
|---|---|
|Validation Rule|Completion validation logic|
|Override Allowed||
|Override Role|Authorized role(s)|



Example: Contrast Administration 

Completion requirements: 

1 Contrast Agent Recorded 

2 3 Contrast Volume Recorded 4 

> 5 Administration Time Recorded 6 

> 7 Operator Recorded 

Only when all mandatory requirements have been met may the step be completed. 

#### Example: Consent Verification 

Completion requirements: 







#### Workflow Engine Behaviour 

When a user selects: 

<u>1 Complete Step</u> 

the system shall: 

###### Step 1 

Validate Completion Rules. 

#### Step2 

If rules fail: 

1 Block Completion 

and display outstanding requirements. 

Example: 

1 Cannot Complete Step 2 3 Missing: 4 5 Contrast Volume 6 7 Administration Time 

#### Step3 

If validation succeeds: 

1 Record Completion 2 3 Update Workflow Progress A 5 Update RIS Status 6 7 Update Main Module Status 8 9 Trigger Inventory Attribution 10 11 Advance To Next Queue 12 13 Generate Audit Record 

###### Override Process 

Certain workflow steps may allow authorized overrides. 

Example: 

<u>1 Creatinine Result Missing</u> 

but: 1 Radiologist Override Authorized 

Configuration: 

1 Allow Override = Yes 2 3 Authorized Override Role: 4 Radiologist <u>5 Imaging Manager</u> Overrides shall require: 

1 Reason 2 3 Timestamp 4 5 User and must be fully audited. 

## Final Architectural Principle 

The RIS shall operate as a workflow-driven imaging execution platform. Protocols shall be composed from reusable workflow steps. Workflow steps shall serve as queues, user assignment points, status mapping triggers, inventory attribution triggers, and audit checkpoints. Workflow progression shall drive RIS and Main Module status updates. Consumption attribution failures shall be tracked through a dedicated exception framework. Workflow completion shall be governed by configurable completion rules and auditable override mechanisms. Peer-review functionality is deferred from Version 1 to maintain implementation focus on core operational workflows. 

