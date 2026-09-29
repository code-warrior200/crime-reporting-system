from pathlib import Path
from docx import Document
from docx.enum.section import WD_SECTION
from docx.enum.style import WD_STYLE_TYPE
from docx.enum.table import WD_CELL_VERTICAL_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Inches, Pt, RGBColor


OUT_DIR = Path('output/documents')
OUT_FILE = OUT_DIR / 'zaria_area_command_hq_digital_reporting_case_study.docx'


def set_cell_shading(cell, fill):
    tc_pr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement('w:shd')
    shd.set(qn('w:fill'), fill)
    tc_pr.append(shd)


def set_cell_border(cell, color='D9D9D9'):
    tc_pr = cell._tc.get_or_add_tcPr()
    borders = tc_pr.first_child_found_in('w:tcBorders')
    if borders is None:
        borders = OxmlElement('w:tcBorders')
        tc_pr.append(borders)
    for edge in ('top', 'left', 'bottom', 'right'):
        tag = 'w:' + edge
        element = borders.find(qn(tag))
        if element is None:
            element = OxmlElement(tag)
            borders.append(element)
        element.set(qn('w:val'), 'single')
        element.set(qn('w:sz'), '6')
        element.set(qn('w:color'), color)


def set_cell_margins(cell, top=110, start=110, bottom=110, end=110):
    tc = cell._tc
    tc_pr = tc.get_or_add_tcPr()
    tc_mar = tc_pr.first_child_found_in('w:tcMar')
    if tc_mar is None:
        tc_mar = OxmlElement('w:tcMar')
        tc_pr.append(tc_mar)
    for m, v in [('top', top), ('start', start), ('bottom', bottom), ('end', end)]:
        node = tc_mar.find(qn('w:' + m))
        if node is None:
            node = OxmlElement('w:' + m)
            tc_mar.append(node)
        node.set(qn('w:w'), str(v))
        node.set(qn('w:type'), 'dxa')


def add_page_field(paragraph):
    run = paragraph.add_run()
    fld_char1 = OxmlElement('w:fldChar')
    fld_char1.set(qn('w:fldCharType'), 'begin')
    instr = OxmlElement('w:instrText')
    instr.set(qn('xml:space'), 'preserve')
    instr.text = 'PAGE'
    fld_char2 = OxmlElement('w:fldChar')
    fld_char2.set(qn('w:fldCharType'), 'end')
    run._r.append(fld_char1)
    run._r.append(instr)
    run._r.append(fld_char2)


def add_para(doc, text='', style=None, bold_lead=None):
    p = doc.add_paragraph(style=style)
    if bold_lead and text.startswith(bold_lead):
        lead = p.add_run(bold_lead)
        lead.bold = True
        p.add_run(text[len(bold_lead):])
    else:
        p.add_run(text)
    return p


def add_bullets(doc, items):
    for item in items:
        doc.add_paragraph(item, style='List Bullet')


def add_table(doc, headers, rows, widths=None):
    table = doc.add_table(rows=1, cols=len(headers))
    table.style = 'Table Grid'
    table.autofit = False
    for i, header in enumerate(headers):
        cell = table.rows[0].cells[i]
        cell.text = header
        set_cell_shading(cell, '17365D')
        set_cell_border(cell)
        set_cell_margins(cell)
        cell.vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
        for run in cell.paragraphs[0].runs:
            run.font.color.rgb = RGBColor(255, 255, 255)
            run.bold = True
            run.font.size = Pt(9)
        if widths:
            cell.width = Inches(widths[i])
    for row_index, values in enumerate(rows):
        cells = table.add_row().cells
        for i, value in enumerate(values):
            cells[i].text = value
            set_cell_border(cells[i])
            set_cell_margins(cells[i])
            cells[i].vertical_alignment = WD_CELL_VERTICAL_ALIGNMENT.CENTER
            if row_index % 2 == 1:
                set_cell_shading(cells[i], 'F3F6FA')
            for paragraph in cells[i].paragraphs:
                for run in paragraph.runs:
                    run.font.size = Pt(9)
            if widths:
                cells[i].width = Inches(widths[i])
    doc.add_paragraph('')
    return table


def build_document():
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    doc = Document()
    section = doc.sections[0]
    section.top_margin = Inches(0.75)
    section.bottom_margin = Inches(0.7)
    section.left_margin = Inches(0.78)
    section.right_margin = Inches(0.78)

    normal = doc.styles['Normal']
    normal.font.name = 'Aptos'
    normal._element.rPr.rFonts.set(qn('w:ascii'), 'Aptos')
    normal._element.rPr.rFonts.set(qn('w:hAnsi'), 'Aptos')
    normal.font.size = Pt(10.5)
    normal.paragraph_format.space_after = Pt(7)
    normal.paragraph_format.line_spacing = 1.12

    for name, size in [('Title', 24), ('Heading 1', 16), ('Heading 2', 12)]:
        style = doc.styles[name]
        style.font.name = 'Aptos Display' if name == 'Title' else 'Aptos'
        style._element.rPr.rFonts.set(qn('w:ascii'), style.font.name)
        style._element.rPr.rFonts.set(qn('w:hAnsi'), style.font.name)
        style.font.size = Pt(size)
        style.font.color.rgb = RGBColor(0, 0, 0)
        style.font.bold = True
        style.paragraph_format.space_before = Pt(14 if name != 'Title' else 0)
        style.paragraph_format.space_after = Pt(6)
        style.paragraph_format.keep_with_next = True

    header = section.header.paragraphs[0]
    header.alignment = WD_ALIGN_PARAGRAPH.RIGHT
    header_run = header.add_run('Zaria Area Command HQ Digital Reporting Case Study')
    header_run.font.size = Pt(8.5)
    header_run.font.color.rgb = RGBColor(89, 104, 125)
    footer = section.footer.paragraphs[0]
    footer.alignment = WD_ALIGN_PARAGRAPH.CENTER
    footer.add_run('Academic prototype - not an official Nigeria Police Force service | Page ')
    add_page_field(footer)
    for run in footer.runs:
        run.font.size = Pt(8.5)
        run.font.color.rgb = RGBColor(89, 104, 125)

    title = doc.add_paragraph(style='Title')
    title.alignment = WD_ALIGN_PARAGRAPH.CENTER
    title.add_run('Zaria Area Command HQ Digital Reporting Case Study')
    subtitle = doc.add_paragraph()
    subtitle.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = subtitle.add_run('Application description, operating workflow, and system logic')
    r.italic = True
    r.font.size = Pt(13)
    doc.add_paragraph('')
    cover = doc.add_paragraph()
    cover.alignment = WD_ALIGN_PARAGRAPH.CENTER
    cover.add_run('Location: Zaria Area Command Headquarters, Kaduna State, Nigeria\n').bold = True
    cover.add_run('Purpose: Academic prototype for non-emergency incident reporting and internal case management\n\n')
    cover.add_run('This document describes the current application design. It does not represent an official Nigeria Police Force platform, policy, deployment, or emergency service.')
    doc.add_page_break()

    doc.add_heading('1 Purpose and scope', level=1)
    add_para(doc, 'The application is a web-based digital police-station case study tailored to Zaria Area Command Headquarters in Kaduna State, Nigeria. It models two connected services: a public-facing channel for non-emergency incident reporting and an internal officer portal for investigation, supervision, and records management.')
    add_para(doc, 'The design gives a reporter a traceable reference after submitting an incident, then gives authorized officers a structured workspace to create and manage the related case. It also models supervisory oversight of officer accounts and workload. The application is a prototype and must not be treated as an emergency-response, dispatch, evidence-custody, or official law-enforcement system without further authorization, legal review, security hardening, and operational integration.')

    doc.add_heading('2 Application overview', level=1)
    add_table(doc, ['Area', 'What it provides', 'Primary users'], [
        ['Public portal', 'Non-emergency report submission, tracking reference, and report-status lookup.', 'Residents and reporters'],
        ['Officer portal', 'Case creation, evidence notes, investigation updates, progress, report notes, and printable case reports.', 'Officers and detectives'],
        ['Supervisor controls', 'Officer account creation, suspension, reinstatement, removal, workforce metrics, and case assignment.', 'Supervisor'],
        ['Database and email service', 'Stores users, reports, cases, evidence, updates, and notifications; sends a tracking-reference email when configured.', 'Application services'],
    ], [1.25, 3.55, 1.35])
    add_para(doc, 'The browser-facing entry point redirects to the public reporting page. Public pages and officer pages live under the pages folder, while configuration, static assets, email services, database setup, and maintenance utilities are separated into dedicated folders.')

    doc.add_heading('3 User roles and access rules', level=1)
    add_table(doc, ['Role', 'Access and responsibilities', 'Restrictions'], [
        ['Public reporter', 'Submits a non-emergency incident and checks its status with a generated reference.', 'Cannot view internal case records or officer actions.'],
        ['Officer', 'Views assigned cases, logs evidence and investigation updates, updates own profile, and resolves own assigned case.', 'Cannot create cases, assign cases, close cases, or manage staff accounts.'],
        ['Detective', 'Views eligible case records, creates cases, and manages investigation information for permitted cases.', 'Cannot assign cases, close cases, or manage staff accounts.'],
        ['Supervisor', 'Views the full case queue, creates and assigns cases, closes resolved cases, manages officer accounts, and sees officer metrics.', 'Cannot edit an officer personal profile. Supervisors are excluded from staff-management actions to prevent administrative lockout.'],
    ], [1.1, 3.65, 1.4])
    add_para(doc, 'Role checks are enforced on the server before each sensitive action. Interface controls reflect those rules, but the server-side check is the authorization boundary.')

    doc.add_heading('4 Public reporting workflow', level=1)
    add_bullets(doc, [
        'A reporter opens the Zaria Area Command HQ case-study portal and completes the non-emergency incident form.',
        'The form collects the reporter name, email address, phone number, category, location, incident date, and description.',
        'The application validates required fields, email format, and the incident date. Future dates are rejected.',
        'A unique reference in the form CASE followed by an uppercase unique identifier is generated and stored with the report.',
        'The system attempts to send the reference to the supplied email address through the configured SMTP service. The report is saved even if email delivery fails.',
        'The reporter can later enter the reference on the tracker page to see the report status and permitted public details.',
    ])
    add_para(doc, 'The public page warns that emergencies must use official emergency channels or the nearest police station. The case-study portal is deliberately scoped to non-emergency reporting.')

    doc.add_heading('5 Officer case management workflow', level=1)
    add_table(doc, ['Stage', 'Logic applied', 'Result'], [
        ['Case creation', 'A supervisor or detective provides a title, narrative, optional linked report, and initial status.', 'A unique CASE identifier and a structured case record are created.'],
        ['Assignment', 'Only a supervisor may assign an active officer or detective.', 'The assignee receives an in-portal assignment notification.'],
        ['Investigation', 'The assigned officer can record evidence, progress, and investigation updates.', 'An evidence trail and chronological update history are retained.'],
        ['Resolution', 'Only the assigned officer may resolve an assigned case.', 'Case progress becomes 100 percent and the linked public report status is synchronized.'],
        ['Closure', 'Only the supervisor may close a resolved case.', 'The case and linked report become Closed with 100 percent progress.'],
    ], [1.2, 3.35, 1.6])
    add_para(doc, 'Case visibility differs by role. Supervisors see every case. An officer sees cases assigned to that officer. The dashboard only exposes actions the signed-in role is authorized to perform.')

    doc.add_heading('6 Supervisor officer management logic', level=1)
    add_para(doc, 'The supervisor dashboard contains an officer-management section for officer and detective accounts. It is intentionally limited to staff accounts and does not allow the supervisor account itself to be suspended, removed, or edited through the same controls.')
    add_bullets(doc, [
        'Add officer: creates an active officer or detective account with a unique officer ID and a password of at least eight characters.',
        'Suspend or reinstate: changes the account status between Active and Suspended. Suspended accounts cannot sign in and an already-suspended user is signed out on the next dashboard request.',
        'Remove officer: clears that officer assignment from active cases, then removes the account. Case records and historical notes remain.',
        'Personal information: an officer or detective can update only their own name, officer ID, and optional password from the My account section. A supervisor cannot edit these fields for another officer.',
        'Metrics: total, active, suspended, and active-rate figures are calculated from the entire officer and detective account list, not only the current page of results.',
    ])
    add_para(doc, 'For privacy and accountability, the officer list uses grid cards and shows ten accounts per page. Its pagination controls are independent of the case-record pagination.')

    doc.add_heading('7 Data model and status logic', level=1)
    add_table(doc, ['Record', 'Key data', 'Logic relationship'], [
        ['users', 'Officer ID, password hash, full name, role, account status.', 'Controls authentication and role-based authorization.'],
        ['reports', 'Reporter details, incident details, reference, public status, officer notes.', 'May be linked to one case; status follows relevant case actions.'],
        ['cases', 'Case code, linked report, assignment, narrative, progress, status, creator.', 'Central investigation record. Linked report ID is optional.'],
        ['case evidence', 'Case ID, evidence type, details, author, time.', 'Evidence entries are retained per case.'],
        ['case updates', 'Case ID, update text, author, time.', 'Provides the investigation timeline.'],
        ['assignment notifications', 'Case ID, recipient ID, message, read time.', 'Informs officers of supervisor assignments.'],
    ], [1.35, 2.75, 2.05])
    add_para(doc, 'Case status values are New, Under Investigation, Resolved, and Closed. New cases begin at zero percent. Under Investigation begins with progress, while Resolved and Closed force progress to 100 percent. The database startup routine creates missing tables and adds the account-status column to existing user tables when needed.')

    doc.add_heading('8 Authentication and account status', level=1)
    add_para(doc, 'Login uses the stored officer ID and password hash. A successful password check is followed by an account-status check. When the account status is Suspended, the user receives the message that the account is suspended and should contact the supervisor. No dashboard session is created.')
    add_para(doc, 'Each dashboard request rechecks the current account status. If the user has been suspended or deleted since signing in, the application clears the session and returns the user to the login page. Passwords are stored with PHP password hashing rather than as plain text.')

    doc.add_heading('9 Search pagination and reporting', level=1)
    add_para(doc, 'The Case records section supports keyword search and filters for case status and report status. It displays ten matching cases per page. Pagination preserves the active keyword, case-status filter, report-status filter, and date-filter values when a user moves between pages.')
    add_para(doc, 'The supervisor officer list also displays ten accounts per page. Officer metrics are calculated before pagination, so the displayed totals remain accurate regardless of the selected page. Printable case reports include case metadata, linked incident details, evidence entries, and investigation updates.')

    doc.add_heading('10 Technical structure', level=1)
    add_table(doc, ['Folder', 'Responsibility'], [
        ['pages', 'Public reporting, tracking, authentication, dashboard, report submission, logout, and printable case report pages.'],
        ['assets', 'Shared CSS and JavaScript used by the portal and dashboard.'],
        ['config', 'Database connection, schema initialization, and SMTP configuration templates.'],
        ['services', 'SMTP mailer implementation and email configuration loading.'],
        ['database', 'Standalone SQL setup script for the database schema.'],
        ['tools', 'Maintenance utility for inspecting database tables.'],
    ], [1.3, 4.85])
    add_para(doc, 'The application is written in PHP and uses PDO prepared statements for database queries that receive user-provided values. The expected local runtime is an Apache, PHP, and MySQL environment such as XAMPP.')

    doc.add_heading('11 Operational considerations for a real deployment', level=1)
    add_bullets(doc, [
        'Confirm authority, ownership, records-retention rules, and data-protection obligations before handling real incident data.',
        'Add production-grade CSRF protection, stronger audit logging, rate limiting, password-reset flows, backups, encryption, monitoring, and formal security testing.',
        'Use a verified official domain, approved sender identity, secure database credentials, HTTPS, and controlled access to server logs and backups.',
        'Validate local emergency contact guidance with the responsible authorities. Do not publish unverified emergency numbers or represent the prototype as an official service.',
        'Define operational workflows for case escalation, dispatch, evidence custody, complaint handling, and account approval before real use.',
    ])

    doc.add_heading('12 Conclusion', level=1)
    add_para(doc, 'The Zaria Area Command HQ Digital Reporting Case Study demonstrates a coherent end-to-end model for non-emergency incident intake, public tracking, role-based officer case management, and supervisory workforce oversight. Its present value is as a localized learning and design prototype. A live operational deployment would require institutional approval and additional security, legal, and process controls.')

    doc.save(OUT_FILE)
    print(OUT_FILE.resolve())


if __name__ == '__main__':
    build_document()
