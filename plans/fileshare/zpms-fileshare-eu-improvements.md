# ZPMS File Sharing Module — Additional Improvements (EU/European Edition)

Healthcare-specific features adapted for European legislation: GDPR, EHDS, eIDAS 2.0, NIS2, EAA, and EU Member State medical record laws.

---

## Table of Contents

1. Compliance-Critical (Immediate)
2. High-Impact Daily Workflows
3. Clinical Integration
4. Security Hardening
5. Advanced Technical Capabilities
6. Workflow Management Features
7. Implementation Roadmap

---

## 1. Compliance-Critical Features (Immediate Priority)

These are legally required or carry enforcement deadlines already in effect or imminent.

### 1.1 WCAG 2.1 AA / EN 301 549 Accessibility

**Regulation:** European Accessibility Act (EAA, Directive 2019/882) — **enforceable since 28 June 2025.** Healthcare websites and apps are explicitly in scope. The technical conformance standard is EN 301 549, which incorporates WCAG 2.1 Level AA. Fines vary by Member State (Ireland includes potential imprisonment; others impose fines up to €500,000).

**Exemption:** Microenterprises (<10 employees, <€2M turnover) are exempt. Mid-size practices are not.

**Requirements for ZPMS file sharing:**

- Full keyboard navigation for all operations (file browser, context menus, dialogs, modals, upload, share)
- 4.5:1 minimum color contrast ratio — audit the teal/slate palette against WCAG AA thresholds
- ARIA labels on all custom components: document viewers, signature pads, drag-and-drop zones, canvas elements, modals, progress indicators
- Semantic HTML: proper heading hierarchy (`h1`→`h6`), landmark regions (`nav`, `main`, `aside`), form labels
- Screen reader compatibility: file listing tables must use proper `<th>` scope attributes; live regions for upload progress; accessible alternatives for icon-only buttons
- Focus management: visible focus indicators, logical tab order, focus trapping in modals
- Skip navigation links
- Accessible drag-and-drop: every drag-and-drop action must have a keyboard-accessible alternative (button or menu)
- Accessible document previews: text alternatives for images, captions for video, transcript links for audio
- Published accessibility statement per EAA requirement

**Implementation:** Audit existing design system CSS variables for contrast. Add ARIA attributes throughout templates. Test with screen readers (NVDA, VoiceOver). Automated testing via axe-core CLI.

### 1.2 Encryption at Rest

**Regulation:** GDPR Article 32 requires "appropriate technical measures" to protect personal data. While not explicitly mandating encryption, the Article 34(3)(a) breach notification exemption (no need to notify data subjects) applies only when data was encrypted with keys the attacker could not access. This is the practical equivalent of making encryption mandatory for health data.

**NIS2 Directive** (Directive 2022/2555) explicitly lists encryption among required cybersecurity measures for essential entities (healthcare is classified as essential).

**Implementation:**

- Use PHP's built-in Sodium extension (available since PHP 7.2): `sodium_crypto_secretbox()` with XSalsa20-Poly1305
- Each file gets a random 24-byte nonce stored alongside the ciphertext
- Encryption key stored in a file with `chmod 600` outside the web root — never in the database or source code
- Key rotation capability: maintain a key version identifier per file, allowing gradual re-encryption when keys are rotated
- MySQL columns containing patient identifiers: use InnoDB Transparent Data Encryption (TDE) or application-level encryption before INSERT
- Database backup files: encrypt with `openssl enc -aes-256-cbc` before storage

### 1.3 Automatic Session Timeout

**Regulation:** NIS2 Article 21 requires access control and authentication policies. GDPR's "appropriate measures" principle also implies session management for systems handling health data.

**Implementation:**

- Server-side `$_SESSION['last_activity']` timestamp checked on each request
- JavaScript inactivity timer with configurable timeout (default: 15 minutes for clinical systems)
- Warning modal at 2 minutes before timeout: "Your session will expire in 2 minutes. Click to continue."
- AJAX keep-alive on user interaction to extend session
- Complete session destruction on timeout — redirect to login with "Session expired" message
- Configuration in `config/security.yml`: `session_timeout_minutes: 15`

### 1.4 Document Integrity Verification

**Regulation:** GDPR Article 5(1)(f) requires data integrity. NIS2 Article 21(2)(h) mandates data integrity verification mechanisms.

**Implementation:**

- Store SHA-256 hash of every file on upload: `$hash = hash_file('sha256', $filePath)`
- Verify hash on every download and preview request — if mismatch, block access and alert administrator
- `file_hash VARCHAR(64)` column already exists in `fileshare_files` — add periodic verification cron job
- Hash-chain audit log: each audit entry includes SHA-256 of the previous entry, creating a tamper-evident chain

### 1.5 GDPR Data Subject Rights Engine

**Regulation:** GDPR Articles 15-22 grant patients rights that ZPMS must support. Unlike HIPAA, GDPR includes rights to erasure, restriction, and portability that healthcare systems must handle (with medical record retention exceptions per Member State law).

**Required workflows:**

**Right of Access (Art. 15):** Patient requests all data held about them → system must compile all files linked to that patient (via `fileshare_patient_links`), all activity logs referencing those files, all share access logs, and export as a structured package. Response deadline: **30 days** (extendable by 60 days for complex requests).

**Right to Rectification (Art. 16):** Patient requests correction of inaccurate data → provide a workflow where corrections are tracked as annotations or version updates (original medical records generally cannot be altered, only supplemented).

**Right to Erasure (Art. 17) with medical exceptions:** The right to erasure does not apply when processing is necessary for healthcare provision, public health, or legal obligations. **Member State medical record retention laws override erasure requests.** Examples: Netherlands requires 20 years after last visit; UK requires retention until 10 years after death; Greece and most EU states require 10-20 years. ZPMS must:

- Accept erasure requests via a workflow
- Automatically check retention obligations per document type and national law
- Reject erasure for records still within mandatory retention
- Approve erasure only for non-clinical data (marketing, expired non-medical documents)
- Log the request and decision regardless of outcome (for accountability under Art. 5(2))

**Right to Restriction (Art. 18):** Mark files as "restricted processing" — data remains stored but cannot be accessed except for storage, legal claims, or with consent. Add `processing_restricted BOOLEAN` flag to `fileshare_files`.

**Right to Data Portability (Art. 20):** Export patient-linked files in structured, machine-readable format. Package as ZIP with JSON manifest listing file metadata, patient identifiers, link types, and timestamps.

**Implementation:** New `GDPRRequestManager` service with request intake, status tracking (`pending` → `under_review` → `approved`/`rejected`), automated retention checks, and deadline monitoring. YAML-configured retention periods per document type and jurisdiction.

### 1.6 72-Hour Breach Notification Support

**Regulation:** GDPR Article 33 requires notification to the supervisory authority within **72 hours** of becoming aware of a personal data breach. Article 34 requires notification to affected data subjects when breach poses "high risk."

**NIS2** requires incident reporting within **24 hours** (early warning), full report within **72 hours**, and final report within **1 month**.

**Implementation:**

- Breach assessment workflow: detect → classify severity → identify affected data subjects → prepare notification
- Pre-built notification templates for DPA (Data Protection Authority) reporting
- Affected patient identification: given a file or set of files, immediately list all linked patients and all users/share visitors who accessed them
- Breach log with timestamps, actions taken, and responsible persons
- Integration with the audit trail system: quickly scope which files were accessed, by whom, from which IPs, during the breach window

### 1.7 Data Protection Impact Assessment (DPIA) Documentation

**Regulation:** GDPR Article 35 mandates DPIAs for "high-risk processing" — processing health data at scale always qualifies.

**Implementation:** Not a software feature per se, but ZPMS should generate the data needed for DPIAs:

- Data flow reports: what patient data enters the file system, where it's stored, who accesses it, where it's shared
- Processing activity records (Art. 30): auto-generated from configuration — purposes, categories, recipients, retention periods, security measures
- Risk assessment inputs: statistics on access patterns, share usage, external access events

---

## 2. High-Impact Daily Workflow Features

### 2.1 Document Inbox/Outbox System

**Context:** This is the single highest-impact workflow feature for European medical practices. The inbox captures all incoming documents — faxes (still used in many EU countries for inter-practice communication), scanned paper, lab results, referral letters, insurance correspondence, patient portal submissions — in a unified processing queue.

**Inbox features:**

- Each item has: source type, priority level, assignment (responsible user), status (`unprocessed` → `in_review` → `filed` → `forwarded`)
- Routing rules: lab results → ordering physician; billing → billing staff; referrals → referral coordinator; urgent results → immediate notification
- Auto-patient-matching: extract patient name/DOB from OCR text and match against patient database
- Unmatched items remain in a "Needs assignment" queue for manual linking
- Dashboard widget: unprocessed count, items by priority, aging items (>24h, >48h, >72h unprocessed)

**Outbox features:**

- Track outgoing documents: referral letters, records release responses, insurance submissions
- Status tracking: `draft` → `sent` → `delivered` → `acknowledged`
- Delivery method: fax, email, secure link, postal
- Integration with fax gateway (see 2.4)

**Database:** `fileshare_inbox` table with `source_type ENUM('fax','scan','lab','email','portal','manual')`, `priority`, `assigned_to`, `status`, `patient_id`, `file_id`, timestamps.

### 2.2 Document Checklists per Patient

**Context:** Every new patient visit requires collecting a standard set of documents. Tracking this manually leads to missing documents at check-in.

**Features:**

- Checklist templates per visit type: new patient, annual exam, surgical consult, referral visit
- Auto-create checklist when appointment is scheduled
- Items: Photo ID, insurance card (front/back), signed consent to treat, GDPR privacy notice acknowledgment, medical history form, medication list, allergy information, referral authorization
- Visual progress: "7 of 10 documents collected" with color-coded status (green=complete, yellow=pending, red=missing+urgent)
- Staff alerts: flag incomplete checklists 48h before scheduled appointment
- Patient-facing: share a checklist link with patient so they can upload missing documents before arrival

**Database:** `fileshare_checklist_templates`, `fileshare_checklists` (linked to appointment), `fileshare_checklist_items` (linked to file when fulfilled).

### 2.3 Electronic Signatures

**Regulation:** eIDAS Regulation (EU 910/2014, amended by 2024/1183) defines three signature levels:

1. **Simple Electronic Signature (SES):** Any data attached to electronic data used to sign (click-to-accept, typed name, drawn signature). Legally admissible but lowest evidential weight.
2. **Advanced Electronic Signature (AES):** Uniquely linked to signatory, capable of identifying them, created using data under signatory's sole control, linked to data so any change is detectable.
3. **Qualified Electronic Signature (QES):** AES created by a qualified signature creation device, based on a qualified certificate. Legal equivalent of handwritten signature in all EU Member States.

**For ZPMS medical consent forms, SES is sufficient** for most use cases (eIDAS states no signature type shall be denied legal effect solely because it is electronic). AES is recommended for higher-value documents. QES is optional and requires integration with a Qualified Trust Service Provider (QTSP).

**Implementation — Built-in SES/AES:**

- HTML5 Canvas signature capture via `signature_pad.js` (MIT license, 8KB minified, vanilla JS, no jQuery)
- Store signature as base64 PNG in MySQL alongside: SHA-256 hash of signed document, signer IP, timestamp, user agent, document version
- Generate tamper-evident PDF with embedded signature using TCPDF (single PHP file, no Composer)
- Signature record includes: signer identity (patient name, ID), method (drawn, typed), consent text displayed at time of signing
- Audit trail: who signed what, when, from where — satisfies eIDAS evidential requirements

**Common documents requiring signatures:**
- General consent to treatment
- GDPR privacy notice acknowledgment (Art. 7 — proof of consent)
- Informed consent for procedures
- Telehealth consent
- Data portability/release authorization
- Financial responsibility agreement

**Optional QES integration:** For practices that need QES (some Member States require it for specific medical documents), integrate with QTSP APIs (e.g., Swisscom, InfoCert, D-Trust) via REST — patient authenticates with eID, QTSP issues signing certificate, document is signed server-side.

### 2.4 Fax Integration

**Context:** While declining, fax remains common in European healthcare for inter-practice communication, especially in Germany, Austria, and parts of Southern Europe. The Greek healthcare system still uses fax extensively for referrals and lab results.

**Implementation:**

- Integrate with a cloud fax API that offers EU data residency: Retarus (Munich-based, GDPR-compliant), or Fax.Plus (Swiss, GDPR-compliant), or self-hosted HylaFAX with ATA adapter
- **Inbound flow:** Cloud service receives fax → sends webhook to ZPMS → PHP downloads PDF via API → stores in document inbox → staff assigns to patient record
- **Outbound flow:** User selects document → PHP calls fax API with PDF and destination number → webhook confirms delivery → status updated in outbox
- Auto-generated fax cover sheets: practice info, confidentiality notice, patient identifiers (if configured), page count, urgency level
- Fax journal: log all sent/received faxes with timestamps, page counts, delivery status

### 2.5 Document Templates with Mail Merge

**Context:** Referral letters, appointment reminders, certificates, and notification letters all follow standard formats with patient-specific data.

**Implementation:**

- DOCX templates with `${placeholder}` markers stored in `modules/fileshare/templates/`
- PHPWord's `TemplateProcessor` class (can be used as a single-file inclusion without full Composer) handles `setValue()`, `setImageValue()`, `cloneRow()`
- Available placeholders: `${patient_name}`, `${patient_dob}`, `${patient_id}`, `${insurance_id}`, `${diagnosis}`, `${medications}`, `${referring_physician}`, `${practice_name}`, `${date}`, etc.
- Output: generated DOCX or convert to PDF via LibreOffice CLI (`soffice --headless --convert-to pdf`)
- Common templates: referral letters, appointment confirmations, fit-for-work certificates, lab result notification letters, GDPR data access response cover letters

### 2.6 Multi-Language Document Support

**Regulation:** While not mandated EU-wide, several Member States require healthcare documents in languages patients understand. Greece specifically requires informed consent in the patient's language.

**Implementation:**

- Store patient language preference in ZPMS patient record
- Maintain translated template versions with `language_code` and `certified_translation BOOLEAN` flag
- Auto-select appropriate language on document generation
- When English/Greek template changes, flag all translations as needing update
- Language matrix dashboard: which templates have translations, which are outdated

---

## 3. Clinical Integration Features

### 3.1 Patient Document Portal (EHDS Compliance)

**Regulation:** The European Health Data Space Regulation (EU 2025/327) entered into force 26 March 2025 with staggered implementation:

- **March 2027:** Commission adopts implementing acts
- **March 2029:** Patient summaries and ePrescriptions must be exchangable across EU
- **March 2031:** Medical images, lab results, and discharge reports must be exchangable

GDPR Articles 15 (right of access) and 20 (right to data portability) already require providing patients electronic access to their data. The EHDS reinforces this with specific requirements for health data access.

**Features:**

- Secure, authenticated patient portal for accessing their linked documents
- Mobile-responsive design (60%+ of portal access is mobile)
- Document download/print capability
- View history: patient can see who accessed their records (EHDS Article 9)
- Opt-out management: EHDS Article 10 allows patients to opt out of secondary data use — ZPMS must track this preference
- Proxy/family access management (parents accessing children's records, legal guardians)
- Language selection matching patient preference

### 3.2 Patient Upload Capabilities

**Features:**

- Patient receives secure upload link 48h before appointment (via SMS or email)
- Upload targets: insurance card photos, ID documents, completed intake forms, external medical records, prior imaging CDs
- MIME type validation server-side (not just extension checking)
- HEIC-to-JPG auto-conversion for iPhone photos (via ImageMagick)
- Staff review queue: uploads arrive in `pending_review` status → staff can approve/reject/request-resubmission
- Auto-link approved uploads to patient record and relevant checklist item

### 3.3 Medical Records Request Management (Release of Information)

**Regulation:** GDPR Article 15 (right of access) gives patients the right to obtain copies of their data within **30 days.** The EHDS further strengthens this right for electronic health records.

**Implementation:**

- Request intake workflow: patient submits request (in-person, portal, or letter) → staff logs request with received date → clock starts
- Deadline tracking: 30-day countdown with alerts at 20, 25, 28, 30 days
- Record retrieval with special handling flags: mental health records, HIV status, substance abuse records may have additional national protections
- Redaction review step (see 5.5)
- QA verification before release
- Delivery tracking: method (electronic, postal, in-person), confirmation of receipt
- Fee calculation per national law (many EU countries prohibit fees for electronic copies under GDPR Art. 15(3))
- Accounting of disclosures log

### 3.4 Lab System Integration

**Context:** Lab results are the highest-volume external document type. In the EU, lab interfaces increasingly use HL7 FHIR alongside traditional HL7 v2 ORU messages.

**Implementation levels (choose based on lab partnerships):**

1. **PDF ingestion (simplest):** Lab sends PDF results via email or SFTP → cron job picks up → stores in inbox → staff assigns to patient
2. **HL7 v2 file processing:** Parse ORU^R01 messages from SFTP drops — extract PID (patient), OBR (test), OBX (results with abnormal flags H/L/C)
3. **FHIR integration (future/EHDS):** REST API consuming FHIR DiagnosticReport resources — aligns with EHDS 2031 requirements for lab result interoperability

**Critical values** in OBX-8 (C=Critical) should trigger immediate provider notifications.

### 3.5 Insurance & Billing Document Tracking

**Context:** European practices deal with both public health insurance (AMKA/EOPYY in Greece, AOK/TK in Germany, etc.) and private insurance. Document types include: referral authorizations, prior authorization requests/responses, claim attachments, EOBs, and appeal documentation.

**Implementation:**

- Document classification subtypes for insurance: `prior_auth`, `eob`, `claim_attachment`, `appeal`, `authorization`
- Lifecycle tracking: `submitted` → `in_review` → `approved`/`denied` → `appealed`
- Link to ZPMS billing module: associate insurance documents with specific claims or encounters
- Expiry tracking for insurance cards and authorizations

---

## 4. Security Hardening

### 4.1 Enhanced Audit Logging (NIS2-Grade)

**Regulation:** NIS2 Article 21 requires comprehensive security logging. GDPR Article 30 requires processing activity records. The EHDS Article 9 grants patients the right to know who accessed their records.

**Required audit categories:**

- **User-level:** Login/logout (with method — password, 2FA, SSO), failed login attempts, password changes, permission changes, role assignments
- **Application-level:** Files opened/closed, records created/read/edited/deleted, **print events, download events, export events, share events, permission changes, configuration changes**
- **Access tracking:** IP address, user agent, whether access was from trusted network or external
- **Patient-facing:** Who accessed which patient's files, when, for what purpose — this feeds the EHDS Art. 9 access log visible to patients

**Tamper-proof logging:**

- Hash-chain approach: each log entry includes SHA-256 hash of the previous entry
- Append-only MySQL table: separate database user with only INSERT permission (no UPDATE/DELETE)
- Minimum retention: **6 years** (aligns with most EU Member State requirements)

### 4.2 Break-the-Glass Emergency Access

**Context:** When a clinician without normal access privileges needs emergency access to patient documents during a medical emergency (e.g., unconscious patient in ER, treating physician not the regular provider).

**Implementation:**

- "Emergency Access" button in the patient files section (prominent but requiring confirmation)
- Workflow: user triggers BTG → enters justification reason → selects emergency type (medical emergency, patient safety, system failure) → receives time-limited elevated access (configurable, default 4-8 hours)
- Enhanced audit: BTG events generate special audit entries with `is_emergency_access = TRUE`
- Automatic notification to: DPO, practice manager, and the patient's regular physician
- Mandatory review: compliance officer must review all BTG access within 48 hours
- Minimum-necessary principle: even during emergency, access is limited to the specific patient's records, not global elevation

### 4.3 Document Sensitivity Classification

**Context:** Mental health records, HIV status, substance abuse records, and genetic data carry additional protections under various EU Member State laws beyond base GDPR.

**Implementation:**

- `sensitivity ENUM('normal','sensitive','restricted')` column on `fileshare_files`
- `sensitive` files: require explicit access justification, generate enhanced audit entries, excluded from bulk exports
- `restricted` files: require break-the-glass to access (even for users with normal patient record access), cannot be shared externally, watermarked on view/print
- Classification can be set manually or auto-assigned based on folder location or document type (e.g., files in "Psychiatry" folder auto-classified as `sensitive`)

### 4.4 Dynamic Watermarking

**Context:** Deters unauthorized disclosure of patient data by making every viewed/printed/downloaded copy traceable to a specific user and time.

**Implementation:**

- Semi-transparent overlay on documents during viewing and downloading: username, timestamp, "CONFIDENTIAL — [Practice Name]"
- PDF watermarking: use FPDI to open existing PDF → add watermark text page-by-page → serve watermarked copy. Original unwatermarked file preserved.
- Print watermarking: CSS `@media print` rules inject watermark text
- Preview watermarking: Canvas overlay on image previews
- Configurable per sensitivity level: `normal` = no watermark, `sensitive` = light watermark, `restricted` = heavy watermark with user ID

### 4.5 Secure Document Destruction

**Regulation:** GDPR Article 17 (right to erasure) and storage limitation principle (Art. 5(1)(e)) require that data no longer needed is securely destroyed.

**Implementation:**

- Beyond soft-delete: permanent destruction workflow using `shred -vfz -n 3` on Linux for multi-pass overwrite before `unlink()`
- Null database records after physical destruction (or delete entirely)
- Auto-generated **Certificate of Destruction** (PDF via TCPDF): document name/ID, destruction date/time, method, destroyed-by user, authorized-by user, digital hash
- Destruction logs retained for minimum 6 years even after document is gone
- **Legal hold** flag: prevent destruction of flagged documents regardless of retention policy (for active legal proceedings, audit investigations, or DPA inquiries)

### 4.6 Virus/Malware Scanning

**Regulation:** NIS2 Article 21 requires measures to prevent and detect malicious software.

**Implementation:**

- ClamAV integration via Unix socket communication with `clamd` daemon
- Scan every upload inline (adds ~1-2 seconds)
- Quarantine infected files in a directory outside the web root
- Alert administrators on detection
- Daily virus definition update via `freshclam` cron job
- Scan patient portal uploads with extra scrutiny (untrusted source)

### 4.7 Processor Agreement (DPA) Tracking

**Regulation:** GDPR Article 28 requires written contracts (Data Processing Agreements) with any processor handling personal data. This is the EU equivalent of HIPAA's BAA.

**Implementation:**

- Registry table: vendor name, service description, data types shared, DPA execution date, expiry date, status, linked DPA document PDF, DPO review date
- Cron-driven alerts at 90, 60, 30, and 7 days before expiry
- Dashboard widget showing DPA status across all vendors
- Link DPA records to relevant file shares (e.g., cloud fax provider DPA linked to fax integration files)

---

## 5. Advanced Technical Capabilities

### 5.1 OCR for Scanned Documents

**Implementation:**

- Tesseract OCR via CLI (`tesseract input.png output -l ell+eng` for Greek+English)
- Pre-processing with ImageMagick: deskew (`-deskew 40%`), noise removal, contrast enhancement, ensure 300 DPI minimum
- OCR output stored in MySQL FULLTEXT indexed column for search
- Process asynchronously via cron job — don't block uploads
- Language configuration in `config/fileshare/ocr.yml`: default languages, confidence threshold, max file size for OCR

### 5.2 Intelligent Document Routing

**Implementation:**

- Keyword-based classification on OCR output:
  - "αποτέλεσμα εξέτασης" / "lab result" / "CBC" → lab results queue
  - "παραπεμπτικό" / "referral" → referrals queue
  - "ασφαλιστικός" / "insurance" / "ΕΟΠΥΥ" → insurance queue
- Rules stored in MySQL, admin-editable via UI
- Patient matching: extract name/AMKA/DOB from OCR text, match against patient database for auto-assignment
- Unmatched documents land in manual classification queue

### 5.3 Document Packaging

**Implementation:**

- Multi-document packet assembly using FPDI/TCPDF for PDF merging
- Packet templates:
  - **Referral packet:** referral letter + relevant labs + imaging + medication list
  - **New patient packet:** registration form + insurance card + consents + GDPR acknowledgment
  - **Records transfer packet:** complete chart with auto-generated cover sheet and table of contents
  - **Insurance submission packet:** claim form + clinical notes + prior authorization
- Cover sheets generated programmatically: practice logo, patient demographics, table of contents with page ranges, total page count

### 5.4 Document Expiry Tracking

**Context:** Insurance cards, provider licenses, staff certifications, GDPR consents, and prior authorizations all have expiry dates.

**Implementation:**

- Track expiry dates on: insurance cards, referring physician licenses, staff certifications (CPR, ACLS), GDPR consent validity periods, prior authorization windows, DPA agreements
- Daily cron job checks for documents expiring within configurable alert windows (90, 60, 30, 14, 7 days)
- Email/notification alerts to responsible staff
- Compliance dashboard with color-coded urgency (green/yellow/red)
- Expired document badges in file browser

### 5.5 Document Redaction

**Context:** Required for records release (GDPR data access requests), anonymization for secondary use (EHDS), and responding to DPA inquiries.

**Implementation:**

- FPDI imports PDF pages as flattened images → draw black rectangles over specified coordinates → underlying text layer removed (permanent, irreversible redaction)
- Semi-automated PHI detection: regex patterns against OCR text to identify likely personal data (AMKA patterns, date formats, phone numbers, patient names from database)
- Highlight suggested redaction areas for human confirmation
- Redaction log: who redacted what, when, which areas — supports audit trail
- GDPR Article 89 "appropriate safeguards" for anonymization

### 5.6 Mobile Document Capture

**Implementation:**

- HTML5 camera API: `<input type="file" accept="image/*" capture="environment">`
- Optional edge detection via jscanify (open-source, client-side OpenCV.js): real-time edge detection, automatic perspective correction, image enhancement
- Server pipeline: receive image → ImageMagick deskew and normalize → Tesseract OCR → classify and file
- Primary use case: front-desk staff capturing patient insurance cards and IDs without a flatbed scanner

---

## 6. Workflow Management Features

### 6.1 Task Assignment from Documents

**Features:**

- Create actionable tasks directly from document views: "Follow up with patient about missing insurance card", "Get physician signature on this referral", "Code and submit this claim"
- Tasks link to both the document and the patient record
- Priority levels, due dates, assignment to specific users
- Integration with notification system
- "My Tasks" dashboard widget with overdue alerts

**Database:** `fileshare_tasks` table with `file_id`, `patient_id`, `assigned_to`, `priority`, `due_date`, `status`, `title`, `description`.

### 6.2 Read Receipts & Acknowledgment Tracking

**Regulation:** GDPR Article 7 requires demonstrating consent was obtained. Article 5(2) requires demonstrating compliance (accountability principle).

**Features:**

- Mark documents as "requires acknowledgment" — tracks who has and hasn't read/acknowledged
- Particularly important for: GDPR privacy notices, updated clinical protocols, safety alerts, infection control policies, practice compliance documents
- Automated reminders to non-acknowledgers
- Compliance reports: "Policy XYZ — 45 of 52 staff acknowledged, 86.5%"
- Digital timestamp and IP address recorded with each acknowledgment

### 6.3 Referral Workflow Tracking

**Features:**

- Full referral lifecycle: creation → sending (fax/email/secure link) → tracking status (`sent` → `received` → `appointment_scheduled` → `consultation_completed` → `report_received` → `reviewed`)
- Specialist directory: name, specialty, fax number, email, accepted insurance plans
- Overdue alerts: flag referrals without responses past configurable threshold (default 14 days)
- Metrics: referral volume by specialist, average time to consultation, completion rate

### 6.4 Smart Folders (Virtual Folders)

**Features:**

- Auto-populated based on saved search queries — documents don't physically move
- Pre-built system smart folders: "Unread lab results", "Expiring insurance cards", "Unsigned referrals", "Today's incoming faxes", "Unprocessed inbox items"
- User-created custom smart folders with configurable filter criteria (JSON filter objects stored in database)
- Badge counts on each smart folder for at-a-glance status

### 6.5 Consent Management Dashboard

**Regulation:** GDPR Article 7 requires being able to demonstrate consent was given. Article 7(3) requires consent to be withdrawable.

**Features:**

- Central view of all patient consents: GDPR data processing consent, treatment consent, telehealth consent, research participation consent, EHDS secondary use opt-out
- Track consent status: `given` / `withdrawn` / `expired` / `not_yet_requested`
- Consent expiry monitoring (some practices require annual re-consent)
- Link to signed consent document in file share
- Bulk reporting: which patients need consent renewal, which consents are missing
- EHDS opt-out flag integration: when patient opts out of secondary data use (Art. 10), flag propagates to all their records

---

## 7. Implementation Roadmap

Phased by regulatory urgency and operational impact:

### Phase 0 — Compliance Urgent (Immediate)

- WCAG 2.1 AA / EN 301 549 accessibility (EAA already enforceable)
- Encryption at rest (GDPR breach safe harbor + NIS2)
- Automatic session timeout (NIS2)
- Document integrity verification (NIS2)
- Virus scanning via ClamAV (NIS2)
- Enhanced tamper-proof audit logging (NIS2 + GDPR)

### Phase 1 — GDPR Core (Next)

- GDPR Data Subject Rights engine (access, erasure, restriction, portability)
- 72-hour breach notification support
- Consent management dashboard
- Secure document destruction with certificates
- DPA (processor agreement) tracking
- Read receipts & acknowledgment tracking

### Phase 2 — Daily Workflows

- Document inbox/outbox system
- Patient checklists per visit type
- Electronic signatures (eIDAS SES/AES)
- Document templates with mail merge
- Task assignment from documents
- Fax integration

### Phase 3 — Clinical Integration

- Patient document portal (EHDS preparation)
- Patient upload capabilities
- Medical records request management
- Lab system integration (PDF → HL7 v2 → FHIR path)
- Insurance document tracking
- Referral workflow tracking
- Multi-language document support

### Phase 4 — Intelligence Layer

- OCR via Tesseract (Greek + English)
- Intelligent document routing
- Document packaging
- Document expiry tracking
- Mobile document capture
- Document redaction tools
- Smart folders

### Phase 5 — Advanced Security

- Break-the-glass emergency access
- Document sensitivity classification
- Dynamic watermarking
- EHDS full compliance (2029/2031 deadlines)
- eIDAS QES integration (if required by practice)

---

## Key Regulatory References

| Regulation | Scope | Key Dates |
|-----------|-------|-----------|
| **GDPR** (EU 2016/679) | All personal data, special protections for health data | In force since May 2018 |
| **EHDS** (EU 2025/327) | Electronic health data access and exchange | In force March 2025; primary use March 2029-2031 |
| **eIDAS 2.0** (EU 2024/1183) | Electronic signatures, digital identity | In force May 2024; implementing acts 2025-2026 |
| **NIS2** (EU 2022/2555) | Cybersecurity for essential entities (healthcare) | Transposed October 2024; enforcement ramping 2025-2026 |
| **EAA** (EU 2019/882) | Digital accessibility | **Enforceable since 28 June 2025** |
| **EN 301 549** | Technical accessibility standard (references WCAG 2.1 AA) | Presumptive conformance standard for EAA |

## Technology Stack Notes

All features implementable on ZPMS's vanilla PHP/MySQL stack:

- **Encryption:** PHP Sodium extension (built-in since PHP 7.2)
- **PDF generation/watermarking:** TCPDF + FPDI (single PHP files)
- **Signatures:** signature_pad.js (vanilla JS, MIT license)
- **OCR:** Tesseract CLI
- **Virus scanning:** ClamAV daemon via socket
- **Fax:** REST API to EU-resident cloud fax service (or self-hosted HylaFAX)
- **Mail merge:** PHPWord TemplateProcessor (extractable single class)
- **Image processing:** ImageMagick CLI + GD extension
- **Accessibility testing:** axe-core CLI for automated audits
