# ZPMS File Sharing Module — Implementation Plan v2

## 1. Overview

A Nextcloud-inspired file sharing module for ZPMS with integrated healthcare compliance. Authenticated users manage files/folders through a web UI with drag-and-drop upload, clipboard operations, and sharing via link/code/QR. Public shares can bypass login. All configuration stored in YAML.

**Core scope includes:** file browser with previews and thumbnails, chunked AJAX uploads, folder operations (cut/copy/paste/rename/delete), public and internal sharing with QR codes, file versioning, activity stream, comments, notifications, favorites, recent files, trash management, file locking, patient record linking, quota management, and audit trail export.

**Regulatory compliance scope:** The module implements mandatory safeguards from three regulatory frameworks that converge on healthcare file management:

- **GDPR** (EU 2016/679) — Data protection for all EU patient data. Requires encryption, access controls, audit logging, data subject rights (access, erasure, restriction, portability), breach notification within 72 hours, and data processing agreements.
- **HIPAA** — US healthcare privacy/security rules. Requires automatic session logoff (§164.312(a)(2)(iii)), document integrity verification (§164.312(c)(1)—required, not addressable), audit controls (§164.312(b)), emergency access procedures (§164.312(a)(2)(ii)), and encryption (§164.312(a)(2)(iv)).
- **NIS2** (EU 2022/2555) — Cybersecurity directive classifying healthcare as "essential entity." Requires encryption, virus scanning, access control, incident reporting (24h/72h/1-month cascade), and security logging.
- **EHDS** (EU 2025/327) — European Health Data Space. Grants patients right to access their electronic health data (Art. 6), right to see who accessed it (Art. 9), and right to opt out of secondary use (Art. 10). Full primary data exchange by March 2029.
- **EAA** (EU 2019/882) — European Accessibility Act, enforceable since 28 June 2025. Microenterprise exemption (<10 employees, <€2M turnover) may apply to small practices. Technical standard: EN 301 549 incorporating WCAG 2.1 Level AA.
- **eIDAS 2.0** (EU 2024/1183) — Electronic signatures. SES (Simple Electronic Signature) is sufficient for most medical consents; QES (Qualified Electronic Signature) available via QTSP integration for Member States requiring it.

**Target practice size:** Small practice (1 doctor, 1 secretary/assistant, <10 employees). Larger clinic features are documented in the companion guide `zpms-fileshare-improvements-by-size.md`.

---

## 2. Directory & Filesystem Structure

```
zpms/
├── modules/
│   └── fileshare/
│       ├── fileshare.module.yml          # Module definition
│       ├── fileshare.routing.yml         # Route definitions
│       ├── fileshare.permissions.yml     # Permission definitions
│       │
│       ├── controllers/
│       │   ├── FileShareController.php   # Main file browser UI
│       │   ├── UploadController.php      # Chunked AJAX upload endpoints
│       │   ├── FileOpsController.php     # Cut/copy/paste/rename/delete/mkdir
│       │   ├── ShareController.php       # Create/edit/revoke external shares
│       │   ├── InternalShareController.php # Share with ZPMS users
│       │   ├── PublicShareController.php  # Public share access (no auth)
│       │   ├── DownloadController.php    # Authenticated file/folder download
│       │   ├── PreviewController.php     # Thumbnail & preview serving
│       │   ├── VersionController.php     # File version history & restore
│       │   ├── CommentController.php     # File comment CRUD (AJAX)
│       │   ├── ActivityController.php    # Activity feed endpoints
│       │   ├── NotificationController.php # Notification list & mark-read
│       │   ├── FavoritesController.php   # Toggle & list favorites
│       │   ├── LockController.php        # File lock/unlock
│       │   ├── PatientLinkController.php # Link files ↔ patient records
│       │   ├── TrashController.php       # Trash browser, restore, purge
│       │   ├── AuditController.php       # Compliance audit trail UI + export
│       │   ├── ComplianceController.php  # GDPR requests, breach workflow, DPA registry
│       │   └── SessionController.php     # Session keep-alive + timeout AJAX
│       │
│       ├── services/
│       │   ├── FileShareManager.php      # Core file operations
│       │   ├── ShareManager.php          # External share CRUD, token/code gen
│       │   ├── InternalShareManager.php  # User-to-user sharing + permissions
│       │   ├── ShareAccessValidator.php  # Password, expiry, revocation checks
│       │   ├── QRCodeGenerator.php       # QR code generation (pure PHP + GD)
│       │   ├── PreviewManager.php        # Thumbnail generation & caching
│       │   ├── VersionManager.php        # File versioning engine
│       │   ├── CommentManager.php        # Comment storage & retrieval
│       │   ├── ActivityLogger.php        # Activity event recording
│       │   ├── NotificationManager.php   # Notification dispatch & storage
│       │   ├── LockManager.php           # File locking with timeouts
│       │   ├── PatientLinkManager.php    # File ↔ patient record associations
│       │   ├── AuditExporter.php         # Export access logs as CSV/PDF
│       │   │
│       │   │── # ── Compliance Services ──
│       │   ├── EncryptionManager.php     # File encryption/decryption at rest
│       │   ├── IntegrityVerifier.php     # SHA-256 hash verification on access
│       │   ├── ComplianceAuditLogger.php # Tamper-proof hash-chain audit log
│       │   ├── VirusScanner.php          # ClamAV integration via Unix socket
│       │   ├── SessionGuard.php          # Inactivity timeout + keep-alive
│       │   ├── GDPRManager.php           # Data subject rights workflows
│       │   ├── BreachManager.php         # Breach detection + notification workflow
│       │   └── DestructionManager.php    # Secure file destruction + certificates
│       │
│       ├── lib/                          # Single-file libraries (no Composer)
│       │   ├── phpqrcode.php             # QR code encoder
│       │   ├── Parsedown.php             # Markdown parser
│       │   └── TCPDF/                    # PDF generation (for audit reports, destruction certs)
│       │       └── tcpdf.php
│       │
│       ├── templates/
│       │   ├── file-browser.html.zem     # Main file browser page
│       │   ├── file-row.html.zem         # Single file/folder row (partial)
│       │   ├── upload-zone.html.zem      # Upload area with drag-drop
│       │   ├── share-dialog.html.zem     # External share creation/edit modal
│       │   ├── internal-share-dialog.html.zem # Share with ZPMS users modal
│       │   ├── public-share.html.zem     # Public share landing page
│       │   ├── public-password.html.zem  # Password prompt for protected shares
│       │   ├── breadcrumb.html.zem       # Path breadcrumb navigation
│       │   ├── preview-panel.html.zem    # Side panel / overlay for file preview
│       │   ├── version-history.html.zem  # Version list for a file
│       │   ├── comments-panel.html.zem   # Comments thread for a file
│       │   ├── activity-feed.html.zem    # Activity stream (global & per-file)
│       │   ├── notifications.html.zem    # Notification dropdown/page
│       │   ├── favorites-view.html.zem   # Favorites list view
│       │   ├── recent-view.html.zem      # Recent files view
│       │   ├── trash-browser.html.zem    # Trash bin browser
│       │   ├── details-panel.html.zem    # File details sidebar
│       │   ├── lock-indicator.html.zem   # Lock status badge partial
│       │   ├── patient-link-dialog.html.zem # Link file to patient modal
│       │   ├── audit-export.html.zem     # Audit trail export page
│       │   │
│       │   │── # ── Compliance Templates ──
│       │   ├── audit-dashboard.html.zem  # Compliance audit log viewer
│       │   ├── session-timeout-modal.html.zem # Session expiry warning overlay
│       │   ├── integrity-alert.html.zem  # Integrity violation alert
│       │   ├── gdpr-request-list.html.zem # GDPR request queue
│       │   ├── gdpr-access-package.html.zem # Patient data access package builder
│       │   ├── breach-workflow.html.zem  # Breach assessment + notification workflow
│       │   ├── breach-form.html.zem      # Breach incident form
│       │   ├── dpa-registry.html.zem     # DPA/BAA vendor registry
│       │   └── destruction-cert.html.zem # Destruction certificate template
│       │
│       ├── js/
│       │   ├── file-browser.js           # File browser UI logic
│       │   ├── file-uploader.js          # Chunked upload (from DICOM module)
│       │   ├── file-ops.js               # Clipboard, context menu, rename
│       │   ├── drag-drop.js              # Drag-and-drop zone handling
│       │   ├── share-dialog.js           # Share modal interactions
│       │   ├── preview.js                # In-browser file preview logic
│       │   ├── comments.js               # Comments thread UI
│       │   ├── activity.js               # Activity feed polling/rendering
│       │   ├── notifications.js          # Notification badge + dropdown
│       │   ├── details-panel.js          # Details sidebar toggle + tabs
│       │   ├── version-history.js        # Version list + restore actions
│       │   └── session-guard.js          # Client-side inactivity timer + keep-alive
│       │
│       └── css/
│           └── fileshare.css             # Module-specific styles
│
├── config/
│   └── fileshare/
│       ├── settings.yml                  # Module settings
│       ├── mime-types.yml                # Allowed MIME types & icons mapping
│       ├── preview.yml                   # Preview/thumbnail settings
│       ├── versioning.yml                # Versioning retention settings
│       ├── notifications.yml             # Notification event configuration
│       ├── cron.yml                      # Scheduled tasks
│       │
│       │── # ── Compliance Configuration ──
│       ├── encryption.yml                # Encryption at rest settings
│       ├── audit.yml                     # Audit log settings + retention
│       ├── virus-scan.yml                # ClamAV integration settings
│       └── destruction.yml               # Secure destruction settings
│   │
│   ├── security.yml                      # Session timeout, BTG, access policies
│   ├── gdpr.yml                          # GDPR retention, response deadlines, rights
│   └── breach.yml                        # Breach detection + notification workflow
│
├── files/
│   └── fileshare/
│       ├── storage/                      # Actual file storage root (encrypted at rest)
│       │   └── {user_id}/               # Per-user storage directories
│       │       └── ...                   # User's folder tree
│       ├── versions/                     # Version archive (encrypted)
│       │   └── {user_id}/
│       │       └── {file_id}/
│       │           └── v{n}_{timestamp}_{hash}
│       ├── thumbnails/                   # Generated thumbnail cache
│       │   └── {size}/
│       │       └── {hash}.jpg
│       ├── tmp/                          # Chunked upload temp area
│       │   └── {upload_token}/
│       ├── quarantine/                   # ClamAV quarantined files (outside web root)
│       │   └── {date}_{hash}_{original_name}
│       └── destruction_certs/            # Generated destruction certificate PDFs
│           └── {year}/
│               └── cert_{id}_{date}.pdf
```

---

## 3. Configuration Files

### 3.1 `config/fileshare/settings.yml`

```yaml
fileshare:
  storage_root: "files/fileshare/storage"
  temp_root: "files/fileshare/tmp"
  versions_root: "files/fileshare/versions"
  thumbnails_root: "files/fileshare/thumbnails"
  quarantine_root: "files/fileshare/quarantine"
  destruction_certs_root: "files/fileshare/destruction_certs"
  max_upload_size: 2147483648        # 2 GB
  chunk_size: 5242880                # 5 MB
  allowed_extensions:
    - pdf, doc, docx, xls, xlsx, pptx
    - jpg, jpeg, png, gif, bmp, svg, webp
    - mp4, avi, mov, mkv, mp3, wav
    - zip, rar, 7z, tar, gz
    - txt, csv, xml, json, yml, yaml
    - dcm
  blocked_extensions:
    - php, phtml, phar, sh, bat, exe, com, cmd
  max_filename_length: 255
  temp_cleanup_hours: 24
  trash_retention_days: 30

  sharing:
    enabled: true
    default_expiry_days: 30
    max_expiry_days: 365
    allow_public_shares: true
    allow_password_protection: true
    allow_internal_shares: true
    share_code_length: 8
    token_length: 32
    qr_enabled: true
    qr_size: 300
    max_downloads: 0

  quotas:
    default_user_quota: 10737418240  # 10 GB
    admin_unlimited: true
    count_trash: false

  locking:
    enabled: true
    auto_lock_on_download: false
    default_timeout_minutes: 120
    max_timeout_minutes: 1440
```

### 3.2 `config/fileshare/mime-types.yml`

```yaml
mime_types:
  pdf:   { mime: "application/pdf",    icon: "file-text",   color: "danger",  preview: "pdf"   }
  doc:   { mime: "application/msword", icon: "file-text",   color: "info",    preview: "none"  }
  docx:  { mime: "application/vnd.openxmlformats-officedocument.wordprocessingml.document", icon: "file-text", color: "info", preview: "none" }
  xls:   { mime: "application/vnd.ms-excel", icon: "file-spreadsheet", color: "success", preview: "none" }
  xlsx:  { mime: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", icon: "file-spreadsheet", color: "success", preview: "none" }
  jpg:   { mime: "image/jpeg",         icon: "image",       color: "primary", preview: "image" }
  jpeg:  { mime: "image/jpeg",         icon: "image",       color: "primary", preview: "image" }
  png:   { mime: "image/png",          icon: "image",       color: "primary", preview: "image" }
  gif:   { mime: "image/gif",          icon: "image",       color: "primary", preview: "image" }
  svg:   { mime: "image/svg+xml",      icon: "image",       color: "primary", preview: "image" }
  webp:  { mime: "image/webp",         icon: "image",       color: "primary", preview: "image" }
  mp4:   { mime: "video/mp4",          icon: "film",        color: "warning", preview: "video" }
  mov:   { mime: "video/quicktime",    icon: "film",        color: "warning", preview: "video" }
  mp3:   { mime: "audio/mpeg",         icon: "music",       color: "warning", preview: "audio" }
  wav:   { mime: "audio/wav",          icon: "music",       color: "warning", preview: "audio" }
  zip:   { mime: "application/zip",    icon: "archive",     color: "neutral", preview: "none"  }
  txt:   { mime: "text/plain",         icon: "file-text",   color: "neutral", preview: "text"  }
  csv:   { mime: "text/csv",           icon: "file-text",   color: "neutral", preview: "text"  }
  json:  { mime: "application/json",   icon: "file-code",   color: "neutral", preview: "code"  }
  xml:   { mime: "application/xml",    icon: "file-code",   color: "neutral", preview: "code"  }
  yml:   { mime: "text/yaml",          icon: "file-code",   color: "neutral", preview: "code"  }
  md:    { mime: "text/markdown",      icon: "file-text",   color: "neutral", preview: "markdown" }
  dcm:   { mime: "application/dicom",  icon: "scan",        color: "primary", preview: "dicom" }

default: { mime: "application/octet-stream", icon: "file", color: "neutral", preview: "none" }
```

### 3.3 `config/fileshare/preview.yml`

```yaml
preview:
  thumbnail_sizes: [64, 256, 1024]
  max_preview_filesize: 52428800       # 50 MB
  cache_ttl_days: 90

  image:
    quality: 85
    max_dimension: 2048
    supported: [jpg, jpeg, png, gif, webp, bmp, svg]

  video:
    extract_frame: true
    ffmpeg_path: "/usr/bin/ffmpeg"

  pdf:
    first_page_thumbnail: true

  text:
    max_lines: 200
    syntax_highlight: true

  code:
    languages: [php, js, json, xml, yml, yaml, css, html, sql, py, sh]
```

### 3.4 `config/fileshare/versioning.yml`

```yaml
versioning:
  enabled: true
  max_versions_per_file: 50
  retention_days: 365
  min_interval_seconds: 60
  excluded_extensions: [tmp, log]
  auto_version_on_overwrite: true
```

### 3.5 `config/fileshare/notifications.yml`

```yaml
notifications:
  enabled: true
  poll_interval_seconds: 30
  retention_days: 90
  max_unread: 100

  events:
    share_downloaded:
      enabled: true
      message: "{user} downloaded your shared file '{filename}'"
    share_expiring:
      enabled: true
      days_before: [7, 1]
      message: "Your share for '{filename}' expires in {days} day(s)"
    file_commented:
      enabled: true
      message: "{user} commented on '{filename}'"
    internal_share_received:
      enabled: true
      message: "{user} shared '{filename}' with you"
    version_created:
      enabled: false
    lock_released:
      enabled: true
      message: "Lock on '{filename}' was released"
    quota_warning:
      enabled: true
      threshold_percent: 90
      message: "Storage usage at {percent}% — consider freeing space"
    # ── Compliance Events ──
    integrity_violation:
      enabled: true
      priority: critical
      message: "INTEGRITY ALERT: File '{filename}' failed verification — access blocked"
    virus_detected:
      enabled: true
      priority: critical
      message: "VIRUS DETECTED: Upload '{filename}' quarantined — {threat_name}"
    session_timeout:
      enabled: true
      message: "Your session was terminated due to inactivity"
    gdpr_request_received:
      enabled: true
      message: "New GDPR data request from patient {patient_name} — 30-day deadline"
    gdpr_request_deadline:
      enabled: true
      days_before: [7, 3, 1]
      message: "GDPR request #{request_id} deadline in {days} day(s)"
    breach_alert:
      enabled: true
      priority: critical
      message: "SECURITY INCIDENT: {description} — breach assessment required"
    dpa_expiring:
      enabled: true
      days_before: [30, 7]
      message: "DPA/BAA with '{vendor}' expires in {days} day(s)"
    destruction_completed:
      enabled: true
      message: "File '{filename}' permanently destroyed — certificate #{cert_id}"
```

### 3.6 `config/fileshare/cron.yml`

```yaml
cron_jobs:
  # ── Core Housekeeping ──
  cleanup_temp_uploads:
    schedule: "0 */4 * * *"
    handler: "UploadController::cleanupExpired"
    description: "Remove expired upload sessions and temp chunks"

  cleanup_trash:
    schedule: "0 2 * * *"
    handler: "FileShareManager::cleanupTrash"
    description: "Permanently delete items in trash past retention period"

  cleanup_expired_shares:
    schedule: "0 */6 * * *"
    handler: "ShareManager::cleanupExpired"
    description: "Deactivate expired external shares"

  share_expiry_notifications:
    schedule: "0 8 * * *"
    handler: "NotificationManager::checkShareExpiry"
    description: "Notify users about shares expiring soon"

  cleanup_expired_locks:
    schedule: "*/15 * * * *"
    handler: "LockManager::cleanupExpired"
    description: "Release expired file locks"

  cleanup_versions:
    schedule: "0 3 * * 0"
    handler: "VersionManager::cleanupOldVersions"
    description: "Remove versions exceeding max count or retention period"

  cleanup_thumbnails:
    schedule: "0 4 * * 0"
    handler: "PreviewManager::cleanupOrphanedThumbnails"
    description: "Remove thumbnails for deleted files"

  cleanup_notifications:
    schedule: "0 5 1 * *"
    handler: "NotificationManager::cleanup"
    description: "Remove old notifications past retention period"

  cleanup_activity:
    schedule: "0 5 1 * *"
    handler: "ActivityLogger::cleanup"
    description: "Remove old activity records"

  quota_warnings:
    schedule: "0 9 * * 1"
    handler: "NotificationManager::checkQuotas"
    description: "Warn users approaching storage quota"

  # ── Compliance Crons ──
  integrity_batch_verify:
    schedule: "0 1 * * 0"
    handler: "IntegrityVerifier::batchVerifyAll"
    description: "Weekly full integrity check of all stored files — compare SHA-256 hashes against database records. Report mismatches via notification + email to admin. HIPAA §164.312(c)(1)."

  virus_definitions_update:
    schedule: "0 */6 * * *"
    handler: "VirusScanner::updateDefinitions"
    description: "Update ClamAV virus definitions via freshclam. NIS2 Art. 21."

  audit_summary_email:
    schedule: "0 7 * * 1"
    handler: "ComplianceAuditLogger::sendWeeklySummary"
    description: "Weekly audit summary email to practice owner — satisfies HIPAA §164.308(a)(1)(ii)(D) periodic review requirement. Includes: login/logout counts, failed login attempts, file access statistics, any integrity violations, any virus detections."

  gdpr_deadline_check:
    schedule: "0 8 * * *"
    handler: "GDPRManager::checkDeadlines"
    description: "Daily check for approaching GDPR Art. 15 response deadlines (30 days). Sends escalating notifications at 7, 3, 1 day(s) remaining."

  dpa_expiry_check:
    schedule: "0 8 1 * *"
    handler: "GDPRManager::checkDPAExpiry"
    description: "Monthly check for DPA/BAA agreements approaching expiry. Alerts at 30 and 7 days before."

  audit_log_partition:
    schedule: "0 0 1 1 *"
    handler: "ComplianceAuditLogger::archiveOldEntries"
    description: "Annual archival of audit entries older than retention period (6 years). Entries are exported to compressed CSV then removed from active table. Archive files retained indefinitely."

  quarantine_cleanup:
    schedule: "0 3 1 * *"
    handler: "VirusScanner::cleanupQuarantine"
    description: "Monthly cleanup of quarantined files older than 90 days. Generates destruction certificates for each removed file."
```

### 3.7 `config/fileshare/encryption.yml`

```yaml
# Encryption at Rest
# Regulation: GDPR Art. 32 + Art. 34(3)(a) | NIS2 Art. 21 | HIPAA §164.312(a)(2)(iv)
#
# When encryption is enabled, all files written to storage_root and versions_root
# are encrypted using PHP Sodium's XSalsa20-Poly1305 authenticated encryption.
# Each file gets a unique random nonce stored in the database.
# The encryption key is stored outside the web root at the path specified below.
#
# Key generation (run once during installation):
#   php -r "file_put_contents('/etc/zpms/encryption.key', sodium_crypto_secretbox_keygen());"
#   chmod 600 /etc/zpms/encryption.key
#   chown www-data:www-data /etc/zpms/encryption.key
#
# IMPORTANT: Back up the encryption key separately from the database and storage.
# Without the key, encrypted files are permanently unrecoverable.

encryption:
  enabled: true
  algorithm: sodium_secretbox          # XSalsa20-Poly1305 authenticated encryption
  key_file: "/etc/zpms/encryption.key" # 256-bit key, chmod 600, owned by web server user
  encrypt_on_upload: true              # Encrypt immediately after virus scan + integrity hash
  encrypt_versions: true               # Also encrypt version snapshots
  encrypt_thumbnails: false            # Thumbnails are derived data, not PHI — skip for performance

  # Backup encryption (for MySQL dumps)
  backup_encryption:
    enabled: true
    method: "openssl_aes256cbc"        # openssl enc -aes-256-cbc
    key_file: "/etc/zpms/backup.key"   # Separate key for backups
```

### 3.8 `config/security.yml`

```yaml
# Session Security & Access Controls
# Regulation: HIPAA §164.312(a)(2)(iii) | NIS2 Art. 21 | GDPR Art. 32

session:
  timeout_minutes: 15                  # HIPAA standard for clinical systems
  warning_before_seconds: 120          # Show warning modal 2 min before expiry
  extend_on_activity: true             # Reset timer on user interaction (click, key, mouse)
  keepalive_endpoint: "/session/keepalive"
  log_timeouts: true                   # Log session timeouts in audit trail
  max_sessions_per_user: 3             # Prevent session sharing
  # Note: For a 2-person practice, 15 minutes is recommended.
  # Unattended screens displaying patient data are a common GDPR/HIPAA violation.

# Break-the-Glass Emergency Access
# Regulation: HIPAA §164.312(a)(2)(ii) | GDPR Art. 9(2)(c)
# Small practice: optional — all users typically have full access.
# Larger clinic: required — must be enabled when RBAC restricts file access.
break_the_glass:
  enabled: false                       # Enable for larger clinics with RBAC
  duration_hours: 4
  max_duration_hours: 8
  require_justification: true
  require_approval: false
  notify: [practice_manager, primary_physician]
  review_deadline_hours: 48

# Failed Login Protection
login_protection:
  max_failed_attempts: 5
  lockout_minutes: 15
  log_all_attempts: true               # Every login attempt logged in audit trail
  alert_on_suspicious: true            # Alert admin after 3+ failed attempts
```

### 3.9 `config/gdpr.yml`

```yaml
# GDPR Data Subject Rights & Retention
# Regulation: GDPR Art. 5, 15-22, 30 | EHDS Art. 6, 9, 10

retention:
  # Minimum retention periods by document type.
  # These OVERRIDE patient erasure requests — medical records cannot be deleted
  # before the retention period expires per national healthcare law.
  medical_records_years: 10            # Greece default; 20 for Netherlands, 10 for Germany
  billing_records_years: 10
  consent_records_years: 6
  non_clinical_years: 3                # Marketing, non-medical correspondence
  audit_logs_years: 6                  # HIPAA minimum: 6 years

# Data Subject Request Handling
data_subject_requests:
  response_deadline_days: 30           # GDPR Art. 12(3): "without undue delay and within one month"
  auto_reject_medical_erasure: true    # Automatically reject Art. 17 requests for medical records
  rejection_reason: "Erasure request rejected under GDPR Art. 17(3)(c): processing necessary for reasons of public interest in the area of public health, and under national medical record retention law requiring retention for {years} years."
  access_package_format: zip           # Art. 15 access response: ZIP with files + JSON manifest + cover letter PDF
  access_package_include_audit: true   # Include audit trail of who accessed the patient's files

# EHDS Opt-Out
ehds:
  secondary_use_opt_out_enabled: true  # Allow patients to opt out of secondary data use (Art. 10)
  opt_out_field: "ehds_secondary_use_opt_out"  # Boolean field on patient record

# DPA/BAA Tracking
dpa_tracking:
  enabled: true
  alert_days_before_expiry: [30, 7]    # Notification at 30 and 7 days before DPA expires
```

### 3.10 `config/fileshare/audit.yml`

```yaml
# Compliance Audit Logging
# Regulation: HIPAA §164.312(b) | HIPAA §164.308(a)(1)(ii)(D) | GDPR Art. 30 | NIS2 Art. 21 | EHDS Art. 9

audit:
  enabled: true
  hash_chain: true                     # Each entry includes SHA-256 hash of itself + previous entry
  retention_years: 6                   # HIPAA minimum; most EU states align with 6-10 years
  weekly_summary_email: true           # Send weekly audit summary to admin — satisfies HIPAA periodic review
  summary_recipients: ["admin"]        # User roles or specific emails
  separate_db_user: true               # Use INSERT+SELECT-only DB user for tamper-proofing

  # What to log (all enabled by default for healthcare compliance)
  log_events:
    authentication: true               # login, logout, failed_login, session_timeout
    file_operations: true              # upload, download, preview, print, rename, move, copy, delete, restore
    sharing: true                      # share_create, share_access, share_revoke
    versioning: true                   # version_create, version_restore
    comments: true                     # comment_create (not content — just the fact)
    locking: true                      # lock_acquire, lock_release
    patient_links: true                # link_create, link_remove
    permissions: true                  # permission_change, config_change
    compliance: true                   # integrity_violation, emergency_access, gdpr_request, destruction

  # Archive settings
  archive:
    format: csv_gzip                   # Compressed CSV for long-term storage
    archive_path: "files/fileshare/audit_archive/"
    encrypt_archives: true             # Encrypt archived audit logs
```

### 3.11 `config/fileshare/virus-scan.yml`

```yaml
# Virus Scanning — ClamAV Integration
# Regulation: NIS2 Art. 21 — malware prevention

virus_scan:
  enabled: true
  scanner: clamav                      # Only supported scanner
  socket_path: "/var/run/clamav/clamd.ctl"  # Unix socket to clamd daemon
  scan_on_upload: true                 # Scan every uploaded file after chunk assembly
  max_scan_size: 209715200             # 200 MB — skip scanning files larger than this
  quarantine_root: "files/fileshare/quarantine"
  quarantine_retention_days: 90        # Keep quarantined files for admin review

  # Actions on detection
  on_detect:
    quarantine: true                   # Move infected file to quarantine
    notify_admin: true                 # Send critical notification to admin
    notify_uploader: true              # Inform the uploader their file was rejected
    log_audit: true                    # Log in compliance audit trail
    block_upload: true                 # Reject the upload entirely

  # Freshclam (definition updates)
  freshclam:
    schedule: "0 */6 * * *"           # Every 6 hours
    command: "/usr/bin/freshclam"
    log_updates: true
```

### 3.12 `config/fileshare/destruction.yml`

```yaml
# Secure Document Destruction
# Regulation: GDPR Art. 17 + Art. 5(1)(e) | HIPAA §164.310(d)(2)(i)

destruction:
  method: shred                        # Use shred(1) for secure deletion
  shred_passes: 3                      # -n 3 = three overwrite passes
  shred_command: "shred -vfz -n 3"     # Verbose, force, zero-fill final pass
  generate_certificate: true           # Auto-generate PDF Certificate of Destruction
  certificate_template: "destruction-cert.html.zem"
  certificates_root: "files/fileshare/destruction_certs"
  certificate_retention_years: 6       # Keep destruction records for HIPAA retention period
  require_legal_hold_check: true       # Verify no legal hold before destruction
  log_audit: true                      # Log every destruction event in compliance audit trail
```

---

## 4. Database Schema

### 4.1 `fileshare_files`

Tracks all files/folders with metadata. Actual files live on disk, encrypted at rest.

```sql
CREATE TABLE fileshare_files (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           INT UNSIGNED NOT NULL,
    parent_id         INT UNSIGNED NULL,
    name              VARCHAR(255) NOT NULL,
    type              ENUM('file','folder') NOT NULL,
    mime_type         VARCHAR(127) NULL,
    size              BIGINT UNSIGNED DEFAULT 0,
    disk_path         VARCHAR(1024) NULL,
    -- Integrity & encryption (HIPAA §164.312(c)(1), GDPR Art. 32)
    file_hash         VARCHAR(64) NOT NULL DEFAULT '',     -- SHA-256 of plaintext content
    hash_algorithm    VARCHAR(10) NOT NULL DEFAULT 'sha256',
    encryption_nonce  VARBINARY(24) NULL,                  -- Sodium nonce, NULL if unencrypted
    is_encrypted      TINYINT(1) DEFAULT 0,
    -- Compliance metadata
    sensitivity       ENUM('normal','sensitive','restricted') DEFAULT 'normal',
    legal_hold        TINYINT(1) DEFAULT 0,                -- Prevent deletion/destruction
    retention_until   DATE NULL,                           -- Earliest allowed destruction date
    -- User-facing state
    is_favorited      TINYINT(1) DEFAULT 0,
    is_trashed        TINYINT(1) DEFAULT 0,
    trashed_at        DATETIME NULL,
    last_accessed     DATETIME NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by        INT UNSIGNED NOT NULL,
    INDEX idx_user_parent (user_id, parent_id),
    INDEX idx_trashed (is_trashed, trashed_at),
    INDEX idx_favorites (user_id, is_favorited),
    INDEX idx_recent (user_id, last_accessed),
    INDEX idx_hash (file_hash),
    INDEX idx_retention (retention_until),
    CONSTRAINT fk_parent FOREIGN KEY (parent_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.2 `fileshare_shares`

External share records.

```sql
CREATE TABLE fileshare_shares (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id         INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED NOT NULL,
    share_token     VARCHAR(64) NOT NULL UNIQUE,
    share_code      VARCHAR(16) NULL UNIQUE,
    share_type      ENUM('link','code') DEFAULT 'link',
    password_hash   VARCHAR(255) NULL,
    permissions     SET('view','download','upload') DEFAULT 'view,download',
    expires_at      DATETIME NULL,
    max_downloads   INT UNSIGNED DEFAULT 0,
    download_count  INT UNSIGNED DEFAULT 0,
    is_active       TINYINT(1) DEFAULT 1,
    is_public       TINYINT(1) DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at      DATETIME NULL,
    INDEX idx_token (share_token),
    INDEX idx_code (share_code),
    INDEX idx_file (file_id),
    INDEX idx_active_expiry (is_active, expires_at),
    CONSTRAINT fk_share_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.3 `fileshare_internal_shares`

Shares between ZPMS users with permission levels.

```sql
CREATE TABLE fileshare_internal_shares (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id         INT UNSIGNED NOT NULL,
    owner_id        INT UNSIGNED NOT NULL,
    target_user_id  INT UNSIGNED NOT NULL,
    permission      ENUM('viewer','editor','uploader') DEFAULT 'viewer',
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    revoked_at      DATETIME NULL,
    INDEX idx_file (file_id),
    INDEX idx_target (target_user_id, is_active),
    INDEX idx_owner (owner_id),
    CONSTRAINT fk_intshare_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.4 `fileshare_share_access_log`

Audit trail for all share access.

```sql
CREATE TABLE fileshare_share_access_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    share_id    INT UNSIGNED NOT NULL,
    share_type  ENUM('external','internal') DEFAULT 'external',
    user_id     INT UNSIGNED NULL,
    ip_address  VARCHAR(45) NOT NULL,
    user_agent  VARCHAR(512) NULL,
    action      ENUM('view','download','upload','password_attempt','password_fail') NOT NULL,
    accessed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_share (share_id, accessed_at),
    INDEX idx_action (action, accessed_at)
);
```

### 4.5 `fileshare_uploads`

Active chunked upload sessions.

```sql
CREATE TABLE fileshare_uploads (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    upload_token   VARCHAR(64) NOT NULL UNIQUE,
    user_id        INT UNSIGNED NOT NULL,
    parent_id      INT UNSIGNED NULL,
    filename       VARCHAR(255) NOT NULL,
    filesize       BIGINT UNSIGNED NOT NULL,
    chunk_size     INT UNSIGNED NOT NULL,
    total_chunks   INT UNSIGNED NOT NULL,
    chunks_received INT UNSIGNED DEFAULT 0,
    status         ENUM('active','complete','failed','expired','quarantined') DEFAULT 'active',
    scan_result    VARCHAR(255) NULL,           -- ClamAV result if virus detected
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (upload_token),
    INDEX idx_status (status, created_at)
);
```

### 4.6 `fileshare_versions`

File version history.

```sql
CREATE TABLE fileshare_versions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id         INT UNSIGNED NOT NULL,
    version_number  INT UNSIGNED NOT NULL,
    size            BIGINT UNSIGNED NOT NULL,
    file_hash       CHAR(64) NOT NULL,          -- SHA-256 of version content
    disk_path       VARCHAR(1024) NOT NULL,
    encryption_nonce VARBINARY(24) NULL,         -- Sodium nonce if encrypted
    created_by      INT UNSIGNED NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file_version (file_id, version_number),
    CONSTRAINT fk_version_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.7 `fileshare_comments`

Threaded comments on files.

```sql
CREATE TABLE fileshare_comments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id     INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NOT NULL,
    parent_id   INT UNSIGNED NULL,
    body        TEXT NOT NULL,
    is_deleted  TINYINT(1) DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_file (file_id, created_at),
    INDEX idx_parent (parent_id),
    CONSTRAINT fk_comment_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE,
    CONSTRAINT fk_comment_parent FOREIGN KEY (parent_id) REFERENCES fileshare_comments(id) ON DELETE CASCADE
);
```

### 4.8 `fileshare_activity`

Activity stream events.

```sql
CREATE TABLE fileshare_activity (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    file_id     INT UNSIGNED NULL,
    action      VARCHAR(50) NOT NULL,
    details     TEXT NULL,
    ip_address  VARCHAR(45) NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id, created_at),
    INDEX idx_file (file_id, created_at),
    INDEX idx_action (action, created_at),
    INDEX idx_global (created_at)
);
```

### 4.9 `fileshare_notifications`

User notification queue.

```sql
CREATE TABLE fileshare_notifications (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    type        VARCHAR(50) NOT NULL,
    priority    ENUM('normal','high','critical') DEFAULT 'normal',
    title       VARCHAR(255) NOT NULL,
    body        TEXT NULL,
    link        VARCHAR(512) NULL,
    is_read     TINYINT(1) DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    read_at     DATETIME NULL,
    INDEX idx_user_unread (user_id, is_read, created_at),
    INDEX idx_type (type, created_at),
    INDEX idx_priority (priority, created_at)
);
```

### 4.10 `fileshare_locks`

File locking.

```sql
CREATE TABLE fileshare_locks (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id     INT UNSIGNED NOT NULL UNIQUE,
    user_id     INT UNSIGNED NOT NULL,
    lock_type   ENUM('manual','auto') DEFAULT 'manual',
    reason      VARCHAR(255) NULL,
    locked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at  DATETIME NOT NULL,
    INDEX idx_file (file_id),
    INDEX idx_expiry (expires_at),
    CONSTRAINT fk_lock_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.11 `fileshare_patient_links`

Association between files and patient records.

```sql
CREATE TABLE fileshare_patient_links (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id     INT UNSIGNED NOT NULL,
    patient_id  INT UNSIGNED NOT NULL,
    link_type   VARCHAR(50) DEFAULT 'general',
    notes       VARCHAR(512) NULL,
    linked_by   INT UNSIGNED NOT NULL,
    linked_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_file (file_id),
    INDEX idx_patient (patient_id, link_type),
    CONSTRAINT fk_plink_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

### 4.12 `fileshare_audit_log` *(NEW — Compliance)*

Tamper-proof compliance audit log with hash chain. Uses a separate database user with INSERT+SELECT only — no UPDATE, no DELETE — to prevent tampering.

**Regulation:** HIPAA §164.312(b), §164.308(a)(1)(ii)(D) | GDPR Art. 30 | NIS2 Art. 21 | EHDS Art. 9

```sql
-- Create dedicated audit DB user (run once during installation):
-- CREATE USER 'zpms_audit'@'localhost' IDENTIFIED BY '...';
-- GRANT INSERT, SELECT ON zpms.fileshare_audit_log TO 'zpms_audit'@'localhost';
-- NO UPDATE, NO DELETE — tamper-proof by design

CREATE TABLE fileshare_audit_log (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prev_hash     VARCHAR(64) NOT NULL DEFAULT '',
    event_hash    VARCHAR(64) NOT NULL DEFAULT '',
    timestamp     DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
    user_id       INT UNSIGNED NOT NULL,
    action        ENUM(
        'login','logout','login_failed','session_timeout',
        'file_upload','file_download','file_preview','file_print',
        'file_rename','file_move','file_copy','file_delete','file_restore',
        'file_share_create','file_share_access','file_share_revoke',
        'file_version_create','file_version_restore',
        'file_comment','file_lock','file_unlock',
        'patient_link_create','patient_link_remove',
        'permission_change','config_change',
        'integrity_violation','emergency_access',
        'virus_detected','virus_quarantine',
        'gdpr_access_request','gdpr_erasure_request','gdpr_erasure_rejected',
        'gdpr_restriction_applied','gdpr_package_generated',
        'destruction_executed','destruction_cert_generated',
        'breach_detected','breach_reported',
        'export','bulk_operation'
    ) NOT NULL,
    resource_type ENUM('file','folder','share','user','system','patient','vendor') NOT NULL,
    resource_id   INT UNSIGNED DEFAULT NULL,
    patient_id    INT UNSIGNED DEFAULT NULL,
    ip_address    VARCHAR(45) NOT NULL,
    user_agent    VARCHAR(255) DEFAULT NULL,
    details       JSON DEFAULT NULL,
    INDEX idx_timestamp (timestamp),
    INDEX idx_user (user_id, timestamp),
    INDEX idx_action (action, timestamp),
    INDEX idx_patient (patient_id, timestamp),
    INDEX idx_resource (resource_type, resource_id)
) ENGINE=InnoDB;
```

**Hash chain implementation (in `ComplianceAuditLogger`):**

```php
// On each new log entry:
$lastEntry = $auditDb->query(
    "SELECT event_hash FROM fileshare_audit_log ORDER BY id DESC LIMIT 1"
);
$prevHash = $lastEntry ? $lastEntry['event_hash'] : str_repeat('0', 64);
$entryData = json_encode([
    $timestamp, $userId, $action, $resourceType, $resourceId, $patientId, $details
]);
$eventHash = hash('sha256', $prevHash . $entryData);
// INSERT with prev_hash and event_hash
```

This hash chain allows post-hoc verification that no audit entries have been inserted, modified, or deleted. Any break in the chain is detectable by iterating forward from the first entry and recomputing hashes.

### 4.13 `fileshare_gdpr_requests` *(NEW — Compliance)*

Tracks GDPR data subject rights requests with deadline enforcement.

```sql
CREATE TABLE fileshare_gdpr_requests (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    request_type    ENUM('access','erasure','restriction','portability','objection') NOT NULL,
    status          ENUM('received','in_progress','completed','rejected','expired') DEFAULT 'received',
    received_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deadline_at     DATETIME NOT NULL,             -- received_at + 30 days
    completed_at    DATETIME NULL,
    rejection_reason TEXT NULL,                     -- e.g., medical retention law
    response_details JSON NULL,                     -- package contents, affected files list
    handled_by      INT UNSIGNED NULL,              -- Staff user who processed the request
    notes           TEXT NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_status (status),
    INDEX idx_deadline (deadline_at, status)
);
```

### 4.14 `fileshare_breach_incidents` *(NEW — Compliance)*

Tracks security breach incidents with regulatory notification deadlines.

```sql
CREATE TABLE fileshare_breach_incidents (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    detected_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    detected_by       ENUM('system','user') DEFAULT 'system',
    reported_by       INT UNSIGNED NULL,             -- User who reported or system
    incident_type     ENUM(
        'integrity_violation','unauthorized_access','data_exposure',
        'malware','lost_device','phishing','other'
    ) NOT NULL,
    severity          ENUM('low','medium','high','critical') DEFAULT 'medium',
    status            ENUM('detected','assessing','contained','notified_dpa','notified_patients','resolved') DEFAULT 'detected',
    description       TEXT NOT NULL,
    affected_files    JSON NULL,                     -- Array of file_ids
    affected_patients JSON NULL,                     -- Array of patient_ids
    affected_count    INT UNSIGNED DEFAULT 0,        -- Number of affected patients
    -- Regulatory deadlines
    nis2_early_warning_at   DATETIME NULL,           -- 24h deadline (NIS2 Art. 23)
    gdpr_dpa_notify_at      DATETIME NULL,           -- 72h deadline (GDPR Art. 33)
    nis2_full_report_at     DATETIME NULL,           -- 72h deadline (NIS2 Art. 23)
    nis2_final_report_at    DATETIME NULL,           -- 1 month deadline (NIS2 Art. 23)
    hipaa_notify_at         DATETIME NULL,           -- 60 days (HIPAA §164.408)
    -- Actual notification timestamps
    dpa_notified_at         DATETIME NULL,
    patients_notified_at    DATETIME NULL,
    remediation_notes       TEXT NULL,
    resolved_at             DATETIME NULL,
    INDEX idx_status (status),
    INDEX idx_detected (detected_at),
    INDEX idx_severity (severity, status)
);
```

### 4.15 `fileshare_dpa_registry` *(NEW — Compliance)*

Data Processing Agreement / Business Associate Agreement vendor registry.

```sql
CREATE TABLE fileshare_dpa_registry (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    vendor_name     VARCHAR(255) NOT NULL,
    service_type    VARCHAR(255) NOT NULL,          -- e.g., "cloud hosting", "fax gateway"
    data_types      VARCHAR(512) NULL,              -- e.g., "patient files, audit logs"
    dpa_signed_at   DATE NOT NULL,
    dpa_expires_at  DATE NULL,
    dpa_file_id     INT UNSIGNED NULL,              -- Link to scanned DPA document in fileshare
    contact_email   VARCHAR(255) NULL,
    contact_phone   VARCHAR(50) NULL,
    notes           TEXT NULL,
    is_active       TINYINT(1) DEFAULT 1,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_expiry (dpa_expires_at, is_active),
    INDEX idx_active (is_active)
);
```

### 4.16 `fileshare_destruction_log` *(NEW — Compliance)*

Permanent record of file destructions with certificate references.

```sql
CREATE TABLE fileshare_destruction_log (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    original_file_id  INT UNSIGNED NULL,             -- May be NULL if file record also purged
    original_name     VARCHAR(255) NOT NULL,
    original_hash     VARCHAR(64) NOT NULL,          -- SHA-256 of file at time of destruction
    original_size     BIGINT UNSIGNED NOT NULL,
    patient_id        INT UNSIGNED NULL,
    destruction_method VARCHAR(50) DEFAULT 'shred',
    certificate_path  VARCHAR(1024) NULL,            -- Path to destruction certificate PDF
    destroyed_by      INT UNSIGNED NOT NULL,
    destroyed_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason            VARCHAR(255) NULL,             -- e.g., "retention expired", "GDPR erasure", "manual"
    INDEX idx_destroyed_at (destroyed_at),
    INDEX idx_patient (patient_id)
);
```

---

## 5. Routing

### `fileshare.routing.yml`

```yaml
# ══════════════════════════════════════════
#  Authenticated UI Pages
# ══════════════════════════════════════════

fileshare.browser:
  path: "/files"
  controller: "FileShareController::browser"
  auth: required

fileshare.browser.subfolder:
  path: "/files/browse/{folder_id}"
  controller: "FileShareController::browser"
  auth: required

fileshare.favorites:
  path: "/files/favorites"
  controller: "FavoritesController::index"
  auth: required

fileshare.recent:
  path: "/files/recent"
  controller: "FileShareController::recent"
  auth: required

fileshare.trash:
  path: "/files/trash"
  controller: "TrashController::index"
  auth: required

fileshare.shared_with_me:
  path: "/files/shared-with-me"
  controller: "InternalShareController::sharedWithMe"
  auth: required

fileshare.shared_by_me:
  path: "/files/shared-by-me"
  controller: "InternalShareController::sharedByMe"
  auth: required

fileshare.activity:
  path: "/files/activity"
  controller: "ActivityController::index"
  auth: required

fileshare.audit:
  path: "/files/audit"
  controller: "AuditController::index"
  auth: required
  permission: "fileshare.admin"

fileshare.audit.export:
  path: "/files/audit/export"
  controller: "AuditController::export"
  method: POST
  auth: required
  permission: "fileshare.admin"

# ══════════════════════════════════════════
#  File Operations (AJAX)
# ══════════════════════════════════════════

fileshare.ops.mkdir:
  path: "/files/api/mkdir"
  controller: "FileOpsController::mkdir"
  method: POST
  auth: required

fileshare.ops.rename:
  path: "/files/api/rename"
  controller: "FileOpsController::rename"
  method: POST
  auth: required

fileshare.ops.move:
  path: "/files/api/move"
  controller: "FileOpsController::move"
  method: POST
  auth: required

fileshare.ops.copy:
  path: "/files/api/copy"
  controller: "FileOpsController::copy"
  method: POST
  auth: required

fileshare.ops.delete:
  path: "/files/api/delete"
  controller: "FileOpsController::delete"
  method: POST
  auth: required

# ── Trash ──
fileshare.ops.trash.restore:
  path: "/files/api/trash/restore"
  controller: "TrashController::restore"
  method: POST
  auth: required

fileshare.ops.trash.purge:
  path: "/files/api/trash/purge"
  controller: "TrashController::purge"
  method: POST
  auth: required

fileshare.ops.trash.empty:
  path: "/files/api/trash/empty"
  controller: "TrashController::emptyTrash"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  Upload (AJAX, chunked)
# ══════════════════════════════════════════

fileshare.upload.init:
  path: "/files/upload/init"
  controller: "UploadController::init"
  method: POST
  auth: required

fileshare.upload.chunk:
  path: "/files/upload/chunk"
  controller: "UploadController::chunk"
  method: POST
  auth: required

fileshare.upload.finalize:
  path: "/files/upload/finalize"
  controller: "UploadController::finalize"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  Download
# ══════════════════════════════════════════

fileshare.download:
  path: "/files/download/{file_id}"
  controller: "DownloadController::download"
  auth: required

fileshare.download.zip:
  path: "/files/download-zip"
  controller: "DownloadController::downloadZip"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  Preview & Thumbnails
# ══════════════════════════════════════════

fileshare.preview.thumbnail:
  path: "/files/preview/thumb/{file_id}/{size}"
  controller: "PreviewController::thumbnail"
  auth: required

fileshare.preview.full:
  path: "/files/preview/{file_id}"
  controller: "PreviewController::full"
  auth: required

# ══════════════════════════════════════════
#  File Versioning
# ══════════════════════════════════════════

fileshare.versions.list:
  path: "/files/api/versions/{file_id}"
  controller: "VersionController::list"
  auth: required

fileshare.versions.restore:
  path: "/files/api/versions/restore"
  controller: "VersionController::restore"
  method: POST
  auth: required

fileshare.versions.download:
  path: "/files/api/versions/download/{version_id}"
  controller: "VersionController::download"
  auth: required

fileshare.versions.delete:
  path: "/files/api/versions/delete"
  controller: "VersionController::delete"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  Comments
# ══════════════════════════════════════════

fileshare.comments.list:
  path: "/files/api/comments/{file_id}"
  controller: "CommentController::list"
  auth: required

fileshare.comments.create:
  path: "/files/api/comments/create"
  controller: "CommentController::create"
  method: POST
  auth: required

fileshare.comments.update:
  path: "/files/api/comments/update"
  controller: "CommentController::update"
  method: POST
  auth: required

fileshare.comments.delete:
  path: "/files/api/comments/delete"
  controller: "CommentController::delete"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  Favorites
# ══════════════════════════════════════════

fileshare.favorites.toggle:
  path: "/files/api/favorites/toggle"
  controller: "FavoritesController::toggle"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  File Locking
# ══════════════════════════════════════════

fileshare.lock.acquire:
  path: "/files/api/lock/acquire"
  controller: "LockController::acquire"
  method: POST
  auth: required

fileshare.lock.release:
  path: "/files/api/lock/release"
  controller: "LockController::release"
  method: POST
  auth: required

fileshare.lock.status:
  path: "/files/api/lock/status/{file_id}"
  controller: "LockController::status"
  auth: required

# ══════════════════════════════════════════
#  Patient Links
# ══════════════════════════════════════════

fileshare.patient.link:
  path: "/files/api/patient/link"
  controller: "PatientLinkController::link"
  method: POST
  auth: required

fileshare.patient.unlink:
  path: "/files/api/patient/unlink"
  controller: "PatientLinkController::unlink"
  method: POST
  auth: required

fileshare.patient.files:
  path: "/files/api/patient/files/{patient_id}"
  controller: "PatientLinkController::filesByPatient"
  auth: required

fileshare.patient.links:
  path: "/files/api/patient/links/{file_id}"
  controller: "PatientLinkController::linksByFile"
  auth: required

# ══════════════════════════════════════════
#  Activity Feed (AJAX)
# ══════════════════════════════════════════

fileshare.activity.feed:
  path: "/files/api/activity"
  controller: "ActivityController::feed"
  auth: required

fileshare.activity.file:
  path: "/files/api/activity/{file_id}"
  controller: "ActivityController::fileActivity"
  auth: required

# ══════════════════════════════════════════
#  Notifications (AJAX)
# ══════════════════════════════════════════

fileshare.notifications.list:
  path: "/files/api/notifications"
  controller: "NotificationController::list"
  auth: required

fileshare.notifications.count:
  path: "/files/api/notifications/count"
  controller: "NotificationController::unreadCount"
  auth: required

fileshare.notifications.read:
  path: "/files/api/notifications/read"
  controller: "NotificationController::markRead"
  method: POST
  auth: required

fileshare.notifications.read_all:
  path: "/files/api/notifications/read-all"
  controller: "NotificationController::markAllRead"
  method: POST
  auth: required

# ══════════════════════════════════════════
#  External Sharing (AJAX)
# ══════════════════════════════════════════

fileshare.share.create:
  path: "/files/api/share/create"
  controller: "ShareController::create"
  method: POST
  auth: required

fileshare.share.update:
  path: "/files/api/share/update"
  controller: "ShareController::update"
  method: POST
  auth: required

fileshare.share.revoke:
  path: "/files/api/share/revoke"
  controller: "ShareController::revoke"
  method: POST
  auth: required

fileshare.share.list:
  path: "/files/api/share/list/{file_id}"
  controller: "ShareController::list"
  auth: required

fileshare.share.qr:
  path: "/files/api/share/qr/{share_token}"
  controller: "ShareController::qrCode"
  auth: required

# ══════════════════════════════════════════
#  Internal Sharing (AJAX)
# ══════════════════════════════════════════

fileshare.internal_share.create:
  path: "/files/api/internal-share/create"
  controller: "InternalShareController::create"
  method: POST
  auth: required

fileshare.internal_share.update:
  path: "/files/api/internal-share/update"
  controller: "InternalShareController::update"
  method: POST
  auth: required

fileshare.internal_share.revoke:
  path: "/files/api/internal-share/revoke"
  controller: "InternalShareController::revoke"
  method: POST
  auth: required

fileshare.internal_share.list:
  path: "/files/api/internal-share/list/{file_id}"
  controller: "InternalShareController::list"
  auth: required

# ══════════════════════════════════════════
#  Public Share Access (NO auth required)
# ══════════════════════════════════════════

fileshare.public.view:
  path: "/s/{share_token}"
  controller: "PublicShareController::view"
  auth: none

fileshare.public.code:
  path: "/s/code/{share_code}"
  controller: "PublicShareController::viewByCode"
  auth: none

fileshare.public.password:
  path: "/s/{share_token}/auth"
  controller: "PublicShareController::authenticate"
  method: POST
  auth: none

fileshare.public.download:
  path: "/s/{share_token}/download"
  controller: "PublicShareController::download"
  auth: none

fileshare.public.download.file:
  path: "/s/{share_token}/download/{file_id}"
  controller: "PublicShareController::downloadFile"
  auth: none

fileshare.public.preview:
  path: "/s/{share_token}/preview/{file_id}"
  controller: "PublicShareController::preview"
  auth: none

# ══════════════════════════════════════════
#  Session Management (AJAX)
# ══════════════════════════════════════════

session.keepalive:
  path: "/session/keepalive"
  controller: "SessionController::keepAlive"
  method: POST
  auth: required

session.status:
  path: "/session/status"
  controller: "SessionController::status"
  auth: required

# ══════════════════════════════════════════
#  Compliance: GDPR, Breach, DPA (Authenticated, Admin)
# ══════════════════════════════════════════

fileshare.compliance.gdpr_requests:
  path: "/files/compliance/gdpr"
  controller: "ComplianceController::gdprRequestList"
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.gdpr_request_create:
  path: "/files/api/compliance/gdpr/create"
  controller: "ComplianceController::createGDPRRequest"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.gdpr_access_package:
  path: "/files/api/compliance/gdpr/access-package/{request_id}"
  controller: "ComplianceController::generateAccessPackage"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.gdpr_erasure_evaluate:
  path: "/files/api/compliance/gdpr/erasure-evaluate/{request_id}"
  controller: "ComplianceController::evaluateErasure"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.breach_list:
  path: "/files/compliance/breach"
  controller: "ComplianceController::breachList"
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.breach_create:
  path: "/files/api/compliance/breach/create"
  controller: "ComplianceController::createBreachIncident"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.breach_update:
  path: "/files/api/compliance/breach/update/{incident_id}"
  controller: "ComplianceController::updateBreachIncident"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.breach_affected:
  path: "/files/api/compliance/breach/affected/{incident_id}"
  controller: "ComplianceController::identifyAffectedPatients"
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.dpa_registry:
  path: "/files/compliance/dpa"
  controller: "ComplianceController::dpaRegistry"
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.dpa_create:
  path: "/files/api/compliance/dpa/create"
  controller: "ComplianceController::createDPA"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.dpa_update:
  path: "/files/api/compliance/dpa/update/{dpa_id}"
  controller: "ComplianceController::updateDPA"
  method: POST
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.destruction_log:
  path: "/files/compliance/destruction"
  controller: "ComplianceController::destructionLog"
  auth: required
  permission: "fileshare.admin"

fileshare.compliance.audit_dashboard:
  path: "/files/compliance/audit"
  controller: "ComplianceController::auditDashboard"
  auth: required
  permission: "fileshare.admin"
```

---

## 6. Core PHP Services

### 6.1 FileShareManager

Wraps filesystem operations with DB tracking. All file writes now pass through the encryption pipeline.

```
FileShareManager
├── getContents(user_id, parent_id, sort, filter) → list files/folders in dir
├── getFile(file_id)                               → single file metadata
├── getBreadcrumb(folder_id)                       → path from root
├── createFolder(user_id, parent_id, name)         → new folder
├── renameItem(file_id, new_name)                  → rename file or folder
├── moveItems(file_ids[], target_folder_id)        → move (cut+paste)
├── copyItems(file_ids[], target_folder_id)        → deep copy (decrypt → re-encrypt with new nonce)
├── deleteItems(file_ids[])                        → soft-delete → trash (check legal_hold + retention_until first)
├── restoreItems(file_ids[])                       → restore from trash
├── emptyTrash(user_id)                            → secure destruction for all trash items
├── purgeItems(file_ids[])                         → secure destruction for specific items
├── getTrashContents(user_id)                      → list trashed items
├── storeUploadedFile(upload_token)                → assemble → scan → hash → encrypt → store
├── getUserQuota(user_id)                          → {used, total, percent}
├── search(user_id, query)                         → filename search
├── resolveConflict(parent_id, name)               → append (1), (2)... to dupes
├── getStoragePath(user_id, relative)              → full disk path
├── getRecentFiles(user_id, limit)                 → last accessed files
├── touchAccessed(file_id)                         → update last_accessed
├── checkPermission(user_id, file_id, action)      → validate user can act on file
├── getSharedContents(user_id, folder_id)          → files shared with user in folder
├── checkRetention(file_id)                        → verify retention_until not in future
└── checkLegalHold(file_id)                        → verify no legal hold flag
```

**Key implementation changes from v1:**
- `deleteItems()` now calls `checkRetention()` and `checkLegalHold()` before soft-delete. If retention has not expired, deletion is blocked with a user-facing message: "This file is under retention policy until {date}."
- `purgeItems()` now calls `DestructionManager::secureDestroy()` instead of `unlink()` for permanent deletion. This performs multi-pass shred and generates a destruction certificate.
- `storeUploadedFile()` now includes the virus scan, integrity hash, and encryption steps in its pipeline (see Section 7).
- `copyItems()` decrypts source, computes new nonce, re-encrypts for the copy. Each copy gets its own nonce.
- All mutating operations call `ComplianceAuditLogger::log()` in addition to `ActivityLogger::log()`.

### 6.2 ShareManager (External Shares)

```
ShareManager
├── createShare(file_id, user_id, options)  → {token, code, url, qr_url}
├── updateShare(share_id, options)          → update settings
├── revokeShare(share_id)                   → mark inactive + set revoked_at
├── getSharesByFile(file_id)               → all external shares for a file/folder
├── getSharesByUser(user_id)               → all external shares created by user
├── getShareByToken(token)                 → lookup by URL token
├── getShareByCode(code)                   → lookup by short code
├── generateToken()                         → crypto-random hex string
├── generateCode()                          → alphanumeric (no ambiguous chars)
├── isShareValid(share)                     → check expiry, revocation, limits
├── recordAccess(share_id, ip, action)      → share access log + compliance audit log
├── incrementDownloads(share_id)            → count + check max
└── cleanupExpired()                        → cron: deactivate expired shares
```

### 6.3 InternalShareManager

```
InternalShareManager
├── createShare(file_id, owner_id, target_user_id, permission) → share record
├── updatePermission(share_id, permission)
├── revokeShare(share_id)
├── getSharesByFile(file_id)
├── getFilesSharedWithUser(user_id)
├── getFilesSharedByUser(user_id)
├── getUserPermission(user_id, file_id)
├── searchUsers(query)
└── notifyRecipient(share)
```

### 6.4 ShareAccessValidator

```
ShareAccessValidator
├── validate(share_token)                   → {valid, share, reason}
├── validatePassword(share, input)          → bool (bcrypt verify)
├── hasSessionAccess(share_token)           → check session for pwd-protected
├── grantSessionAccess(share_token)         → store in $_SESSION
└── getRateLimitKey(share_token, ip)        → brute-force protection
```

### 6.5 QRCodeGenerator

Pure PHP QR code generator using `phpqrcode` single-file library + GD.

```
QRCodeGenerator
├── generate(data, size=300)                → PNG binary data
├── toDataUri(data, size=300)               → base64 data URI
└── toFile(data, filepath, size=300)        → save to disk
```

### 6.6 PreviewManager

Generates and caches thumbnails/previews. Now includes integrity verification before serving previews.

```
PreviewManager
├── getThumbnail(file_id, size)             → cached thumbnail path or generate
├── getPreviewData(file_id)                 → {type, content_or_url, mime}
├── generateImageThumbnail(path, size)      → resize with GD → save to cache
├── generatePdfThumbnail(path, size)        → extract page 1 → image
├── generateVideoThumbnail(path, size)      → ffmpeg first frame → image
├── getTextPreview(path, max_lines)         → first N lines of text file
├── getCodePreview(path, language)          → text with language hint
├── getMarkdownPreview(path)                → rendered HTML from Parsedown
├── invalidateCache(file_id)               → delete cached thumbnails on file change
├── cleanupOrphanedThumbnails()            → cron: remove thumbnails for deleted files
└── getCachePath(hash, size)               → thumbnail filesystem path
```

**Change from v1:** `getThumbnail()` and `getPreviewData()` now call `IntegrityVerifier::verify()` before decrypting and serving. If integrity check fails, access is blocked and a critical notification is dispatched.

### 6.7 VersionManager

```
VersionManager
├── createVersion(file_id)                  → snapshot with its own hash + nonce
├── getVersions(file_id)
├── getVersion(version_id)
├── restoreVersion(version_id)             → integrity-verify version → replace current
├── deleteVersion(version_id)              → secure destruction
├── deleteAllVersions(file_id)
├── getVersionDiskPath(file_id, version)
├── cleanupOldVersions()
├── shouldVersion(file_id)
└── getVersionStorageUsage(user_id)
```

### 6.8 CommentManager

```
CommentManager
├── getComments(file_id)                    → threaded comment list
├── addComment(file_id, user_id, body, parent_id=null)
├── updateComment(comment_id, user_id, body)
├── deleteComment(comment_id, user_id)
├── getCommentCount(file_id)
└── notifyParticipants(comment)
```

### 6.9 ActivityLogger

```
ActivityLogger
├── log(user_id, file_id, action, details=[])
├── getGlobalFeed(user_id, limit, offset)
├── getFileFeed(file_id, limit, offset)
├── getUserFeed(user_id, limit, offset)
├── cleanup(days)
└── formatEntry(activity)
```

### 6.10 NotificationManager

```
NotificationManager
├── dispatch(user_id, type, data, priority='normal')
├── getUnread(user_id)
├── getUnreadCount(user_id)
├── getAll(user_id, limit, offset)
├── markRead(notification_id)
├── markAllRead(user_id)
├── cleanup(days)
└── shouldNotify(user_id, type)
```

**Change from v1:** Added `priority` parameter. Critical notifications (integrity violations, virus detections, breach alerts) display with red highlight and cannot be auto-dismissed.

### 6.11 LockManager

```
LockManager
├── acquire(file_id, user_id, reason=null, timeout=null)
├── release(file_id, user_id)
├── forceRelease(file_id, admin_id)
├── getStatus(file_id)
├── isLocked(file_id)
├── isLockedByUser(file_id, user_id)
├── refreshLock(file_id, user_id)
├── cleanupExpired()
└── checkLockForOperation(file_id, user_id)
```

### 6.12 PatientLinkManager

```
PatientLinkManager
├── link(file_id, patient_id, link_type, notes, user_id)
├── unlink(link_id)
├── getFileLinks(file_id)
├── getPatientFiles(patient_id, type=null)
├── getLinkTypes()
├── searchPatients(query)
├── getLinkedPatientsCount(file_id)
└── getPatientAccessLog(patient_id)        ← NEW: for EHDS Art. 9
```

**Addition:** `getPatientAccessLog()` queries `fileshare_audit_log` filtered by `patient_id` to show the patient (or their representative) exactly who accessed their health data and when. This satisfies EHDS Art. 9.

### 6.13 AuditExporter

```
AuditExporter
├── exportShareAccessLog(filters)
├── exportActivityLog(filters)
├── exportPatientFileLog(patient_id)       → all file access for a patient (EHDS Art. 9)
├── exportComplianceAuditLog(filters)      → NEW: export from fileshare_audit_log
├── generateComplianceReport(date_range)   → PDF: summary + statistics + violations
└── getExportFormats()                     → ['csv', 'pdf']
```

### 6.14 EncryptionManager *(NEW — Compliance)*

Handles transparent encryption/decryption of files at rest.

**Regulation:** GDPR Art. 32 + Art. 34(3)(a) | NIS2 Art. 21 | HIPAA §164.312(a)(2)(iv)

```
EncryptionManager
├── encrypt(plaintext_path, dest_path)     → {nonce, encrypted_size}
│     - Reads key from config key_file
│     - Generates random 24-byte nonce via random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES)
│     - Encrypts: sodium_crypto_secretbox(file_get_contents($plaintext), $nonce, $key)
│     - Writes ciphertext to dest_path
│     - Returns nonce for DB storage
│
├── decrypt(encrypted_path, nonce)         → plaintext binary data
│     - Reads key from key_file
│     - Decrypts: sodium_crypto_secretbox_open(file_get_contents($encrypted), $nonce, $key)
│     - Returns plaintext bytes (caller writes to temp or streams)
│     - On failure (wrong key/tampered): throws IntegrityException
│
├── decryptToTemp(file_id)                 → temp file path (auto-cleaned after request)
│     - Looks up nonce from DB
│     - Decrypts to temporary file in sys_get_temp_dir()
│     - Registers cleanup via register_shutdown_function()
│     - Used by PreviewManager, DownloadController
│
├── isEnabled()                            → bool from config
├── getKeyFingerprint()                    → SHA-256 of key (for verification, never expose key)
└── validateKeyFile()                      → checks key file exists, permissions, and key length
```

**Implementation notes:**
- Encryption happens **after** virus scan and integrity hash computation. The hash is of the **plaintext**, not the ciphertext, so integrity verification decrypts first then hashes.
- For large files (>50MB), a streaming approach using `sodium_crypto_secretstream_*` functions should be used instead of loading the entire file into memory. The nonce format changes for streams but the DB schema accommodates both.
- Thumbnails are **not** encrypted (configurable) because they are derived data generated from the file, not the original PHI. This avoids decryption overhead on every file listing.

### 6.15 IntegrityVerifier *(NEW — Compliance)*

Verifies file integrity on every access. This is one of the few HIPAA requirements classified as **required** (not addressable).

**Regulation:** HIPAA §164.312(c)(1), §164.312(c)(2) | GDPR Art. 5(1)(f) | NIS2 Art. 21(2)(h)

```
IntegrityVerifier
├── computeHash(file_path)                 → SHA-256 hash string
│     - hash_file('sha256', $filePath)
│     - For encrypted files: decrypt first, then hash the plaintext
│
├── verify(file_id)                        → bool
│     - Loads stored hash from fileshare_files.file_hash
│     - Decrypts file if encrypted → computes hash of plaintext
│     - Compares computed hash vs stored hash
│     - On match: returns true
│     - On mismatch: calls onIntegrityViolation(), returns false
│
├── verifyVersion(version_id)              → bool (same logic for version files)
│
├── onIntegrityViolation(file_id, expected, actual)
│     - Logs critical event in fileshare_audit_log (action: integrity_violation)
│     - Dispatches critical notification to admin
│     - Creates breach incident in fileshare_breach_incidents (severity: critical)
│     - BLOCKS ACCESS to the file — user sees "This file failed integrity verification"
│
├── batchVerifyAll()                       → {total, passed, failed, errors[]}
│     - Weekly cron job: iterates all files in fileshare_files
│     - Recomputes hash for each file
│     - Reports all mismatches via notification + email
│     - Generates summary report
│
├── setHash(file_id, hash)                 → update stored hash (called on upload/overwrite)
└── getHash(file_id)                       → retrieve stored hash
```

**Performance:** SHA-256 hashing runs at ~500MB/s on modern hardware. A 100MB file takes ~200ms. For files under 10MB (typical medical documents), the check is imperceptible (<20ms). The batch verify cron processes files sequentially to avoid I/O spikes.

### 6.16 ComplianceAuditLogger *(NEW — Compliance)*

Tamper-proof audit logging with hash chain integrity. Uses a dedicated database connection with INSERT+SELECT-only permissions.

**Regulation:** HIPAA §164.312(b) | GDPR Art. 30 | NIS2 Art. 21 | EHDS Art. 9

```
ComplianceAuditLogger
├── log(user_id, action, resource_type, resource_id, patient_id=null, details=null)
│     - Fetches prev_hash from last entry
│     - Computes event_hash = SHA-256(prev_hash + entry_data)
│     - INSERT via dedicated audit DB connection (no UPDATE/DELETE rights)
│     - Captures: IP address, User-Agent, timestamp with millisecond precision
│
├── getEntries(filters)                    → paginated audit log entries
│     filters: {date_range, user_id, action, resource_type, patient_id, ip_address}
│
├── getPatientAccessHistory(patient_id)    → all accesses to patient's files (EHDS Art. 9)
│
├── verifyChainIntegrity()                 → {valid, first_break_at, total_entries}
│     - Iterates all entries, recomputes hashes, checks chain continuity
│     - Reports first break point if any tampering detected
│
├── sendWeeklySummary()                    → email digest to admin
│     Contents: login count, failed logins, file operations count,
│     integrity violations (if any), virus detections (if any),
│     GDPR request status, breach incidents, DPA expirations
│
├── archiveOldEntries()                    → export entries older than retention period to CSV.gz
│     - Encrypts archive file
│     - Removes archived entries from active table
│     - Records archival in a new audit entry
│
└── getStatistics(date_range)              → {logins, downloads, uploads, violations, ...}
```

### 6.17 VirusScanner *(NEW — Compliance)*

ClamAV integration for upload-time malware scanning.

**Regulation:** NIS2 Art. 21 — malware prevention

```
VirusScanner
├── scan(file_path)                        → {clean: bool, threat_name: string|null}
│     - Connects to clamd via Unix socket
│     - Sends INSTREAM command + file data
│     - Parses response: "stream: OK" = clean, "stream: {name} FOUND" = infected
│     - On connection failure: logs warning, allows upload with "scan_pending" flag
│
├── quarantine(file_path, threat_name, upload_info)
│     - Moves file to quarantine_root/{date}_{hash}_{name}
│     - Logs in compliance audit trail (action: virus_quarantine)
│     - Dispatches critical notification to admin + uploader
│     - Updates upload record status to 'quarantined'
│
├── isAvailable()                          → bool (check if clamd socket exists and responds)
│
├── updateDefinitions()                    → runs freshclam, logs result
│
├── cleanupQuarantine()                    → cron: remove quarantined files older than retention
│     - Generates destruction certificate for each removed file
│
└── getQuarantineList()                    → list of quarantined files with metadata
```

**Installation prerequisite:** ClamAV daemon must be installed and running:
```bash
apt install clamav clamav-daemon
systemctl enable clamav-daemon
systemctl start clamav-daemon
```

### 6.18 SessionGuard *(NEW — Compliance)*

Server-side session timeout enforcement with client-side synchronization.

**Regulation:** HIPAA §164.312(a)(2)(iii) | NIS2 Art. 21 | GDPR Art. 32

```
SessionGuard
├── check()                                → called on every authenticated request
│     - Reads $_SESSION['last_activity']
│     - If time() - last_activity > timeout: destroySession(), redirect to login
│     - If valid: updates $_SESSION['last_activity'] = time()
│     - Logs session_timeout in audit trail if expired
│
├── keepAlive()                            → AJAX endpoint handler
│     - Validates session is still active
│     - Resets $_SESSION['last_activity']
│     - Returns {remaining_seconds, timeout_seconds}
│
├── destroySession()
│     - Logs session_timeout event with session duration
│     - Clears all session data
│     - Destroys session
│
├── getStatus()                            → {active: bool, remaining_seconds: int}
│
└── getTimeout()                           → int (seconds, from config)
```

**Client-side (`session-guard.js`):**

```javascript
const SessionGuard = {
    timeoutMs: 15 * 60 * 1000,          // From server config
    warningMs: 2 * 60 * 1000,           // 2 min before expiry
    timer: null,
    warningShown: false,

    init(config) {
        this.timeoutMs = config.timeout_seconds * 1000;
        this.warningMs = config.warning_seconds * 1000;
        this.resetTimer();
        // Listen for user activity
        ['click', 'keypress', 'mousemove', 'scroll'].forEach(evt =>
            document.addEventListener(evt, () => this.onActivity(), { passive: true })
        );
    },

    resetTimer() {
        clearTimeout(this.timer);
        this.warningShown = false;
        const warningAt = this.timeoutMs - this.warningMs;
        // Set warning timer
        this.timer = setTimeout(() => this.showWarning(), warningAt);
    },

    async onActivity() {
        // Debounce: max 1 keepalive per 60 seconds
        if (this._lastKeepAlive && Date.now() - this._lastKeepAlive < 60000) return;
        this._lastKeepAlive = Date.now();
        try {
            const resp = await fetch('/session/keepalive', { method: 'POST' });
            if (resp.ok) this.resetTimer();
            else this.onTimeout();
        } catch (e) { /* network error — don't timeout */ }
    },

    showWarning() {
        // Display modal: "Session expires in 2:00. Click to continue."
        // Countdown timer in modal
        // "Continue Working" button calls onActivity()
        this.warningShown = true;
        this.timer = setTimeout(() => this.onTimeout(), this.warningMs);
    },

    onTimeout() {
        // Clear any cached form data
        // Show "Session expired for security" message
        // Redirect to login page
        window.location.href = '/login?reason=timeout';
    }
};
```

### 6.19 GDPRManager *(NEW — Compliance)*

Handles GDPR data subject rights workflows.

**Regulation:** GDPR Art. 15-22 | EHDS Art. 6, 9, 10

```
GDPRManager
├── createRequest(patient_id, request_type, notes=null) → request record
│     - Computes deadline_at = NOW() + 30 days
│     - Logs in audit trail (action: gdpr_access_request or gdpr_erasure_request)
│     - Dispatches notification to admin with deadline
│
├── generateAccessPackage(request_id)      → ZIP file path
│     - Collects all files linked to patient via fileshare_patient_links
│     - Decrypts each file → adds to ZIP
│     - Generates JSON manifest: file list with names, dates, types, sizes
│     - Generates cover letter PDF (TCPDF) with practice details, request info
│     - Optionally includes patient access log (who accessed their files, when)
│     - Logs in audit trail (action: gdpr_package_generated)
│     - Marks request as completed
│
├── evaluateErasure(request_id)            → {can_erase: [], must_retain: [], reason: string}
│     - For each patient-linked file:
│       - Check link_type against config/gdpr.yml retention periods
│       - Check retention_until date on file record
│       - Check legal_hold flag
│     - Medical records: always in must_retain[] with explanation
│     - Non-clinical: in can_erase[] if past retention
│     - Logs decision in audit trail
│
├── executeErasure(request_id, approved_file_ids[])
│     - Only processes files in the can_erase list
│     - Calls DestructionManager::secureDestroy() for each
│     - Updates request status
│     - Logs each destruction
│
├── setEHDSOptOut(patient_id, opt_out)     → toggle EHDS secondary use opt-out
│
├── getRequestsByPatient(patient_id)       → list of GDPR requests
├── getRequestsByStatus(status)            → requests filtered by status
├── getPendingDeadlines()                  → requests approaching deadline
│
├── checkDeadlines()                       → cron: send alerts for approaching deadlines
│     - 7 days: normal notification
│     - 3 days: high-priority notification
│     - 1 day: critical notification
│     - Overdue: critical notification + email to admin
│
└── checkDPAExpiry()                       → cron: check DPA/BAA expiry dates
```

### 6.20 BreachManager *(NEW — Compliance)*

Breach detection, assessment, and notification workflow.

**Regulation:** GDPR Art. 33-34 | NIS2 Art. 23 | HIPAA §164.408

```
BreachManager
├── createIncident(type, description, detected_by='system', severity='medium')
│     - Creates fileshare_breach_incidents record
│     - Computes regulatory deadlines from detected_at:
│       nis2_early_warning_at  = detected_at + 24 hours
│       gdpr_dpa_notify_at     = detected_at + 72 hours
│       nis2_full_report_at    = detected_at + 72 hours
│       nis2_final_report_at   = detected_at + 1 month
│       hipaa_notify_at        = detected_at + 60 days
│     - Dispatches critical notification to admin
│     - Logs in audit trail (action: breach_detected)
│
├── identifyAffectedPatients(incident_id, file_ids=null, time_range=null)
│     - Given file IDs: find all patients linked via fileshare_patient_links
│     - Given time range: find all files accessed in that period, then their patients
│     - Updates affected_patients and affected_count on incident record
│     - Returns list of {patient_id, patient_name, files_affected[]}
│
├── updateStatus(incident_id, new_status, notes=null)
│     - Advances incident through workflow:
│       detected → assessing → contained → notified_dpa → notified_patients → resolved
│     - Records actual notification timestamps
│     - Logs each status change in audit trail
│
├── generateDPANotification(incident_id)   → PDF
│     - Pre-filled notification template for supervisory authority
│     - Includes: practice details, incident description, affected data categories,
│       estimated affected count, measures taken, contact information
│
├── getActiveIncidents()                   → list of unresolved incidents
├── getDeadlineStatus(incident_id)         → which deadlines are approaching/overdue
│
└── autoDetect()                           → called by system events
│     - Triggered by: IntegrityVerifier failures, repeated login failures (>10/hour),
│       virus detections, suspicious access patterns
│     - Creates incident automatically when thresholds are exceeded
```

### 6.21 DestructionManager *(NEW — Compliance)*

Secure file destruction with audit trail and certificates.

**Regulation:** GDPR Art. 17 + Art. 5(1)(e) | HIPAA §164.310(d)(2)(i)

```
DestructionManager
├── secureDestroy(file_id, reason, user_id) → destruction_log record
│     - Verify no legal_hold on file
│     - Verify retention_until is past (or null)
│     - Record metadata before destruction: name, hash, size, patient links
│     - Decrypt file if encrypted (to get plaintext for shredding)
│     - Execute: shell_exec("shred -vfz -n 3 " . escapeshellarg($path))
│     - Verify file is gone (clearstatcache + !file_exists)
│     - Remove DB record from fileshare_files
│     - Also destroy all versions via VersionManager
│     - Remove thumbnails via PreviewManager
│     - Insert into fileshare_destruction_log
│     - Generate destruction certificate PDF
│     - Log in compliance audit trail (action: destruction_executed)
│
├── generateCertificate(destruction_log_id) → PDF path
│     - TCPDF-generated PDF containing:
│       Practice name + address, certificate number, date/time of destruction,
│       original filename, file hash (SHA-256), file size, destruction method,
│       linked patient (if any), performed by (user name),
│       statement: "The above-described electronic document has been permanently
│       and irreversibly destroyed using [method] in compliance with applicable
│       data protection regulations."
│     - Stored in destruction_certs/{year}/cert_{id}_{date}.pdf
│
├── batchDestroy(file_ids[], reason, user_id) → array of results
│     - Validates each file individually
│     - Destroys sequentially, collecting results
│     - Single summary certificate for the batch
│
├── getDestructionLog(filters)             → paginated destruction records
│     filters: {date_range, patient_id, destroyed_by}
│
└── checkRetentionExpiry()                 → list of files past retention_until
│     - For notification only — does not auto-destroy
│     - Returns files eligible for destruction
```

---

## 7. Upload System (Adapted from DICOM Module)

### 7.1 Server-Side — `UploadController.php`

The upload pipeline now integrates virus scanning, integrity hashing, and encryption as mandatory steps. The flow is:

**Chunk Assembly → MIME Validation → Virus Scan → Integrity Hash → Version (if overwrite) → Encryption → Store → DB Record → Audit Log**

| Step | Operation | On Failure |
|------|-----------|------------|
| 1 | Concatenate chunks → temp file | Delete temp, return error |
| 2 | Validate MIME type (server-side) | Delete temp, return error |
| 3 | **ClamAV virus scan** | Quarantine file, notify admin+user, return error |
| 4 | **Compute SHA-256 hash** (of plaintext) | Should not fail; log error |
| 5 | If overwriting existing: create version | Version creation may fail; log warning, continue |
| 6 | **Encrypt file** (Sodium) | Delete temp, return error |
| 7 | Move encrypted file to storage | Delete temp, return error |
| 8 | Create/update DB record with hash + nonce | Rollback file, return error |
| 9 | Log activity + compliance audit | Should not fail; log error |
| 10 | Clean up temp directory | Should not fail; log error |

**Endpoints:**

1. **`POST /files/upload/init`** — Initialize upload session
   - Input: `filename`, `filesize`, `parent_id`
   - Validates: extension, quota, filename length, parent ownership, lock status
   - Creates temp directory: `files/fileshare/tmp/{upload_token}/`
   - Returns: `{upload_token, chunk_size, total_chunks, will_overwrite, existing_file_id}`

2. **`POST /files/upload/chunk`** — Receive chunk
   - Input: `upload_token`, `chunk_index`, `chunk` (binary)
   - Writes to: `tmp/{upload_token}/chunk_{index}`
   - Returns: `{received, total, progress_pct}`

3. **`POST /files/upload/finalize`** — Assemble, scan, hash, encrypt, store
   - Concatenates chunks → temp file
   - **Virus scan:** `VirusScanner::scan($tempPath)`. If infected: quarantine, notify, return `{error: 'virus_detected', threat: $name}`
   - **Integrity hash:** `$hash = IntegrityVerifier::computeHash($tempPath)`. Store in `fileshare_files.file_hash`
   - If overwriting existing: `VersionManager::createVersion()` first
   - **Encrypt:** `$result = EncryptionManager::encrypt($tempPath, $storagePath)`. Store nonce in `fileshare_files.encryption_nonce`
   - Create/update DB record
   - **Compliance audit:** `ComplianceAuditLogger::log($userId, 'file_upload', 'file', $fileId, $patientId)`
   - Activity log via `ActivityLogger`
   - Invalidate thumbnail cache
   - Clean up temp dir
   - Returns: `{file_id, name, size, mime_type, is_new, version_created, scan_result: 'clean'}`

### 7.2 Client-Side — `file-uploader.js`

Same structure as v1 with added scan result handling:

```javascript
const FileUploader = {
    chunkSize: 5 * 1024 * 1024,
    baseUrl: '/files/upload',
    queue: [],
    active: 0,
    maxConcurrent: 2,

    addFiles(files, parentId, callbacks) { /* ... */ },
    processQueue() { /* ... */ },

    async uploadFile(file, parentId, callbacks) {
        // Step 1: Init
        // Step 2: Send chunks with progress
        // Step 3: Finalize
        //   - Handle new responses:
        //     - {error: 'virus_detected', threat: '...'} → show red alert
        //     - {scan_result: 'clean'} → normal success
    },

    cancel(uploadToken) { /* ... */ },
    cancelAll() { /* ... */ }
};
```

### 7.3 Drag-and-Drop Zone — `drag-drop.js`

Unchanged from v1. Handles `dragenter/dragover/dragleave/drop`, visual feedback, recursive folder reads via `webkitGetAsEntry()`.

---

## 8. File Browser UI

### 8.1 Layout

Same Nextcloud-inspired layout as v1 with the following compliance additions:

- **Session timeout indicator** in the topbar (right side): small countdown or icon showing remaining session time. Changes color at the warning threshold.
- **Compliance sidebar section**: "Compliance" menu group below "Activity" with links to Audit Dashboard, GDPR Requests, Breach Incidents, DPA Registry, Destruction Log (admin only).
- **Legal hold badge** (⚖️) on files under legal hold
- **Retention badge** with tooltip showing earliest destruction date on files with `retention_until` set

### 8.2–8.6 — Unchanged from v1

Bulk operations bar, context menu, upload progress panel, details side panel, notification dropdown — all unchanged from v1. The context menu gains one new entry:

```
│ 🗑️ Secure Destroy       │  ← only shown for admin, only on files past retention
```

### 8.7 Session Timeout Warning Modal *(NEW)*

When session approaches timeout, a modal overlay appears:

```
┌─────────────────────────────────────────────────┐
│          ⏱ Session Expiring                      │
│                                                  │
│  Your session will expire in 1:45 due to         │
│  inactivity. Any unsaved changes may be lost.    │
│                                                  │
│  This timeout is required by healthcare          │
│  security policy to protect patient data.        │
│                                                  │
│         [Continue Working]    [Log Out]           │
└──────────────────────────────────────────────────┘
```

Countdown updates in real-time. "Continue Working" sends keep-alive. "Log Out" destroys session immediately. If countdown reaches 0:00, page redirects to login.

### 8.8 Integrity Violation Alert *(NEW)*

If a file fails integrity verification on access:

```
┌─────────────────────────────────────────────────┐
│  🛑 File Integrity Violation                     │
│                                                  │
│  The file "report-q4.pdf" failed integrity       │
│  verification. The stored hash does not match    │
│  the computed hash of the file contents.         │
│                                                  │
│  ACCESS BLOCKED for security purposes.           │
│                                                  │
│  This may indicate unauthorized tampering.       │
│  The practice administrator has been notified.   │
│                                                  │
│  Expected: a8f3b1c...                            │
│  Actual:   7d92e4f...                            │
│                                                  │
│                                    [Dismiss]     │
└──────────────────────────────────────────────────┘
```

---

## 9. Share Dialog

Unchanged from v1. External share creation modal, internal share dialog, active shares list, QR code popup — all as previously specified.

---

## 10. Public Share Pages

Unchanged from v1. Public view, password prompt, expired/revoked share pages — all as previously specified. Note: files served through public shares are decrypted on-the-fly by `DownloadController` before streaming to the client. The encryption is transparent to the end user.

---

## 11. File Operations — `file-ops.js`

Unchanged from v1. Clipboard system, keyboard shortcuts, inline rename — all as previously specified. All mutating operations now trigger both `ActivityLogger` and `ComplianceAuditLogger` on the server side.

---

## 12. File Preview System

Unchanged from v1 in terms of UI and supported formats. The key change is that `PreviewController` now calls `IntegrityVerifier::verify()` and `EncryptionManager::decryptToTemp()` before serving any preview or thumbnail of an encrypted file. The decrypted temp file is auto-cleaned after the request completes.

---

## 13. Security Considerations

### 13.1 Authentication
- All `/files/**` routes require ZPMS session auth (except `/s/**` public routes)
- CSRF token required for all POST operations
- Upload tokens are single-use, tied to user session
- Internal share access verified on every request via `checkPermission()`

### 13.2 Session Security (HIPAA §164.312(a)(2)(iii) / NIS2 / GDPR)
- **Automatic session logoff** after 15 minutes of inactivity (configurable)
- Server-side enforcement via `SessionGuard::check()` on every authenticated request
- Client-side countdown with warning modal 2 minutes before expiry
- AJAX keep-alive on user activity (debounced to max 1/minute)
- Session timeouts logged in compliance audit trail with session duration
- Maximum 3 concurrent sessions per user to prevent session sharing

### 13.3 Encryption at Rest (GDPR Art. 32 / NIS2 / HIPAA)
- All uploaded files encrypted via PHP Sodium (XSalsa20-Poly1305 authenticated encryption)
- Unique random 24-byte nonce per file, stored in database
- Encryption key stored at `/etc/zpms/encryption.key`, `chmod 600`, outside web root
- Decryption is transparent: happens on-demand for downloads, previews, and integrity checks
- File versions also encrypted with their own nonces
- Thumbnails optionally unencrypted (derived data, not PHI) for performance
- Database backups encrypted separately via OpenSSL AES-256-CBC
- **Breach safe harbor:** If a data breach occurs but files were encrypted and the key was not compromised, GDPR Art. 34(3)(a) exempts the practice from notifying individual data subjects

### 13.4 Document Integrity (HIPAA §164.312(c)(1) — REQUIRED)
- SHA-256 hash computed on every upload (of plaintext, before encryption)
- Hash stored in `fileshare_files.file_hash`
- Hash re-verified on every download and preview access
- Mismatch = **immediate access block** + critical alert + auto-breach-incident creation
- Weekly batch verification of all files via cron
- Version files also have individual hashes
- Hash chain in audit log prevents log tampering

### 13.5 Virus Scanning (NIS2 Art. 21)
- ClamAV scans every uploaded file after chunk assembly, before encryption
- Infected files quarantined outside web root
- Critical notifications to admin and uploader on detection
- ClamAV definitions updated every 6 hours via cron
- Quarantine retention: 90 days (admin can review)
- If ClamAV is unavailable: upload proceeds with "scan_pending" flag, admin notified

### 13.6 Audit Trail (HIPAA §164.312(b) / GDPR Art. 30 / NIS2 / EHDS Art. 9)
- Comprehensive logging of all file operations, authentication events, and compliance actions
- Tamper-proof hash chain: each entry's hash includes the previous entry's hash
- Dedicated database user with INSERT+SELECT only — no UPDATE, no DELETE
- 6-year retention (HIPAA minimum, EU states align at 6-10 years)
- Weekly summary email to admin satisfies HIPAA periodic review requirement
- Patient-scoped audit queries enable EHDS Art. 9 (patient's right to see who accessed their data)
- Chain integrity verifiable via `ComplianceAuditLogger::verifyChainIntegrity()`

### 13.7 Path Traversal Prevention
- All filenames sanitized: strip `../`, `./`, null bytes, control chars
- `realpath()` check ensures resolved path stays within user's storage root
- Folder IDs used for navigation instead of raw paths

### 13.8 Share Security
- Tokens: cryptographically random, 32-char hex (128-bit entropy)
- Codes: 8-char from 55-char alphabet (~46 bits entropy)
- Password brute-force: rate limiting (5 attempts / 15 min per IP per share)
- All share access logged with IP and user-agent

### 13.9 Upload Security
- Server-side MIME validation (not just extension)
- PHP/executable files rejected regardless of extension
- **ClamAV scan before storage** (added in v2)
- Uploads stored outside web root, served through PHP controller
- Chunk assembly validates total size matches declared size

### 13.10 Content Serving
- Files served via PHP with `X-Content-Type-Options: nosniff`
- Inline display only for safe types (images, PDF); rest force-download
- `Content-Disposition` header set appropriately
- Thumbnails served via authenticated controller
- **Encrypted files decrypted on-the-fly** — ciphertext never reaches the client

### 13.11 Lock Security
- Lock bypass only by lock holder or admin
- Expired locks auto-released by cron
- Lock check enforced server-side

### 13.12 Secure Destruction (GDPR Art. 17 / HIPAA §164.310(d)(2)(i))
- Permanent deletion uses `shred -vfz -n 3` (3-pass overwrite + zero fill)
- Destruction certificate PDF generated for each destroyed file
- Destruction log retained 6 years
- Legal hold flag prevents destruction regardless of retention expiry
- Retention period enforced: files with `retention_until` in the future cannot be destroyed

### 13.13 GDPR Data Subject Rights
- Right of Access (Art. 15): automated access package generation (ZIP + manifest + cover letter)
- Right to Erasure (Art. 17): automatic evaluation against retention obligations; medical records always retained
- EHDS Opt-Out (Art. 10): per-patient toggle for secondary data use
- 30-day response deadline tracked with escalating notifications
- All requests and decisions logged in compliance audit trail

### 13.14 Breach Notification (GDPR Art. 33 / NIS2 Art. 23 / HIPAA §164.408)
- Auto-detection: integrity violations, repeated failed logins, virus detections
- Regulatory deadline tracker: NIS2 24h early warning → GDPR/NIS2 72h full report → NIS2 1-month final → HIPAA 60 days
- One-click "Identify Affected Patients" from file or time range
- Pre-filled DPA notification template (PDF)
- Workflow: detected → assessing → contained → notified_dpa → notified_patients → resolved

---

## 14. Implementation Phases

### Phase 0 — Compliance Foundation (Before Go-Live)

**Must be completed before the module handles any patient data.** These are regulatory requirements, not optional features.

1. `EncryptionManager` service — key generation, encrypt/decrypt/decryptToTemp
2. `IntegrityVerifier` service — computeHash, verify, batchVerifyAll, onIntegrityViolation
3. `VirusScanner` service — ClamAV socket integration, scan, quarantine
4. `ComplianceAuditLogger` service — hash-chain log, dedicated DB user setup
5. `SessionGuard` service — server-side timeout check on every request
6. `session-guard.js` — client-side timer, keep-alive, warning modal
7. `SessionController` — keep-alive + status endpoints
8. Database: `fileshare_audit_log` table creation + dedicated DB user
9. Database: update `fileshare_files` with `file_hash`, `hash_algorithm`, `encryption_nonce`, `is_encrypted`, `sensitivity`, `legal_hold`, `retention_until` columns
10. Database: update `fileshare_versions` with `file_hash`, `encryption_nonce` columns
11. Database: update `fileshare_uploads` with `scan_result` column and `quarantined` status
12. Config files: `encryption.yml`, `security.yml`, `audit.yml`, `virus-scan.yml`
13. Integration: modify `UploadController::finalize()` pipeline to include scan → hash → encrypt
14. Integration: modify `DownloadController::download()` to verify → decrypt → serve
15. Integration: modify `PreviewController` to verify → decrypt → generate preview
16. Cron: integrity batch verify (weekly), virus definitions update (6h), audit summary email (weekly)
17. Templates: session-timeout-modal, integrity-alert

### Phase 1 — Core File Browser & Upload
1. Database schema creation (all base tables: 4.1 – 4.11)
2. `FileShareManager` service (CRUD, trash, quota, retention checks)
3. `UploadController` (chunked upload with scan/hash/encrypt pipeline)
4. `file-uploader.js` + `drag-drop.js`
5. `FileOpsController` (mkdir, rename, delete/trash with retention guard)
6. File browser template + `file-browser.js`
7. `DownloadController` (single file + ZIP, with decrypt-on-fly)
8. Favorites toggle + favorites view
9. Recent files view
10. Quota display bar

### Phase 2 — Clipboard, Operations & Trash
1. Cut/copy/paste with `Clipboard` JS module
2. Move/copy server endpoints (copy = decrypt → re-encrypt with new nonce)
3. Inline rename
4. Context menu (with "Secure Destroy" for admin)
5. Keyboard shortcuts
6. Bulk selection + bulk operations bar
7. Sorting and filtering
8. Trash browser UI with restore + purge (purge = secure destruction)
9. Trash retention cron

### Phase 3 — Previews & Thumbnails
1. `PreviewManager` service (with integrity check before serve)
2. Image thumbnails (GD)
3. PDF page-1 thumbnails (Imagick or Ghostscript)
4. Video first-frame thumbnails (ffmpeg)
5. `PreviewController` (decrypt → verify → serve)
6. Thumbnail column in file list
7. Grid/thumbnail view toggle
8. Image gallery overlay
9. Inline preview for: PDF, video, audio, text, code, markdown
10. DICOM preview integration
11. Thumbnail cache cleanup cron

### Phase 4 — External Sharing
1. `ShareManager` service (with compliance audit logging)
2. `ShareController` AJAX endpoints
3. Share dialog UI + `share-dialog.js`
4. `PublicShareController` (view, password, download — decrypt on fly)
5. Public share templates
6. Share access logging → both `fileshare_share_access_log` and `fileshare_audit_log`
7. `QRCodeGenerator`
8. QR display + download/print
9. Short code sharing
10. Preview support on public share pages

### Phase 5 — Internal Sharing
1. `InternalShareManager` service
2. `InternalShareController` AJAX endpoints
3. Internal share dialog UI
4. "Shared with me" / "Shared by me" views
5. Permission checking integrated into all file operations
6. Share recipient notifications

### Phase 6 — Versioning
1. `VersionManager` service (with per-version hash + encryption)
2. Auto-versioning on overwrite (integrated into upload finalize pipeline)
3. Version history tab in details panel
4. Version restore (verify integrity → decrypt → re-encrypt as current)
5. Version download + delete (secure destruction)
6. Version cleanup cron

### Phase 7 — Activity, Comments & Notifications
1. `ActivityLogger` + integration with all operations
2. Activity feed page (global + per-file)
3. Activity tab in details panel
4. `CommentManager`
5. Comments tab in details panel
6. `NotificationManager` (with priority levels for compliance alerts)
7. Notification dropdown UI with priority highlighting
8. Notification badge with polling
9. Mark read / mark all read
10. Share expiry + quota warning + compliance event notifications (cron)
11. Cleanup crons

### Phase 8 — File Locking & Patient Links
1. `LockManager`
2. Lock/unlock UI
3. Lock indicator badge
4. Lock enforcement in operations
5. Lock expiry cron
6. `PatientLinkManager` (with `getPatientAccessLog()` for EHDS Art. 9)
7. Patient link dialog
8. Patient icon badge
9. Files tab on patient detail page
10. Link type classification

### Phase 9 — Compliance Workflows
1. `GDPRManager` service — request creation, access package generation, erasure evaluation
2. `BreachManager` service — incident creation, affected patient identification, deadline tracking
3. `DestructionManager` service — secure destroy, certificate generation, batch destroy
4. `ComplianceController` — all compliance UI routes
5. Database: `fileshare_gdpr_requests`, `fileshare_breach_incidents`, `fileshare_dpa_registry`, `fileshare_destruction_log` tables
6. Config files: `gdpr.yml`, `breach.yml`, `destruction.yml`
7. GDPR request list page + access package builder
8. Breach workflow page + incident form + affected patients identifier
9. DPA/BAA registry page (CRUD for vendor records)
10. Destruction log page + certificate viewer
11. Compliance audit dashboard (filterable log viewer with chain verification)
12. `AuditExporter` enhancements: compliance audit log export, patient access history export
13. Crons: GDPR deadline check (daily), DPA expiry check (monthly), audit archive (annual), quarantine cleanup (monthly)

### Phase 10 — Audit & Admin
1. Audit trail page (admin-only)
2. Filter by date range, user, action, file, patient
3. Hash chain verification UI (show integrity status)
4. CSV export of: share access log, activity log, compliance audit log
5. Per-patient file access export (EHDS Art. 9 compliance)
6. Compliance summary report PDF (periodic review evidence)
7. Dashboard widgets: open GDPR requests, active breach incidents, expiring DPAs, session statistics

---

## 15. Dependency Summary

| Component | Dependency | Notes |
|-----------|-----------|-------|
| Encryption | PHP Sodium (built-in ≥7.2) | `sodium_crypto_secretbox()`, `sodium_crypto_secretbox_open()` |
| Integrity | PHP hash (built-in) | `hash_file('sha256', ...)` |
| Virus scan | ClamAV daemon | `apt install clamav clamav-daemon`. Socket at `/var/run/clamav/clamd.ctl` |
| Secure destroy | `shred` (coreutils) | Pre-installed on all Linux distributions |
| QR codes | PHP GD extension | Already installed for DICOM module |
| Image thumbnails | PHP GD extension | Resize, crop, format conversion |
| PDF thumbnails | Imagick or Ghostscript | `gs` command-line for page-1 extraction |
| Video thumbnails | ffmpeg | First-frame extraction (optional) |
| PDF generation | TCPDF (single PHP file) | Destruction certs, compliance reports, access package cover letters |
| ZIP downloads | PHP ZipArchive | Built-in PHP extension |
| YAML config | PHP yaml extension | Already used by ZPMS |
| File uploads | Vanilla JS + PHP | Adapted from DICOM module |
| Passwords | `password_hash()` / `password_verify()` | Built-in PHP |
| Tokens | `random_bytes()` / `bin2hex()` | Built-in PHP |
| Markdown render | Parsedown.php | Single file, placed in `lib/` |
| QR encode | phpqrcode.php | Single file, placed in `lib/` |
| UI | Design system CSS + Vanilla JS | No external deps |

**Zero Composer dependencies.** Single-file libraries in `modules/fileshare/lib/`. ClamAV is the only system-level dependency beyond standard PHP.

---
---

# Addendum A — Small Practice Tier 2: High-Impact Operations

Features that dramatically improve daily workflow for a small practice (1 doctor, 1 secretary). Strong regulatory backing but less immediate enforcement risk than Tier 1. Target: implement within 6 months of go-live.

---

## A.1 Electronic Signatures for Consent Forms

**Regulation:** eIDAS (EU 910/2014) — SES sufficient for most medical consents | GDPR Art. 7 — demonstrable proof of consent

Replaces paper consent forms with on-screen signature capture. The most immediate workflow improvement for a small practice's check-in process.

**Components:**
- `SignatureController.php` — consent form display, signature capture, PDF generation
- `SignatureManager.php` — template loading, signature storage, PDF assembly
- `signature-pad.js` — HTML5 Canvas signature capture (uses `signature_pad.js` MIT library, single file in `lib/`)
- Templates stored in `modules/fileshare/templates/consents/`: general treatment consent, GDPR privacy notice acknowledgment, telehealth consent, procedure-specific consent forms

**Database:**
```sql
CREATE TABLE fileshare_signatures (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    template_name   VARCHAR(100) NOT NULL,         -- consent form template used
    signer_name     VARCHAR(255) NOT NULL,
    signature_data  MEDIUMBLOB NOT NULL,            -- Base64 PNG of drawn signature
    document_hash   VARCHAR(64) NOT NULL,           -- SHA-256 of document at time of signing
    signed_pdf_file_id INT UNSIGNED NULL,           -- Link to generated PDF in fileshare
    ip_address      VARCHAR(45) NOT NULL,
    user_agent      VARCHAR(255) NULL,
    signed_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_patient (patient_id),
    INDEX idx_template (template_name)
);
```

**Workflow:**
1. Secretary opens consent form on tablet/screen → selects patient → selects template
2. System renders consent text with patient details filled in
3. Patient reads and draws signature on Canvas
4. System captures: signature PNG, SHA-256 of rendered document, IP, timestamp, user agent
5. TCPDF generates signed PDF: consent text + signature image + verification metadata block at bottom
6. PDF automatically: encrypted → stored in fileshare → linked to patient record → checklist item marked complete
7. Audit trail entry: `gdpr_consent_signed` with template name and patient ID

**Consent templates (`config/fileshare/consent-templates.yml`):**
```yaml
consent_templates:
  general_treatment:
    title: "General Treatment Consent"
    file: "consents/general_treatment.html.zem"
    required_for: [new_patient, procedure]
    renewal_months: 12
  gdpr_privacy:
    title: "GDPR Privacy Notice Acknowledgment"
    file: "consents/gdpr_privacy_notice.html.zem"
    required_for: [new_patient]
    renewal_months: null                 # One-time
  telehealth:
    title: "Telehealth Consent"
    file: "consents/telehealth.html.zem"
    required_for: [telehealth_visit]
    renewal_months: 12
```

---

## A.2 Document Checklists (Simplified)

Tracks required documents per appointment type. For a small practice, 2-3 templates covering the most common visit types.

**Components:**
- `ChecklistController.php` — checklist CRUD, appointment integration
- `ChecklistManager.php` — template-based checklist creation, status tracking, auto-creation hooks

**Database:**
```sql
CREATE TABLE fileshare_checklists (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id      INT UNSIGNED NOT NULL,
    appointment_id  INT UNSIGNED NULL,
    template_name   VARCHAR(100) NOT NULL,
    status          ENUM('incomplete','complete') DEFAULT 'incomplete',
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    DATETIME NULL,
    INDEX idx_patient (patient_id),
    INDEX idx_appointment (appointment_id),
    INDEX idx_status (status)
);

CREATE TABLE fileshare_checklist_items (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checklist_id    INT UNSIGNED NOT NULL,
    label           VARCHAR(255) NOT NULL,
    item_type       VARCHAR(50) DEFAULT 'general',   -- general, insurance, consent, referral
    is_required     TINYINT(1) DEFAULT 1,
    is_completed    TINYINT(1) DEFAULT 0,
    completed_by    INT UNSIGNED NULL,
    completed_at    DATETIME NULL,
    linked_file_id  INT UNSIGNED NULL,               -- File that satisfies this item
    sort_order      INT UNSIGNED DEFAULT 0,
    INDEX idx_checklist (checklist_id),
    CONSTRAINT fk_checklist FOREIGN KEY (checklist_id) REFERENCES fileshare_checklists(id) ON DELETE CASCADE
);
```

**Checklist templates (`config/fileshare/checklists.yml`):**
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
      - { label: "Referral authorization", type: referral, required: false }
  follow_up:
    items:
      - { label: "Updated insurance card (if changed)", type: insurance, required: false }
      - { label: "Updated medication list", type: general, required: true }
  procedure:
    items:
      - { label: "Signed procedure consent", type: consent, required: true }
      - { label: "Pre-op lab results", type: general, required: true }
      - { label: "Referring physician letter", type: referral, required: false }
```

**Dashboard widget:** "Upcoming appointments with incomplete documents" — shows next 5 appointments with checklist completion percentage. Green = complete, yellow = partially complete, red = missing required items.

**Hook:** When ZPMS scheduling module creates an appointment, it calls `ChecklistManager::createFromAppointment($patientId, $appointmentId, $visitType)` which auto-creates the checklist from the matching template.

---

## A.3 Document Inbox (Simplified)

A single queue for incoming documents from all sources. For a small practice, no auto-routing rules — the secretary reviews and files manually.

**Components:**
- `InboxController.php` — inbox list, assign to patient, file to folder
- `InboxManager.php` — item creation, status management, source tracking

**Database:**
```sql
CREATE TABLE fileshare_inbox (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id         INT UNSIGNED NOT NULL,
    source          ENUM('scan','fax','email','upload','patient_upload','system') NOT NULL,
    source_detail   VARCHAR(255) NULL,             -- e.g., fax number, email address
    status          ENUM('new','in_review','filed','rejected') DEFAULT 'new',
    assigned_to     INT UNSIGNED NULL,              -- Staff user assigned to process
    patient_id      INT UNSIGNED NULL,              -- Linked patient (once identified)
    notes           TEXT NULL,
    received_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at    DATETIME NULL,
    processed_by    INT UNSIGNED NULL,
    INDEX idx_status (status, received_at),
    INDEX idx_patient (patient_id),
    CONSTRAINT fk_inbox_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

**Workflow:**
1. Document arrives via any source → `InboxManager::receive($fileId, $source, $sourceDetail)` creates inbox item with status `new`
2. Secretary opens inbox → sees list sorted by received_at (newest first), with unprocessed count badge
3. Secretary clicks item → previews document → assigns to patient (autocomplete search) → files to appropriate folder → status becomes `filed`
4. Filed item's file gets auto-linked to the assigned patient via `PatientLinkManager`

**Sidebar navigation:** New "📥 Inbox" entry with unprocessed count badge (between "Activity" and "Compliance" sections).

**Sources integration:**
- **Scanned documents:** after scanning, user drops file in a designated "Inbox" folder → cron picks up new files → creates inbox items with source `scan`
- **Email:** email parsing outside ZPMS scope, but files forwarded to a watched directory are picked up
- **Manual upload:** "Upload to inbox" button in inbox view
- **Patient uploads:** when patient upload feature is added (Tier 3), uploads land in inbox with source `patient_upload`

---

## A.4 Document Templates with Mail Merge

DOCX templates with placeholder markers that auto-populate from patient and practice data.

**Components:**
- `TemplateController.php` — template selection, merge execution, output options
- `MailMergeManager.php` — placeholder resolution, PHPWord integration, PDF conversion

**Library:** PHPWord `TemplateProcessor` class — extract the single class file into `lib/PHPWordTemplate.php`. No full PHPWord install needed; the `TemplateProcessor` is self-contained for `${placeholder}` replacement in DOCX files.

**Template location:** `modules/fileshare/templates/documents/`

**Available placeholders:**
```yaml
placeholders:
  patient:
    - ${patient.full_name}
    - ${patient.first_name}
    - ${patient.last_name}
    - ${patient.date_of_birth}
    - ${patient.age}
    - ${patient.amka}                  # Greek national ID
    - ${patient.address}
    - ${patient.phone}
    - ${patient.email}
    - ${patient.insurance_provider}
    - ${patient.insurance_number}
  practice:
    - ${practice.name}
    - ${practice.address}
    - ${practice.phone}
    - ${practice.email}
    - ${practice.doctor_name}
    - ${practice.doctor_title}
    - ${practice.license_number}
  dates:
    - ${date.today}
    - ${date.today_long}               # "15 February 2026"
    - ${date.today_short}              # "15/02/2026"
```

**Essential templates (5-8 for a small practice):**
1. Referral letter to specialist
2. Appointment confirmation letter
3. Lab result notification letter
4. Fit-for-work / medical certificate
5. GDPR data access response cover letter
6. Generic patient letter
7. Insurance pre-authorization request
8. Medical report template

**Workflow:** Patient record → "Generate Document" → select template → preview with placeholders filled → review/edit → save as PDF → optionally print → auto-file in patient's folder.

---

## A.5 Secure Document Destruction (with Certificates)

Enhances the `DestructionManager` from Phase 9 with a user-facing workflow for manual destruction requests.

**Already implemented in core:** The `DestructionManager` service handles the actual destruction. This addendum adds:

- **Destruction request workflow:** Admin selects files eligible for destruction (past retention, no legal hold) → reviews list → confirms → system executes secure destruction → generates certificate
- **Batch destruction:** Monthly cleanup of expired files — system generates list → admin reviews → batch approve → batch destroy with single summary certificate
- **UI page:** `/files/compliance/destruction` shows destruction history with certificate download links

---

## A.6 DPA/BAA Tracking

Simple vendor registry for data processing agreements. A small practice typically has 3-5 vendors (cloud hosting, fax service, lab interface, backup provider, IT support).

**Already implemented in core:** The `fileshare_dpa_registry` table and `ComplianceController` routes from Phase 9 cover this. This addendum clarifies the small practice workflow:

- **Registry page:** simple table of vendors with DPA dates and expiry
- **Cron alerts:** 30 and 7 days before DPA expiry → notification to admin
- **Linked document:** each DPA entry can link to a scanned copy of the agreement stored in fileshare
- **Annual review reminder:** yearly cron reminds admin to review all active DPAs

---
---

# Addendum B — Small Practice Tier 3: Competitive Advantage

Low urgency features that differentiate a modern small practice. Target: implement within 12-18 months of go-live.

---

## B.1 Patient Upload Links

Generate secure, time-limited upload links sent to patients before appointments. Patients upload required documents (insurance card photos, ID, completed forms) without needing a login.

**Components:**
- `fileshare_upload_requests` table (from original plan Addendum A.1)
- `PublicUploadController.php` — public upload page at `/u/{token}`
- Mobile-optimized upload page with camera capture for document photos
- HEIC-to-JPG auto-conversion via ImageMagick for iPhone users
- Files land in document inbox with source `patient_upload` and auto-linked patient
- Notification to secretary on each received file

**Integration with checklists:** If Addendum A.2 (checklists) is implemented, patient uploads can auto-satisfy checklist items based on document type detection.

---

## B.2 Mobile Document Capture

Replace the flatbed scanner with phone/tablet camera for insurance cards, IDs, and paper forms.

**Implementation:**
```html
<input type="file" accept="image/*" capture="environment">
```
- Server-side: ImageMagick `convert` with `-deskew 40%` and `-normalize` for quality improvement
- Auto-crop to document edges where possible
- Save as high-quality JPEG (85%)
- Works on all modern mobile browsers without any app installation

---

## B.3 Basic OCR

Run Tesseract OCR on scanned/photographed documents to enable full-text search.

**Implementation:**
- Tesseract CLI: `tesseract input.png output -l ell+eng` (Greek + English)
- Async processing via cron: new image/PDF uploads queued, OCR'd in background
- OCR text stored in `fileshare_search_index` (from original plan Addendum A.2)
- Enables filename + content search across all documents
- No routing rules in the small practice version — just search

**Prerequisite:** `apt install tesseract-ocr tesseract-ocr-ell`

---

## B.4 Document Expiry Tracking

Track time-sensitive documents and alert before they expire.

**Database:**
```sql
CREATE TABLE fileshare_document_expiry (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_id     INT UNSIGNED NOT NULL,
    patient_id  INT UNSIGNED NULL,
    expiry_type VARCHAR(50) NOT NULL,              -- insurance_card, referral_auth, prior_auth, license
    expires_at  DATE NOT NULL,
    alert_sent  TINYINT(1) DEFAULT 0,
    INDEX idx_expiry (expires_at, alert_sent),
    INDEX idx_patient (patient_id),
    CONSTRAINT fk_expiry_file FOREIGN KEY (file_id) REFERENCES fileshare_files(id) ON DELETE CASCADE
);
```

**Cron:** Weekly check for documents expiring within 30 days. Dashboard badge: "3 documents expiring this month."

**Common expiry types for a small practice:** insurance card validity, referral authorization windows, prior authorization periods.

---

## B.5 Read Receipts for GDPR Privacy Notice

Track which patients have acknowledged the GDPR privacy notice, supporting GDPR Art. 7 demonstrable consent.

**Implementation:**
- Use the signature system (Addendum A.1) for formal acknowledgment — signed PDF stored as proof
- For existing patients who signed on paper: manual "acknowledged" checkbox in patient record
- Simple report: acknowledged / not yet acknowledged, with date
- Reminder list for secretary: "These patients have appointments this week but haven't acknowledged the privacy notice"

---
---

# Addendum C — General Enhancements (Medium Term)

Features from the original plan that remain relevant, not overlapping with compliance addendums.

---

## C.1 File Requests (Upload Links)

*(Originally Addendum A.1)* — Subsumed by Addendum B.1 (Patient Upload Links) for the patient-facing use case. The general-purpose upload request system (for non-patient external uploads) remains relevant:

- `fileshare_upload_requests` table with token, target folder, max files, allowed types, password, expiry
- Public upload page at `/u/{token}`
- Notifications to owner on each received file
- Same security model as external shares

---

## C.2 Full-Text Search

*(Originally Addendum A.2)* — The `fileshare_search_index` table and `SearchIndexer` service remain as specified. When OCR (Addendum B.3) is added, OCR output feeds into the same search index.

---

## C.3 Smart Filters / Saved Searches

*(Originally Addendum A.3)* — Unchanged. Predefined and user-defined filter combinations.

---

## C.4 Tagging & Organization

*(Originally Addendum A.4)* — Unchanged. Custom color-coded tags.

---

## C.5 File Templates

*(Originally Addendum A.5)* — Subsumed by Addendum A.4 (Document Templates with Mail Merge) for the document generation use case. The basic "New from template" (copy template to user folder) functionality remains as a simpler alternative.

---

## C.6 Retention Compliance

*(Originally Addendum A.6)* — Now integrated into the core plan. Retention periods are enforced via `fileshare_files.retention_until` and `legal_hold` columns, checked by `FileShareManager::deleteItems()` and `DestructionManager`. Configuration in `config/gdpr.yml`.

---

## C.7 Document Classification

*(Originally Addendum A.7)* — Unchanged. Auto-categorize uploads by folder path, filename pattern, or MIME type.

---
---

# Addendum D — Advanced Features (Later Phase)

Lower-priority features that round out the platform.

---

## D.1 Transfer Ownership

*(Originally Addendum B.1)* — Reassign files on staff changes. Decrypt with old user context → re-assign → update all DB records.

## D.2 Quota Reporting & Admin Dashboard

*(Originally Addendum B.2)* — Storage usage analytics. Extended with compliance statistics (audit log size, quarantine usage, destruction volume).

## D.3 Version Diff

*(Originally Addendum B.3)* — Compare file versions visually. Text diff + image overlay.

## D.4 External Storage Mounts

*(Originally Addendum B.4)* — Map network drives as virtual folders. Read-only mounts for legacy archives.

## D.5 Workflow Automation

*(Originally Addendum B.5)* — File-based triggers (upload to folder → auto-tag, auto-share, auto-notify). Foundation for future inbox auto-routing.

## D.6 Retention Policies (Automated)

*(Originally Addendum B.6)* — Auto-delete files past retention. Now integrated with `DestructionManager` — expired files are listed for admin review rather than auto-deleted.

## D.7 Secure Messaging Attachment Integration

*(Originally Addendum B.7)* — Attach fileshare documents to internal ZPMS messages.

---
---

# Addendum E — Future / Aspirational

Features for when the core and compliance layers are mature.

---

## E.1 Offline / Sync

*(Originally Addendum C.1)* — Mark folders for offline access with manifest-based change detection.

## E.2 Delta Storage / Deduplication

*(Originally Addendum C.2)* — Content-addressed storage with SHA-256 deduplication. Note: encryption complicates deduplication since identical plaintext produces different ciphertext (unique nonces). Would require dedup before encryption with shared nonce management.

## E.3 Auto-Lock with Heartbeat

*(Originally Addendum C.3)* — Heartbeat-based lock release.

## E.4 Department-Level Quotas

*(Originally Addendum C.4)* — Per-department storage quotas.

## E.5 File Type Usage Analytics

*(Originally Addendum C.5)* — Storage trend charts over time.

---
---

# Appendix: Regulatory Reference Summary

| Regulation | Identifier | Status | Key Requirements for ZPMS |
|------------|-----------|--------|--------------------------|
| GDPR | EU 2016/679 | In force May 2018 | Encryption, access controls, audit logging, data subject rights, 72h breach notification, DPA tracking |
| EHDS | EU 2025/327 | In force March 2025; primary data exchange March 2029 | Patient data access, access log transparency (Art. 9), opt-out for secondary use (Art. 10) |
| eIDAS 2.0 | EU 2024/1183 | In force May 2024 | SES for consent forms, optional QES via QTSP |
| NIS2 | EU 2022/2555 | Transposed October 2024 | Encryption, virus scanning, incident reporting (24h/72h/1mo), security logging |
| EAA | EU 2019/882 | Enforceable since 28 June 2025 | WCAG 2.1 AA via EN 301 549. Microenterprise exemption may apply. |
| HIPAA Security | 45 CFR 164 | In force | Session logoff (§312(a)(2)(iii)), integrity (§312(c)(1)—required), audit controls (§312(b)), encryption (§312(a)(2)(iv)), emergency access (§312(a)(2)(ii)) |
