<?php
// config/teb.php — Technical Review Board (TRB) Portal

return [

    // Connection that holds the teb_* tables. null = the app's default connection.
    'connection' => env('TEB_DB_CONNECTION'),

    // TRB Admin = employees whose Authify emp_dept is one of these (case-insensitive).
    // They review, schedule, update status, assign TRB numbers and keep the approver matrix.
    'admin_departments' => array_filter(array_map('trim', explode(',', env('TEB_ADMIN_DEPARTMENTS', 'Process Engineering')))),

    // TRB No. is auto-generated as yyyy-NNNNN (sequence restarts every year).
    'teb_no_digits' => (int) env('TEB_NO_DIGITS', 5),

    // Customer dropdown source: qdn_db.customer_list
    'customer' => [
        'connection'  => env('TEB_CUSTOMER_CONNECTION', env('QDB_CONNECTION', 'qdn')),
        'table'       => env('TEB_CUSTOMER_TABLE', 'customer_list'),
        'key_column'  => env('TEB_CUSTOMER_KEY', 'id'),
        'name_column' => env('TEB_CUSTOMER_NAME', 'customer_name'),
    ],

    // Reviewee list on "Schedule TRB Review": specimen_signature_db.email_accounts (active rows only).
    // Invites go to the selected addresses.
    'email_accounts' => [
        'connection'        => env('TEB_EMAIL_CONNECTION', env('EDB_CONNECTION', 'email')),
        'table'             => env('TEB_EMAIL_TABLE', 'email_accounts'),
        'email_column'      => env('TEB_EMAIL_COLUMN', 'email'),
        'active_column'     => env('TEB_EMAIL_ACTIVE', 'is_active'),
    ],

    // "Database per Category" — every TRB entry is filed under one of these.
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
    // dep_name must match masterlist.department.dep_name. The project owner's department,
    // when it is not in this list, is routed as "Others".
    'approval_departments' => [
        'Production',
        'Process Engineering',
        'Equipment Engineering',
        'Quality Assurance',
        env('TEB_QMS_DEPARTMENT', 'QMS'),
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
    // masterlist.job_level ids per level: 2 Supervisor, 3 Section Head, 4 Manager (6 President and 8 Operation Director, both position level 35 Executive, are company-wide, see executive_approvals).
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

    // Major changes: after the department portions, these sign company-wide, in this order.
    // Only employees whose position level (masterlist.employee_position_level -> position_level) is Executive,
    // id 35, can sign here. Among them, the job_level id or the job / position level name decides which box:
    // Operation Director or President. Leave TEB_EXECUTIVE_POSITION_LEVEL_IDS empty to drop the Executive check.
    'executive_position_level_ids' => array_map('intval', array_filter(explode(',', env('TEB_EXECUTIVE_POSITION_LEVEL_IDS', '35')))),
    'executive_approval_classifications' => ['major'],
    'executive_approvals' => [
        'operation_director' => [
            'label'         => 'Operation Director',
            'job_level_ids' => array_map('intval', array_filter(explode(',', env('TEB_JOB_LEVEL_ID_OPERATION_DIRECTOR', '8')))),
            'names'         => array_filter(array_map('trim', explode(',', env('TEB_JOB_LEVEL_OPERATION_DIRECTOR', 'Operation Director')))),
        ],
        'president' => [
            'label'         => 'President',
            'job_level_ids' => array_map('intval', array_filter(explode(',', env('TEB_JOB_LEVEL_ID_PRESIDENT', '6')))),
            'names'         => array_filter(array_map('trim', explode(',', env('TEB_JOB_LEVEL_PRESIDENT', 'President')))),
        ],
    ],

    // Step 4 "Trial plan & required authorizations approved?": 'departments' routes the trial plan through the
    // approval section (same signatories as the results in step 6); 'admin' lets the TRB Admin approve it alone.
    'plan_approval' => env('TEB_PLAN_APPROVAL', 'departments'),

    // Departments that share approvers: anyone who can sign a shared level in one of them can sign that level in the others
    // (e.g. one manager over Facilities and MIS). dep_name as in masterlist.department, case-insensitive.
    // .env: TEB_SHARED_APPROVERS="Facilities,MIS,Facilities / MIS;Dept A,Dept B"  TEB_SHARED_APPROVER_LEVELS="manager"
    'approval_shared_departments' => array_values(array_filter(array_map(
        fn($group) => array_values(array_filter(array_map('trim', explode(',', $group)))),
        explode(';', env('TEB_SHARED_APPROVERS', 'Facilities,MIS,Facilities / MIS'))
    ), fn($group) => count($group) > 1)),
    'approval_shared_levels' => array_filter(array_map('trim', explode(',', env('TEB_SHARED_APPROVER_LEVELS', 'manager')))),

    // Step 1 Project Charter: BMP alignment / driver (TQRDC).
    'bmp_drivers' => [
        'T' => 'Technology',
        'Q' => 'Quality',
        'R' => 'Responsiveness',
        'D' => 'Delivery',
        'C' => 'Cost',
    ],

    // Recommended category per BMP driver and the change classification it carries (TQRDC_BMP_Drivers_Category.xlsx).
    // A project is Major when any category it picks is Major, otherwise Minor.
    'bmp_categories' => [
        'T' => [
            'Equipment & System Enhancement'    => 'minor',
            'Automation & Digitalization'       => 'minor',
            'Machine & Process Qualification'   => 'major',
            'Process Development & Improvement' => 'major',
        ],
        'Q' => [
            'Quality & Defect Improvement'                => 'major',
            'Quality Escape Prevention'                   => 'major',
            'Process Control & Monitoring'                => 'minor',
            'Process & Change Management'                 => 'major',
            'Quality Management System'                   => 'major',
            'Workplace & Process Environment Improvement' => 'minor',
            'Workflow Optimization & Standardization'     => 'minor',
        ],
        'R' => [
            'Response & Cycle Time Improvement' => 'minor',
            'Alert & Escalation Management'     => 'minor',
            'Customer Response & RCA'           => 'major',
        ],
        'D' => [
            'Cycle Time & Process Optimization'     => 'major',
            'Equipment Availability & Productivity' => 'major',
            'WIP & Hold Lot Management'             => 'minor',
            'Yield & Output Improvement'            => 'major',
        ],
        'C' => [
            'Productivity Optimization'                   => 'major',
            'Labor Optimization'                          => 'major',
            'Material Optimization'                       => 'major',
            'Scrap Reduction'                             => 'major',
            'Digitalization & Paperless'                  => 'major',
            'P&L Optimization'                            => 'major',
            'Workplace & Process Environment Improvement' => 'major',
            'Workflow Optimization & Standardization'     => 'major',
        ],
    ],

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

    'mail_from_name' => 'TRB Portal',
];
