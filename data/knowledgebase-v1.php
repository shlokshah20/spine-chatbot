<?php
/**
 * Spine Technologies — Accurate Knowledge Base (v2.0)
 *
 * Sourced directly from spinetechnologies.com, hrsuite, and individual
 * module pages. No information has been added beyond what the website states.
 *
 * Format: flat array of entries. Each entry has:
 *   content     — the text the AI searches and uses to answer questions
 *   module      — which product/module this belongs to
 *   entry_type  — Overview | Feature | FAQ | General
 *
 * Loaded by Spine_Chatbot_DB::seed_kb_from_static() on first install,
 * or via the "Import from Legacy KB" button in the admin KB page.
 *
 * @package SpineChatbot
 */

defined( 'ABSPATH' ) || exit;

return [

    // ══════════════════════════════════════════════════════════════════
    // COMPANY
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Company',
        'entry_type' => 'Overview',
        'content'    => 'Spine Technologies is an HR and Fixed Asset Management software company. Their tagline is "India\'s #1 Integrated HRMS Trusted Worldwide." They have been operating for over 20 years and serve 10,000+ client companies with 100,000+ satisfied HR users. Spine Technologies has generated over 50 million employee payslips. The company has 250+ business partners and manages assets worth over ₹50 billion.',
    ],

    [
        'module'     => 'Company',
        'entry_type' => 'General',
        'content'    => 'Spine Technologies offers two primary software products: Spine HR Suite (a comprehensive HRMS covering the full employee lifecycle) and Spine Assets (Fixed Asset Management software). They also offer an International HR Suite for global payroll and compliance needs. The company holds a CRISIL SME certification and ISO certification. They are rated 4.6 on Google, 4.3 on G2, 4.5 on Software Suggest, and 4.8 on TechImply.',
    ],

    [
        'module'     => 'Company',
        'entry_type' => 'FAQ',
        'content'    => 'Q: What products does Spine Technologies offer?\nA: Spine Technologies offers Spine HR Suite (an integrated HRMS with modules for recruitment, onboarding, payroll, leave, attendance, performance, and more), Spine Assets (fixed asset management software), and an International HR Suite for global compliance needs.',
    ],

    [
        'module'     => 'Company',
        'entry_type' => 'FAQ',
        'content'    => 'Q: How many clients does Spine Technologies have?\nA: Spine Technologies serves over 10,000 client companies with more than 100,000 satisfied HR users. They have been in business for over 20 years.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SPINE HR SUITE — OVERVIEW
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Spine HR Suite',
        'entry_type' => 'Overview',
        'content'    => 'Spine HR Suite is a comprehensive HRMS (Human Resource Management System) that helps organisations track, record, and manage complex HR tasks and data, eliminating the need for manual reports and tedious paperwork. It covers the full employee lifecycle with modules for Recruitment, Onboarding, Core HRIS & Payroll, Employee Self Service (ESS), Leave Management, Time & Attendance, Expense Management, Timesheet, Performance Management, HR Help Desk, Offboarding, and Visitors Management.',
    ],

    [
        'module'     => 'Spine HR Suite',
        'entry_type' => 'FAQ',
        'content'    => 'Q: What modules are included in Spine HR Suite?\nA: Spine HR Suite includes 12 modules: Recruitment, Onboarding, Core HRIS & Payroll, Employee Self Service (ESS), Leave Management, Time & Attendance, Expense Management, Timesheet, Performance Management System (PMS), HR Help Desk, Offboarding, and Visitors Management.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // RECRUITMENT
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Overview',
        'content'    => 'The Recruitment module in Spine HR Suite provides "Efficient Tools for Smarter Hiring." It offers advanced search and interview process automation to cut down repetitive tasks and streamline the hiring process. It handles the entire hiring process in one place — from job posting and candidate management to offer letter generation.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Vacancy Request Board: The Recruitment module includes a centralised Vacancy Request Board for tracking open positions and hiring requests across the organisation.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Hiring Dashboard: A real-time Hiring Dashboard gives insights into recruitment progress and pending tasks, so HR managers can stay on top of all open positions and candidate pipelines.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Candidate Management: The module enables HR teams to organise and track applicant data efficiently throughout the hiring pipeline.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Resume Parser: An automated Resume Parser extracts data from resumes for quick candidate profiling, reducing manual data entry.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Offer Letter Generation: The system can auto-generate customisable offer letters, reducing the time needed to extend offers to selected candidates.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'Feature',
        'content'    => 'Multi-Panel Interview Mapping: Interviews can be assigned based on job descriptions with structured feedback. The module supports scheduling interviews and tracking candidate progress through multiple interview panels.',
    ],

    [
        'module'     => 'Recruitment',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can Spine HR handle job postings and candidate tracking?\nA: Yes. The Recruitment module lets you post jobs, manage applications seamlessly, schedule interviews, and track candidate progress — all in one place. It also integrates with third-party job boards and popular recruitment platforms.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // ONBOARDING
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Overview',
        'content'    => 'The Onboarding module in Spine HR Suite is designed to provide new hires with a warm welcome and smooth transition into their new roles. The tagline is "Seamless Starts Make Stronger Teams." It automates documentation, compliance, and onboarding workflows, enabling a fully paperless onboarding experience.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Digitised Onboarding Process: The module automates documentation, compliance, and workflows, making the entire onboarding process paperless. Employees upload documents directly through a simple portal for quick verification and approval.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Pre-Onboard & Pre-Joined Status Tracking: HR can monitor candidates from the point of offer acceptance right through to their joining date, ensuring nothing falls through the gaps.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Employee Joining Kit: A structured checklist (joining kit) is provided to cover all onboarding tasks and ensure a consistent experience for every new hire.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Task Assignment & Role-Based Access: Onboarding responsibilities can be assigned across teams with role-based access, so each department completes its part of the onboarding process.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Automated Notifications & Reminders: The system keeps both candidates and HR updated with automated notifications and reminders throughout the onboarding process.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'Pre-Joining Access: Employees can access onboarding materials, training content, and company information before their official start date, enabling early engagement and faster ramp-up.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'Feature',
        'content'    => 'HR Dashboard for New Joiners: A dedicated HR dashboard provides real-time insights into the onboarding progress of all new joiners.',
    ],

    [
        'module'     => 'Onboarding',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Is Spine HR onboarding paperless?\nA: Yes. The Onboarding module is fully digital. New employees upload documents through a self-service portal, HR verifies them online, and automated checklists guide both parties through the process without any physical paperwork.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // CORE HRIS & PAYROLL
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Overview',
        'content'    => 'The Core HRIS & Payroll module is described as "Seamless Payroll and All Your HR Data in One Place." It is a centralised platform that consolidates employee records, payroll processing, compliance, tax management, and HR functions. It supports automated salary computation, customisable payslips, and backdated payment handling.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Complete Employee Data Bank: A centralised database stores and manages detailed employee records, maintained in real time and accessible to authorised HR staff.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'High-Speed Payroll Processing: Salary calculations and disbursements are automated to reduce manual errors and ensure smooth payroll runs. The system supports customisable payslips with relevant deductions and earnings.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Backdated Payment Handling: The system can process arrears and salary adjustments efficiently without delays, supporting backdated payment calculations.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Loan & Advances Setup: Employee loans and salary advances can be configured with repayment tracking directly within the payroll module.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Automated Document Generation: The module auto-generates HR documents such as offer letters and appraisal letters using pre-built templates.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Advanced Roles & Permissions: User access is controlled through role-based security settings. The system supports maker-checker and parallel approval workflows for compliance.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Custom Report Writer & Dashlets: HR analytics and insights can be generated through a custom report writer with dashlets for quick data visibility.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'Feature',
        'content'    => 'Mobile Accessibility: The Spine HR mobile app gives employees and HR managers access to HR data on the go. The system also supports third-party integration.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine HR handle payroll processing?\nA: Yes. The Core HRIS & Payroll module automates salary calculations, generates customisable payslips, handles deductions and earnings, processes arrears, and manages tax compliance — all from a single centralised platform.',
    ],

    [
        'module'     => 'Core HRIS & Payroll',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can I manage employee loans and advances in Spine HR?\nA: Yes. The Core HRIS & Payroll module includes a Loan & Advances Setup feature that lets you configure employee loans with repayment tracking.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // EMPLOYEE SELF SERVICE (ESS)
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Overview',
        'content'    => 'The Employee Self Service (ESS) module gives employees direct access to their own HR information through a personalised dashboard. Employees can manage HR functions independently, reducing the workload on HR teams. It is accessible via both web and mobile app.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'HR Dashboard & Profile Updates: Employees can view and update their personal details through a self-service dashboard. Updates reflect automatically in the HR system.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'Payslip, CTC & Tax Projection Access: Employees can view their salary breakdowns, CTC structure, and tax forecasts directly from their self-service portal.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'Investment & TDS Declarations: The ESS module allows employees to submit investment declarations and manage TDS under both old and new tax regimes.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'Company Assets & Organogram: Employees can track company assets assigned to them and view the organisational hierarchy through the ESS portal.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'Single Sign-On & Two-Factor Authentication: The ESS module supports Single Sign-On (SSO) and Two-Factor Authentication (2FA) for secure and easy access.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'Feature',
        'content'    => 'Mobile App Accessibility: Employees can access HR functions anytime, anywhere via the Spine HR mobile app, with a mobile-friendly interface and role-based permission controls.',
    ],

    [
        'module'     => 'Employee Self Service',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can employees access their payslips on their own?\nA: Yes. Through the Employee Self Service (ESS) module, employees can view payslips, CTC breakdowns, tax projections, and submit leave requests — all without needing to contact HR.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // LEAVE MANAGEMENT
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Overview',
        'content'    => 'The Leave Management module in Spine HR Suite enables "Simpler Requests, Smarter Planning." It provides streamlined leave application and approval workflows, leave balance tracking, and utilisation analysis. The module supports multiple leave types including casual leave, sick leave, earned leave, and comp-offs.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Multiple Leave Types & Policies: HR administrators can configure different leave types (vacation, sick, casual, comp-offs, etc.) with accrual rules, carryover limits, and leave categories.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Easy Leave Application & Approval: Employees submit leave requests through the self-service portal. The system supports multi-level approval workflows so managers can approve or reject requests online.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Leave Planner & Utilisation Analysis: A visual leave planner lets HR and managers see leave balances and trends across the team, helping with workforce planning.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Email & Mobile-Based Approvals: Managers can approve leave requests on the go via email or the mobile app, without needing to log in to the desktop system.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Revoke & Substitute Leave Approvals: The module supports controlled cancellation of approved leaves and allows alternative approvers to be designated when the primary approver is unavailable.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'Feature',
        'content'    => 'Historic Data Management: Past leave records and usage trends are maintained for reporting and audit purposes.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'FAQ',
        'content'    => 'Q: What types of leave can be configured in Spine HR?\nA: The Leave Management module supports multiple leave types including vacation, sick leave, casual leave, personal leave, and comp-offs. Leave types, accrual rules, and carryover limits are all configurable by HR administrators.',
    ],

    [
        'module'     => 'Leave Management',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can managers approve leave requests from their mobile phone?\nA: Yes. The Leave Management module supports email-based and mobile-based approvals, so managers can approve or reject leave requests on the go without logging into the desktop system.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // TIME & ATTENDANCE
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Overview',
        'content'    => 'The Time & Attendance module in Spine HR Suite is designed to "Track Time to Boost Productivity." It automates work hour calculations, tracks overtime, and integrates attendance data directly with payroll. It supports multiple attendance tracking methods including biometric devices, web/mobile login, and geo-fencing for remote teams.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'Payroll-Integrated Attendance Tracking: Attendance data syncs directly with payroll, ensuring accurate salary calculations based on actual working hours without manual intervention.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'Biometric Device Integration: The module integrates seamlessly with biometric attendance devices and access control systems to accurately record employee attendance.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'Geo-Fencing & Geo-Tagging: Remote and field employees can mark attendance using the mobile app with geo-fencing and geo-tagging, ensuring attendance is recorded from the correct location.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'Shift Management & Roster Planning: HR teams can configure different shifts, shift policies, and holiday calendars. Roster planning helps manage employee schedules across departments.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'OT Hours & Short Leave Approval: The module includes workflows for managing and approving overtime hours and short-leave requests.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'Feature',
        'content'    => 'Graphical Attendance Analysis: Real-time attendance insights and trends are available through graphical dashboards, helping managers quickly identify attendance patterns or anomalies.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine HR support biometric attendance devices?\nA: Yes. The Time & Attendance module integrates with biometric devices and access control systems. It also supports web login, mobile app check-in, and geo-fencing for remote teams.',
    ],

    [
        'module'     => 'Time & Attendance',
        'entry_type' => 'FAQ',
        'content'    => 'Q: How does attendance link to payroll in Spine HR?\nA: The Time & Attendance module is directly integrated with payroll. Attendance records, including overtime and short leaves, sync automatically with the payroll module to ensure accurate salary calculations.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // EXPENSE MANAGEMENT
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Overview',
        'content'    => 'The Expense Management module in Spine HR Suite allows organisations to handle salary and business expense reimbursements through a digital platform. It supports multi-currency expense claims, travel reimbursements, per-kilometre expense tracking, and integration with ERP/financial systems.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Salary & Business Expense Reimbursements: Employees can submit and process expense reimbursement claims digitally, uploading receipts through the self-service portal.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Multi-Currency & Per Kilometre Expense: The module supports multi-currency expense claims and per-kilometre travel reimbursements for employees working across locations.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Limit-Based Approval & Compliance: Administrators can define budget thresholds and pre-set rules to ensure expense claims stay within approved limits.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Mobile-Based Submission & Tracking: Employees can submit expense claims and track their status (approved, pending, declined) on the go from the mobile app.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Expense Ledger & ERP Integration: Expense data syncs with financial/ERP systems to ensure accurate financial reporting and record-keeping.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'Feature',
        'content'    => 'Advanced Reports & Dashlets: Detailed expense analytics and approval insights are available through reports and dashboard dashlets.',
    ],

    [
        'module'     => 'Expense Management',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can employees submit expense claims from their phone?\nA: Yes. The Expense Management module has a mobile-based submission feature. Employees can upload receipts and submit claims from anywhere, and track the approval status in real time.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // TIMESHEET
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Overview',
        'content'    => 'The Timesheet module in Spine HR Suite helps organisations "Efficiently Track Every Hour." It enables employees to log work hours, track project allocation, and manage tasks. Timesheets integrate with leave records and the attendance module for accurate tracking.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Feature',
        'content'    => 'Daily & Weekly Logging: Employees can log work hours daily or weekly with categorised tasks, either manually or through automated time entries.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Feature',
        'content'    => 'Project Master: A Project Master feature tracks real-time project updates with a calendar-based timesheet, enabling project cost tracking and resource allocation monitoring.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Feature',
        'content'    => 'Leave Integration: The Timesheet module automatically reflects approved leaves in the timesheet, ensuring accurate hour tracking without manual adjustments.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Feature',
        'content'    => 'Manager Approvals: Managers receive automated notifications and can review and approve timesheets through a streamlined workflow.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'Feature',
        'content'    => 'Productivity Insights: HR can generate reports to analyse productivity, overtime, project costs, and work patterns. Billable and non-billable hours can be differentiated.',
    ],

    [
        'module'     => 'Timesheet',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine HR support project-based timesheet tracking?\nA: Yes. The Timesheet module includes a Project Master feature for project-based time tracking. Employees log hours against specific projects and tasks, and managers can review project costs and resource utilisation.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // PERFORMANCE MANAGEMENT SYSTEM (PMS)
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Overview',
        'content'    => 'The Performance Management System (PMS) in Spine HR Suite is designed to "Align, Track & Elevate Employee Performance." It supports KRA/KPI-based appraisals, 360-degree feedback, bell curve normalisation, succession planning, and continuous mid-term and annual performance reviews.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'KRAs & KPI-Based Appraisals: Measurable goals (KRAs and KPIs) are set for employees and tracked throughout the appraisal cycle. Employees create and update personal goals while managers track alignment with organisational objectives.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'Multi-Level Appraisal Reviews: The system supports multi-level reviews including 360-degree feedback, capturing inputs from managers, peers, and employees themselves.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'Bell Curve Normalisation: A bell curve normalisation feature ensures fair and structured performance evaluations across the organisation.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'Succession Planning & Promotions: The PMS identifies high performers for career growth and succession planning, supporting promotion and career development decisions.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'Competency & Skill Assessments: Performance can be measured against defined competencies and skill sets, not just numerical targets.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'Feature',
        'content'    => 'Mid-Term & Annual Performance Reviews: The system supports continuous appraisal cycles with both mid-term check-ins and annual reviews, keeping performance management ongoing rather than a once-a-year event.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine HR support 360-degree performance reviews?\nA: Yes. The Performance Management module supports multi-level appraisal reviews including 360-degree feedback from managers, peers, and employees themselves.',
    ],

    [
        'module'     => 'Performance Management',
        'entry_type' => 'FAQ',
        'content'    => 'Q: How does goal setting work in Spine HR PMS?\nA: Employees set KRA and KPI-based goals, which managers can track for alignment with team and organisational objectives. Both mid-term and annual review cycles are supported.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // HR HELP DESK
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Overview',
        'content'    => 'The HR Help Desk module in Spine HR Suite is "Your HR Assistance Just a Click Away." It provides a centralised communication hub for managing employee requests with an integrated ticketing system, automated workflows, multi-level approvals, and real-time request tracking.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Integrated Ticketing System: Employee requests are automatically assigned to the right department for resolution through an integrated ticketing system.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Multi-Level Approvals & Escalations: Request workflows are automated with escalation handling, ensuring requests are not left unresolved past defined SLAs.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Custom Request Categories: HR administrators can configure various request types and approval paths to match organisational requirements.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Real-Time Request Tracking: Employees can monitor the status of their pending and resolved requests in real time, with transparency into where their request sits in the workflow.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Email & SMS Notifications: Automated email and SMS notifications keep employees updated on the progress of their requests.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'Feature',
        'content'    => 'Self-Service Request Submissions: Employees can initiate HR requests online without walking up to the HR desk. Supported request types include IT support, HR inquiries, payroll concerns, and administrative services.',
    ],

    [
        'module'     => 'HR Help Desk',
        'entry_type' => 'FAQ',
        'content'    => 'Q: What kind of requests can employees raise through the HR Help Desk?\nA: Employees can raise IT support requests, HR inquiries, payroll concerns, administrative service requests, and provide feedback on resolutions — all through a self-service portal with real-time status tracking.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // OFFBOARDING
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Overview',
        'content'    => 'The Offboarding module in Spine HR Suite ensures "Smooth Exits for Lasting Impressions." It streamlines employee departures through automated exit workflows, department-wise clearance tracking, final settlement processing, and structured exit interviews.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Feature',
        'content'    => 'Exit Request & Approval: Resignation requests are managed through a structured approval workflow, ensuring transparency and compliance throughout the exit process.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Feature',
        'content'    => 'Clearance Sign-Off: The module automates clearance processes across departments, tracking pending approvals from IT, finance, and other teams before the employee\'s last working day.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Feature',
        'content'    => 'Final Settlement Processing: Full and final settlements are calculated and processed, including pending dues, reimbursements, leave encashment, and gratuity calculations.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Feature',
        'content'    => 'Exit Interview & Feedback: Structured exit interviews are conducted within the system, capturing employee feedback and generating insights for retention strategies.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'Feature',
        'content'    => 'Document & Asset Retrieval: The module tracks and manages the retrieval of company-issued assets and documentation before the employee\'s last working day.',
    ],

    [
        'module'     => 'Offboarding',
        'entry_type' => 'FAQ',
        'content'    => 'Q: How does Spine HR handle full and final settlement?\nA: The Offboarding module automatically calculates full and final settlements including pending salary, leave encashment, reimbursements, and gratuity. It also tracks department-wise clearances before processing the final payout.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // VISITORS MANAGEMENT
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Overview',
        'content'    => 'The Visitors Management module in Spine HR Suite ensures secure entry through OTP-based authentication. It manages visitor pre-registration, check-in, visitor pass generation, and exit tracking — providing a complete audit trail of all visitors to the premises.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'Visitor Request & Host Initiation: Hosts can pre-register visitors, submit visit requests, and manage approvals before the visitor arrives, reducing reception delays.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'OTP-Based Visitor Verification: Visitors are authenticated via mobile OTP, eliminating proxy entries and ensuring only authorised individuals enter the premises.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'VIP & Blacklist Controls: The system includes smart identification of VIP visitors for priority handling and blacklisted individuals for restricted entry.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'Visitor Pass Printing & Check-In: Digital or printed visitor passes can be generated instantly for smooth entry. Real-time arrival notifications are sent to hosts.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'Arrival Alerts, Exit Tracking & Sign-Off: Hosts receive real-time arrival notifications. Security controls the final exit with mandatory host sign-off, ensuring complete visitor movement tracking.',
    ],

    [
        'module'     => 'Visitors Management',
        'entry_type' => 'Feature',
        'content'    => 'Advanced Roles & Permission Control: Access levels are defined for security teams, hosts, and administrators with policy-based permissions through a centralised dashboard.',
    ],

    // ══════════════════════════════════════════════════════════════════
    // SPINE ASSETS
    // ══════════════════════════════════════════════════════════════════

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Overview',
        'content'    => 'Spine Assets is Spine Technologies\' Fixed Asset Management Software with the tagline "Chaos to Clarity – Simplifying Fixed Assets Lifecycle." It helps organisations manage accounting and depreciation, track fixed assets, maintain asset registers, and stay on top of warranties, AMCs, and insurance renewals. Spine Assets has helped manage over 500 million assets worth over ₹50 billion.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Fixed Asset Tracking: The software keeps tabs on all company assets, ensuring nothing goes unnoticed. Assets can be tracked across multiple companies, locations, and branches from a single platform.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Depreciation Management: Spine Assets automatically calculates and tracks depreciation, making it easier to stay on top of asset value changes over time.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Fixed Asset Schedule & Register: A detailed asset register is maintained, including purchase dates, current status, and complete asset history.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Barcode Integration: Assets are tracked using barcode scanning, saving time and reducing manual errors in asset identification and management.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Physical Verification: The system supports regular physical verification of assets, matching real-world assets against system records to prevent discrepancies.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Tracking Warranties, AMCs & Dues: Spine Assets tracks important renewal dates for insurance policies, Annual Maintenance Contracts (AMCs), and warranties, with email reminders and dashboard notifications to prevent lapses.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Document Attachment: Relevant documents such as invoices, contracts, and certificates can be attached directly to asset records for easy reference.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Access & Approval Levels with Audit Trail: User access is controlled with defined approval levels. A clear audit trail is maintained for security and accountability.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Report Writer Tool: Custom reports can be created to analyse asset data, helping decision-makers stay informed about the state of their asset portfolio.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Integration with Accounting Software: Asset data syncs with accounting/ERP software to ensure smooth financial management and reporting.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'Feature',
        'content'    => 'Interactive Dashboard: Spine Assets includes a graphical, interactive dashboard with dynamic representations of asset data, making it easy to get a quick overview of the asset portfolio.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'FAQ',
        'content'    => 'Q: What is Spine Assets?\nA: Spine Assets is Spine Technologies\' Fixed Asset Management Software. It helps organisations track fixed assets, automate depreciation calculations, manage warranties and AMCs, conduct physical verifications, and integrate asset data with accounting systems.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine Assets support barcode scanning?\nA: Yes. Spine Assets includes barcode integration for tracking and managing assets, reducing manual effort and errors in asset identification.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can Spine Assets track warranty and AMC renewal dates?\nA: Yes. The software tracks warranty expiry dates, AMC renewals, and insurance due dates, and sends timely email reminders and dashboard notifications before they lapse.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Does Spine Assets integrate with accounting software?\nA: Yes. Spine Assets integrates with accounting and ERP systems to sync asset and depreciation data for financial reporting.',
    ],

    [
        'module'     => 'Spine Assets',
        'entry_type' => 'FAQ',
        'content'    => 'Q: Can I manage assets across multiple company locations?\nA: Yes. Spine Assets supports multiple companies, locations, and branches from a single platform.',
    ],

];
