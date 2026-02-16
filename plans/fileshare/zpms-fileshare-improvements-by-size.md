# ZPMS File Sharing Module — Implementation Guide by Practice Size

Dual-compliance approach: EU legislation (GDPR, EHDS, eIDAS, NIS2, EAA) **and** HIPAA where indicated.
Features are split by practice size and ranked into three implementation tiers.

---

## How to Read This Document

**Practice sizes:**
- **Small Practice (SP):** 1 doctor, 1 secretary/assistant, <10 employees. May qualify for EAA microenterprise exemption (<10 employees, <€2M turnover). Still fully subject to GDPR, HIPAA (if handling US patients), and NIS2 (if classified as essential entity by Member State).
- **Larger Clinic (LC):** Multiple physicians, nursing staff, administrative team, potentially multiple locations. No exemptions. Full NIS2 essential entity obligations.

**Implementation tiers:**
- **Tier 1 — MUST (Regulatory/Legal):** Non-negotiable. Failure to implement carries fines, sanctions, or criminal liability. Implement before go-live.
- **Tier 2 — SHOULD (High-Impact Operations):** Major daily workflow improvements. Strong regulatory backing but enforcement is less immediate. Implement within 6 months of go-live.
- **Tier 3 — COULD (Competitive Advantage):** Differentiating features that elevate ZPMS above basic compliance. Implement within 12-18 months.

---

# PART A — SMALL PRACTICE (1 Doctor, 1 Secretary)

A small practice has limited IT resources, no dedicated compliance officer, and needs features that are simple, automated, and low-maintenance. The system must handle compliance without requiring manual intervention wherever possible.

---

## SP Tier 1 — MUST (Regulatory/Legal)

These features are legally mandatory. A small practice operating without them risks fines (GDPR: up to €20M or 4% of turnover; NIS2: up to €10M or 2% of turnover) and is exposed to breach liability.

### SP-1.1 Encryption at Rest

**Regulation:** GDPR Art. 32 + Art. 34(3)(a) breach safe harbor | NIS2 Art. 21 | HIPAA §164.312(a)(2)(iv)

All three frameworks converge: encrypting stored health data is the single most effective compliance measure because it neutralizes breach notification obligations if keys remain secure.

**Small practice scope:**
- Encrypt all files in the storage directory using PHP Sodium (`sodium_crypto_secretbox()`)
- Single encryption key stored in `/etc/zpms/encryption.key` with `chmod 600`, owned by the web server user
- Add `file_hash VARCHAR(64)` and `encryption_nonce VARBINARY(24)` columns to `fileshare_files`
- Encrypt MySQL backups with `openssl enc -aes-256-cbc`
- No key rotation needed initially — add in Tier 2

**Config (`config/fileshare/encryption.yml`):**
```yaml
enabled: true
algorithm: sodium_secretbox  # XSalsa20-Poly1305
key_file: /etc/zpms/encryption.key
encrypt_on_upload: true
```

### SP-1.2 Automatic Session Logoff

**Regulation:** HIPAA §164.312(a)(2)(iii) — required addressable safeguard | NIS2 Art. 21 — access control policies | GDPR Art. 32 — appropriate technical measures

HIPAA explicitly requires "electronic procedures that terminate an electronic session after a predetermined time of inactivity." NIS2 and GDPR require access control measures proportionate to the data sensitivity.

**Implementation:**
- Server-side: check `$_SESSION['last_activity']` timestamp on every request
- If `time() - $_SESSION['last_activity'] > $timeout`: destroy session, redirect to login
- Client-side: JavaScript countdown timer synchronized with server timeout
- Warning modal 2 minutes before expiry: "Your session expires in 2:00. Click to continue working."
- User action (click, keypress, mouse move) sends AJAX keep-alive to reset server timestamp
- On timeout: clear any cached form data, destroy session, show "Session expired for security" message
- Log session timeouts in audit trail (user, time, duration of session)

**Config (`config/security.yml`):**
```yaml
session:
  timeout_minutes: 15          # HIPAA standard for clinical systems
  warning_before_seconds: 120  # Show warning 2 min before
  extend_on_activity: true     # Reset timer on user interaction
  log_timeouts: true
```

**Small practice note:** For a 2-person office where the doctor frequently steps away from the screen, 15 minutes is the recommended default. The secretary's station in the reception area should use the same timeout — unattended screens displaying patient data are a common HIPAA/GDPR violation vector.

### SP-1.3 Document Integrity Verification

**Regulation:** HIPAA §164.312(c)(1) — "implement electronic mechanisms to corroborate that ePHI has not been altered or destroyed in an unauthorized manner" | HIPAA §164.312(c)(2) — "implement electronic mechanisms to authenticate ePHI" | GDPR Art. 5(1)(f) — integrity principle | NIS2 Art. 21(2)(h) — data integrity verification

This is one of the few HIPAA requirements that is **required** (not addressable). The system MUST verify that stored health data has not been tampered with.

**Implementation:**
- On upload: compute `$hash = hash_file('sha256', $filePath)` and store in `fileshare_files.file_hash`
- On every download/preview: recompute hash and compare
- If mismatch: **block access immediately**, log integrity violation with file ID, expected hash, actual hash, requesting user, timestamp
- Alert administrator via notification system
- Weekly cron job: batch-verify all file hashes, report any mismatches
- Integrity verification also covers file versions — each version has its own hash

**Database addition:**
```sql
ALTER TABLE fileshare_files ADD COLUMN file_hash VARCHAR(64) NOT NULL DEFAULT '';
ALTER TABLE fileshare_files ADD COLUMN hash_algorithm VARCHAR(10) NOT NULL DEFAULT 'sha256';
ALTER TABLE fileshare_file_versions ADD COLUMN file_hash VARCHAR(64) NOT NULL DEFAULT '';
```

**Small practice note:** This runs automatically with zero user interaction. The only visible effect is a brief integrity check on download (< 50ms for files under 100MB) and the weekly cron report.

### SP-1.4 Enhanced Audit Logging

**Regulation:** HIPAA §164.312(b) — "implement hardware, software, and/or procedural mechanisms that record and examine activity in information systems that contain or use ePHI" | HIPAA §164.308(a)(1)(ii)(D) — "implement procedures to regularly review records of information system activity, such as audit logs, access reports, and security incident tracking reports" | GDPR Art. 30 — records of processing activities | NIS2 Art. 21 — security logging | EHDS Art. 9 — patients' right to know who accessed their data

HIPAA requires three categories of audit controls:
1. **User-level:** login/logout, failed attempts, password changes, permission changes
2. **System-level:** device identification (IP address), access origin (internal/external)
3. **Application-level:** files opened, read, created, edited, deleted, printed, downloaded, exported, shared

**Implementation:**

**Database (`fileshare_audit_log`):**
```sql
CREATE TABLE fileshare_audit_log (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  prev_hash VARCHAR(64) NOT NULL DEFAULT '',    -- hash chain
  event_hash VARCHAR(64) NOT NULL DEFAULT '',   -- SHA-256 of this entry
  timestamp DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  user_id INT UNSIGNED NOT NULL,
  action ENUM(
    'login','logout','login_failed','session_timeout',
    'file_upload','file_download','file_preview','file_print',
    'file_rename','file_move','file_copy','file_delete','file_restore',
    'file_share_create','file_share_access','file_share_revoke',
    'file_version_create','file_version_restore',
    'file_comment','file_lock','file_unlock',
    'patient_link_create','patient_link_remove',
    'permission_change','config_change',
    'integrity_violation','emergency_access',
    'export','bulk_operation'
  ) NOT NULL,
  resource_type ENUM('file','folder','share','user','system') NOT NULL,
  resource_id INT UNSIGNED DEFAULT NULL,
  patient_id INT UNSIGNED DEFAULT NULL,       -- for EHDS Art. 9
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) DEFAULT NULL,
  details JSON DEFAULT NULL,                   -- action-specific metadata
  INDEX idx_timestamp (timestamp),
  INDEX idx_user (user_id),
  INDEX idx_action (action),
  INDEX idx_patient (patient_id),
  INDEX idx_resource (resource_type, resource_id)
) ENGINE=InnoDB;
```

**Tamper-proof hash chain:**
```php
// On each new log entry:
$lastEntry = $db->query("SELECT event_hash FROM fileshare_audit_log ORDER BY id DESC LIMIT 1");
$prevHash = $lastEntry ? $lastEntry['event_hash'] : str_repeat('0', 64);
$entryData = json_encode([$timestamp, $userId, $action, $resourceId, $details]);
$eventHash = hash('sha256', $prevHash . $entryData);
```

**Separate database user for audit table:**
```sql
CREATE USER 'zpms_audit'@'localhost' IDENTIFIED BY '...';
GRANT INSERT, SELECT ON zpms.fileshare_audit_log TO 'zpms_audit'@'localhost';
-- NO UPDATE, NO DELETE — tamper-proof
```

**Retention:** Minimum **6 years** (HIPAA requires 6 years for security-related documentation; GDPR requires "as long as necessary" but most EU Member States align with 6-10 years for healthcare audit trails).

**Small practice note:** Logging happens automatically in the background. The doctor and secretary see a simple "Activity" panel in the admin dashboard showing recent events. The weekly cron generates an audit summary email. For HIPAA compliance, someone (typically the doctor in a small practice) must review audit logs periodically — the summary email satisfies this requirement with minimal effort.

### SP-1.5 GDPR Data Subject Rights (Simplified)

**Regulation:** GDPR Art. 15-22 | EHDS Art. 6, 9, 10

Even a 2-person practice must handle patient data requests. The simplified version automates the most common requests.

**Implementation — Small practice needs only 3 workflows:**

**1. Right of Access (Art. 15) — "Give me my records"**
- Patient requests all data → system compiles all files linked to that patient via `fileshare_patient_links`
- Auto-generates ZIP with: all linked files, JSON manifest (file list with dates, types, who accessed), cover letter PDF
- Response deadline tracker: 30-day countdown from request date
- Single-click "Prepare Access Package" button in patient record

**2. Right to Erasure (Art. 17) — "Delete my data"**
- System automatically checks retention obligations per document type
- Medical records: **always reject** with explanation citing national retention law (e.g., Greece: 10 years minimum)
- Non-clinical data (marketing consent, expired non-medical docs): auto-approve
- Logs the request and decision regardless of outcome

**3. EHDS Opt-Out (Art. 10) — "Don't use my data for research"**
- Toggle in patient record: `ehds_secondary_use_opt_out BOOLEAN`
- Flag propagates to all patient-linked files
- No impact on primary care access — only affects secondary use requests

**Config (`config/gdpr.yml`):**
```yaml
retention:
  medical_records_years: 10      # Greece default; adjust per country
  billing_records_years: 10
  consent_records_years: 6
  non_clinical_years: 3
  audit_logs_years: 6
response_deadline_days: 30
auto_reject_medical_erasure: true
```

### SP-1.6 72-Hour Breach Notification Support

**Regulation:** GDPR Art. 33 (72h to DPA) + Art. 34 (notify data subjects if high risk) | NIS2 Art. 23 (24h early warning + 72h full report) | HIPAA §164.408 (60 days to individuals, immediate to HHS if >500 affected)

**Small practice implementation (minimal, workflow-driven):**
- Breach detection: integrity verification failures, multiple failed login attempts, unauthorized access patterns → auto-alert to doctor/practice owner
- Pre-built breach assessment form: what happened, when discovered, what data affected, how many patients
- One-click "Identify Affected Patients": given a file or time range, list all patients whose data was potentially exposed
- Pre-filled DPA notification template (PDF) with practice details, incident summary, affected data categories
- Checklist: DPA notification → patient notification → remediation → follow-up report
- Deadline tracker: NIS2 24h early warning | GDPR/NIS2 72h full report | NIS2 1-month final report

### SP-1.7 Virus Scanning

**Regulation:** NIS2 Art. 21 — malware prevention

- ClamAV integration via Unix socket to `clamd`
- Scan every upload (adds ~1-2s)
- Quarantine infected files outside web root
- Daily `freshclam` update via cron
- Alert doctor by email on detection

---

## SP Tier 2 — SHOULD (High-Impact Operations)

These dramatically improve daily workflow for a 2-person office. Strong regulatory backing but less immediate enforcement risk than Tier 1.

### SP-2.1 Electronic Signatures for Consent Forms

**Regulation:** eIDAS (EU 910/2014) — SES sufficient for most medical consents | GDPR Art. 7 — demonstrable proof of consent

A small practice's biggest signature need: patient consent forms signed on a tablet or screen during check-in instead of paper.

**Implementation:**
- `signature_pad.js` (MIT, vanilla JS) captures drawn signature on HTML5 Canvas
- Store: base64 PNG of signature, SHA-256 hash of signed document at time of signing, signer IP, timestamp, user agent
- Generate signed PDF via TCPDF: consent text + signature image + verification metadata
- Auto-link signed PDF to patient record
- Templates stored in `modules/fileshare/templates/consents/`: general treatment consent, GDPR privacy notice, telehealth consent, procedure-specific consents

**Small practice workflow:** Secretary pulls up consent form on tablet → patient reads and signs → PDF generated and filed automatically → checklist item marked complete.

### SP-2.2 Document Checklists (Simplified)

**Implementation:**
- 2-3 checklist templates: new patient, follow-up visit, procedure visit
- Auto-creates checklist when appointment is scheduled
- Items: ID, insurance card, signed treatment consent, signed GDPR notice, medical history form
- Dashboard widget: "Upcoming appointments with incomplete documents"
- Visual: green check / red X per item

### SP-2.3 Document Inbox (Simplified)

A small practice receives documents from: fax (if used), email attachments, scanned paper, patient uploads.

**Implementation:**
- Single inbox queue (no complex routing rules)
- Each item: source, date received, status (`new` → `filed`)
- Secretary reviews inbox, assigns to patient, files in appropriate folder
- Dashboard widget: "X unprocessed documents"
- No auto-routing — the secretary IS the routing engine

### SP-2.4 Document Templates with Mail Merge

**Implementation:**
- 5-8 essential templates: referral letter, appointment confirmation, lab result notification, fit-for-work certificate, GDPR access response cover letter
- DOCX with `${placeholder}` markers → PHPWord `TemplateProcessor`
- One-click generation from patient record: select template → auto-populate → review → save as PDF → file or print

### SP-2.5 Secure Document Destruction

**Regulation:** GDPR Art. 17 + Art. 5(1)(e) | HIPAA §164.310(d)(2)(i)

- `shred -vfz -n 3` on Linux before `unlink()`
- Auto-generate Certificate of Destruction PDF (TCPDF)
- Destruction log retained 6 years
- Legal hold flag prevents destruction
- Triggered manually by doctor or automatically when retention period expires

### SP-2.6 DPA/BAA Tracking

- Simple registry: vendor name, service, DPA date, expiry
- Cron alerts at 30 and 7 days before expiry
- Linked document (scanned DPA/BAA PDF)
- Typically 3-5 vendors for a small practice: cloud hosting, fax service, lab interface, backup service

---

## SP Tier 3 — COULD (Competitive Advantage)

Features that differentiate a modern small practice. Low urgency but high patient satisfaction.

### SP-3.1 Patient Upload Link

- Generate secure time-limited link sent via SMS/email before appointment
- Patient uploads: insurance card photo, ID, completed forms
- Files land in inbox with "patient upload" source tag
- HEIC-to-JPG auto-conversion for iPhones
- Reduces check-in time dramatically

### SP-3.2 Mobile Document Capture

- `<input type="file" accept="image/*" capture="environment">` for insurance card / ID scanning
- ImageMagick deskew + normalize
- Replace flatbed scanner entirely

### SP-3.3 Basic OCR

- Tesseract on scanned documents (async via cron)
- Greek + English language support
- Enables full-text search across scanned files
- No routing rules — just search

### SP-3.4 Document Expiry Tracking

- Track: insurance card expiry, referral authorization windows
- Weekly cron checks, email alerts
- Dashboard badge: "3 documents expiring this month"

### SP-3.5 Read Receipts for GDPR Privacy Notice

- Track which patients have acknowledged the privacy notice
- Simple report: acknowledged / not yet acknowledged
- Reminder list for secretary during check-in

---

# PART B — LARGER CLINIC (Multiple Physicians, Departments)

A larger clinic has more complex document flows, multiple user roles (physicians, nurses, lab techs, billing staff, receptionists, practice manager), potentially multiple locations, and needs role-based access control, delegation workflows, and departmental organization. Compliance burden is higher and cannot rely on a single person's manual oversight.

---

## LC Tier 1 — MUST (Regulatory/Legal)

All SP Tier 1 features are included by reference — they apply equally. The items below are **additional** requirements driven by the clinic's larger scale.

### LC-1.1 All SP Tier 1 Features

Encryption at rest, automatic session logoff, document integrity verification, enhanced audit logging, GDPR data subject rights, breach notification support, and virus scanning — all required at the same specifications. No exceptions.

### LC-1.2 WCAG 2.1 AA / EN 301 549 Accessibility

**Regulation:** EAA (Directive 2019/882) — **enforceable since 28 June 2025**

Small practices (<10 employees) may qualify for the microenterprise exemption. **Larger clinics do not.** Full EN 301 549 compliance is mandatory.

**Requirements (complete list):**
- Full keyboard navigation: file browser, context menus, dialogs, modals, upload, share workflows, search
- 4.5:1 minimum color contrast ratio — audit the teal/slate design system palette
- ARIA labels on all custom components: document viewers, signature pads, drag-and-drop zones, canvas elements, modals, progress indicators, file trees, breadcrumbs
- Semantic HTML: heading hierarchy (`h1`→`h6`), landmark regions (`nav`, `main`, `aside`, `form`), form labels with `for` attributes
- Screen reader compatibility: file listing tables with `<th scope>`, live regions (`aria-live`) for upload progress and notifications, accessible alternatives for icon-only buttons (text labels or `aria-label`)
- Focus management: visible focus indicators (`:focus-visible`), logical tab order, focus trapping in modals, focus restoration on dialog close
- Skip navigation links at page top
- Every drag-and-drop operation has a keyboard alternative (button or menu)
- Accessible document previews: alt text for images, captions for video, transcript links for audio
- Published accessibility statement per EAA
- Tested with: axe-core automated audit, NVDA (Windows), VoiceOver (macOS/iOS), keyboard-only navigation

**Implementation priority:** Audit existing design system first. The teal palette (`--primary-500: #0d9488` on white) may pass but needs verification. The slate tertiary text (`--text-tertiary: #94a3b8` on white) **will likely fail** — contrast ratio is approximately 3:1, below the 4.5:1 threshold.

### LC-1.3 Role-Based Access Control for Files

**Regulation:** GDPR Art. 25 (privacy by design — minimum necessary access) | HIPAA §164.312(a)(1) (access controls) | NIS2 Art. 21

A clinic needs granular access control beyond "user owns file":

**Role definitions (YAML-configured):**
```yaml
roles:
  physician:
    files: [read, write, delete, share_internal, share_external, patient_link]
    folders: [create, rename, delete]
    admin: [view_audit_log]
  nurse:
    files: [read, write, patient_link]
    folders: [create]
    admin: []
  receptionist:
    files: [read, upload]
    folders: []
    admin: []
  billing:
    files: [read, upload, download]
    folders: [create]
    admin: []
    restricted_to_types: [insurance, billing, eob, prior_auth]
  lab_tech:
    files: [read, upload]
    folders: [create]
    restricted_to_folders: [/lab-results/]
  practice_manager:
    files: [read, write, delete, share_internal, share_external]
    folders: [create, rename, delete]
    admin: [view_audit_log, manage_users, manage_config, view_reports]
```

**Department-scoped access:** Billing staff sees only billing-related folders. Lab techs see only lab results. Physicians see patient records within their panel. Cross-department access requires explicit sharing or break-the-glass.

**Database additions:**
```sql
CREATE TABLE fileshare_role_permissions (
  role VARCHAR(50) NOT NULL,
  permission VARCHAR(50) NOT NULL,
  scope_type ENUM('global','folder','file_type') DEFAULT 'global',
  scope_value VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (role, permission, scope_type, scope_value)
);
```

### LC-1.4 Break-the-Glass Emergency Access

**Regulation:** HIPAA §164.312(a)(2)(ii) — emergency access procedure (required) | GDPR Art. 9(2)(c) — processing necessary to protect vital interests

HIPAA explicitly requires a documented procedure for obtaining access to ePHI during an emergency. For a larger clinic with role-based access, this is essential — an ER doctor must access records for a patient not in their panel.

**Implementation:**
- "Emergency Access" button visible in patient files section (requires confirmation dialog)
- Workflow: user triggers BTG → mandatory justification text field → selects emergency type (`medical_emergency`, `patient_safety`, `system_failure`) → time-limited elevated access (default: 4 hours, max: 8 hours)
- During BTG window: user can access the specific patient's records regardless of normal role restrictions
- **Not** a global privilege elevation — access is scoped to the one patient
- Enhanced audit: all access during BTG window logged with `emergency_access = TRUE`, justification text, approver (if required)
- Automatic notifications to: practice manager, DPO (if designated), patient's primary physician
- Mandatory review: practice manager must review within 48 hours, mark as `justified` or `unjustified`
- Unjustified BTG triggers disciplinary workflow

**Config (`config/security.yml`):**
```yaml
break_the_glass:
  enabled: true
  duration_hours: 4
  max_duration_hours: 8
  require_justification: true
  require_approval: false       # true = another user must approve before access granted
  notify: [practice_manager, primary_physician]
  review_deadline_hours: 48
```

### LC-1.5 GDPR Data Subject Rights (Full)

All SP-1.5 workflows plus:

**Right to Restriction (Art. 18):**
- Mark files as "restricted processing" → `processing_restricted BOOLEAN` on `fileshare_files`
- Restricted files: stored but cannot be accessed, shared, or included in reports — only for storage, legal defense, or with renewed consent
- Visual indicator in file browser: 🔒 badge with "Processing restricted" tooltip
- Only practice manager or DPO can lift restriction

**Right to Data Portability (Art. 20):**
- Export all patient-linked files in structured, machine-readable format
- ZIP archive containing: files in original format, JSON manifest with metadata (file name, type, dates, link types, who uploaded), FHIR-compatible metadata wrapper (preparation for EHDS 2029)
- Available as self-service via patient portal or staff-initiated

**DPIA Documentation Support (Art. 35):**
- Auto-generated processing activity report from system configuration
- Data flow visualization: what patient data enters the file system → where stored → who accesses → where shared
- Statistics export: access patterns, share usage, external access events — feeds into DPIA risk assessment

### LC-1.6 Consent Management Dashboard

**Regulation:** GDPR Art. 7 (demonstrable consent) | EHDS Art. 10 (opt-out management)

**Implementation:**
- Central view of all consents per patient: treatment consent, GDPR data processing, telehealth, specific procedure consents, research participation, EHDS secondary use opt-out
- Status per consent: `given` / `withdrawn` / `expired` / `not_yet_requested`
- Consent expiry monitoring (configurable renewal periods)
- Link to signed consent document in file share
- Bulk reporting: patients needing consent renewal, missing consents, withdrawn consents
- EHDS opt-out flag: when toggled, propagates to all patient records and prevents inclusion in any secondary use data export
- Audit trail for every consent status change

---

## LC Tier 2 — SHOULD (High-Impact Operations)

### LC-2.1 Document Inbox/Outbox System (Full)

**Inbox with routing rules:**
- Sources: fax, scanned paper, lab results, email, patient portal, internal transfers
- Each item: source type, priority (`normal`/`urgent`/`critical`), assigned user, status (`unprocessed` → `in_review` → `filed` → `forwarded`)
- **Auto-routing rules** (admin-configurable in `config/fileshare/routing.yml`):
  ```yaml
  rules:
    - match: { source: lab, keywords: [αποτέλεσμα, lab result, CBC, metabolic] }
      route_to: ordering_physician
      priority: normal
    - match: { source: lab, keywords: [κρίσιμο, critical, STAT] }
      route_to: ordering_physician
      priority: critical
      notify: true
    - match: { source: fax, keywords: [ασφαλιστικός, insurance, ΕΟΠΥΥ, prior auth] }
      route_to: role:billing
      priority: normal
    - match: { source: fax, keywords: [παραπεμπτικό, referral] }
      route_to: role:receptionist
      priority: normal
  ```
- Auto-patient-matching: extract patient name/AMKA/DOB from OCR text, match against patient database
- Unmatched items: "Needs assignment" queue
- Dashboard widget per user: "Your unprocessed items: X" with aging indicators (>24h yellow, >48h red)
- SLA tracking: average processing time per document type

**Outbox:**
- Track outgoing: referral letters, records releases, insurance submissions
- Status: `draft` → `sent` → `delivered` → `acknowledged`
- Delivery method: fax, email, secure link, postal
- Delivery confirmations from fax gateway / email read receipts

### LC-2.2 Document Checklists (Full)

**Multiple template types:**
```yaml
checklist_templates:
  new_patient:
    items:
      - { label: "Photo ID", type: general, required: true }
      - { label: "Insurance card (front)", type: insurance, required: true }
      - { label: "Insurance card (back)", type: insurance, required: true }
      - { label: "Signed treatment consent", type: consent, required: true }
      - { label: "Signed GDPR privacy notice", type: consent, required: true }
      - { label: "Medical history form", type: general, required: true }
      - { label: "Medication list", type: general, required: false }
      - { label: "Allergy information", type: general, required: false }
      - { label: "Referral authorization", type: insurance, required: false }
  annual_exam:
    items:
      - { label: "Updated insurance card", type: insurance, required: true }
      - { label: "Updated medication list", type: general, required: true }
  surgical_consult:
    items:
      - { label: "Referring physician letter", type: referral, required: true }
      - { label: "Prior imaging/labs", type: general, required: true }
      - { label: "Signed procedure consent", type: consent, required: true }
      - { label: "Anesthesia consent", type: consent, required: true }
      - { label: "Pre-op lab results", type: general, required: true }
```

**Features beyond SP:**
- Auto-create checklist on appointment creation (hooked to scheduling module)
- 48h pre-appointment alert for incomplete checklists → generates patient email/SMS with upload link
- Per-department assignment: "insurance card" check assigned to reception, "procedure consent" to nursing
- Analytics: average completion rate, most commonly missing items, time to completion

### LC-2.3 Electronic Signatures (Full eIDAS)

All SP-2.1 features plus:

**Multi-signer workflows:**
- Templates with multiple signature slots: patient + physician, patient + witness, multiple physicians for interdisciplinary consent
- Signing order enforcement: patient signs first → physician countersigns
- Status tracking: `awaiting_patient` → `awaiting_physician` → `fully_signed`
- Email/SMS notification when it's someone's turn to sign
- Expiry: unsigned documents expire after configurable period (default 7 days)

**Optional QES integration:**
- For Member States requiring qualified signatures for specific medical documents
- REST API integration with QTSP (Qualified Trust Service Provider): Swisscom, InfoCert, D-Trust
- Workflow: patient authenticates via eID → QTSP issues signing certificate → document signed server-side → QES-stamped PDF returned

### LC-2.4 Referral Workflow Tracking

**Full lifecycle management:**
- Creation: auto-populated from patient record (demographics, diagnosis, relevant history)
- Specialist directory: name, specialty, fax/email, accepted insurance plans, average response time
- Sending: via fax gateway, secure email, or printed
- Status tracking: `created` → `sent` → `received` → `appointment_scheduled` → `consultation_completed` → `report_received` → `reviewed`
- Overdue alerts: configurable thresholds (default 14 days for "no response")
- Metrics dashboard: referral volume by specialist, average time-to-consultation, completion rate, referral leakage rate

### LC-2.5 Fax Integration

**Full bidirectional integration:**
- Cloud fax API with EU data residency: Retarus (Munich), Fax.Plus (Swiss)
- Inbound: webhook → PDF download → inbox with "fax" source tag → auto-OCR → auto-route
- Outbound: select document → enter/select destination → auto-generated cover sheet → send → delivery confirmation
- Fax journal: all transmissions logged with timestamps, page counts, delivery status, duration
- Batch sending: same document to multiple destinations (e.g., referral to specialist + copy to insurance)

### LC-2.6 Document Templates with Mail Merge

All SP-2.4 features plus:
- Department-specific templates: each department manages their own template library
- Approval workflow for new templates: created by staff → approved by practice manager
- Template versioning: track changes, rollback to previous versions
- Bulk generation: generate letters for multiple patients (e.g., recall notices for annual exams)
- Multi-language: templates available in multiple languages, auto-selected based on patient preference

### LC-2.7 Document Sensitivity Classification

**Implementation:**
- `sensitivity ENUM('normal','sensitive','restricted')` on `fileshare_files`
- **Normal:** standard role-based access
- **Sensitive:** enhanced audit logging, excluded from bulk exports, requires acknowledgment before access, visible warning banner on preview
- **Restricted:** requires break-the-glass to access (even for users with standard patient access), cannot be shared externally, watermarked on view/print/download
- Auto-classification rules: files in "Psychiatry" folder → `sensitive`; files tagged with mental health, HIV, substance abuse → `sensitive`; genetic data → `restricted`
- Manual override by physician or practice manager

### LC-2.8 Dynamic Watermarking

- Semi-transparent overlay: username, timestamp, "CONFIDENTIAL — [Clinic Name]"
- PDF: FPDI renders watermark per page on download/print
- Preview: Canvas overlay on image previews
- Print: CSS `@media print` watermark injection
- Configurable by sensitivity: `normal` = none, `sensitive` = light, `restricted` = heavy with user ID
- Watermark text configurable in `config/fileshare/watermark.yml`

### LC-2.9 Secure Document Destruction (with approval workflow)

All SP-2.5 features plus:
- **Two-person approval** for destruction of medical records: requesting user ≠ approving user
- Bulk destruction batches: select multiple files past retention → submit batch → approver reviews → batch destroyed
- Destruction report: monthly summary of all destroyed documents
- Legal hold override: practice manager can flag documents for preservation regardless of retention expiry

### LC-2.10 Task Assignment from Documents

- Create tasks from any document view: "Follow up on missing insurance verification", "Get specialist signature on referral", "Submit claim attachment"
- Task fields: title, description, assigned user, priority, due date, linked file, linked patient
- "My Tasks" dashboard widget with overdue highlighting
- Task notifications via bell icon + optional email
- Task completion closes the loop: marks related checklist item, updates document status

---

## LC Tier 3 — COULD (Competitive Advantage)

### LC-3.1 Patient Document Portal (EHDS Preparation)

**Regulation:** EHDS Art. 5-10 (patient access rights) — full application by March 2029-2031

**Implementation:**
- Secure, authenticated portal: patient accesses their linked documents
- Mobile-responsive (60%+ access is mobile)
- View, download, print capabilities
- Access log visible to patient: who accessed their records, when (EHDS Art. 9)
- Opt-out toggle for secondary data use (EHDS Art. 10)
- Proxy access management: parents for children, legal guardians
- Upload capability: pre-visit document submission
- Appointment-linked: portal shows "Documents needed for your appointment on [date]" with upload targets

### LC-3.2 Patient Upload & Pre-Visit Workflow

- Automated flow: appointment scheduled → 48h before, system sends SMS/email with secure upload link → patient uploads required documents → files land in inbox with patient auto-linked → staff reviews → approved items satisfy checklist items
- MIME type validation (server-side, not just extension)
- HEIC-to-JPG auto-conversion (ImageMagick)
- Image quality checks: reject blurry uploads with "Please retake" message
- Staff review queue: approve / reject / request-resubmission with message to patient

### LC-3.3 Medical Records Request Management (ROI)

- Full workflow: request intake → authorization validation → record retrieval → sensitivity flags → redaction review → QA → delivery → confirmation
- 30-day deadline tracking (GDPR Art. 15) with escalation alerts
- Special handling flags: mental health, HIV, substance abuse, genetic data (check national laws for extra protections)
- Redaction step integrated (see LC-3.6)
- Fee calculation per national law (many EU countries: free for electronic copies under GDPR)
- Accounting of disclosures log

### LC-3.4 Lab System Integration

Three-level path:
1. **PDF ingestion:** lab sends PDF via email/SFTP → cron picks up → inbox → staff assigns
2. **HL7 v2 processing:** parse ORU^R01 from SFTP drops → auto-extract patient/results → auto-file → critical value alerts
3. **FHIR (future):** REST API consuming DiagnosticReport resources — aligns with EHDS 2031

### LC-3.5 OCR + Intelligent Document Routing

- Tesseract OCR (Greek + English) on all scanned/faxed documents
- OCR output feeds full-text search index
- Keyword-based auto-classification feeds inbox routing rules
- Auto-patient-matching from OCR text against patient database
- Admin-editable classification rules in database

### LC-3.6 Document Redaction

- FPDI imports PDF pages as flattened images → black rectangles over specified coordinates → permanent, irreversible
- Semi-automated PHI detection: regex patterns for AMKA, dates, phone numbers, patient names from database
- Highlighted suggestions for human confirmation before applying
- Redaction audit log: who redacted what, when, which areas
- Essential for records release (LC-3.3) and GDPR anonymization

### LC-3.7 Smart Folders (Virtual Folders)

- Auto-populated from saved queries — no physical file movement
- System smart folders: "Unread lab results", "Expiring insurance", "Unsigned referrals", "Today's faxes", "Unprocessed inbox", "My pending tasks"
- User-created custom folders with JSON filter criteria
- Badge counts for at-a-glance status awareness
- Department-scoped: billing sees billing smart folders, clinical sees clinical ones

### LC-3.8 Insurance & Billing Document Tracking

- Document subtypes: `prior_auth`, `eob`, `claim_attachment`, `appeal`, `authorization`
- Lifecycle: `submitted` → `in_review` → `approved`/`denied` → `appealed`
- Link to ZPMS billing module: associate documents with claims/encounters
- Expiry tracking for authorizations
- Dashboard: pending authorizations, denied claims needing appeal, aging submissions

### LC-3.9 Document Packaging

- Multi-document packet assembly via FPDI/TCPDF
- Templates: referral packet, new patient packet, records transfer packet, insurance submission packet
- Auto-generated cover sheets with practice logo, patient demographics, table of contents, page count
- One-click "Prepare referral packet for Dr. [Name]" from patient record

### LC-3.10 Multi-Language Document Support

- Patient language preference in ZPMS patient record
- Translated template versions with `certified_translation` flag
- Auto-select language on document generation
- Translation currency tracking: when source template changes, flag translations as outdated
- Language coverage dashboard

### LC-3.11 Document Expiry Tracking (Extended)

All SP-3.4 features plus:
- Track: staff certifications (CPR, ACLS, HIPAA training), provider licenses (DEA equivalent, medical license, board certifications), CLIA certificates, malpractice policies, DPA/BAA agreements
- Escalation: expired staff certifications → alert practice manager → potential role restriction
- Compliance dashboard with color-coded urgency across all tracked documents

### LC-3.12 DPA/BAA Tracking (Extended)

All SP-2.6 features plus:
- Larger vendor list (10-30+ vendors typical for a clinic)
- Annual review workflow: practice manager reviews each DPA yearly
- Risk assessment per vendor: data types shared, storage location, sub-processors
- Compliance report: all vendors, DPA status, last review date, risk level

---

# PART C — Feature Matrix Summary

| Feature | SP Tier | LC Tier | GDPR | HIPAA | NIS2 | EHDS | eIDAS | EAA |
|---------|---------|---------|------|-------|------|------|-------|-----|
| Encryption at rest | 1 | 1 | ● | ● | ● | | | |
| Automatic session logoff | 1 | 1 | ● | ● | ● | | | |
| Document integrity verification | 1 | 1 | ● | ● | ● | | | |
| Enhanced audit logging | 1 | 1 | ● | ● | ● | ● | | |
| GDPR data subject rights | 1 | 1 | ● | | | ● | | |
| Breach notification support | 1 | 1 | ● | ● | ● | | | |
| Virus scanning (ClamAV) | 1 | 1 | | | ● | | | |
| WCAG 2.1 AA accessibility | 3* | 1 | | | | | | ● |
| Role-based file access control | — | 1 | ● | ● | ● | | | |
| Break-the-glass access | — | 1 | ● | ● | | | | |
| Consent management dashboard | — | 1 | ● | | | ● | | |
| Electronic signatures | 2 | 2 | ● | | | | ● | |
| Document checklists | 2 | 2 | | | | | | |
| Document inbox/outbox | 2 | 2 | | | | | | |
| Mail merge templates | 2 | 2 | | | | | | |
| Secure destruction + cert | 2 | 2 | ● | ● | | | | |
| DPA/BAA tracking | 2 | 2 | ● | ● | | | | |
| Referral workflow | — | 2 | | | | | | |
| Fax integration | — | 2 | | | | | | |
| Sensitivity classification | — | 2 | ● | | ● | | | |
| Dynamic watermarking | — | 2 | | | | | | |
| Task assignment | — | 2 | | | | | | |
| Patient portal | 3 | 3 | ● | | | ● | | |
| Patient uploads | 3 | 3 | | | | | | |
| Records request mgmt (ROI) | — | 3 | ● | ● | | | | |
| Lab integration | — | 3 | | | | ● | | |
| OCR + intelligent routing | 3 | 3 | | | | | | |
| Document redaction | — | 3 | ● | ● | | | | |
| Smart folders | — | 3 | | | | | | |
| Insurance doc tracking | — | 3 | | | | | | |
| Document packaging | — | 3 | | | | | | |
| Multi-language support | — | 3 | | | | | | |
| Document expiry tracking | 3 | 3 | | | | | | |
| Mobile document capture | 3 | 3 | | | | | | |
| Read receipts | — | 2** | ● | | | | | |

*SP WCAG: microenterprise exemption may apply — listed as Tier 3 but should be Tier 1 if exemption doesn't apply.

**Read receipts: regulatory driver is GDPR Art. 7 (demonstrable consent) + Art. 5(2) (accountability).

---

# PART D — Technology Stack (Both Sizes)

All features implementable on vanilla PHP/MySQL. No Composer, no Node.js, no frameworks.

| Component | Technology | Notes |
|-----------|-----------|-------|
| Encryption | PHP Sodium (built-in ≥7.2) | `sodium_crypto_secretbox()` |
| PDF generation | TCPDF (single PHP file) | Certificates, watermarks, consent PDFs |
| PDF manipulation | FPDI (single PHP file) | Watermarking, redaction, packaging |
| Signatures | signature_pad.js (MIT, vanilla JS) | HTML5 Canvas capture |
| OCR | Tesseract CLI | `tesseract input.png output -l ell+eng` |
| Virus scan | ClamAV daemon | Unix socket to `clamd` |
| Fax | REST API (Retarus / Fax.Plus) | EU data residency, GDPR-compliant |
| Mail merge | PHPWord TemplateProcessor | Extractable single class |
| Image processing | ImageMagick CLI + GD | Deskew, normalize, convert |
| Accessibility | axe-core CLI | Automated WCAG auditing |
| Hash chain | PHP `hash('sha256', ...)` | Built-in, no dependencies |
| QR codes | phpqrcode (single PHP file) | Share links, patient portal links |
