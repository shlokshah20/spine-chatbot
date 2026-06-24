<?php
/**
 * Spine HR Chatbot – Static Knowledge Base v1
 *
 * Content compiled from web-crawled pages:
 *   spinetechnologies.com/hrsuite/, /recruitment/, /onboarding/, /core-hris/,
 *   /employee-self-service/, /leave-management/, /time-attendance/,
 *   /expense-management/, /timesheet/, /performance-management/,
 *   /hr-help-desk/, /offboarding/, /visitors-management/, /spine-assets/
 *   + International HR Suite (GCC/MENA/SEA coverage)
 *
 * Architecture note:
 *   This file returns a plain PHP array consumed by Spine_Chatbot_Search.
 *   In Phase 2 this return statement will be replaced by a MySQL FULLTEXT
 *   query against wp_spine_kb_entries, preserving the identical array shape
 *   so all search/scoring code remains untouched.
 *
 * product_categories — maps the 4 frontend branch IDs to the module IDs that
 *   should be searched when that branch is selected. Spine_Chatbot_Search
 *   uses this to scope queries to the relevant subset of modules.
 *
 * @package SpineChatbot
 * @version 1.1
 */

defined( 'ABSPATH' ) || exit;

return [

    // ── Meta ──────────────────────────────────────────────────────────────────
    'version'      => '1.1',
    'generated_at' => '2025-06-01',
    'source_urls'  => [
        'https://spinetechnologies.com/hrsuite/',
        'https://spinetechnologies.com/recruitment/',
        'https://spinetechnologies.com/onboarding/',
        'https://spinetechnologies.com/core-hris/',
        'https://spinetechnologies.com/employee-self-service/',
        'https://spinetechnologies.com/leave-management/',
        'https://spinetechnologies.com/time-attendance/',
        'https://spinetechnologies.com/expense-management/',
        'https://spinetechnologies.com/timesheet/',
        'https://spinetechnologies.com/performance-management/',
        'https://spinetechnologies.com/hr-help-desk/',
        'https://spinetechnologies.com/offboarding/',
        'https://spinetechnologies.com/visitors-management/',
        'https://spinetechnologies.com/spine-assets/',
    ],

    // ── Product category → module ID mapping ─────────────────────────────────
    // Used by Spine_Chatbot_Search::query() to scope results to the branch
    // the user selected from the initial 4-button menu.
    'product_categories' => [
        'hr_suite' => [
            'hr-suite', 'recruitment', 'onboarding', 'core-hris',
            'employee-self-service', 'leave-management', 'time-attendance',
            'expense-management', 'timesheet', 'performance-management',
            'hr-help-desk', 'offboarding', 'visitors-management',
        ],
        'assets'        => [ 'spine-assets' ],
        'international' => [ 'international-hr-suite' ],
    ],

    // ── Global / company info ─────────────────────────────────────────────────
    'global' => [
        'company_name'   => 'Spine Technologies Pvt. Ltd.',
        'product_suite'  => 'Spine HR Suite',
        'tagline'        => 'Simplify HR. Amplify People.',
        'description'    => 'Spine HR Suite is a comprehensive, cloud-native Human Resource Management System (HRMS) designed to manage the complete employee lifecycle — from recruitment and onboarding through payroll, performance, and offboarding — on a single unified platform. Used by 4,500+ organisations across India, Middle East, and South-East Asia.',
        'industries'     => [ 'Manufacturing', 'IT & Technology', 'Retail', 'Healthcare', 'BFSI', 'Education', 'Hospitality', 'Logistics' ],
        'deployment'     => [ 'Cloud (SaaS)', 'On-Premise', 'Hybrid' ],
        'mobile_apps'    => [ 'Android', 'iOS' ],
        'contact' => [
            'email'    => 'info@spinetechnologies.com',
            'support'  => 'support@spinetechnologies.com',
            'phone'    => '+91 22 6138 7777',
            'demo_url' => 'https://spinetechnologies.com/request-demo/',
            'website'  => 'https://spinetechnologies.com',
        ],
        'general_faqs' => [
            [
                'question' => 'What is Spine HR Suite?',
                'answer'   => 'Spine HR Suite is an end-to-end cloud HRMS that covers Recruitment, Onboarding, Core HRIS, Leave, Attendance, Payroll, Expense, Timesheet, Performance Management, Help Desk, Offboarding, and Visitors Management — all on one platform.',
                'keywords' => [ 'spine', 'hrms', 'hr suite', 'hr software', 'what is', 'overview', 'platform', 'system' ],
            ],
            [
                'question' => 'How many companies use Spine HR Suite?',
                'answer'   => 'Spine HR Suite is trusted by 4,500+ organisations with 1 million+ employees managed across industries in India, the Middle East, and South-East Asia.',
                'keywords' => [ 'customers', 'companies', 'clients', 'users', 'how many', 'trusted' ],
            ],
            [
                'question' => 'Is Spine HR available on mobile?',
                'answer'   => 'Yes. Spine HR offers native mobile apps for both Android and iOS, enabling employees and managers to access all self-service features, approve requests, and track attendance on the go.',
                'keywords' => [ 'mobile', 'app', 'android', 'ios', 'phone', 'smartphone' ],
            ],
            [
                'question' => 'What deployment models does Spine HR support?',
                'answer'   => 'Spine HR Suite can be deployed as a Cloud SaaS solution, On-Premise on your own servers, or in a Hybrid model — giving you full flexibility based on your IT policy.',
                'keywords' => [ 'deployment', 'cloud', 'on-premise', 'hosting', 'saas', 'server' ],
            ],
            [
                'question' => 'How do I book a demo?',
                'answer'   => 'You can book a personalised demo at https://spinetechnologies.com/request-demo/ or speak directly with our team. Our sales consultants will schedule a live walkthrough tailored to your industry.',
                'keywords' => [ 'demo', 'book', 'schedule', 'trial', 'see', 'walkthrough', 'request' ],
            ],
            [
                'question' => 'What is the pricing of Spine HR Suite?',
                'answer'   => 'Pricing is customised based on employee count, modules selected, and deployment type. Please book a demo or contact us at info@spinetechnologies.com for a tailored quote.',
                'keywords' => [ 'price', 'pricing', 'cost', 'quote', 'how much', 'subscription', 'license' ],
            ],
        ],

        // Semantic synonym groups — terms treated as equivalent during scoring
        'semantic_groups' => [
            'recruitment'     => [ 'recruitment', 'hiring', 'talent acquisition', 'ats', 'applicant tracking', 'hire', 'job posting', 'vacancy', 'candidate', 'sourcing' ],
            'onboarding'      => [ 'onboarding', 'new hire', 'new employee', 'joining', 'induction', 'pre-boarding', 'orientation' ],
            'hris'            => [ 'hris', 'core hr', 'employee records', 'employee master', 'org chart', 'organisation structure', 'employee data', 'profile', 'employee database' ],
            'ess'             => [ 'ess', 'employee self service', 'self-service', 'employee portal', 'myhr', 'self service portal' ],
            'leave'           => [ 'leave', 'vacation', 'holiday', 'time off', 'absence', 'pto', 'sick leave', 'casual leave', 'earned leave', 'annual leave' ],
            'attendance'      => [ 'attendance', 'time tracking', 'biometric', 'clock in', 'clock out', 'shift', 'time and attendance', 'punch', 'check in' ],
            'expense'         => [ 'expense', 'reimbursement', 'claim', 'bill', 'receipt', 'travel expense', 'expense report' ],
            'timesheet'       => [ 'timesheet', 'project time', 'billable hours', 'time log', 'project tracking', 'work hours' ],
            'performance'     => [ 'performance', 'appraisal', 'review', 'goals', 'okr', 'kra', 'kpi', '360', 'feedback', 'rating', 'evaluation' ],
            'helpdesk'        => [ 'help desk', 'helpdesk', 'ticket', 'query', 'support', 'hr support', 'employee query', 'hr ticket', 'grievance' ],
            'offboarding'     => [ 'offboarding', 'resignation', 'exit', 'termination', 'full and final', 'clearance', 'fnf', 'leaving', 'separation' ],
            'visitors'        => [ 'visitor', 'visitors management', 'guest', 'visitor registration', 'reception', 'visitor tracking' ],
            'assets'          => [ 'asset', 'assets', 'equipment', 'laptop', 'device', 'asset tracking', 'asset management', 'inventory' ],
            'payroll'         => [ 'payroll', 'salary', 'pay', 'payslip', 'pay slip', 'compensation', 'ctc', 'wage' ],
            'integration'     => [ 'integration', 'api', 'connect', 'sync', 'third party', 'erp', 'tally', 'sap' ],
            'report'          => [ 'report', 'analytics', 'dashboard', 'insight', 'data', 'export', 'download' ],
            'international'   => [ 'international', 'global', 'multi-country', 'uae', 'gcc', 'gulf', 'middle east', 'wps', 'mena', 'overseas', 'expat' ],
        ],
    ],

    // ── Modules ───────────────────────────────────────────────────────────────
    'modules' => [

        // ── 1. HR Suite (Main) ─────────────────────────────────────────────────
        'hr-suite' => [
            'id'          => 'hr-suite',
            'title'       => 'Spine HR Suite',
            'url'         => 'https://spinetechnologies.com/hrsuite/',
            'tagline'     => 'One Platform. Complete HR.',
            'overview'    => 'Spine HR Suite is a modular, cloud-native HRMS that unifies every stage of the employee lifecycle on a single database. Organisations can choose only the modules they need and expand over time without data migration. Built on a robust multi-tenant architecture, it delivers enterprise-grade security, 99.9% uptime SLA, role-based access control, and real-time dashboards — available via web browser and iOS/Android mobile app.',
            'keywords'    => [ 'hr suite', 'hrms', 'hris', 'hr platform', 'hr software', 'hr system', 'human resource', 'spine', 'complete hr', 'unified', 'modular', 'cloud hr' ],
            'key_metrics' => [ '4,500+ clients', '1M+ employees managed', '14+ modules', '15+ years experience', '99.9% uptime SLA' ],
            'features'    => [
                [ 'name' => 'Modular Architecture',        'desc' => 'Pick and activate only the HR modules your organisation needs; scale by adding more.' ],
                [ 'name' => 'Single Employee Database',    'desc' => 'One central record for each employee — updated across all modules in real time.' ],
                [ 'name' => 'Role-Based Access Control',   'desc' => 'Granular permission matrix — HR Admin, Manager, Employee, Super-Admin — with field-level security.' ],
                [ 'name' => 'Real-Time Dashboards',        'desc' => 'Executive dashboards with live KPIs: headcount, attrition, leave liability, attendance rate.' ],
                [ 'name' => 'Open REST API',               'desc' => 'Bi-directional API integration with ERP systems (SAP, Oracle, Tally), payroll engines, and third-party tools.' ],
                [ 'name' => 'Multi-Company / Multi-Branch','desc' => 'Manage multiple legal entities, subsidiaries, and branch locations under a single license.' ],
                [ 'name' => 'Workflow Engine',             'desc' => 'Configurable multi-level approval workflows for any HR process — no code required.' ],
                [ 'name' => 'Audit Trail',                 'desc' => 'Immutable log of every change across every module for compliance and forensic review.' ],
                [ 'name' => 'Mobile App',                  'desc' => 'Feature-parity iOS and Android app for employees and managers.' ],
                [ 'name' => 'Data Security',               'desc' => 'ISO 27001 aligned, AES-256 encryption at rest and TLS in transit, SOC 2 Type II compliant hosting.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Does Spine HR Suite integrate with payroll?',
                    'answer'   => 'Yes. Spine HR Suite has a built-in payroll engine and also offers bi-directional API integration with third-party payroll providers and ERP systems like SAP, Oracle, and Tally.',
                    'keywords' => [ 'payroll', 'salary', 'integration', 'sap', 'oracle', 'tally', 'erp' ],
                ],
                [
                    'question' => 'Can I use only selected modules?',
                    'answer'   => 'Absolutely. Spine HR Suite is fully modular. You can activate Recruitment, Leave, and Attendance today and add Performance Management or Help Desk later — all on the same platform and data.',
                    'keywords' => [ 'modules', 'selective', 'pick', 'choose', 'individual', 'standalone' ],
                ],
                [
                    'question' => 'Is Spine HR suitable for large enterprises?',
                    'answer'   => 'Yes. Spine HR Suite serves organisations from 50 to 50,000+ employees. Its multi-tenant architecture, multi-entity support, and enterprise-grade RBAC make it suitable for complex, large-scale deployments.',
                    'keywords' => [ 'enterprise', 'large', 'big company', 'scale', '10000', '50000' ],
                ],
            ],
        ],

        // ── 2. Recruitment ────────────────────────────────────────────────────
        'recruitment' => [
            'id'       => 'recruitment',
            'title'    => 'Recruitment & Applicant Tracking',
            'url'      => 'https://spinetechnologies.com/recruitment/',
            'tagline'  => 'Hire Smarter. Hire Faster.',
            'overview' => 'Spine Recruitment is a full-featured Applicant Tracking System (ATS) that streamlines every stage of the hiring funnel — from raising a job requisition and multi-channel sourcing through structured interviews, offer management, and seamless handoff to Onboarding. It integrates with major job portals (Naukri, LinkedIn, Indeed, Monster) and includes AI-powered resume parsing to eliminate manual data entry.',
            'keywords' => [ 'recruitment', 'ats', 'hiring', 'applicant tracking', 'job posting', 'candidate', 'resume', 'interview', 'offer letter', 'talent acquisition', 'sourcing', 'job portal', 'vacancy', 'jd', 'job description' ],
            'features' => [
                [ 'name' => 'Job Requisition Management',    'desc' => 'Raise, approve, and track open positions with department-wise headcount budgets.' ],
                [ 'name' => 'Multi-Channel Job Publishing',  'desc' => 'Post to Naukri, LinkedIn, Indeed, Monster, and your own career portal in one click.' ],
                [ 'name' => 'Career Portal Builder',         'desc' => 'Branded, SEO-optimised career page embeddable on your company website.' ],
                [ 'name' => 'AI Resume Parser',              'desc' => 'Auto-extract candidate details from uploaded CVs to pre-fill application profiles.' ],
                [ 'name' => 'Kanban Pipeline View',          'desc' => 'Drag-and-drop candidate cards across configurable hiring stages.' ],
                [ 'name' => 'Structured Interview Kits',     'desc' => 'Score candidates consistently using preset question banks and rating rubrics.' ],
                [ 'name' => 'Automated Interview Scheduling','desc' => 'Sync with calendars and send automated invitations/reminders to candidates and interviewers.' ],
                [ 'name' => 'Offer Letter Automation',       'desc' => 'Generate branded offer letters from templates; track acceptance status digitally.' ],
                [ 'name' => 'Assessment Integration',        'desc' => 'Embed online skill tests and psychometric assessments into the hiring pipeline.' ],
                [ 'name' => 'Recruitment Analytics',         'desc' => 'Time-to-hire, source-of-hire, offer-acceptance rate, and cost-per-hire dashboards.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Which job portals does Spine Recruitment integrate with?',
                    'answer'   => 'Spine Recruitment integrates with Naukri, LinkedIn, Indeed, Monster, Shine, and your own branded career portal. Job postings are distributed in one click.',
                    'keywords' => [ 'naukri', 'linkedin', 'indeed', 'monster', 'portal', 'job board', 'job site' ],
                ],
                [
                    'question' => 'Does Spine have an AI resume parser?',
                    'answer'   => 'Yes. The built-in AI resume parser automatically extracts candidate name, contact, education, work history, and skills from uploaded CVs — eliminating manual data entry for your recruiters.',
                    'keywords' => [ 'ai', 'resume', 'parser', 'cv', 'parse', 'extract', 'automatic' ],
                ],
                [
                    'question' => 'Can I track time-to-hire and other recruitment metrics?',
                    'answer'   => 'Yes. Spine Recruitment provides dashboards for time-to-hire, time-to-fill, source-of-hire ROI, offer acceptance rate, interview-to-hire ratio, and cost-per-hire.',
                    'keywords' => [ 'time to hire', 'metrics', 'analytics', 'report', 'kpi', 'dashboard' ],
                ],
            ],
        ],

        // ── 3. Onboarding ─────────────────────────────────────────────────────
        'onboarding' => [
            'id'       => 'onboarding',
            'title'    => 'Employee Onboarding',
            'url'      => 'https://spinetechnologies.com/onboarding/',
            'tagline'  => 'First Impressions That Last.',
            'overview' => 'Spine Onboarding delivers a paperless, digitally-driven new-hire experience that begins the moment an offer is accepted — covering pre-boarding document collection, task assignment, equipment provisioning, induction scheduling, and Day-1 readiness checks. Structured checklists and automated reminders ensure no onboarding step is missed.',
            'keywords' => [ 'onboarding', 'new hire', 'new employee', 'joining', 'induction', 'pre-boarding', 'orientation', 'paperless', 'digital', 'checklist', 'document collection', 'day 1' ],
            'features' => [
                [ 'name' => 'Pre-Boarding Portal',           'desc' => 'New hires complete forms, upload documents, and review company policies before Day 1.' ],
                [ 'name' => 'Digital Document Collection',   'desc' => 'Collect ID proofs, educational certificates, bank details, and signed agreements digitally.' ],
                [ 'name' => 'Onboarding Checklist Engine',   'desc' => 'Assign role-specific task lists to HR, IT, Finance, and the new hire — with due dates and automated reminders.' ],
                [ 'name' => 'Equipment & Access Provisioning','desc' => 'Raise asset requests and system-access tickets directly from the onboarding workflow.' ],
                [ 'name' => 'Buddy & Mentor Assignment',     'desc' => 'Automatically pair new hires with a buddy for guided first-week support.' ],
                [ 'name' => 'Induction Schedule Builder',    'desc' => 'Create and distribute structured orientation calendars with training session links.' ],
                [ 'name' => 'E-Signature Integration',       'desc' => 'Collect legally valid electronic signatures on offer letters, NDAs, and policies.' ],
                [ 'name' => 'New Hire Survey',               'desc' => 'Automated Day-30, Day-60, Day-90 check-in surveys to capture new hire sentiment.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Can new hires complete their paperwork before joining?',
                    'answer'   => 'Yes. Spine\'s pre-boarding portal lets accepted candidates upload documents, fill in personal and bank details, and e-sign offer letters — all before their first day, eliminating Day-1 paperwork.',
                    'keywords' => [ 'pre-boarding', 'before joining', 'paperwork', 'documents', 'before day 1' ],
                ],
                [
                    'question' => 'How does the onboarding checklist work?',
                    'answer'   => 'HR admins configure role-based checklists with tasks assigned to specific departments (IT, Finance, Manager, Employee). Each task has a deadline and automated email/SMS reminders. A progress dashboard shows completion status in real time.',
                    'keywords' => [ 'checklist', 'task', 'workflow', 'reminder', 'progress', 'steps' ],
                ],
            ],
        ],

        // ── 4. Core HRIS ──────────────────────────────────────────────────────
        'core-hris' => [
            'id'       => 'core-hris',
            'title'    => 'Core HRIS — Employee Information System',
            'url'      => 'https://spinetechnologies.com/core-hris/',
            'tagline'  => 'Your Single Source of HR Truth.',
            'overview' => 'Spine Core HRIS is the master employee record system at the heart of the suite. It maintains rich employee profiles, manages organisational hierarchy, handles all lifecycle events (hire, confirm, transfer, promote, exit), and provides deep HR reporting and custom analytics — all from one secure, searchable database.',
            'keywords' => [ 'hris', 'core hr', 'employee record', 'employee master', 'employee database', 'profile', 'org chart', 'organization', 'hierarchy', 'lifecycle', 'transfer', 'promotion', 'confirmation', 'employee information' ],
            'features' => [
                [ 'name' => 'Employee Master Database',     'desc' => '100+ configurable employee fields — personal, contact, emergency, education, work history, and bank details.' ],
                [ 'name' => 'Organisational Chart',         'desc' => 'Interactive org chart with reporting lines, span-of-control analytics, and drill-down views.' ],
                [ 'name' => 'Position & Grade Management',  'desc' => 'Define job roles, grades, pay bands, and positional headcount norms.' ],
                [ 'name' => 'Employee Lifecycle Events',    'desc' => 'Structured workflows for hire, probation confirmation, internal transfer, promotion, and separation.' ],
                [ 'name' => 'Document Repository',         'desc' => 'Secure upload and version-control for contracts, certificates, and compliance documents per employee.' ],
                [ 'name' => 'Custom Fields & Forms',        'desc' => 'Extend any employee record with custom text, date, dropdown, or file fields — no coding.' ],
                [ 'name' => 'Letter Generation',            'desc' => 'Auto-generate appointment, confirmation, experience, and salary revision letters from templates.' ],
                [ 'name' => 'HR Reports & Analytics',       'desc' => '50+ standard reports (headcount, attrition, tenure, diversity) plus a report builder for custom queries.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How does Spine manage employee transfers and promotions?',
                    'answer'   => 'Core HRIS includes structured lifecycle event workflows. A transfer or promotion request is raised by HR/Manager, routed through approvals, and upon sanction, automatically updates the employee\'s grade, reporting manager, cost centre, and salary — with an auto-generated letter.',
                    'keywords' => [ 'transfer', 'promotion', 'lifecycle', 'letter', 'grade', 'designation' ],
                ],
                [
                    'question' => 'Can I generate appointment and experience letters automatically?',
                    'answer'   => 'Yes. Spine Core HRIS has a letter template engine. HR admins design templates with dynamic placeholders; the system merges employee data and generates print-ready PDFs with one click.',
                    'keywords' => [ 'appointment letter', 'experience letter', 'letter', 'generate', 'template', 'pdf' ],
                ],
            ],
        ],

        // ── 5. Employee Self Service ───────────────────────────────────────────
        'employee-self-service' => [
            'id'       => 'employee-self-service',
            'title'    => 'Employee Self Service (ESS)',
            'url'      => 'https://spinetechnologies.com/employee-self-service/',
            'tagline'  => 'Empower Every Employee, Every Day.',
            'overview' => 'Spine ESS gives every employee a personal HR portal — on desktop and mobile — where they can apply for leave, submit expenses, view payslips, download tax documents, request IT assets, raise HR tickets, and update personal information without contacting HR. This dramatically reduces HR administrative overhead while increasing employee satisfaction.',
            'keywords' => [ 'ess', 'employee self service', 'self-service', 'employee portal', 'portal', 'leave apply', 'payslip', 'tax', 'form 16', 'employee app', 'it declaration', 'self service' ],
            'features' => [
                [ 'name' => 'Unified Employee Dashboard',   'desc' => 'Personalised home screen showing pending approvals, recent payslip, leave balance, and company announcements.' ],
                [ 'name' => 'Leave Application & Tracking', 'desc' => 'Apply, cancel, and check status of leave requests; view team leave calendar.' ],
                [ 'name' => 'Payslip & Salary History',     'desc' => 'Download current and historical payslips as password-protected PDFs.' ],
                [ 'name' => 'IT & Investment Declaration',  'desc' => 'Submit tax-saving investment declarations and upload proof documents for Form 16.' ],
                [ 'name' => 'Expense Submission',           'desc' => 'Raise expense claims and track reimbursement status from the mobile app.' ],
                [ 'name' => 'Attendance & Shift View',      'desc' => 'View daily punch records, shift schedule, and regularisation requests.' ],
                [ 'name' => 'Profile & Document Updates',  'desc' => 'Update personal/contact/bank information with HR-approval routing.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Can employees apply for leave through the mobile app?',
                    'answer'   => 'Yes. The Spine mobile app (Android & iOS) allows employees to apply for leave, view balances, check team calendars, and receive approval notifications — all from their phone.',
                    'keywords' => [ 'leave', 'mobile', 'app', 'apply', 'balance', 'android', 'ios' ],
                ],
                [
                    'question' => 'Can employees download their payslips from the portal?',
                    'answer'   => 'Yes. Employees can view and download password-protected PDF payslips for any past month directly from the ESS portal or mobile app.',
                    'keywords' => [ 'payslip', 'salary slip', 'download', 'portal', 'pdf', 'password' ],
                ],
            ],
        ],

        // ── 6. Leave Management ────────────────────────────────────────────────
        'leave-management' => [
            'id'       => 'leave-management',
            'title'    => 'Leave Management',
            'url'      => 'https://spinetechnologies.com/leave-management/',
            'tagline'  => 'Automate Every Absence, Every Policy.',
            'overview' => 'Spine Leave Management automates the full leave lifecycle — from policy configuration and accrual rules through employee applications, multi-level approvals, and real-time balance updates. Supports unlimited leave types, location-based holiday calendars, carry-forward, encashment, and detailed compliance reports.',
            'keywords' => [ 'leave', 'leave management', 'vacation', 'leave policy', 'leave balance', 'casual leave', 'sick leave', 'earned leave', 'annual leave', 'holiday', 'leave calendar', 'accrual', 'encashment', 'carry forward', 'leave approval' ],
            'features' => [
                [ 'name' => 'Unlimited Leave Types',          'desc' => 'Configure Casual Leave, Sick Leave, Earned Leave, Maternity, Paternity, Compensatory, LOP, and any custom type.' ],
                [ 'name' => 'Policy Configuration Engine',    'desc' => 'Set accrual frequency (monthly/annual), proration rules, negative balance rules, and gender/grade applicability.' ],
                [ 'name' => 'Location-Based Holiday Calendars','desc' => 'Assign different public holiday calendars by state, country, or office location.' ],
                [ 'name' => 'Multi-Level Approval Workflow',  'desc' => 'Configurable approval chains — direct manager, skip-level, HR — with delegation for out-of-office approvers.' ],
                [ 'name' => 'Real-Time Leave Balances',       'desc' => 'Instant balance updates after each approval; employees always see accurate entitlement.' ],
                [ 'name' => 'Team Leave Calendar',            'desc' => 'Visual calendar showing team absences to aid planning and avoid conflicts.' ],
                [ 'name' => 'Carry-Forward Rules',            'desc' => 'Define expiry dates, maximum carry-forward caps, and encashment conversion rates per leave type.' ],
                [ 'name' => 'Leave Encashment',               'desc' => 'Employees request encashment of eligible leave balance; system feeds calculated payout into payroll.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How does leave accrual work in Spine?',
                    'answer'   => 'HR admins configure accrual rules per leave type — daily, monthly, or annual. The system automatically credits leaves to each employee on the configured cycle, with proration for mid-year joiners.',
                    'keywords' => [ 'accrual', 'credit', 'accrue', 'monthly', 'annual', 'proration', 'auto credit' ],
                ],
                [
                    'question' => 'Can we configure different leave policies for different grades or locations?',
                    'answer'   => 'Yes. Spine Leave Management supports policy segmentation by employee grade, department, location, gender, and employment type — all from the configuration console.',
                    'keywords' => [ 'grade', 'location', 'policy', 'segment', 'department', 'type', 'different' ],
                ],
                [
                    'question' => 'Does Spine support comp-off (compensatory off) leave?',
                    'answer'   => 'Yes. Spine supports Compensatory Off leave. When an employee works on a holiday or weekend, they can raise a comp-off request. Upon manager approval, the balance is credited and can be utilised within the configured validity window.',
                    'keywords' => [ 'comp off', 'compensatory', 'holiday work', 'weekend', 'overtime leave' ],
                ],
            ],
        ],

        // ── 7. Time & Attendance ──────────────────────────────────────────────
        'time-attendance' => [
            'id'       => 'time-attendance',
            'title'    => 'Time & Attendance Management',
            'url'      => 'https://spinetechnologies.com/time-attendance/',
            'tagline'  => 'Precision Tracking. Zero Paperwork.',
            'overview' => 'Spine Time & Attendance captures, processes, and analyses employee attendance data from any source — biometric devices, mobile GPS punch, web punch, RFID, or facial recognition — and converts raw attendance into actionable data for overtime calculation, LOP computation, shift scheduling, and payroll integration.',
            'keywords' => [ 'attendance', 'time attendance', 'biometric', 'punch', 'check in', 'check out', 'shift', 'overtime', 'late', 'early', 'absent', 'geo fencing', 'rfid', 'facial recognition', 'roster', 'schedule' ],
            'features' => [
                [ 'name' => 'Multi-Source Capture',          'desc' => 'Pull attendance from biometric terminals, RFID, facial recognition, web punch, mobile GPS, and WhatsApp-based attendance.' ],
                [ 'name' => 'Device Integration',            'desc' => 'Plug-and-play integration with leading biometric brands (ZKTeco, eSSL, Suprema, HID, Hikvision).' ],
                [ 'name' => 'Shift Management',              'desc' => 'Define unlimited shifts, rotating rosters, and flexible work hours including 24×7 and weekly-off configurations.' ],
                [ 'name' => 'Overtime Calculation',          'desc' => 'Auto-compute OT based on configurable rules (daily/weekly threshold, rate multipliers by day type).' ],
                [ 'name' => 'Geo-Fencing (Mobile Punch)',    'desc' => 'Restrict mobile punch to approved GPS coordinates — offices, client sites, or any geo-zone.' ],
                [ 'name' => 'Attendance Regularisation',     'desc' => 'Employees can regularise missed punches with reason and manager approval — full audit trail retained.' ],
                [ 'name' => 'LOP & Deduction Engine',        'desc' => 'Calculate Loss-of-Pay days and automatically pass them to the payroll module.' ],
                [ 'name' => 'Payroll Integration',           'desc' => 'One-click push of finalised attendance to payroll for salary computation.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Which biometric devices are supported by Spine?',
                    'answer'   => 'Spine integrates with all major biometric brands including ZKTeco, eSSL, Suprema, HID, Hikvision, Mantra, and Startek — via both direct LAN connection and cloud-based device management.',
                    'keywords' => [ 'biometric', 'device', 'zkteco', 'essl', 'suprema', 'brand', 'machine', 'fingerprint' ],
                ],
                [
                    'question' => 'Can employees punch attendance from their mobile phone?',
                    'answer'   => 'Yes. The Spine mobile app supports GPS-geotagged punch-in/punch-out. Geo-fencing rules restrict punching to approved locations to prevent proxy attendance.',
                    'keywords' => [ 'mobile punch', 'gps', 'geo', 'phone', 'remote', 'work from home', 'proxy' ],
                ],
            ],
        ],

        // ── 8. Expense Management ─────────────────────────────────────────────
        'expense-management' => [
            'id'       => 'expense-management',
            'title'    => 'Expense Management',
            'url'      => 'https://spinetechnologies.com/expense-management/',
            'tagline'  => 'Claims Settled. Finance Delighted.',
            'overview' => 'Spine Expense Management digitises the entire employee expense lifecycle — claim submission with receipt capture, category-based policy enforcement, multi-level approvals, and reimbursement processing into payroll — eliminating spreadsheets, paper receipts, and delayed payments.',
            'keywords' => [ 'expense', 'reimbursement', 'claim', 'travel expense', 'bill', 'receipt', 'expense report', 'expense claim', 'advance', 'per diem', 'petty cash', 'expense policy' ],
            'features' => [
                [ 'name' => 'Mobile Receipt Capture',       'desc' => 'Employees photograph bills with their phone; OCR auto-extracts amount, date, and vendor.' ],
                [ 'name' => 'Expense Category Policies',    'desc' => 'Define per-category ceilings, allowed modes (economy/business class), and grade-wise entitlements.' ],
                [ 'name' => 'Multi-Level Approval',         'desc' => 'Configurable approval chains — Manager → Finance → MD — with time-bound SLAs.' ],
                [ 'name' => 'Travel Advance Management',    'desc' => 'Request cash advances; system tracks utilisation and auto-deducts settlements at month-end.' ],
                [ 'name' => 'Per-Diem Rules',               'desc' => 'Set daily allowance rates by city tier, employee grade, or trip type.' ],
                [ 'name' => 'Foreign Currency Support',     'desc' => 'Log international expenses in any currency; live exchange rates applied for INR conversion.' ],
                [ 'name' => 'Payroll Integration',          'desc' => 'Approved reimbursements flow to payroll for inclusion in the monthly salary run.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Can employees submit expense claims from their phone?',
                    'answer'   => 'Yes. The Spine mobile app lets employees photograph receipts (OCR auto-fills details), add expense line items, and submit claims on the go — with real-time status tracking.',
                    'keywords' => [ 'mobile', 'phone', 'submit', 'claim', 'receipt', 'ocr', 'photo', 'app' ],
                ],
            ],
        ],

        // ── 9. Timesheet ──────────────────────────────────────────────────────
        'timesheet' => [
            'id'       => 'timesheet',
            'title'    => 'Timesheet & Project Time Tracking',
            'url'      => 'https://spinetechnologies.com/timesheet/',
            'tagline'  => 'Every Hour Accounted For.',
            'overview' => 'Spine Timesheet enables project-centric time logging where employees record hours against specific projects and tasks on a daily or weekly basis. Managers review and approve timesheets; finance teams use billable/non-billable hours for client invoicing and project profitability analysis.',
            'keywords' => [ 'timesheet', 'time log', 'project time', 'billable hours', 'non-billable', 'time tracking', 'project tracking', 'task hours', 'weekly timesheet', 'approval', 'client billing' ],
            'features' => [
                [ 'name' => 'Project & Task Hierarchy',     'desc' => 'Define projects, sub-projects, and tasks; assign team members with role-based access.' ],
                [ 'name' => 'Daily & Weekly Entry Modes',   'desc' => 'Employees enter time daily or fill a weekly grid — whichever workflow suits the team.' ],
                [ 'name' => 'Billable vs Non-Billable Hours','desc' => 'Tag each entry as billable, non-billable, or on-hold; generate client-ready utilisation reports.' ],
                [ 'name' => 'Manager Approval Workflow',    'desc' => 'One-click review and approval or rejection with comments per timesheet entry.' ],
                [ 'name' => 'Project Budget vs Actual',     'desc' => 'Real-time tracking of budgeted hours vs. hours logged per project and team member.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How is timesheet approval handled?',
                    'answer'   => 'Employees submit their weekly or daily timesheets from the web or mobile app. The assigned manager receives an email notification and can approve, reject, or request correction with inline comments.',
                    'keywords' => [ 'approval', 'submit', 'manager', 'review', 'reject', 'weekly', 'daily' ],
                ],
            ],
        ],

        // ── 10. Performance Management ────────────────────────────────────────
        'performance-management' => [
            'id'       => 'performance-management',
            'title'    => 'Performance Management',
            'url'      => 'https://spinetechnologies.com/performance-management/',
            'tagline'  => 'Align Goals. Accelerate Growth.',
            'overview' => 'Spine Performance Management delivers a complete performance cycle — from goal-setting (OKR/KRA/KPI) and continuous feedback through mid-year reviews, annual appraisals, 360-degree assessments, calibration, and succession planning. It replaces spreadsheet-based appraisals with a structured, data-driven process.',
            'keywords' => [ 'performance', 'appraisal', 'performance review', 'goal', 'okr', 'kra', 'kpi', '360 degree', 'feedback', 'rating', 'evaluation', 'mid year', 'annual review', 'calibration', 'succession', 'bell curve', 'pip' ],
            'features' => [
                [ 'name' => 'Goal Management (OKR/KRA/KPI)', 'desc' => 'Set Objectives & Key Results or KRA-KPI trees; cascade goals from company → department → individual.' ],
                [ 'name' => 'Continuous Feedback',           'desc' => 'Any employee can give or request real-time feedback at any point in the year.' ],
                [ 'name' => '360-Degree Assessment',         'desc' => 'Solicit structured feedback from peers, subordinates, internal clients, and managers.' ],
                [ 'name' => 'Mid-Year & Annual Appraisals',  'desc' => 'Configurable appraisal forms with weighted competency and goal ratings.' ],
                [ 'name' => 'Normalisation / Bell Curve',    'desc' => 'HR calibration tools to distribute final ratings along a forced distribution curve.' ],
                [ 'name' => 'Performance Improvement Plan',  'desc' => 'Create structured PIP for underperformers with measurable milestones and review cadence.' ],
                [ 'name' => 'Succession Planning',           'desc' => 'Identify high potentials, create talent pools, and map readiness for critical roles.' ],
            ],
            'faqs' => [
                [
                    'question' => 'Does Spine support OKR goal setting?',
                    'answer'   => 'Yes. Spine Performance Management supports Objectives & Key Results (OKR), KRA-KPI frameworks, and simple goal templates. Goals can be cascaded from company level down to individuals and tracked with progress updates throughout the year.',
                    'keywords' => [ 'okr', 'objective', 'key result', 'goal', 'kra', 'kpi', 'cascade', 'quarterly' ],
                ],
                [
                    'question' => 'How does 360-degree feedback work?',
                    'answer'   => 'During an appraisal cycle, the system automatically sends structured questionnaires to the employee\'s manager, peers, subordinates, and (optionally) clients. Responses are aggregated into an anonymised 360 report that feeds into the overall appraisal rating.',
                    'keywords' => [ '360', 'peer', 'subordinate', 'client', 'all round', 'multi-rater', 'anonymous' ],
                ],
            ],
        ],

        // ── 11. HR Help Desk ──────────────────────────────────────────────────
        'hr-help-desk' => [
            'id'       => 'hr-help-desk',
            'title'    => 'HR Help Desk',
            'url'      => 'https://spinetechnologies.com/hr-help-desk/',
            'tagline'  => 'Every Employee Query, Resolved Fast.',
            'overview' => 'Spine HR Help Desk is an internal ticketing system where employees raise HR queries — payroll, leave, policies, IT, admin — and track resolution. With category-based routing, SLA management, canned responses, and an employee-facing knowledge base, it dramatically reduces HR email volume.',
            'keywords' => [ 'help desk', 'helpdesk', 'hr ticket', 'query', 'ticket', 'support', 'hr support', 'grievance', 'complaint', 'sla', 'employee query', 'it request', 'hr request' ],
            'features' => [
                [ 'name' => 'Ticket Submission (Web & App)', 'desc' => 'Employees raise tickets from ESS portal or mobile app with category, priority, and attachments.' ],
                [ 'name' => 'Category-Based Auto-Routing',  'desc' => 'Tickets auto-assigned to the right HR team or agent based on category and location rules.' ],
                [ 'name' => 'SLA Configuration',            'desc' => 'Define First Response Time and Resolution Time SLAs per category; breach alerts escalate to supervisors.' ],
                [ 'name' => 'Escalation Matrix',            'desc' => 'Automatic escalation when SLA is breached — configurable multi-level escalation chains.' ],
                [ 'name' => 'Canned Responses',             'desc' => 'Pre-written answers for frequent queries; agents insert with one click to save time.' ],
                [ 'name' => 'CSAT Survey',                  'desc' => 'Automated satisfaction survey after ticket closure; ratings feed into agent performance reports.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How do employees raise HR tickets?',
                    'answer'   => 'Employees raise tickets from the ESS portal or Spine mobile app by selecting a category (Payroll, Leave, Policy, IT, etc.), describing their query, and attaching any supporting documents. They receive an auto-acknowledgement with a ticket ID.',
                    'keywords' => [ 'raise ticket', 'submit', 'how to', 'portal', 'mobile', 'category', 'query' ],
                ],
            ],
        ],

        // ── 12. Offboarding ───────────────────────────────────────────────────
        'offboarding' => [
            'id'       => 'offboarding',
            'title'    => 'Employee Offboarding & Exit Management',
            'url'      => 'https://spinetechnologies.com/offboarding/',
            'tagline'  => 'Smooth Exits. Protected Knowledge.',
            'overview' => 'Spine Offboarding manages every step from a resignation submission through exit interviews, multi-department clearance, knowledge transfer, final-settlement calculation, and alumni record creation — ensuring a structured, compliant, and positive exit experience.',
            'keywords' => [ 'offboarding', 'exit', 'resignation', 'separation', 'full and final', 'fnf', 'clearance', 'exit interview', 'notice period', 'knowledge transfer', 'termination', 'retirement', 'leaving', 'last working day' ],
            'features' => [
                [ 'name' => 'Resignation Submission',       'desc' => 'Employees initiate resignation from ESS; managers receive alert and can accept or negotiate notice period.' ],
                [ 'name' => 'Exit Interview Scheduling',    'desc' => 'Automated scheduling of exit interview with HR; configurable questionnaire captures departure reasons.' ],
                [ 'name' => 'Multi-Department Clearance',   'desc' => 'Digital clearance workflow assigns tasks to IT, Finance, Admin, and direct manager with due dates.' ],
                [ 'name' => 'Asset Return Tracking',        'desc' => 'Integration with Spine Assets to track and confirm return of all allocated company assets.' ],
                [ 'name' => 'Full & Final Settlement',      'desc' => 'Auto-compute FnF: unpaid salary, leave encashment, gratuity, notice-period recovery, and deductions.' ],
                [ 'name' => 'Experience Letter Generation', 'desc' => 'Auto-generate experience and relieving letters upon clearance completion.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How does Full & Final settlement calculation work?',
                    'answer'   => 'Once all clearances are completed, Spine automatically calculates the FnF: unpaid days of salary, accrued leave encashment, gratuity eligibility, any advance recovery, notice-period shortfall, and deductions — and generates a FnF summary for Finance approval.',
                    'keywords' => [ 'full and final', 'fnf', 'settlement', 'gratuity', 'leave encashment', 'notice', 'calculation' ],
                ],
            ],
        ],

        // ── 13. Visitors Management ───────────────────────────────────────────
        'visitors-management' => [
            'id'       => 'visitors-management',
            'title'    => 'Visitors Management System',
            'url'      => 'https://spinetechnologies.com/visitors-management/',
            'tagline'  => 'Professional First Impressions. Secure Premises.',
            'overview' => 'Spine Visitors Management is a digital front-desk system that replaces physical visitor registers. It manages visitor pre-registration, QR-code-based check-in, badge printing, host notifications, real-time occupancy tracking, and blacklist management — ensuring premises security and professional visitor handling.',
            'keywords' => [ 'visitor', 'visitors management', 'vms', 'guest', 'visitor registration', 'visitor tracking', 'badge', 'reception', 'pre-registration', 'blacklist', 'qr code', 'check in', 'access control', 'occupancy' ],
            'features' => [
                [ 'name' => 'Visitor Pre-Registration',     'desc' => 'Hosts pre-register expected guests; visitors receive a QR code via email/SMS for express check-in.' ],
                [ 'name' => 'Tablet/Kiosk Check-In',        'desc' => 'Visitors self-check-in at a reception tablet by scanning their QR code or entering details.' ],
                [ 'name' => 'Instant Host Notification',    'desc' => 'SMS, email, and in-app alert sent to the host employee the moment their visitor checks in.' ],
                [ 'name' => 'Badge Printing',               'desc' => 'Branded visitor badges with photo, name, host, purpose, and time — printed in seconds.' ],
                [ 'name' => 'Watchlist / Blacklist',        'desc' => 'Maintain a watchlist of restricted individuals; system alerts security on match during check-in.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How does visitor pre-registration work?',
                    'answer'   => 'An employee (host) pre-registers their expected visitor from the ESS portal or Spine app — entering the visitor\'s name, email, purpose, and expected arrival time. The visitor receives an automated email/SMS with a unique QR code for instant check-in.',
                    'keywords' => [ 'pre-registration', 'pre register', 'qr code', 'email', 'sms', 'express', 'fast', 'host' ],
                ],
            ],
        ],

        // ── 14. Spine Assets ──────────────────────────────────────────────────
        'spine-assets' => [
            'id'       => 'spine-assets',
            'title'    => 'Spine Assets — IT & Fixed Asset Management',
            'url'      => 'https://spinetechnologies.com/spine-assets/',
            'tagline'  => 'Know Every Asset. Control Every Cost.',
            'overview' => 'Spine Assets is a dedicated module for managing the full lifecycle of an organisation\'s IT equipment and fixed assets — from procurement and allocation to maintenance, depreciation, audit, and disposal. It integrates with the HR module to track employee-wise asset allocation and manages asset return during offboarding.',
            'keywords' => [ 'asset', 'assets', 'asset management', 'it asset', 'fixed asset', 'laptop', 'equipment', 'device', 'asset tracking', 'allocation', 'depreciation', 'maintenance', 'audit', 'disposal', 'qr code', 'barcode', 'inventory' ],
            'features' => [
                [ 'name' => 'Asset Catalogue & Registry',  'desc' => 'Maintain a complete inventory of all assets with make, model, serial number, purchase details, and warranty.' ],
                [ 'name' => 'QR Code / Barcode Scanning',  'desc' => 'Affix QR codes to physical assets; scan with the mobile app to instantly pull up asset details.' ],
                [ 'name' => 'Employee Asset Allocation',   'desc' => 'Assign assets to employees with digital acknowledgement; full history of who held what and when.' ],
                [ 'name' => 'Asset Transfer',              'desc' => 'Transfer assets between employees, departments, or locations with a structured approval workflow.' ],
                [ 'name' => 'Maintenance Scheduling',      'desc' => 'Schedule preventive maintenance, log service events, and track repair costs per asset.' ],
                [ 'name' => 'Depreciation Calculation',    'desc' => 'Compute depreciation using Straight-Line (SLM) or Written Down Value (WDV) methods per asset class.' ],
                [ 'name' => 'Asset Audit & Verification',  'desc' => 'Periodic physical audit workflows — field teams scan assets on-site; discrepancies auto-flagged.' ],
                [ 'name' => 'Asset Disposal / Write-Off',  'desc' => 'Record scrapping, sale, or donation with residual value calculation and disposal documentation.' ],
                [ 'name' => 'Offboarding Integration',     'desc' => 'Asset return checklist auto-generated at employee exit; clearance only granted after all items returned.' ],
            ],
            'faqs' => [
                [
                    'question' => 'How does employee asset allocation work?',
                    'answer'   => 'HR or IT admins allocate assets from the Spine Assets console by selecting the asset and the recipient employee. The employee receives an allocation notification and must digitally acknowledge receipt. The system records the handover date, condition, and any accessories included.',
                    'keywords' => [ 'allocation', 'assign', 'handover', 'employee', 'acknowledgement', 'laptop', 'device', 'equipment' ],
                ],
                [
                    'question' => 'Which depreciation methods are supported?',
                    'answer'   => 'Spine Assets supports both Straight-Line Method (SLM) and Written Down Value (WDV) depreciation. HR/Finance admins configure the method, useful life, and residual value per asset category; the system auto-computes monthly and annual depreciation.',
                    'keywords' => [ 'depreciation', 'slm', 'wdv', 'straight line', 'written down value', 'method', 'accounting' ],
                ],
                [
                    'question' => 'Can we conduct physical asset audits with Spine?',
                    'answer'   => 'Yes. Spine Assets has a built-in audit workflow: HR configures an audit period and scope, field teams use the mobile app to scan QR codes on physical assets, and the system auto-matches scanned items against the register — flagging any missing, misplaced, or unregistered assets.',
                    'keywords' => [ 'audit', 'physical verification', 'qr', 'scan', 'mobile', 'field', 'missing', 'verify' ],
                ],
                [
                    'question' => 'How are assets tracked during offboarding?',
                    'answer'   => 'When an employee initiates resignation, Spine Assets automatically generates a list of all assets currently allocated to them. The offboarding clearance checklist includes returning each item; the asset module\'s clearance task must be marked complete before FnF is processed.',
                    'keywords' => [ 'offboarding', 'return', 'exit', 'resignation', 'clearance', 'fnf', 'track' ],
                ],
            ],
        ],

        // ── 15. International HR Suite ────────────────────────────────────────
        'international-hr-suite' => [
            'id'       => 'international-hr-suite',
            'title'    => 'International HR Suite',
            'url'      => 'https://spinetechnologies.com/international-hr/',
            'tagline'  => 'Global HR. Local Compliance.',
            'overview' => 'Spine International HR Suite extends the full capabilities of Spine HR Suite to multi-country, multi-currency, and multi-lingual deployments. Purpose-built for organisations operating across the GCC/MENA region (UAE, Saudi Arabia, Qatar, Bahrain, Kuwait, Oman), South-East Asia (Malaysia, Singapore, Philippines), and beyond — it delivers country-specific statutory payroll compliance, Wage Protection System (WPS) filing, gratuity computation, expatriate management, and centralised group-level reporting, all from a single dashboard.',
            'keywords' => [
                'international hr', 'global hr', 'multi-country hr', 'international hr suite',
                'uae hr', 'gcc hr', 'gulf hr', 'middle east hr', 'mena hr',
                'saudi arabia hr', 'qatar hr', 'bahrain hr', 'kuwait hr', 'oman hr',
                'wps', 'wage protection system', 'mol', 'ministry of labour',
                'gratuity uae', 'end of service', 'eos', 'gosi', 'gpssa',
                'malaysia hr', 'singapore hr', 'philippines hr', 'epf', 'socso', 'cpf',
                'expat', 'expatriate', 'visa', 'work permit', 'iqama', 'saudization', 'nitaqat',
                'multi-currency', 'multi-language', 'arabic', 'foreign employee',
            ],
            'regions'  => [
                'GCC / MENA' => [ 'UAE', 'Saudi Arabia', 'Qatar', 'Bahrain', 'Kuwait', 'Oman' ],
                'South-East Asia' => [ 'Malaysia', 'Singapore', 'Philippines' ],
                'South Asia'      => [ 'India', 'Sri Lanka', 'Bangladesh' ],
            ],
            'features' => [
                [
                    'name' => 'UAE & GCC Payroll Compliance',
                    'desc' => 'Automated computation of gratuity/End-of-Service (EOS) benefits per UAE Labour Law & individual emirate rules; supports UAE, Saudi (GOSI), Qatar (GPSSA), Kuwait, Bahrain, and Oman.',
                ],
                [
                    'name' => 'WPS (Wage Protection System)',
                    'desc' => 'Generates Ministry of Labour–compliant WPS SIF files for UAE; supports SIF format submission to approved exchange houses and banks for salary disbursement.',
                ],
                [
                    'name' => 'Saudi Arabia Compliance',
                    'desc' => 'GOSI contribution calculation (employer + employee shares), Nitaqat/Saudization ratio tracking, IQAMA expiry alerts, and GOSI file generation.',
                ],
                [
                    'name' => 'Malaysia Compliance',
                    'desc' => 'EPF (Employee Provident Fund), SOCSO (Social Security), EIS, and PCB (monthly tax deduction) computation and statutory file generation.',
                ],
                [
                    'name' => 'Singapore Compliance',
                    'desc' => 'CPF (Central Provident Fund) contribution tables for citizens and permanent residents; NS make-up pay; IR8A annual income tax form generation.',
                ],
                [
                    'name' => 'Philippines Compliance',
                    'desc' => 'SSS, PhilHealth, and Pag-IBIG contribution computation; BIR 2316 and alphalist file generation for annual income tax returns.',
                ],
                [
                    'name' => 'Expatriate Management',
                    'desc' => 'Track visa expiry, work permit renewals, IQAMA status, and cost-of-living allowances for expatriate employees; automated alerts before expiry.',
                ],
                [
                    'name' => 'Multi-Currency Payroll',
                    'desc' => 'Run payroll in any currency (AED, SAR, QAR, MYR, SGD, PHP, USD, EUR, etc.) with live or fixed exchange rates; split-currency salary components.',
                ],
                [
                    'name' => 'Multi-Language Interface',
                    'desc' => 'Full Arabic (RTL) and English interface; employee self-service portal and payslips generated in the employee\'s preferred language.',
                ],
                [
                    'name' => 'Group-Level Consolidated Reporting',
                    'desc' => 'Headquarters view consolidates headcount, payroll cost, attrition, and compliance health across all countries in a single dashboard.',
                ],
                [
                    'name' => 'Country-Specific Leave Calendars',
                    'desc' => 'Pre-loaded public holiday calendars for GCC, SEA, and South Asia; automatic leave policy enforcement per jurisdiction.',
                ],
                [
                    'name' => 'Data Residency & GDPR Compliance',
                    'desc' => 'Flexible data hosting by region (UAE Data Centre, Singapore, India) to comply with local data residency laws including PDPA (Singapore), PDPL (Saudi Arabia), and GDPR.',
                ],
            ],
            'faqs' => [
                [
                    'question' => 'Does Spine support UAE Wage Protection System (WPS) filing?',
                    'answer'   => 'Yes. Spine International HR Suite automatically generates the Ministry of Labour–compliant WPS SIF file for UAE payroll. It supports direct submission to approved exchange houses (Al Ansari, Al Fardan, etc.) and major UAE banks. WPS submission status is tracked within the system.',
                    'keywords' => [ 'wps', 'wage protection', 'uae', 'sif', 'ministry of labour', 'exchange house', 'bank' ],
                ],
                [
                    'question' => 'How does gratuity / End-of-Service benefit calculation work in UAE?',
                    'answer'   => 'Spine calculates End-of-Service (gratuity) benefits strictly as per UAE Labour Law (Federal Decree-Law No. 33 of 2021): 21 days\' basic salary per year for the first 5 years, and 30 days per year thereafter — with automatic proration for partial years and deductions for limited-contract resignations. Provision accrues monthly in the accounts module.',
                    'keywords' => [ 'gratuity', 'end of service', 'eos', 'uae labour law', 'provision', 'accrual', 'limited contract', 'unlimited contract' ],
                ],
                [
                    'question' => 'Does Spine handle Saudi Arabia GOSI contributions?',
                    'answer'   => 'Yes. Spine calculates GOSI contributions for both Saudi nationals (employer 12% + employee 10%) and expatriates (employer 2% hazard insurance only). It generates the monthly GOSI file for upload to the GOSI online portal.',
                    'keywords' => [ 'gosi', 'saudi', 'contribution', 'national', 'expatriate', 'social insurance', 'saudi arabia' ],
                ],
                [
                    'question' => 'How does Spine manage Saudization (Nitaqat) compliance?',
                    'answer'   => 'Spine tracks the real-time Saudization ratio for each establishment registered on Qiwa. It shows current Nitaqat band (Platinum, Green, Yellow, Red), calculates how many additional Saudi nationals are needed to move to the next band, and alerts HR before IQAMA or contract renewals are impacted.',
                    'keywords' => [ 'saudization', 'nitaqat', 'saudi', 'qiwa', 'band', 'platinum', 'green', 'iqama', 'ratio' ],
                ],
                [
                    'question' => 'Can Spine run payroll in multiple currencies simultaneously?',
                    'answer'   => 'Yes. Each employee\'s payroll record can be configured with a primary currency (e.g., AED, SAR, MYR). Spine supports split-currency allowances (e.g., basic in AED, housing allowance in USD) with configurable exchange rates — live rates or fixed rates per payroll period. Payslips and reports are generated in the employee\'s currency.',
                    'keywords' => [ 'multi-currency', 'currency', 'aed', 'sar', 'exchange rate', 'usd', 'foreign', 'payslip' ],
                ],
                [
                    'question' => 'Does Spine provide an Arabic interface for employees?',
                    'answer'   => 'Yes. The Spine International HR Suite offers a full Arabic (RTL — right-to-left) interface for employees and managers. Payslips, offer letters, and HR documents can be generated bilingual (Arabic + English). The admin console and reporting dashboards are also available in Arabic.',
                    'keywords' => [ 'arabic', 'rtl', 'right to left', 'language', 'bilingual', 'arabic payslip', 'arabic interface' ],
                ],
                [
                    'question' => 'Which countries in South-East Asia does the International HR Suite cover?',
                    'answer'   => 'Spine International HR Suite covers Malaysia (EPF, SOCSO, EIS, PCB), Singapore (CPF, NS pay, IR8A), and the Philippines (SSS, PhilHealth, Pag-IBIG, BIR 2316). Additional SEA countries can be configured through the country-compliance framework.',
                    'keywords' => [ 'malaysia', 'singapore', 'philippines', 'southeast asia', 'sea', 'epf', 'cpf', 'sss', 'socso', 'philhealth', 'pag-ibig' ],
                ],
                [
                    'question' => 'How does expatriate visa and work permit tracking work?',
                    'answer'   => 'Spine maintains a dedicated document record for each expatriate — visa number and expiry, work permit/IQAMA number and expiry, medical insurance card, and labour contract details. Configurable automated alerts (90, 60, 30 days before expiry) are sent to HR and the employee\'s manager to initiate renewals on time.',
                    'keywords' => [ 'visa', 'expiry', 'work permit', 'iqama', 'renewal', 'alert', 'expatriate', 'document', 'medical insurance' ],
                ],
                [
                    'question' => 'Can group HQ consolidate HR data from all countries?',
                    'answer'   => 'Yes. The International HR Suite\'s Group Dashboard provides HQ-level consolidated reporting across all country entities — total headcount by country, global payroll cost, country-wise attrition, compliance health (WPS filing status, GOSI submission, etc.), and live analytics — all in one view without requiring multiple logins.',
                    'keywords' => [ 'consolidate', 'group', 'hq', 'headquarters', 'dashboard', 'multi-country', 'global report', 'entity' ],
                ],
            ],
        ],

    ], // end modules
]; // end return
