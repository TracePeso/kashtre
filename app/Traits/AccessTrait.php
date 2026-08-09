<?php

namespace App\Traits;

trait AccessTrait
{
    public static $admin = [
        "Dashboard" => [
            "View Dashboard", "View Dashboard Cards", "View Dashboard Charts",
        ]
    ];

    public static $entities = [
        "Entities" => ['View Entities', 'Edit Entities', 'Add Entities'],
    ];

    public static $departments = [
        "Departments" => ['View Departments', 'Edit Departments', 'Add Departments'],
    ];

    public static $staff = [
        "Staff" => ['View Staff', 'Edit Staff', 'Add Staff', 'Assign Roles'],
    ];

    public static $reports = [
        "Reports" => ['View Report', 'Edit Report', 'Add Report'],
    ];

    public static $logs = [
        "Logs" => ['View Logs'],
    ];

    public static $contractor = [
        "Contractor" => ["View Contractor"],
    ];



    public static $sales = [
        "Sales" => ['View Sales', 'Edit Sales', 'Add Sales'],
    ];

    public static $cashier = [
        "Cashier" => ['Cashier'],
    ];

    public static $clients = [
        "Clients" => ['View Clients', 'Edit Clients', 'Add Clients'],
    ];

    public static $visits = [
        "Visits" => ['View Visits'],
    ];

    public static $queues = [
        "Customers" => ['View Queues'],
    ];

    public static $withdrawaal = [
        "Withdrawals" => ['View Withdrawals', 'Edit Withdrawals', 'Add Withdrawals'],
    ];


    public static $modules = [
        "Modules" => ['View Modules', 'Edit Modules', 'Add Modules'],
    ];

    public static $stock = [
        "Stock" => ['View Stock', 'Edit Stock', 'Add Stock'],
    ];

    public static $masters = [
        "Service Points" => ['View Service Points', 'Edit Service Points', 'Add Service Points', 'Bulky Update Service Points'],
        "Service Charges" => ['Manage Service Charges'],
        "Contractor Service Charges" => ['Manage Contractor Service Charges'],
        "Departments" => ['View Departments', 'Edit Departments', 'Add Departments', 'Bulky Update Departments'],
        "Qualifications" => ['View Qualifications', 'Edit Qualifications', 'Add Qualifications', 'Bulky Update Qualifications'],
        "Titles" => ['View Titles', 'Edit Titles', 'Add Titles', 'Bulky Update Titles'],
        "Staff Categories" => ['View Staff Categories', 'Edit Staff Categories', 'Add Staff Categories', 'Bulky Update Staff Categories'],
        "Supplier Industries" => ['View Supplier Industries', 'Edit Supplier Industries', 'Add Supplier Industries', 'Bulky Update Supplier Industries'],
        "Supplier Sub Categories" => ['View Supplier Sub Categories', 'Edit Supplier Sub Categories', 'Add Supplier Sub Categories', 'Bulky Update Supplier Sub Categories'],
        "Rooms" => ['View Rooms', 'Edit Rooms', 'Add Rooms', 'Bulky Update Rooms'],
        "Sections" => ['View Sections', 'Edit Sections', 'Add Sections', 'Bulky Update Sections'],
        "Item Units" => ['View Item Units', 'Edit Item Units', 'Add Item Units', 'Bulky Update Item Units'],
        "Groups" => ['View Groups', 'Edit Groups', 'Add Groups', 'Bulky Update Groups'],
        "Patient Categories" => ['View Patient Categories', 'Edit Patient Categories', 'Add Patient Categories', 'Bulky Update Patient Categories'],
        "Suppliers" => ['View Suppliers', 'Edit Suppliers', 'Add Suppliers', 'Bulky Update Suppliers'],
        "Stores" => ['View Stores', 'Edit Stores', 'Add Stores', 'Bulky Update Stores'],
        "Item Categories" => ['View Item Categories', 'Edit Item Categories', 'Add Item Categories', 'Delete Item Categories'],
        "Insurance Companies" => ['View Insurance Companies', 'Edit Insurance Companies', 'Add Insurance Companies', 'Bulky Update Insurance Companies'],
        "Sub Groups" => ['View Sub Groups', 'Edit Sub Groups', 'Add Sub Groups', 'Bulky Update Sub Groups'],
        "Maturation Periods" => ['View Maturation Periods', 'Edit Maturation Periods', 'Add Maturation Periods', 'Manage Maturation Periods'],
        "Bank Schedules" => ['View Bank Schedules', 'Edit Bank Schedules', 'Add Bank Schedules', 'Manage Bank Schedules'],
        "Withdrawal Settings" => ['View Withdrawal Settings', 'Edit Withdrawal Settings', 'Add Withdrawal Settings'],
        "Business Withdraw Charges" => ['View Business Withdrawal Charges', 'Edit Business Withdrawal Charges', 'Add Business Withdrawal Charges'],
        "Refund Workflows" => ['View Credit Note Workflows', 'Edit Credit Note Workflows', 'Add Credit Note Workflows'],
    ];

    public static $callers = [
        "Calling Module" => ['View Calling Module', 'Add Calling Module', 'Edit Calling Module', 'Manage Calling Module', 'Delete Calling Module'],
        "Callers" => ['View Callers', 'Add Callers', 'Edit Callers', 'Manage Callers', 'Broadcast Announcements'],
    ];

    public static $inventoryModule = [
        "Inventory Module" => ['View Inventory Module', 'Add Inventory Module', 'Edit Inventory Module', 'Manage Inventory Module', 'Delete Inventory Module'],
        "Inventory" => [
            'View Inventory',
            'Add Inventory',
            'Edit Inventory',
            'Manage Inventory',
            'View End Store Queue',
            'Dispense End Store Queue',
            'View Approved Pool',
            'Record Inventory Usage',
        ],
    ];

    public static $adminAccess = [
        "Admin Users" => ['View Admin Users', 'Edit Admin Users', 'Add Admin Users', 'Assign Roles', 'Bulk Admin Upload'],
        "Audit Logs" => ['View Audit Logs'],
        "System Settings" => [
            'View System Settings',
            'Edit System Settings',
            'Manage System Settings',
            'Manage Settings',
            'Manage Countries & Currencies',
        ],
    ];

    public static $businessAccess = [
        "Business" => ['View Business', 'Edit Business', 'Add Business'],
        "Branches" => ['View Branches', 'Edit Branches', 'Add Branches'],
        "Client Spaces" => ['View Client Spaces', 'Add Client Spaces', 'Edit Client Spaces', 'Delete Client Spaces'],
        "Business Settings" => ['View Business Settings', 'Edit Business Settings'],
    ];

    public static $clientAccess = [
        "Clients" => ['View Clients', 'Edit Clients', 'Add Clients', 'Admit Clients', 'Discharge Clients'],
        "Third Party Payers" => ['View Third Party Payers', 'Edit Third Party Payers', 'Add Third Party Payers'],
    ];

    public static $staffAccess = [
        "Staff" => [
            'View Staff', 'Edit Staff', 'Add Staff', 'Assign Roles',
            "Edit Contractor", "Add Contractor Profile", 'View Contractor Profile', 'Edit Contractor Profile'
    ],
    ];

    public static $hrModule = [
        "HR Module" => [
            'View HR Staff',
            'Add HR Staff',
            'Edit HR Staff',
            'View HR Setup',
            'Add HR Setup',
            'Edit HR Setup',
            'View HR Approvals',
            'Edit HR Approvals',
        ],
    ];

    public static $reportAccess = [
        "Reports" => ['View Reports', 'Export Reports', 'Filter Reports'],
    ];

    public static $bulkUpload = [
        "Bulk Upload" => ['Bulk Validations Upload'],
    ];

    public static $items = [
        "Items" => ['View Items', 'Edit Items', 'Add Items', 'Bulk Upload Items'],
    ];

    public static $finance = [
                    "Finance" => ['View Finance', 'Manage Finance', 'View Business Balance Statement', 'View Client Balance Statement', 'View Money Tracking', 'View Withdrawal Requests', 'Manage Withdrawal Requests', 'View Accounts Receivable', 'Process Pay Back', 'Manage Credit Limits'],
    ];

    public static $packageTracking = [
        "Package Tracking" => ['View Package Tracking', 'Edit Package Tracking', 'Add Package Tracking', 'View Package History'],
    ];

    public static $packageSales = [
        "Package Sales" => ['View Package Sales', 'Edit Package Sales', 'Add Package Sales', 'View Package Sales History', 'Export Package Sales'],
    ];

    public static $imagingModule = [
        "Imaging Orders" => ['View Imaging Orders', 'Add Imaging Orders'],
        "Imaging Studies" => ['View Imaging Studies', 'Progress Imaging Studies'],
        "Imaging Reports" => ['Report Imaging Studies', 'Verify Imaging Reports'],
        "Peer Review" => ['View Peer Review Cases', 'Complete Peer Review Cases'],
        "Critical Findings" => ['Receive Critical Imaging Alerts'],
        "My Imaging Queue" => ['View My Imaging Queue', 'Claim Imaging Studies', 'Release Imaging Studies', 'Transfer Imaging Studies'],
        "Imaging Audit Log" => ['View Imaging Audit Log'],
        "Imaging Analytics" => ['View Imaging Analytics'],
        "Contrast Vials" => ['View Contrast Vials', 'Manage Contrast Vials'],
        "Consumption Exceptions" => ['View Consumption Exceptions', 'Resolve Consumption Exceptions'],
        "Imaging Settings" => [
            'View Imaging Protocols', 'Manage Imaging Protocols',
            'View Imaging Readiness Checks', 'Manage Imaging Readiness Checks',
            'View Imaging Critical Findings', 'Manage Imaging Critical Findings',
            'View Imaging Module', 'Manage Imaging Module',
            'View Imaging Service Point Configs', 'Manage Imaging Service Point Configs',
            'View Imaging Modalities', 'Manage Imaging Modalities',
            'View Imaging Workflow Steps', 'Manage Imaging Workflow Steps',
        ],
    ];


    public static $clinicalModule = [
        "Clinical Observations" => ['View Clinical Observations', 'Add Clinical Observations'],
        "Ward Census" => ['View Ward Census', 'Manage Ward Census', 'Add Overflow Beds'],
        "Care Assignments" => ['View Care Assignments', 'Manage Care Assignments'],
        "Clinical Work Orders" => ['View Clinical Work Orders', 'Add Clinical Work Orders'],
        "Clinical Process Registry" => ['View Clinical Process Registry', 'Progress Clinical Process Registry'],
        // Rollout gap fix: clinical_process_steps.required_role ('WARD_NURSE',
        // 'CONSULTANT') and the Claim Patient doctor/nurse choice were stored
        // but never checked against anything. This app has no reliable
        // "job role" concept (Title/Qualification are free-text, admin-typed,
        // no canonical codes) — the only real, enforced access-control
        // primitive here is User.permissions, so role gating is modeled the
        // same way as everything else in this trait, not as string-matching
        // against free text.
        "Clinical Role Gates" => ['Act As Ward Nurse (Clinical)', 'Act As Consultant (Clinical)'],
        "Medication Orders" => ['View Medication Orders', 'Prescribe Medication Orders', 'Override CDSS Safety Block'],
        "Medication Administration" => ['View MAR', 'Administer MAR Doses'],
        "Break Glass" => ['Trigger Break Glass Override'],
        "Clinical Settings" => [
            'View Clinical Dictionaries', 'Manage Clinical Dictionaries',
            'View Clinical Module', 'Manage Clinical Module',
        ],
        "Clinical Diagnoses" => ['View Clinical Diagnoses', 'Add Clinical Diagnoses'],
        "Clinical Interoperability" => ['Export FHIR Bundle'],
        "Clinical Audit" => ['View Clinical Audit Trail'],
    ];

    public static function spreadArrayKeys($assocArray)
    {
        $result = [];
        foreach ($assocArray as $key => $value) {
            if (is_string($key)) {
                $result[] = $key;
            }
            if (is_array($value)) {
                $result = array_merge($result, static::spreadArrayKeys($value));
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }

    public static function getAllPermissions()
    {
        $roles = static::spreadArrayKeys(
            array_merge(
                static::$admin,
                static::$entities,
                static::$items,
                static::$staff,
                static::$reports,
                static::$logs,
                static::$contractor,
                static::$sales,
                static::$cashier,
                static::$clients,
                static::$visits,
                static::$queues,
                static::$withdrawaal,
                static::$modules,
                static::$stock,
                static::$masters,
                static::$callers,
                static::$inventoryModule,
                static::$adminAccess,
                static::$businessAccess,
                static::$clientAccess,
                static::$staffAccess,
                static::$hrModule,
                static::$reportAccess,
                static::$bulkUpload,
                static::$finance,
                static::$packageTracking,
                static::$packageSales,
                static::$imagingModule,
                static::$clinicalModule
            )
        );
        return $roles;
    }

    public static function getAccessControl(array $exclude = [])
{
    $permissions = [
        "Dashboard" => self::$admin,
        "Entities" => self::$entities,
        "Items" => self::$items,
        "Staff" => self::$staff,
        "Reports" => self::$reports,
        "Logs" => self::$logs,
        "Contractor" => self::$contractor,
        "Sales" => self::$sales,
        "Cashier" => self::$cashier,
        "Clients" => self::$clients,
        "Visits" => self::$visits,
        "Queues" => self::$queues,
        "Withdrawals" => self::$withdrawaal,
        "Modules" => self::$modules,
        "Stock" => self::$stock,
        "Masters" => self::$masters,
        "Callers" => self::$callers,
        "Inventory" => self::$inventoryModule,
        "Admin" => self::$adminAccess,
        "Business" => self::$businessAccess,
        "Client" => self::$clientAccess,
        "Staff Access" => self::$staffAccess,
        "HR Module" => self::$hrModule,
        "Report Access" => self::$reportAccess,
        "Bulk Upload" => self::$bulkUpload,
        "Finance" => self::$finance,
        "Package Tracking" => self::$packageTracking,
        "Package Sales" => self::$packageSales,
        "Imaging" => self::$imagingModule,
        "Clinical" => self::$clinicalModule,
    ];

    if (!empty($exclude)) {
        $permissions = collect($permissions)->reject(function ($_, $key) use ($exclude) {
            return in_array($key, $exclude);
        })->toArray();
    }

    return $permissions;
}


    public static function userCan($pageRole, $permissions)
    {
        $permissions = json_decode($permissions);
        return in_array($pageRole, $permissions);
    }

    public static function user_can($page_role)
    {
        $actions1 = $_SESSION['actions'];
        $actions = json_decode($actions1);
        return in_array($page_role, $actions);
    }

    public static function is_assoc(array $array)
    {
        $keys = array_keys($array);
        return array_keys($keys) !== $keys;
    }
}
