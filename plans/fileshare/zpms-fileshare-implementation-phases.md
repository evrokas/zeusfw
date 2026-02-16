# ZPMS Fileshare Module — Phased Implementation Plan

**Date:** February 2026
**Branch:** `fileshare`
**Reference:** `zpms-fileshare-plan-v2.md`, `zpms-comprehensive-doc.md`

Each phase produces a **testable, working increment**. Do not start a phase before the previous one passes its test criteria. Phases are sized to take roughly a focused work session.

---

## Phase 1 — Foundation: Database + Module Skeleton

**Goal:** Module loads without errors. DB tables exist. Route `/files` returns a placeholder page.

### Tasks

1. **Create SQL migration file** `sql/fileshare.sql`
   - Tables (in dependency order):
     1. `fileshare_files`
     2. `fileshare_uploads`
     3. `fileshare_shares`
     4. `fileshare_internal_shares`
     5. `fileshare_share_access_log`
     6. `fileshare_versions`
     7. `fileshare_comments`
     8. `fileshare_activity`
     9. `fileshare_notifications`
     10. `fileshare_locks`
     11. `fileshare_patient_links`
     12. `fileshare_audit_log`
     13. `fileshare_gdpr_requests`
     14. `fileshare_breach_incidents`
     15. `fileshare_dpa_registry`
     16. `fileshare_destruction_log`
   - Use exact DDL from Section 4 of `zpms-fileshare-plan-v2.md`

2. **Create YAML entity schemas** in `web/classes/yaml/`:
   - `fileshare_files.yaml`
   - `fileshare_shares.yaml`
   - `fileshare_uploads.yaml`
   - `fileshare_versions.yaml`
   - `fileshare_notifications.yaml`
   - `fileshare_locks.yaml`
   - `fileshare_patient_links.yaml`
   - `fileshare_activity.yaml`
   - *(compliance tables are INSERT-only or rarely accessed — skip auto-generation for audit_log, gdpr_requests, breach_incidents)*

3. **Create module directory structure**:
   ```
   web/modules/fileshare/
   ├── fileshare.php          # Module class + all route handler functions
   ├── fileshare.info.yaml    # Module metadata
   ├── fileshare.zetem        # Empty module template (renders nothing)
   ├── fileshare.yaml         # Routes config (loaded by module)
   ```

4. **Create `fileshare.info.yaml`**:
   ```yaml
   name: fileshare
   description: "File sharing and document management"
   version: 1.0.0
   ```

5. **Create `fileshare.yaml`** (routes config, ZPMS pattern — see `dicom.yaml`):
   - Register route: `GET /files` → `fileshare_browser`
   - Access: `fileshare-view` permission

6. **Create `fileshare.php`** (minimal module class):
   - Class `fileshareModule extends moduleClass`
   - Constructor loads `fileshare.yaml` routes (same pattern as `dicomModule`)
   - `render()` returns `''`
   - `register_fileshare_module()` function
   - Route handler function `fileshare_browser($params)` that returns a placeholder rendered template

7. **Create `web/templates/content/fileshare_browser.zetem`** — minimal HTML: `<h1>Files</h1>`

8. **Register module** in `config/settings.info.yaml`:
   - Add `fileshare` to `modules.modules` list
   - Add `fileshare-view` permission (mapped to `authenticated` role)
   - Add menu item: `/files` → "Files" (gr: "Αρχεία")

9. **Create storage directories**:
   ```
   data/fileshare/storage/
   data/fileshare/tmp/
   data/fileshare/versions/
   data/fileshare/thumbnails/
   data/fileshare/quarantine/
   data/fileshare/destruction_certs/
   ```
   (Note: `data/` mirrors the DICOM module's `data/dicom/` pattern)

### Test Criteria
- Run `sql/fileshare.sql` — no errors, all 16 tables created
- Navigate to `/files` — page loads, placeholder heading visible, no PHP errors
- Menu shows "Files" link for logged-in users

---

## Phase 2 — File Browser: List View

**Goal:** `/files` shows the authenticated user's root files/folders from DB with proper layout.

### Tasks

1. **Create `ClassesEx.php` extensions** for fileshare entities (add to `web/ClassesEx.php`):
   ```php
   class fileshare_filesClassEx extends fileshare_filesClass {
       static function getFolderContents(int $userId, ?int $parentId): array
       static function getRootContents(int $userId): array
       static function getFolderById(int $id, int $userId): ?object
   }
   ```

2. **Update `fileshare_browser()` handler**:
   - Accept optional `{folder_id}` URL param
   - Fetch folder contents via `fileshare_filesClassEx::getFolderContents()`
   - Build breadcrumb trail by walking up parent_id chain
   - Pass to template: `files`, `current_folder`, `breadcrumbs`, `parent_id`

3. **Add route** `GET /files/browse/{folder_id}` → same `fileshare_browser` handler

4. **Design `fileshare_browser.zetem`** template:
   - Breadcrumb navigation bar
   - Toolbar: Upload button (disabled for now), New Folder button (disabled), Sort controls
   - File list table: icon, name (clickable for folders), size, modified, actions column
   - Empty state message when folder is empty
   - Use existing CSS variables from `web/css/design/design-system.css`

5. **Create `web/css/fileshare.css`**:
   - File browser layout
   - File row styles (hover, selected state)
   - Icon color classes per file type (reuse design tokens)
   - Breadcrumb styles

6. **Register CSS library** in `config/settings.info.yaml` under `libraries:`:
   ```yaml
   fileshare:
     css:
       - web/css/fileshare.css
   ```
   Attach in template: `{% attach_library('fileshare') %}`

7. **Insert 3–5 test rows** directly in DB (via SQL) to verify rendering

### Test Criteria
- `/files` shows list of test files with correct icons and sizes
- Clicking a folder navigates to `/files/browse/{id}` showing its contents
- Breadcrumb shows correct path
- Empty folder shows friendly empty-state message
- Logged-out users are redirected to login

---

## Phase 3 — Folder Operations (Create, Rename, Delete)

**Goal:** Users can create folders, rename files/folders, and soft-delete them — all via AJAX.

### Tasks

1. **Add AJAX route handlers** in `fileshare.php`:
   - `POST /files/api/mkdir` → `fileshare_api_mkdir($params)`
   - `POST /files/api/rename` → `fileshare_api_rename($params)`
   - `POST /files/api/delete` → `fileshare_api_delete($params)` (soft-delete: sets `is_trashed=1`)
   - All return JSON `{success: bool, message: string, data: ...}`

2. **Add validation** in each handler:
   - `mkdir`: name length, duplicate name in same parent, user ownership
   - `rename`: same validation, ownership check
   - `delete`: ownership check, check for `legal_hold=1` (refuse with message)

3. **Insert activity log entry** on every successful operation via helper:
   ```php
   fileshare_activityClassEx::log($userId, $fileId, 'folder_create'|'rename'|'delete', $details)
   ```

4. **Create `web/js/fileshare.js`** (module JS entry point):
   - `mkdir()` — shows inline input field, POSTs, refreshes list
   - `renameFile(id)` — inline rename, POSTs
   - `deleteFile(id)` — confirm dialog, POSTs, removes row from DOM
   - Generic `apiCall(url, data)` → returns JSON, handles errors

5. **Register JS** in library config and attach in template

6. **Enable toolbar buttons**: "New Folder" triggers `mkdir()`, row action menu shows Rename / Delete

### Test Criteria
- Create a folder — appears in list immediately without page reload
- Rename a file row — name updates in place
- Delete a file — row disappears; navigating to Trash later (Phase 8) shows it
- Legal-hold files show delete disabled/error message
- JS errors: none in browser console

---

## Phase 4 — File Upload (Chunked AJAX)

**Goal:** Users can upload files via drag-and-drop or file picker with progress bar and chunked transfer.

### Tasks

1. **Add upload route handlers**:
   - `POST /files/api/upload/init` → `fileshare_api_upload_init($params)`
     - Validates filename, extension, filesize
     - Creates `fileshare_uploads` record, returns `{token, chunk_size}`
   - `POST /files/api/upload/chunk` → `fileshare_api_upload_chunk($params)`
     - Accepts `token`, `chunk_index`, binary chunk data
     - Saves chunk to `data/fileshare/tmp/{token}/chunk_{n}`
     - Updates `chunks_received` count
   - `POST /files/api/upload/finalize` → `fileshare_api_upload_finalize($params)`
     - Assembles chunks into final file
     - Computes SHA-256 hash
     - Creates `fileshare_files` record with `disk_path`, `file_hash`, `size`
     - Cleans up temp chunks
     - Returns `{success, file_id, file_name}`

2. **Extension/MIME validation** in `upload_init`:
   - Block extensions from the `blocked_extensions` list in settings
   - Validate against `allowed_extensions`

3. **Create `web/js/fileshare-uploader.js`** (adapted from `web/js/dicom-upload.js`):
   - Drag-and-drop zone on file browser
   - File picker fallback
   - Chunked upload loop with progress bar per file
   - Queue multiple files
   - On complete: add new file rows to DOM

4. **Create `web/templates/content/fileshare_upload_zone.zetem`** partial (included in browser template):
   - Drop zone overlay
   - Upload queue panel showing progress bars

5. **Register uploader JS** in library config

6. **Add upload permission** `fileshare-upload` (mapped to `authenticated`)

### Test Criteria
- Drag a file onto the browser — upload starts, progress bar fills
- File appears in the browser list on completion
- Uploading a `.php` file → rejected with "File type not allowed" error
- Upload a 20 MB file (multi-chunk) — assembles correctly, hash matches
- Refresh page — uploaded file persists (DB record confirmed)

---

## Phase 5 — File Download + Preview

**Goal:** Users can download files and preview images/PDFs/text inline.

### Tasks

1. **Add download route**:
   - `GET /files/download/{file_id}` → `fileshare_download($params)`
   - Validates ownership or internal share access
   - Streams file with correct Content-Type and Content-Disposition headers
   - Updates `last_accessed` timestamp
   - Logs `file_download` in activity

2. **Add preview/thumbnail route**:
   - `GET /files/preview/{file_id}` → `fileshare_preview($params)`
   - For images: resize to max 1024px, cache thumbnail in `data/fileshare/thumbnails/`
   - For PDFs: serve inline with `Content-Disposition: inline`
   - For text/code/JSON: serve UTF-8 content with syntax highlighting hint
   - Returns 404 for unsupported types

3. **File click behaviour in browser**:
   - Image/PDF/text: opens preview panel (side panel or modal)
   - Other: triggers download

4. **Create preview panel** in `fileshare_browser.zetem`:
   - Slide-in side panel (CSS transition)
   - Shows: file name, size, type, date, preview area
   - Close button, Download button

5. **Add preview JS** in `fileshare.js`:
   - `openPreview(fileId, mimeType)` — fetches `/files/preview/{id}`, injects into panel
   - Shows spinner while loading

### Test Criteria
- Click a JPG — preview panel opens with image rendered
- Click a PDF — preview panel shows embedded PDF viewer
- Click a `.docx` — browser download dialog appears
- Download URL requires authentication (logged-out → redirect to login)
- `last_accessed` column updated in DB after download

---

## Phase 6 — Cut / Copy / Paste (Clipboard Operations)

**Goal:** Users can cut/copy files and paste them into another folder.

### Tasks

1. **Add move/copy route handlers**:
   - `POST /files/api/move` → `fileshare_api_move($params)` (cut+paste)
     - Updates `parent_id` of file record
     - Validates target folder belongs to same user, not moving folder into itself
   - `POST /files/api/copy` → `fileshare_api_copy($params)`
     - Duplicates `fileshare_files` record with new `parent_id`
     - Copies the physical file on disk to new path
     - Generates new SHA-256 hash record

2. **Clipboard state in JS** (in-memory, no server state needed):
   - `clipboardAction`: `'cut'` or `'copy'`
   - `clipboardItems`: array of `{id, name}`
   - Visual indicator on cut items (muted opacity)

3. **Context menu** (right-click) on file rows:
   - Cut, Copy, Paste (when clipboard non-empty + in folder view), Rename, Delete, Share, Download

4. **Keyboard shortcuts**: Ctrl+X, Ctrl+C, Ctrl+V via `keydown` listener

5. **Activity logging** for move and copy events

### Test Criteria
- Cut a file from folder A, navigate to folder B, paste — file moves (disappears from A, appears in B)
- Copy a file — original stays in A, copy appears in B
- Attempt to paste a folder into itself → rejected with error message
- Keyboard shortcuts work
- After page refresh, moved file is in correct location (DB confirmed)

---

## Phase 7 — External File Sharing (Link + Code + QR)

**Goal:** Users can share files via a public link, a short code, and a QR code.

### Tasks

1. **Add share management route handlers**:
   - `POST /files/api/share/create` → `fileshare_api_share_create($params)`
     - Generates random `share_token` (32 chars) and optional `share_code` (8 chars)
     - Creates `fileshare_shares` record
     - Returns `{token, code, share_url, qr_url}`
   - `GET /files/api/share/list/{file_id}` → list active shares for a file
   - `POST /files/api/share/revoke` → set `is_active=0`, record `revoked_at`

2. **Public share access route** (no auth required):
   - `GET /share/{token}` → `fileshare_public_share($params)`
     - Validates token, checks `is_active`, `expires_at`, `max_downloads`
     - If password-protected → shows password form
     - Shows file info + Download button
   - `POST /share/{token}` — password submission
   - `GET /share/{token}/download` — actual file download (no auth)
   - `GET /share/code` + `POST /share/code` — enter share code form

3. **QR code generation**:
   - Include `phpqrcode.php` (pure PHP, no Composer) in `web/modules/fileshare/lib/`
   - `GET /files/api/share/qr/{token}` → outputs PNG QR code image
   - Show QR in share dialog

4. **Share dialog** in browser template:
   - Toggle: Link | Code
   - Optional: expiry date picker, password, max downloads
   - Display generated link with copy button, QR code image
   - List existing shares with revoke buttons

5. **Create `web/templates/content/fileshare_public.zetem`** — public share landing page:
   - File name, type icon, size, shared-by (optional), expiry info
   - Download button
   - "Powered by ZPMS" footer

6. **Share access logging**: every visit to `/share/{token}` inserts into `fileshare_share_access_log`

7. **Add `fileshare-share` permission** (mapped to `authenticated`)

### Test Criteria
- Create a share → link and QR code appear in dialog
- Open share link in incognito (not logged in) → public page shows file details
- Download from public link → file downloads correctly
- Revoke share → public link returns 404/expired page
- Password-protected share → password form shown, wrong password rejected, correct allows download
- `download_count` increments in DB on each download

---

## Phase 8 — Trash Bin

**Goal:** Deleted files go to trash; users can restore or permanently delete them.

### Tasks

1. **Add trash route handlers**:
   - `GET /files/trash` → `fileshare_trash($params)` — list `is_trashed=1` files for user
   - `POST /files/api/trash/restore` → set `is_trashed=0`, `trashed_at=NULL`
   - `POST /files/api/trash/purge` → permanently delete: remove DB record + disk file (`unlink`)
   - `POST /files/api/trash/empty` → purge all trash items for user

2. **Create `web/templates/content/fileshare_trash.zetem`**:
   - Same list layout as browser, but shows `trashed_at` date
   - Per-row: Restore, Delete Permanently buttons
   - "Empty Trash" button at top
   - Confirmation dialog for permanent deletion

3. **Add "Trash" menu item** under Files navigation

4. **Auto-purge cron stub**: add `fileshare_trash_cleanup()` function (called from maintenance hook) that deletes items where `trashed_at < NOW() - INTERVAL 30 DAY`

5. **Activity logging** for restore and purge events

### Test Criteria
- Delete a file → appears in Trash with trashed date
- Restore → returns to original folder (breadcrumb path preserved)
- Purge → record gone from DB, disk file deleted
- Empty Trash → all user's trash items removed
- Files in trash do NOT appear in the main browser

---

## Phase 9 — File Versioning

**Goal:** When a file is overwritten, the old version is automatically archived and can be restored.

### Tasks

1. **Versioning logic** integrated into `upload_finalize`:
   - When file with same name in same folder already exists:
     - Copy current `disk_path` content to `data/fileshare/versions/{user_id}/{file_id}/v{n}_{ts}`
     - Insert `fileshare_versions` record with `version_number`, `file_hash`, `disk_path`
     - Update `fileshare_files` record with new file content

2. **Add versioning route handlers**:
   - `GET /files/api/versions/{file_id}` → return JSON list of versions
   - `POST /files/api/versions/restore` → copy version file back as current, create new version of current

3. **Version history panel** in preview/details panel (fetched via AJAX):
   - List of versions: version number, date, size, uploader
   - "Restore" button per version
   - "Download this version" link

4. **Version cleanup stub**: `fileshare_versions_cleanup()` — removes versions > `max_versions_per_file` (50) or older than `retention_days` (365)

### Test Criteria
- Upload file `report.pdf`, then upload another `report.pdf` to same folder
- Version history shows 2 entries (original + overwrite)
- Restore v1 → current file becomes v1 content; a new version record is created for what was current
- Download a specific version → correct content served
- Hash of each version matches content

---

## Phase 10 — Internal User-to-User Sharing

**Goal:** ZPMS users can share files/folders with other ZPMS users with viewer/editor/uploader roles.

### Tasks

1. **Add internal share route handlers**:
   - `POST /files/api/internal-share/create` → `fileshare_api_internal_share_create($params)`
   - `POST /files/api/internal-share/revoke`
   - `GET /files/api/internal-share/list/{file_id}`

2. **Access control in all file handlers**:
   - In `fileshare_browser`, `fileshare_download`, etc.: also fetch files shared *with* current user
   - A user with `viewer` permission can view/download but not rename/delete
   - A user with `editor` can rename, delete, upload to shared folder
   - A user with `uploader` can only upload into the folder

3. **"Shared with me" view**:
   - `GET /files/shared` → `fileshare_shared($params)` — list files/folders in `fileshare_internal_shares` where `target_user_id = current_user`
   - Template similar to browser

4. **Internal share dialog** in browser (separate tab from external share dialog):
   - User search/select input (AJAX autocomplete from user list)
   - Permission dropdown: Viewer / Editor / Uploader
   - List of current internal shares with revoke buttons

5. **Notification on share**: insert into `fileshare_notifications` when a share is created

### Test Criteria
- Share a folder with User B as Viewer
- Log in as User B → folder appears in "Shared with me"
- User B can browse and download files
- User B CANNOT rename or delete (action buttons disabled or return 403)
- Revoking share → folder disappears from User B's "Shared with me"
- User B receives a notification "User A shared X with you"

---

## Phase 11 — File Locking

**Goal:** Users can lock a file to signal exclusive editing; others see it as locked.

### Tasks

1. **Add lock route handlers**:
   - `POST /files/api/lock` → `fileshare_api_lock($params)` — insert into `fileshare_locks`
   - `POST /files/api/unlock` → `fileshare_api_unlock($params)` — delete lock record
   - `GET /files/api/lock/status/{file_id}` — return lock info (locked by, expires)

2. **Show lock indicator** in file browser rows:
   - Lock icon badge on locked files
   - Tooltip: "Locked by [name] until [time]"

3. **Enforce lock in rename/delete/overwrite**: if file is locked by another user → return 423 Locked with message

4. **Lock cleanup stub**: `fileshare_locks_cleanup()` — deletes rows where `expires_at < NOW()`

5. **Notifications**: notify file owner when their file's lock is released by expiry

### Test Criteria
- Lock a file → lock icon appears in browser
- Attempt rename while locked by another user → error message shown
- Lock owner can unlock their own lock
- Lock with 1-minute expiry → after expiry, lock icon disappears, file editable again

---

## Phase 12 — Comments + Activity Feed

**Goal:** Users can post threaded comments on files and see an activity stream.

### Tasks

1. **Comments route handlers**:
   - `GET /files/api/comments/{file_id}` → JSON list of comments (threaded)
   - `POST /files/api/comments/add` → insert comment, return rendered HTML
   - `POST /files/api/comments/delete` → soft-delete (set `is_deleted=1`, body becomes "[deleted]")

2. **Activity feed route handlers**:
   - `GET /files/activity` → `fileshare_activity($params)` — global activity feed page
   - `GET /files/api/activity/{file_id}` → JSON activity for a specific file

3. **Comments panel** in file details sidebar (loaded via AJAX):
   - Threaded comment display (indent replies)
   - Reply/Delete buttons
   - Post comment form

4. **Activity feed template** `web/templates/content/fileshare_activity.zetem`:
   - Timeline of events: who, what action, which file, when
   - Filter by file or by action type

5. **Ensure all prior operations log activity**: review Phases 3–11 handlers and add `fileshare_activityClassEx::log()` calls where missing

### Test Criteria
- Post a comment on a file → appears immediately
- Reply to a comment → indented below parent
- Delete a comment → shows "[deleted]"
- Activity feed shows upload, rename, delete, share events in chronological order
- Per-file activity in details panel shows only events for that file

---

## Phase 13 — Notifications

**Goal:** Users see an unread notification badge and dropdown with recent alerts.

### Tasks

1. **Notification route handlers**:
   - `GET /files/api/notifications` → JSON: `{unread_count, items: [...]}`
   - `POST /files/api/notifications/read` → mark one or all as read

2. **Notification badge** in top nav (via existing framework topbar module):
   - Poll `/files/api/notifications` every 30 seconds via `setInterval`
   - Update badge count
   - Dropdown list of recent notifications (max 10)
   - "Mark all read" button

3. **Consolidate notification dispatch**: create helper `fileshare_notify($userId, $type, $title, $body, $link)` used throughout all handlers

4. **Wire up notifications** for key events:
   - File shared with you (Phase 10 already done)
   - Comment on your file
   - Share accessed/downloaded (you shared the file)
   - Lock released on your file

### Test Criteria
- Receive a share → notification appears in badge within 30 seconds
- Click notification → navigates to relevant file/folder
- Mark as read → badge count decreases
- Unread count persists on page reload (server-side)

---

## Phase 14 — Favorites + Recent Files

**Goal:** Users can favorite files and see a "Recent" view of last-accessed files.

### Tasks

1. **Route handlers**:
   - `POST /files/api/favorite/toggle` → flip `is_favorited` flag on `fileshare_files`
   - `GET /files/favorites` → `fileshare_favorites($params)` — list `is_favorited=1` files
   - `GET /files/recent` → `fileshare_recent($params)` — list last 50 files by `last_accessed DESC`

2. **Favorite star icon** in file browser rows:
   - Filled star = favorited, empty star = not favorited
   - Click toggles via AJAX (no page reload)

3. **Templates**:
   - `fileshare_favorites.zetem` — same list component, titled "Favorites"
   - `fileshare_recent.zetem` — same list component, shows "Last accessed" column

4. **Navigation**: add Favorites and Recent items to Files sidebar nav

### Test Criteria
- Star a file → star fills, file appears in Favorites view
- Unstar → disappears from Favorites
- Download a file → it appears at top of Recent view
- Recent shows at most 50 items, newest first

---

## Phase 15 — Patient Record Linking

**Goal:** Files can be linked to patient records, visible from both the file browser and patient record.

### Tasks

1. **Route handlers**:
   - `POST /files/api/patient-link/create` → insert into `fileshare_patient_links`
   - `POST /files/api/patient-link/remove`
   - `GET /files/api/patient-link/list/{file_id}` → linked patients for a file
   - `GET /files/api/patient-files/{patient_id}` → files linked to a patient

2. **Patient link dialog** in file details panel:
   - Patient search (AJAX autocomplete, reuse existing patient search from ZPMS)
   - Link type dropdown: "general", "lab_result", "imaging", "prescription", "consent", "invoice"
   - List of current links with remove buttons

3. **Patient record integration** (view-only for now):
   - In the patient detail page template, show a "Files" tab/section
   - Calls `/files/api/patient-files/{patient_id}` to render linked files

4. **Audit logging** for link create/remove (uses `fileshare_audit_log`)

### Test Criteria
- Link a PDF to a patient → appears in patient's Files section
- Remove link → disappears from patient section
- Link type "lab_result" shows in both places with correct label
- Audit log contains `patient_link_create` event with correct `patient_id`

---

## Phase 16 — Compliance Layer (Core)

**Goal:** Encryption at rest, integrity verification, and ClamAV virus scanning are operational.

### Tasks

1. **Encryption (PHP Sodium)**:
   - Create `web/modules/fileshare/EncryptionManager.php`:
     - `encrypt(string $plaintext): array {ciphertext, nonce}`
     - `decrypt(string $ciphertext, string $nonce): string`
     - Reads key from `/etc/zpms/encryption.key` (or config path)
   - Update `upload_finalize` to encrypt file after assembly
   - Update `fileshare_download` to decrypt before streaming
   - Update `fileshare_versions` to encrypt version files
   - Store `encryption_nonce` (hex) in `fileshare_files.encryption_nonce`

2. **Integrity verification**:
   - SHA-256 hash computed in `upload_finalize` *before* encryption → stored in `file_hash`
   - On every download: recompute hash of decrypted content → compare to `file_hash`
   - Mismatch → block download, log `integrity_violation` in audit log, notify admin

3. **ClamAV scanning**:
   - Create `web/modules/fileshare/VirusScanner.php`:
     - `scan(string $filePath): array {clean: bool, threat: string|null}`
     - Connects to ClamAV Unix socket at `/var/run/clamav/clamd.ctl`
     - On virus found: move file to quarantine, set upload status `quarantined`
   - Integrate into `upload_finalize` *before* encryption step
   - If ClamAV socket unavailable: log warning, allow upload (fail-open with warning)

4. **Compliance audit logger** stub:
   - Create `web/modules/fileshare/ComplianceAuditLogger.php`:
     - `log($userId, $action, $resourceType, $resourceId, $patientId, $details)`
     - Computes hash chain (prev_hash + entry data → SHA-256)
     - Inserts into `fileshare_audit_log`
   - Wire into: login/logout (existing ZPMS auth), upload, download, delete, share events

5. **Create `config/fileshare/` config files** (from plan Section 3):
   - `encryption.yml` — enable flag, key_file path
   - `virus-scan.yml` — socket path, quarantine settings
   - `audit.yml` — retention, events to log

### Test Criteria
- Upload a file → `is_encrypted=1` in DB, `encryption_nonce` populated
- Physical file on disk is binary garbage (not readable as plain text)
- Download → file decrypts correctly, content matches original
- Upload EICAR test file (`X5O!P%@AP[4\PZX...`) → upload rejected, file in quarantine folder
- Corrupt a file on disk → download blocked with "Integrity violation" error
- `fileshare_audit_log` gains entries for upload and download actions; hash chain is valid

---

## Phase 17 — Audit Dashboard + Export

**Goal:** Administrators can view the compliance audit trail and export it as CSV.

### Tasks

1. **Audit dashboard route**:
   - `GET /files/audit` → `fileshare_audit($params)` — paginated audit log view
   - Filters: date range, user, action type, patient
   - Add `fileshare-audit` permission (mapped to `administrator` role)

2. **CSV export**:
   - `GET /files/audit/export` → streams CSV download of filtered results
   - Columns: id, timestamp, user, action, resource_type, resource_id, patient_id, ip_address, details

3. **Hash chain verification** endpoint (admin):
   - `GET /files/audit/verify` → walks all audit log entries, recomputes chain
   - Reports: "Chain intact" or "Break detected at entry #{id}"

4. **Template `fileshare_audit.zetem`**:
   - Filter form at top
   - Paginated table: timestamp, user, action (colored badge), resource, patient, IP
   - Export CSV button
   - Verify Chain button

### Test Criteria
- Audit page accessible to admin, blocked for non-admin
- Filter by "upload" action → shows only upload rows
- Export CSV → downloads valid CSV with all filtered rows
- Verify chain on unmodified log → "Chain intact"
- Manually INSERT a row with wrong `prev_hash` → verify reports break at correct entry

---

## Phase 18 — GDPR Request Workflow

**Goal:** Staff can log and process GDPR data subject requests with deadline tracking.

### Tasks

1. **GDPR route handlers**:
   - `GET /files/compliance/gdpr` → list of requests with status and countdown
   - `POST /files/compliance/gdpr/create` → create new request record, set `deadline_at = NOW() + 30 days`
   - `POST /files/compliance/gdpr/update` → update status, add notes
   - `GET /files/compliance/gdpr/package/{request_id}` → build access package (ZIP with files + manifest)

2. **Access package builder**:
   - For `access` requests: ZIP all files linked to that patient + JSON manifest + cover letter text
   - For `erasure` requests: if `retention_until` not yet passed → auto-reject with reason from config
   - For `portability`: same as access but in structured JSON format

3. **Deadline warnings** in UI:
   - Red badge when < 7 days remaining
   - Orange badge when < 14 days

4. **Template `fileshare_gdpr.zetem`**:
   - Request list: patient, type, received date, deadline countdown, status, actions
   - Create request modal
   - Process request panel with notes and status update

5. **Add `fileshare-compliance` permission** (mapped to `administrator`)

### Test Criteria
- Create access request for patient → row shows 30-day deadline
- Advance system date by 23 days → request shows < 7 days warning in red
- Generate access package for patient with linked files → ZIP downloads with correct contents
- Create erasure request for patient with `retention_until` in future → auto-rejected with reason
- Audit log contains `gdpr_access_request` event

---

## Phase 19 — Session Guard (HIPAA Inactivity Timeout)

**Goal:** Sessions expire after 15 minutes of inactivity with a 2-minute warning modal.

### Tasks

1. **Server-side keepalive endpoint** (framework-level, not fileshare-specific):
   - `POST /session/keepalive` → resets `$_SESSION['last_activity']`, returns `{ok: true}`
   - `GET /session/status` → returns `{expires_in: seconds}`

2. **Create `web/js/session-guard.js`**:
   - Track last user activity (mouse move, click, keydown) with throttle
   - Every 30 seconds: check time since last activity
   - At `timeout - 120 seconds`: show warning modal with countdown
   - "Stay logged in" button → POST to keepalive, dismiss modal
   - At timeout: POST to `/logout`, reload page

3. **Warning modal** (HTML in base page template):
   - Overlay with countdown timer
   - "Stay logged in" / "Logout now" buttons

4. **Session timeout logging**: on server-side session expiry detection, log `session_timeout` in audit

5. **Attach `session-guard.js`** to all authenticated pages (base library)

### Test Criteria
- Idle for 13 minutes → warning modal appears with ~2 minute countdown
- Click "Stay logged in" → modal dismisses, session extended
- Let timer reach 0 → redirected to login
- Session timeout event appears in audit log
- Active user (periodic clicks) → no modal shown

---

## Phase 20 — Destruction Workflow + Certificates

**Goal:** Files can be securely destroyed with a generated PDF certificate of destruction.

### Tasks

1. **Secure delete function**:
   - Create `web/modules/fileshare/DestructionManager.php`:
     - `destroy(int $fileId, int $userId, string $reason): string $certPath`
     - Calls `shred -vfz -n 3 {filepath}` via `shell_exec()`
     - Inserts record into `fileshare_destruction_log`
     - Generates PDF certificate (using simple HTML-to-PDF or pre-formatted text cert)

2. **Certificate generation**:
   - Use PHP's built-in `dompdf` or simple text-based cert (avoid TCPDF complexity for now)
   - Certificate includes: file name, hash, size, destruction date, method, user, reason
   - Saved to `data/fileshare/destruction_certs/{year}/cert_{id}_{date}.pdf`

3. **Legal hold check** in destruction: if `legal_hold=1` → refuse with message

4. **Destruction route**:
   - `POST /files/api/destroy` → `fileshare_api_destroy($params)`
   - Requires `fileshare-destroy` permission (administrator only)
   - Confirmation step: show file details + download certificate link

5. **Destruction log view**:
   - `GET /files/compliance/destructions` → paginated log with certificate download links

### Test Criteria
- Destroy a file → physical file removed from disk (verified with `ls`)
- `fileshare_destruction_log` record created with correct hash and cert path
- Certificate PDF downloadable and shows correct details
- Attempt to destroy a `legal_hold=1` file → rejected
- Attempt destruction by non-admin → 403 error

---

## Summary — Phase Sequence

| Phase | Feature | Testable Outcome |
|-------|---------|-----------------|
| 1 | Foundation: DB + module skeleton | `/files` loads |
| 2 | File browser list view | Files/folders displayed |
| 3 | Folder ops: create/rename/delete | AJAX CRUD works |
| 4 | Chunked file upload | Files upload with progress |
| 5 | Download + preview | Images/PDFs preview inline |
| 6 | Cut/copy/paste | Files move/copy between folders |
| 7 | External sharing (link/code/QR) | Public share link works |
| 8 | Trash bin | Soft-delete + restore |
| 9 | File versioning | Overwrite creates version, restore works |
| 10 | Internal user sharing | Share with ZPMS user, RBAC enforced |
| 11 | File locking | Lock prevents edits by others |
| 12 | Comments + activity feed | Threaded comments, event timeline |
| 13 | Notifications | Real-time badge + dropdown |
| 14 | Favorites + recent files | Star files, recent history |
| 15 | Patient record linking | Files attached to patient records |
| 16 | Compliance: encryption + AV + audit | Files encrypted, viruses rejected, chain audit |
| 17 | Audit dashboard + CSV export | Admin can view and export audit log |
| 18 | GDPR request workflow | 30-day deadline tracking, access packages |
| 19 | Session guard (HIPAA) | 15-min inactivity timeout with warning |
| 20 | Destruction + certificates | Secure shred + PDF certificate |

---

## Implementation Notes

### Pattern Reference
- Follow the exact module pattern of `web/modules/dicom/dicom.php`
- All route handlers are plain functions in `fileshare.php` (no controller classes)
- Routes registered via `fileshare.yaml` loaded in module constructor
- Use `Renderer::render('template.zetem', $vars)` for all page responses
- AJAX handlers return `json_encode(['success' => ..., 'data' => ...])` with appropriate headers
- Permissions added to `config/settings.info.yaml` under `roles:`, access checked with `SecurityClass::require()`

### File Storage Path
- DICOM uses `data/dicom/`. Fileshare will use `data/fileshare/` (same sibling pattern under `__APPDIR__`)
- `__APPDIR__` is defined by the framework bootstrap

### No External Libraries
- No Composer. Vendor libs go in `web/modules/fileshare/lib/` as single-file includes
- QR codes: `phpqrcode.php` (pure PHP)
- PDF certs: either simple `dompdf` single-file or generate as HTML and trigger browser print (Phase 20)

### CSS Approach
- New styles go in `web/css/fileshare.css` and registered as a library
- Use existing design tokens from `web/css/design/design-system.css` (CSS variables)
- Do not modify global `styles.css`
