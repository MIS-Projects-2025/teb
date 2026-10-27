<?php
// config/teb.php — Telford Engineering Board (TEB) Portal

return [

    // Connection that holds the teb_* tables. null = the app's default connection.
    'connection' => env('TEB_DB_CONNECTION'),

    // TEB Admin = employees whose Authify emp_dept is one of these (case-insensitive).
    // They review, schedule, update status, assign TEB numbers and keep the approver matrix.
    'admin_departments' => array_filter(array_map('trim', explode(',', env('TEB_ADMIN_DEPARTMENTS', 'Process Engineering')))),

    // TEB No. is auto-generated as yyyy-NNNNN (sequence restarts every year).
    'teb_no_digits' => (int) env('TEB_NO_DIGITS', 5),

    // Customer dropdown source: qdn_db.customer_list
    'customer' => [
        'connection'  => env('TEB_CUSTOMER_CONNECTION', env('QDB_CONNECTION', 'qdn')),
        'table'       => env('TEB_CUSTOMER_TABLE', 'customer_list'),
        'key_column'  => env('TEB_CUSTOMER_KEY', 'id'),
        'name_column' => env('TEB_CUSTOMER_NAME', 'customer_name'),
    ],

    // Reviewee list on "Schedule TEB Review": specimen_signature_db.email_accounts (active rows only).
    // Invites go to the selected addresses.
    'email_accounts' => [
        'connection'        => env('TEB_EMAIL_CONNECTION', env('EDB_CONNECTION', 'email')),
        'table'             => env('TEB_EMAIL_TABLE', 'email_accounts'),
        'email_column'      => env('TEB_EMAIL_COLUMN', 'email'),
        'active_column'     => env('TEB_EMAIL_ACTIVE', 'is_active'),
    ],

    // "Database per Category" — every TEB entry is filed under one of these.
    'categories' => [
        'machine_qualification'  => 'Machine Qualification',
        'inhouse_procurement'    => 'In-house Procurement of Materials',
        'machine_modification'   => 'Machine Features / Mechanical Modification',
        'procedure'              => 'New / Changed Procedure',
    ],

    'classifications' => [
        'major' => 'Major',
        'minor' => 'Minor',
    ],

    'types_of_change' => [
        'permanent' => 'Permanent',
        'temporary' => 'Temporary',
        'waiver'    => 'Waiver',
        'deletion'  => 'Deletion',
        'revision'  => 'Revision',
        'addition'  => 'Addition',
    ],

    // Approval section. Each department signs Supervisor -> Section Head -> Manager.
    // dep_name must match masterlist.department.dep_name. Affected areas outside
    // this list are routed as "Others".
    'approval_departments' => [
        'Production',
        'Process Engineering',
        'Equipment Engineering',
        'Quality Assurance',
    ],

    // Name shown on the approval section when it differs from the masterlist dep_name.
    'approval_department_labels' => [
        'Production' => 'Manufacturing',
    ],

    'approval_levels' => [
        'supervisor'   => 'Supervisor',
        'section_head' => 'Section Head',
        'manager'      => 'Manager',
    ],

    // Settings > Approver matrix: the dropdown for each level lists employees of that department
    // (masterlist.employee_department) whose job level (employee_job_level -> job_level.emp_job_level_name)
    // or position level (employee_position_level -> position_level.position_level_name)
    // contains one of these words, case-insensitive. Comma-separated in .env.
    // masterlist.job_level ids per level: 2 Supervisor, 3 Section Head, 4 Manager (5 Operation Director is not an approval level).
    'approval_job_level_ids' => [
        'supervisor'   => array_map('intval', array_filter(explode(',', env('TEB_JOB_LEVEL_ID_SUPERVISOR', '2')))),
        'section_head' => array_map('intval', array_filter(explode(',', env('TEB_JOB_LEVEL_ID_SECTION_HEAD', '3')))),
        'manager'      => array_map('intval', array_filter(explode(',', env('TEB_JOB_LEVEL_ID_MANAGER', '4')))),
    ],

    // Also matched by name (job level or position level name contains one of these words):
    'approval_job_levels' => [
        'supervisor'   => array_filter(array_map('trim', explode(',', env('TEB_JOB_LEVEL_SUPERVISOR', 'Supervisor')))),
        'section_head' => array_filter(array_map('trim', explode(',', env('TEB_JOB_LEVEL_SECTION_HEAD', 'Section Head')))),
        'manager'      => array_filter(array_map('trim', explode(',', env('TEB_JOB_LEVEL_MANAGER', 'Manager')))),
    ],

    // Departments that share approvers: anyone who can sign a level in one of them can sign that level in the others
    // (e.g. one manager over Facilities and MIS). dep_name as in masterlist.department, case-insensitive.
    // .env: TEB_SHARED_APPROVERS="Facilities,MIS;Dept A,Dept B"
    'approval_shared_departments' => array_values(array_filter(array_map(
        fn($group) => array_values(array_filter(array_map('trim', explode(',', $group)))),
        explode(';', env('TEB_SHARED_APPROVERS', 'Facilities,MIS,Facilities / MIS'))
    ), fn($group) => count($group) > 1)),
    'approval_shared_levels' => array_filter(array_map('trim', explode(',', env('TEB_SHARED_APPROVER_LEVELS', 'manager')))),

    '4m_items' => [
        'man'         => 'Man',
        'machine'     => 'Machine',
        'method'      => 'Method',
        'material'    => 'Materials',
        'environment' => 'Environment',
    ],

    'uploads' => [
        'disk'   => env('TEB_UPLOAD_DISK', 'local'),
        'max_kb' => 20480,
        'mimes'  => 'pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg,zip',
    ],

    'mail_from_name' => 'TEB Portal',
];
