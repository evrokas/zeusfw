# ZPMS File Sharing Module — Feature Documentation

Version 1.0 — Covers Core Features through Tier 2

---

## Table of Contents

1. File Browser & Navigation
2. File Upload System
3. File & Folder Operations
4. Trash & Recovery
5. File Previews & Thumbnails
6. Favorites & Recent Files
7. Storage Quotas
8. External Sharing (Links & Codes)
9. Internal Sharing (User-to-User)
10. QR Code Generation
11. File Versioning
12. File Comments
13. Activity Stream
14. Notification System
15. File Locking
16. Patient Record Linking
17. Audit Trail & Compliance Export
18. Public Share Access
19. Scheduled Tasks (Cron)

---

## 1. File Browser & Navigation

### 1.1 Purpose

The file browser is the primary interface for managing files and folders. It provides a Nextcloud-inspired two-panel layout: a persistent left sidebar for navigation categories and a main content area displaying the file listing for the current location.

### 1.2 Sidebar Navigation

The left sidebar contains the following navigation entries, each loading a different view in the main content area:

- **All files** — Default view. Shows the user's root directory. Clicking folders navigates into them.
- **Favorites** — Shows all files and folders the user has starred, across all directories, sorted by most recently favorited.
- **Recent** — Shows the last 50 files the user has accessed (opened, downloaded, or previewed), sorted by `last_accessed` timestamp descending.
- **Shared with me** — Lists files and folders that other ZPMS users have shared with the current user via internal sharing. Grouped by share owner.
- **Shared by me** — Lists files and folders the current user has shared with other ZPMS users, along with active external shares.
- **Trash** — Shows soft-deleted files awaiting permanent removal. Displays days remaining before auto-purge.
- **Activity** — Full-page activity feed showing all file operations performed by or affecting the current user.

Below the navigation entries, a **quota usage bar** displays current storage consumption as a visual progress bar with text label (e.g., "2.3 GB of 10 GB used").

### 1.3 Breadcrumb Navigation

Above the file listing, a breadcrumb trail shows the path from root to the current folder. Each segment is clickable to navigate up the tree. The root is labeled "Home". Example:

```
Home / Documents / Lab Reports / 2026
```

Clicking "Documents" navigates directly to that folder, skipping the intermediate steps.

### 1.4 File Listing — List View

The default view displays files in a table with the following columns:

| Column | Description |
|--------|-------------|
| Checkbox | Row selection for bulk operations. Header checkbox selects all. |
| Thumbnail | 64px thumbnail for images/PDFs/videos. Type icon for other files. Folder icon for folders. |
| Name | Filename or folder name. Truncated with ellipsis if too long. Includes inline badge icons (see below). |
| Size | Human-readable size (e.g., "2.3 MB"). Dash for folders. |
| Modified | Relative time (e.g., "5 min ago", "3 days ago"). Full datetime on hover tooltip. |
| Actions | "⋮" menu button opening the context menu for that row. |

**Sort behavior:** Folders always appear before files. Within each group, default sort is alphabetical by name ascending. Clicking any column header toggles sort direction. Active sort column shows an arrow indicator (▲/▼).

**Badge icons** displayed inline after the filename:
- 🔗 — File has one or more active external shares
- 👥 — File is shared with one or more ZPMS users (internal share)
- ⭐ — File is favorited by the current user
- 🔒 — File is locked. Hover tooltip shows: lock holder name, reason (if any), and expiry time
- 🏥 — File is linked to one or more patient records

### 1.5 File Listing — Grid View

A toggle button in the toolbar switches between list view and grid view. Grid view displays files as cards arranged in a responsive grid. Each card shows:

- A 256px thumbnail (for supported types) or a large type icon
- Filename below the thumbnail, truncated to 2 lines
- File size or item count (for folders)
- Badge icons in the card corner

Grid view is particularly useful for browsing folders containing primarily images.

### 1.6 Filter Bar

Below the toolbar, a horizontal row of filter chips allows quick filtering of the file listing:

- **All** — No filter (default, active)
- **Folders** — Show only folders
- **Documents** — Show files matching document MIME types (PDF, DOC, DOCX, XLS, XLSX, PPTX, TXT, CSV)
- **Images** — Show files matching image MIME types (JPG, PNG, GIF, WEBP, SVG, BMP)
- **Media** — Show video and audio files (MP4, MOV, MP3, WAV)
- **Archives** — Show compressed files (ZIP, RAR, 7Z, TAR, GZ)

Only one filter is active at a time. Filters work client-side on the already-loaded listing for the current folder. The active chip is visually highlighted.

### 1.7 Search

The toolbar contains a search input field. Typing a query and pressing Enter (or waiting 300ms for debounce) performs a server-side filename search across all of the user's files and folders (not limited to the current directory). Results are displayed in the main content area replacing the current folder listing, with each result showing the full path to the file. Clicking a result navigates to that file's parent folder with the file highlighted.

Search scope includes files shared with the user via internal sharing.

### 1.8 Toolbar Actions

The toolbar above the file listing contains:

- **Upload button** — Opens the system file picker dialog. Multiple file selection is enabled. Selected files are enqueued for chunked upload to the current folder.
- **New Folder button** — Prompts for a folder name (inline input or small modal), creates the folder in the current directory via AJAX.
- **View toggle** — Switches between list view and grid view. Preference is persisted in the user's session.
- **More menu (⋮)** — Dropdown with: "Select all", "Sort by…", "Empty trash" (when in trash view).

---

## 2. File Upload System

### 2.1 Purpose

The upload system allows users to add files to their storage via a chunked AJAX uploader. It supports multiple simultaneous files, provides per-file and overall progress feedback, and handles large files efficiently by splitting them into chunks. The implementation is adapted from the ZPMS DICOM module's proven chunked upload architecture.

### 2.2 Upload Initiation

Files can be uploaded through three methods:

1. **Upload button** — Clicking the toolbar upload button opens the browser's native file selection dialog with `multiple` attribute enabled. Selected files are added to the upload queue.

2. **Drag and drop** — The entire file browser area acts as a drop zone. When files are dragged over the browser area, a visual overlay appears with the message "Drop files here to upload" and a dashed border animation. Dropping files adds them to the upload queue targeting the current folder. Folder drops are supported via the `webkitGetAsEntry()` API, which recursively reads directory contents and preserves the folder structure by creating subfolders as needed.

3. **Paste** — Files copied to the system clipboard can be pasted with Ctrl+V, adding them to the upload queue.

### 2.3 Chunked Upload Protocol

Each file follows a three-step upload process:

**Step 1 — Initialize (`POST /files/upload/init`)**

The client sends the filename, file size, and target parent folder ID. The server performs validation:
- Extension check against the blocklist (`blocked_extensions` in settings.yml)
- File size check against `max_upload_size`
- Filename length check against `max_filename_length`
- Quota check: ensures user has sufficient remaining storage
- Parent folder ownership/permission check
- Lock check: if a file with the same name exists and is locked by another user, the upload is rejected

The server generates a unique `upload_token` (32-char hex via `bin2hex(random_bytes(16))`), creates a temporary directory at `files/fileshare/tmp/{upload_token}/`, inserts a record into `fileshare_uploads`, and returns:

```json
{
  "upload_token": "a8f3e1b2c4d5...",
  "chunk_size": 5242880,
  "total_chunks": 12,
  "will_overwrite": true,
  "existing_file_id": 456
}
```

The `will_overwrite` and `existing_file_id` fields inform the client if this upload will replace an existing file (triggering versioning).

**Step 2 — Send Chunks (`POST /files/upload/chunk`)**

The client splits the file into chunks of `chunk_size` bytes and sends each sequentially (or with limited parallelism). Each request includes:
- `upload_token` — Session identifier
- `chunk_index` — Zero-based chunk number
- `chunk` — Binary blob (FormData file field)

The server writes each chunk to `tmp/{upload_token}/chunk_{index}`, increments the `chunks_received` counter, and returns:

```json
{
  "received": 5,
  "total": 12,
  "progress_pct": 42
}
```

**Step 3 — Finalize (`POST /files/upload/finalize`)**

The client sends only the `upload_token`. The server:
1. Concatenates all chunk files in order into a single temporary file
2. Computes SHA-256 hash of the assembled file
3. Validates the MIME type using `finfo_file()` against allowed types
4. Validates that the assembled file size matches the declared size from init
5. If overwriting an existing file: calls `VersionManager::createVersion()` to snapshot the current version before replacement
6. Moves the assembled file to `storage/{user_id}/{path}/{filename}`, resolving naming conflicts by appending `(1)`, `(2)`, etc. if necessary
7. Creates or updates the `fileshare_files` database record with size, hash, MIME type, and timestamps
8. Invalidates any cached thumbnails for the file via `PreviewManager::invalidateCache()`
9. Logs the upload activity via `ActivityLogger`
10. Deletes the temporary directory and its contents
11. Returns the final file record:

```json
{
  "file_id": 789,
  "name": "report-q4.pdf",
  "size": 2412544,
  "mime_type": "application/pdf",
  "is_new": false,
  "version_created": true
}
```

### 2.4 Upload Queue & Concurrency

The client-side `FileUploader` module maintains an internal queue of pending uploads. When files are added (via any of the three initiation methods), they are appended to the queue with status "waiting". The queue processor runs up to `maxConcurrent` (default: 2) uploads in parallel. As one upload completes, the next waiting file is started.

Each file in the queue tracks its own state: `waiting`, `uploading`, `processing` (finalize step), `complete`, `error`.

### 2.5 Upload Progress Panel

A fixed-position panel in the bottom-right corner of the screen shows the upload queue when active. It includes:

- **Header row** — "Uploading N files" with a close (✕) button that minimizes the panel (uploads continue in the background)
- **Per-file rows** — Each showing: filename, file size, progress bar, status text. Completed files show a checkmark. Failed files show an error icon with a tooltip explaining the failure. Active files show a filled progress bar with percentage.
- **Footer row** — Overall progress percentage across all files, and a "Cancel All" button that aborts all active and queued uploads

The panel auto-appears when uploads begin and remains visible until dismissed after all uploads complete or are cancelled.

### 2.6 Upload Cancellation

Individual uploads can be cancelled while in progress. Cancellation sends an abort signal to the active `fetch()` request and calls a server-side cleanup endpoint to remove the temporary chunks. The "Cancel All" button cancels all active uploads and clears the queue.

### 2.7 Conflict Resolution

When a file with the same name already exists in the target folder:

- If the existing file is **not locked**: the upload proceeds, overwriting the file. The previous version is automatically saved by the versioning system.
- If the existing file is **locked by the current user**: the upload proceeds as above.
- If the existing file is **locked by another user**: the init step returns an error and the file is skipped with an error message displayed in the progress panel.

For new files where a naming conflict exists (e.g., concurrent uploads), the server appends a counter: `file.pdf` → `file (1).pdf` → `file (2).pdf`.

---

## 3. File & Folder Operations

### 3.1 Create Folder

**Trigger:** "New Folder" button in toolbar, or right-click in empty area → "New Folder"

**Behavior:** An inline input field appears at the top of the file listing (or a small modal dialog). The user types a folder name and presses Enter. An AJAX `POST` to `/files/api/mkdir` sends the `parent_id` and `name`. The server validates the name (sanitization, length, uniqueness within parent) and creates the folder in both the database and on disk. The new folder appears immediately in the listing.

**Validation rules for folder names:**
- Maximum 255 characters
- Forbidden characters: `/ \ : * ? " < > |` and null bytes
- Cannot be `.` or `..`
- Cannot duplicate an existing name in the same parent folder

### 3.2 Rename

**Trigger:** Select a file/folder → press F2, or right-click → "Rename", or click the ⋮ menu → "Rename"

**Behavior:** The filename text in the listing row is replaced with an inline `<input>` element pre-filled with the current name. For files with extensions, the selection range is set to highlight only the name portion (excluding the extension) so the user can type a new name without accidentally changing the extension.

- Pressing **Enter** commits the rename via `POST /files/api/rename` with `{file_id, new_name}`
- Pressing **Escape** cancels the rename and restores the original text

The server validates the new name, checks for uniqueness within the parent folder, renames the physical file/folder on disk, and updates the `fileshare_files` record. An activity log entry is created with both old and new names.

**Lock check:** If the file is locked by another user, the rename is rejected with an error message.

### 3.3 Cut, Copy, Paste

The module implements a virtual clipboard system for moving and copying files between folders.

**Cut (Ctrl+X or context menu → "Cut"):**
Stores the selected file/folder IDs and sets the operation to "cut". Cut items are visually dimmed (opacity reduced) in the file listing to indicate pending move. The paste target has not been decided yet.

**Copy (Ctrl+C or context menu → "Copy"):**
Stores the selected file/folder IDs and sets the operation to "copy". No visual change to the source items.

**Paste (Ctrl+V or context menu → "Paste"):**
Sends the stored IDs and the current folder as the target to the appropriate server endpoint:
- Cut → `POST /files/api/move` with `{file_ids[], target_folder_id}`
- Copy → `POST /files/api/copy` with `{file_ids[], target_folder_id}`

After a successful paste, the clipboard is cleared and the file listing refreshes.

**Move behavior (cut + paste):**
The server validates that the target folder exists and is owned by (or shared with) the user. It then calls `rename()` on the physical file to relocate it, and updates the `parent_id` and `disk_path` fields in the database. Moving a folder moves all its contents recursively.

**Copy behavior:**
The server performs a deep copy: for files, it copies the physical file and creates a new database record with a new ID. For folders, it recursively copies the entire subtree. Copied files do not inherit shares, locks, favorites, or patient links from the original — they are treated as new files.

**Conflict handling:** If the target folder already contains a file with the same name, the pasted file is renamed with a `(1)` suffix.

**Lock check:** Locked files cannot be moved (cut). They can be copied.

### 3.4 Delete (Soft Delete to Trash)

**Trigger:** Select items → press Delete, or right-click → "Delete", or bulk action bar → "Delete"

**Behavior:** Sends `POST /files/api/delete` with `{file_ids[]}`. The server sets `is_trashed = 1` and `trashed_at = NOW()` on the selected items. The items disappear from the current folder view and appear in the Trash view.

For folders, all children are trashed recursively (the `ON DELETE CASCADE` foreign key handles database cleanup if permanent deletion occurs later).

**Lock check:** Locked files cannot be deleted. The operation fails for those items with an error message; unlocked items in the selection are still deleted.

**Active shares:** Trashing a file does not automatically revoke its shares. The file remains accessible via share links until the share expires or is revoked, or the file is permanently purged from trash.

### 3.5 Context Menu

Right-clicking a file/folder row (or clicking its ⋮ button) opens a context menu positioned near the click point. The menu contains:

| Menu Item | Action | Availability |
|-----------|--------|-------------|
| Open | Navigate into folder / open preview for file | Always |
| Preview | Open the details panel with preview tab active | Files only |
| Download | Download the file (or ZIP for folders) | Always |
| — separator — | | |
| Share externally… | Open external share dialog | Always |
| Share with user… | Open internal share dialog | Always |
| — separator — | | |
| Rename | Start inline rename | Not when locked by other |
| Cut | Cut to clipboard | Not when locked by other |
| Copy | Copy to clipboard | Always |
| — separator — | | |
| Add to favorites / Remove from favorites | Toggle favorite status | Always |
| Lock file / Unlock file | Acquire or release lock | Files only |
| Link to patient… | Open patient link dialog | Files only |
| — separator — | | |
| Details | Open details side panel | Always |
| Delete | Soft-delete to trash | Not when locked by other |

Items that are unavailable (e.g., due to locks or permissions) are shown grayed out with a tooltip explaining why.

The context menu closes when clicking outside it, pressing Escape, or selecting an item.

### 3.6 Bulk Operations

When one or more items are selected via checkboxes, a sticky toolbar appears at the top of the file listing with bulk action buttons:

- **Copy** — Copy all selected items to clipboard
- **Cut** — Cut all selected items to clipboard
- **Paste** — Paste clipboard contents into current folder (shown only when clipboard has items)
- **Delete** — Soft-delete all selected items
- **Download ZIP** — Package all selected files into a ZIP archive and trigger browser download
- **Share** — Open share dialog (only for single selection)
- **Favorite** — Toggle favorite on all selected items

The toolbar shows the count of selected items (e.g., "3 selected"). The header checkbox provides select-all / deselect-all functionality.

### 3.7 Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+C` | Copy selected items to clipboard |
| `Ctrl+X` | Cut selected items to clipboard |
| `Ctrl+V` | Paste clipboard contents into current folder |
| `Ctrl+A` | Select all items in the current listing |
| `Delete` | Soft-delete selected items to trash |
| `F2` | Begin inline rename on the single selected item |
| `Enter` | Open selected folder or file preview |
| `Backspace` | Navigate up to the parent folder |
| `Escape` | Clear current selection, close open modals/menus, or cancel inline rename |
| `Space` | Toggle the details side panel for the selected file |

Keyboard shortcuts are disabled when an input field or modal is focused.

---

## 4. Trash & Recovery

### 4.1 Purpose

The trash provides a safety net against accidental deletion. Deleted files are retained for a configurable period (`trash_retention_days`, default 30 days) before automatic permanent removal.

### 4.2 Trash Browser

Accessed via the "Trash" entry in the sidebar. The trash browser displays all soft-deleted items belonging to the current user in a flat list (not preserving folder hierarchy). Each row shows:

- File/folder name
- Original location (path where it was deleted from)
- Deletion date
- Days remaining before auto-purge
- Size

### 4.3 Restore

**Trigger:** Select items in trash → click "Restore" button, or right-click → "Restore"

**Behavior:** Sends `POST /files/api/trash/restore` with `{file_ids[]}`. The server clears `is_trashed` and `trashed_at`, making the items reappear in their original location.

If the original parent folder no longer exists (it was also deleted), the items are restored to the user's root directory.

If a file with the same name now exists in the original location, the restored file is renamed with a conflict suffix.

### 4.4 Permanent Delete (Purge)

**Trigger:** Select items in trash → click "Delete permanently" button

**Behavior:** Sends `POST /files/api/trash/purge` with `{file_ids[]}`. A confirmation dialog warns that this action cannot be undone. On confirmation, the server:

1. Deletes the physical file from disk
2. Deletes all associated version files from the versions directory
3. Removes the `fileshare_files` record (cascading to shares, comments, versions, locks, patient links)
4. Invalidates cached thumbnails

### 4.5 Empty Trash

**Trigger:** "Empty trash" button (visible only in trash view), or sidebar More menu → "Empty trash"

**Behavior:** After confirmation dialog, permanently deletes all items in the user's trash. Equivalent to purging every trashed item.

### 4.6 Automatic Cleanup

A cron job (`cleanup_trash`) runs daily and permanently deletes all trashed items where `trashed_at` is older than `trash_retention_days`. This ensures the trash does not grow indefinitely.

### 4.7 Quota Interaction

By default (`count_trash: false` in settings), trashed files do not count toward the user's storage quota. This allows users to delete files to free quota immediately without waiting for trash purge. If configured as `count_trash: true`, trashed files continue consuming quota until permanently purged.

---

## 5. File Previews & Thumbnails

### 5.1 Purpose

The preview system generates visual representations of files for inline display in the file browser (thumbnails in list/grid view) and detailed preview in the side panel or overlay. This reduces the need to download files just to see their contents.

### 5.2 Thumbnail Generation

Thumbnails are generated on first access and cached on disk for subsequent requests. Three sizes are produced as defined in `preview.yml`:

| Size | Dimensions | Usage |
|------|-----------|-------|
| 64px | 64×64 | List view thumbnail column |
| 256px | 256×256 | Grid view cards |
| 1024px | Up to 1024px longest edge | Details panel preview tab, public share pages |

**Cache location:** `files/fileshare/thumbnails/{size}/{sha256_hash}.jpg`

Thumbnails are keyed by the file's content hash, meaning identical files (even with different names or owned by different users) share the same cached thumbnail. When a file is updated (new upload/version), its hash changes, and the old thumbnail naturally becomes orphaned for later cleanup.

**Supported thumbnail sources:**

| File Type | Generation Method |
|-----------|------------------|
| Images (JPG, PNG, GIF, WEBP, BMP) | PHP GD: `imagecreatefrom*()` → resize → `imagejpeg()` |
| SVG | Rendered at target size via GD or served as-is at small sizes |
| PDF | Page 1 extracted via Imagick (`$im->setIteratorIndex(0)`) or Ghostscript (`gs -dFirstPage=1`) → image |
| Video (MP4, MOV) | First frame extracted via ffmpeg (`-vframes 1 -ss 00:00:01`) → image |
| All other types | No thumbnail generated. A generic type icon is displayed based on the `icon` field in `mime-types.yml` |

**Performance:** Thumbnails are generated lazily — only when first requested. The `PreviewController::thumbnail` endpoint checks the cache first and returns the cached file with appropriate `Cache-Control` headers. If not cached, it generates the thumbnail synchronously and streams it to the client while saving to cache.

Files exceeding `max_preview_filesize` (default 50 MB) skip thumbnail generation entirely.

### 5.3 Inline Previews

The details side panel and the preview overlay support richer inline previews based on file type:

**Images** — Full-resolution image displayed in an `<img>` tag, scaled to fit the preview area. Maximum dimension capped at `max_dimension` (2048px) to avoid excessive memory usage.

**PDF** — Embedded via `<embed type="application/pdf">` using the browser's built-in PDF viewer. Falls back to a thumbnail + download link if the browser doesn't support inline PDF.

**Video** — HTML5 `<video>` element with native browser controls (play, pause, seek, volume, fullscreen). Supported formats: MP4, MOV (browser codec support varies).

**Audio** — HTML5 `<audio>` element with native browser controls (play, pause, seek, volume). Supported formats: MP3, WAV.

**Text** — First 200 lines displayed in a `<pre>` element with monospace font. Line numbers shown in a gutter column. A "Show more" link at the bottom offers full download.

**Code** — Same as text, but with basic syntax highlighting. Keywords, strings, comments, and numbers are colored using a lightweight vanilla JS highlighter (no external library). Language is inferred from the file extension. Supported: PHP, JS, JSON, XML, YAML, CSS, HTML, SQL, Python, Shell.

**Markdown** — Rendered to HTML using Parsedown (single-file PHP library) and displayed in a styled container. Links are clickable. Images referenced in the markdown are not resolved (shown as alt text).

**DICOM** — Displays a link to the ZPMS DICOM viewer module, passing the file reference. If the DICOM module has generated a thumbnail, it is displayed.

### 5.4 Image Gallery Mode

When previewing an image file, the preview opens in a fullscreen overlay (lightbox). Navigation arrows and keyboard controls (←/→) allow browsing through all images in the same folder without closing the overlay. The overlay shows:

- Current image scaled to fit the viewport
- Image counter ("3 of 12")
- Filename, file size, and dimensions
- Close button (✕) and download button (⬇)

Pressing Escape or clicking outside the image closes the gallery.

### 5.5 Thumbnail Cache Maintenance

A weekly cron job (`cleanup_thumbnails`) scans the thumbnail cache directory and removes thumbnails whose SHA-256 hash no longer matches any file in the `fileshare_files` table. This cleans up orphaned thumbnails from deleted or updated files.

The `cache_ttl_days` setting (default 90 days) provides an additional time-based cleanup: thumbnails not accessed within this period are deleted even if the source file still exists (they will be regenerated on next access).

---

## 6. Favorites & Recent Files

### 6.1 Favorites

**Purpose:** Allow users to bookmark frequently accessed files and folders for quick retrieval.

**Toggle:** Click the star icon on a file row, or right-click → "Add to favorites" / "Remove from favorites", or use the bulk action bar "Favorite" button.

**Implementation:** Sends `POST /files/api/favorites/toggle` with `{file_id}`. The server flips the `is_favorited` flag on the `fileshare_files` record. The star icon updates immediately in the UI without a full page refresh.

**Favorites view:** Accessed from the sidebar. Displays all favorited items in a flat list, sorted by name. Shows the file's full path as secondary text so users know where each file lives. Standard file operations (download, share, rename, delete) are available from this view.

Favorites are per-user. Favoriting a shared file does not affect other users' favorites.

### 6.2 Recent Files

**Purpose:** Provide quick access to files the user has recently interacted with.

**Tracking:** The `last_accessed` timestamp on `fileshare_files` is updated whenever the user:
- Opens a file preview
- Downloads a file
- Opens a file's details panel

This is done via `FileShareManager::touchAccessed(file_id)` called from the relevant controllers.

**Recent view:** Accessed from the sidebar. Displays the last 50 files accessed by the user, sorted by `last_accessed` descending. Folders are excluded (only files). Each row shows the file's full path as secondary text.

The recent view does not include files that have been trashed or permanently deleted.

---

## 7. Storage Quotas

### 7.1 Purpose

Storage quotas prevent any single user from consuming disproportionate disk space. Quotas are enforced on upload and displayed in the UI for user awareness.

### 7.2 Configuration

Quotas are defined in `settings.yml`:

```yaml
quotas:
  default_user_quota: 10737418240   # 10 GB in bytes
  admin_unlimited: true              # Admins bypass quota
  count_trash: false                 # Trashed files don't count
```

Per-user quota overrides can be stored in the ZPMS user system if needed, falling back to `default_user_quota`.

### 7.3 Quota Calculation

`FileShareManager::getUserQuota(user_id)` computes:
- **Used:** `SUM(size)` from `fileshare_files` where `user_id` matches, `type = 'file'`, and (if `count_trash` is false) `is_trashed = 0`
- **Total:** The user's configured quota
- **Percent:** `used / total * 100`
- **Remaining:** `total - used`

Version files stored in the `versions/` directory also count toward the user's quota. Version storage is tracked via `VersionManager::getVersionStorageUsage(user_id)`.

### 7.4 Enforcement

Quota is checked during upload initialization (`UploadController::init`). If the declared file size would exceed the remaining quota, the upload is rejected with an error message: "Insufficient storage. You have X remaining of Y total."

Copy operations also check quota before proceeding, since copying creates new files that consume additional space.

### 7.5 UI Display

The sidebar shows a quota bar below the navigation entries:
- Visual progress bar (colored teal under 75%, yellow 75–90%, red over 90%)
- Text label: "2.3 GB of 10 GB"
- Clicking the quota bar navigates to a storage breakdown view (or links to account settings)

### 7.6 Quota Warning Notification

A weekly cron job (`quota_warnings`) checks all users' quota usage. Users exceeding 90% (configurable via `notifications.yml` → `quota_warning.threshold_percent`) receive a notification: "Storage usage at 92% — consider freeing space."

---

## 8. External Sharing (Links & Codes)

### 8.1 Purpose

External sharing allows users to create public or restricted links to files and folders that can be accessed by anyone with the link, without requiring a ZPMS account. This is the primary mechanism for sharing files with patients, external doctors, or other parties outside the system.

### 8.2 Share Types

**Link share:** A long URL containing a cryptographic token. Example:
```
https://zpms.example.com/s/a8f3e1b2c4d5f6a7b8c9d0e1f2a3b4c5
```

**Code share:** A short, human-readable alphanumeric code that can be communicated verbally or in print. Example: `XKFM-3R7P`. Accessed via:
```
https://zpms.example.com/s/code/XKFM3R7P
```

The code alphabet excludes ambiguous characters (`0O1lI`) for readability: `23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz`.

### 8.3 Share Creation

**Trigger:** Right-click a file/folder → "Share externally…", or context menu, or details panel.

**Share dialog** presents the following options:

**Share type** — Radio buttons: Link or Code.

**Link/code display** — After creation, the generated URL or code is shown with a "Copy" button for easy clipboard access.

**Password protection** — Optional. When enabled, a password field appears. The password is hashed with `password_hash()` (bcrypt) before storage. Visitors must enter the password before accessing the file.

**Expiration** — Optional. A date picker sets when the share automatically becomes inactive. Default is 30 days from creation (`default_expiry_days`). Maximum is 365 days (`max_expiry_days`). Leaving the field empty creates a share with no expiry.

**Download limit** — Optional. A number field sets the maximum number of times the file can be downloaded. After reaching the limit, the share becomes inactive for downloads (view may still work if permissions allow). Default is 0 (unlimited).

**Permissions** — Checkboxes:
- **View** — Can see the file name, metadata, and preview (always enabled)
- **Download** — Can download the file (default: enabled)
- **Upload** — Can upload files into a shared folder (default: disabled, only applicable to folder shares)

**Access scope** — Radio buttons:
- **Public** — Anyone with the link can access (no ZPMS login required)
- **Authenticated only** — Only logged-in ZPMS users can access the link (useful for restricting to staff while still using a convenient link)

### 8.4 Share Management

Once a share is created, it appears in the "Active Shares" section of the share dialog, and also in the "Shared by me" view. Each active share shows:

- Share type (link or code)
- Creation date
- Download count
- Expiry date (or "No expiry")
- Whether password-protected
- Action buttons: Copy link/code, QR code, Edit, Revoke

**Edit** allows changing password, expiry, download limit, and permissions after creation. The token/code itself does not change.

**Revoke** immediately deactivates the share. Sets `is_active = 0` and `revoked_at = NOW()`. The link immediately stops working, showing the "Share Unavailable" page. Revocation cannot be undone — a new share must be created.

### 8.5 Share Token Security

Tokens are generated using `bin2hex(random_bytes(16))`, producing 32 hexadecimal characters (128 bits of entropy). This makes brute-force guessing computationally infeasible.

Codes are generated from the 55-character safe alphabet using `random_bytes()` mapped to the alphabet, producing 8-character codes (~46 bits of entropy). While lower than link tokens, codes are always paired with rate limiting and optional password protection.

### 8.6 Shared Folder Behavior

When a folder is shared, the public page displays a read-only mini file browser allowing navigation into subfolders. Individual files can be downloaded, or the entire folder can be downloaded as a ZIP archive.

If the share has `upload` permission, an upload zone is displayed allowing visitors to add files to the shared folder.

---

## 9. Internal Sharing (User-to-User)

### 9.1 Purpose

Internal sharing allows ZPMS users to share files and folders with other ZPMS users, with granular permission levels. Unlike external shares which use tokens, internal shares are tied to authenticated user accounts.

### 9.2 Permission Levels

| Permission | Browse & View | Download | Upload | Rename & Delete |
|-----------|:---:|:---:|:---:|:---:|
| Viewer | ✓ | ✓ | — | — |
| Uploader | ✓ | ✓ | ✓ | — |
| Editor | ✓ | ✓ | ✓ | ✓ (within shared folder) |

Editors can rename and delete files within the shared folder but cannot delete the shared folder itself or modify files outside of it.

### 9.3 Share Creation

**Trigger:** Right-click a file/folder → "Share with user…"

**Internal share dialog** contains:
- **User search** — An autocomplete input that searches ZPMS users by name or email. Type-ahead results appear as the user types.
- **Permission picker** — Radio buttons: Viewer, Uploader, Editor
- **Share button** — Creates the internal share

Below the creation form, the dialog shows a list of users the file is currently shared with, displaying each user's name, permission level, share date, and "Change" / "Revoke" action buttons.

### 9.4 Permission Inheritance

Sharing a folder implicitly shares all of its contents (files and subfolders) at the same permission level. This is implemented as a single share record on the folder; the system checks for shares up the parent chain when validating access.

When `FileShareManager::checkPermission(user_id, file_id, action)` is called:
1. Check if the user owns the file → full access
2. Check if there is an active internal share directly on the file
3. Walk up the parent chain checking for shares on each ancestor folder
4. Return the highest permission level found, or deny access if none

### 9.5 Shared Views

**"Shared with me"** view in the sidebar shows all files/folders that other users have shared with the current user. Grouped by sharing user. Each entry shows the file/folder name, sharer name, permission level, and share date. Clicking navigates into shared folders (with the appropriate permission restrictions applied to available actions).

**"Shared by me"** view shows all files/folders the current user has shared with others (both internal and external shares). Useful for auditing and managing active shares.

### 9.6 Notifications

When a file is shared with a user, they receive a notification: "{User} shared '{filename}' with you — Viewer access". The notification links to the shared file. This is dispatched by `InternalShareManager::notifyRecipient()`.

---

## 10. QR Code Generation

### 10.1 Purpose

QR codes provide a convenient way to share file links on printed materials, in clinical settings (posted on walls or included in documents), or for quick scanning from mobile devices.

### 10.2 Implementation

The `QRCodeGenerator` service uses the `phpqrcode` library (a single PHP file included in `modules/fileshare/lib/`) combined with the GD extension to generate QR code images as PNG files.

**Encoded data:** The full public share URL, e.g., `https://zpms.example.com/s/a8f3e1b2c4d5...`

**Parameters:**
- Error correction level: M (medium, 15% data recovery) — balances size and resilience
- Module size: Calculated from the target pixel size (default 300px per `settings.yml`)
- Quiet zone: 4 modules (standard margin around the QR code)

### 10.3 Access Points

**Share dialog** — After creating an external share, a "QR Code" button appears next to the link. Clicking it opens a popup overlay showing:
- The QR code image centered
- The share URL as selectable text below
- "Download QR" button — downloads the PNG image
- "Print" button — opens the browser print dialog with only the QR code and URL

**API endpoint** — `GET /files/api/share/qr/{share_token}` returns the QR code as a PNG image with appropriate `Content-Type: image/png` headers. Requires authentication (only the share creator can generate QR codes for their shares).

**Data URI mode** — For inline embedding in the share dialog, `QRCodeGenerator::toDataUri()` returns a base64-encoded data URI string that can be used directly in an `<img src="...">` attribute, avoiding an additional HTTP request.

---

## 11. File Versioning

### 11.1 Purpose

File versioning automatically preserves previous versions of a file each time it is overwritten by a new upload. This protects against accidental overwrites and allows users to review or restore earlier versions.

### 11.2 Automatic Version Creation

When a file is uploaded and a file with the same name already exists in the target folder, the upload finalize step calls `VersionManager::createVersion(file_id)` before replacing the file. This:

1. Checks `shouldVersion()` — skips if the last version was created less than `min_interval_seconds` ago (default 60s), or if the file extension is in `excluded_extensions`
2. Determines the next version number (`MAX(version_number) + 1` for the file)
3. Copies the current file from its storage location to the versions directory: `versions/{user_id}/{file_id}/v{n}_{timestamp}_{hash_prefix}`
4. Inserts a `fileshare_versions` record with size, hash, path, and creation metadata
5. Logs a `version_create` activity entry

The current (newest) file is always in its normal storage location. Versions are stored separately in the versions directory.

### 11.3 Version History

Accessible via the details side panel → "Versions" tab, or via `GET /files/api/versions/{file_id}`.

Displays a list of all versions ordered by version number descending (newest first). Each entry shows:
- Version label: "Current" for the active file, "v2", "v1", etc. for older versions
- File size
- Timestamp (relative, with full date on hover)
- Created by (user name)
- Action buttons: Download, Restore (not on current), Delete

### 11.4 Version Restore

Clicking "Restore" on a previous version:
1. Creates a new version entry from the current file (so the current state is preserved)
2. Copies the selected version file to the main storage location, replacing the current file
3. Updates the `fileshare_files` record with the restored version's hash and size
4. Invalidates the thumbnail cache
5. Logs a `version_restore` activity entry

This ensures no data is ever lost — the current file becomes a version, and the selected version becomes current.

### 11.5 Version Deletion

Individual versions can be deleted to free storage. The "Delete" button on a version entry sends `POST /files/api/versions/delete` with `{version_id}`. The server deletes the version file from disk and removes the database record. The current (active) file cannot be deleted through the versions interface.

### 11.6 Retention & Cleanup

The `versioning.yml` configuration controls automatic cleanup:
- `max_versions_per_file: 50` — When a new version is created and the count exceeds this limit, the oldest version is automatically deleted
- `retention_days: 365` — Versions older than this are candidates for automatic deletion

A weekly cron job (`cleanup_versions`) enforces both limits, deleting version files from disk and removing database records.

### 11.7 Storage Accounting

Version storage counts toward the user's quota. `VersionManager::getVersionStorageUsage(user_id)` returns the total bytes consumed by all versions across all of the user's files. This is included in the quota calculation.

---

## 12. File Comments

### 12.1 Purpose

Comments allow users to discuss files in context — asking questions, providing feedback, or recording observations. Comments are attached to specific files and are visible to the file owner and anyone with internal share access.

### 12.2 Comment Structure

Comments support two levels of threading:
- **Top-level comments** — Direct comments on the file (`parent_id = NULL`)
- **Replies** — Responses to a top-level comment (`parent_id` set to the parent comment's ID)

Deeper nesting (replies to replies) is not supported to keep the UI simple. A reply to a reply is stored as a reply to the original top-level comment.

### 12.3 Adding Comments

The comments tab in the details side panel shows a text input at the bottom with a submit button. Users type their comment in plain text and click submit or press Ctrl+Enter.

The comment is sent via `POST /files/api/comments/create` with `{file_id, body, parent_id}`. The server inserts the record and dispatches a notification to:
- The file owner (if the commenter is not the owner)
- Previous commenters on the same thread (excluding the current commenter)

### 12.4 Editing and Deleting

Users can edit their own comments by clicking an "Edit" button that appears on hover. The comment text switches to an editable input, and saving sends `POST /files/api/comments/update`.

Users can delete their own comments. Deletion is soft: the comment body is replaced with "[deleted]" and `is_deleted = 1` is set, but the record persists so that replies still make sense in context. Administrators can delete any comment.

### 12.5 Display

Comments are displayed in chronological order (oldest first) within the details side panel "Comments" tab. Each comment shows:
- User avatar (initials) and name
- Relative timestamp ("2 hours ago")
- Comment body text
- Reply button
- Edit/Delete buttons (for own comments)

The tab label shows the comment count as a badge: "💬 3".

URLs in comment text are automatically converted to clickable links.

---

## 13. Activity Stream

### 13.1 Purpose

The activity stream provides a chronological log of all file operations, giving users visibility into what has happened to their files and files shared with them. It supports both a global feed and per-file activity.

### 13.2 Logged Actions

Every significant file operation creates an activity record via `ActivityLogger::log()`:

| Action | Logged When | Details Stored |
|--------|-------------|----------------|
| `upload` | New file uploaded | filename, size, parent folder |
| `download` | File downloaded | filename |
| `rename` | File or folder renamed | old_name, new_name |
| `move` | File moved to different folder | old_path, new_path |
| `copy` | File copied | source_id, target_folder |
| `delete` | File soft-deleted to trash | filename, parent folder |
| `restore` | File restored from trash | filename |
| `share_create` | External share created | share_token, share_type, permissions |
| `share_revoke` | External share revoked | share_token |
| `share_download` | External share accessed for download | share_token, ip_address |
| `internal_share` | Internal share created | target_user, permission |
| `internal_unshare` | Internal share revoked | target_user |
| `comment` | Comment added | comment_id, excerpt |
| `lock` | File locked | reason, expires_at |
| `unlock` | File unlocked | — |
| `version_create` | New version created | version_number, old_hash |
| `version_restore` | Previous version restored | restored_version_number |
| `patient_link` | File linked to patient | patient_id, link_type |
| `patient_unlink` | File unlinked from patient | patient_id |

Each record stores: acting `user_id`, `file_id`, `action` string, `details` (JSON), `ip_address`, and `created_at` timestamp.

### 13.3 Global Activity Feed

Accessed via the "Activity" sidebar entry or `/files/activity`. Shows a paginated, reverse-chronological list of all activity involving:
- Files owned by the current user
- Files shared with the current user (internal shares)
- Share access events on files the current user has shared

Each entry is rendered as a human-readable sentence:
- "**Dr. Smith** uploaded **report-q4.pdf** to Documents — 2 hours ago"
- "**Nurse Jane** downloaded **lab-results.pdf** via share link — 1 hour ago"
- "**Dr. Jones** commented on **consent-form.pdf** — 30 minutes ago"

Clicking an activity entry navigates to the relevant file (if it still exists).

### 13.4 Per-File Activity

The details side panel can show a file's activity history. Accessed via `GET /files/api/activity/{file_id}`. Shows all activity records for that specific file in reverse chronological order.

### 13.5 Retention

A monthly cron job (`cleanup_activity`) deletes activity records older than a configurable period. The default is 365 days, after which records are deleted. Activity records for files that have been permanently deleted are also cleaned up.

---

## 14. Notification System

### 14.1 Purpose

Notifications inform users about events that affect their files, shares, or storage without requiring them to actively check the activity stream.

### 14.2 Notification Types

Defined in `notifications.yml`, each type can be individually enabled or disabled:

| Type | Trigger | Default |
|------|---------|---------|
| `share_downloaded` | Someone downloads a file via user's external share | Enabled |
| `share_expiring` | User's external share is approaching its expiry date | Enabled (7 days, 1 day before) |
| `file_commented` | Someone comments on a file the user owns | Enabled |
| `internal_share_received` | Another user shares a file with this user | Enabled |
| `version_created` | A new version is created for a file the user owns | Disabled (too noisy) |
| `lock_released` | A lock on a file the user was waiting on has been released | Enabled |
| `quota_warning` | User's storage usage exceeds 90% of their quota | Enabled |

### 14.3 Notification Delivery

Notifications are stored in the `fileshare_notifications` table and delivered via the UI:

**Notification bell** — A bell icon in the topbar shows an unread count badge (red circle with number). The count is updated by polling `GET /files/api/notifications/count` every 30 seconds (configurable via `poll_interval_seconds`).

**Notification dropdown** — Clicking the bell opens a dropdown panel listing recent notifications in reverse chronological order. Each notification shows:
- Icon matching the notification type
- Title text (e.g., "Dr. Jones downloaded your shared file")
- Descriptive text (e.g., "'report-q4.pdf' · 15 min ago")
- Read/unread state (unread notifications have a teal left border or dot)

Clicking a notification marks it as read and navigates to the relevant file or view (if the `link` field is set).

**Mark read** — Individual notifications can be marked as read by clicking them. A "Mark all read" button at the top of the dropdown marks all notifications as read in one action.

### 14.4 Notification Lifecycle

1. **Dispatch** — A manager service (e.g., `CommentManager`, `ShareManager`) calls `NotificationManager::dispatch(user_id, type, data)`. The manager checks `shouldNotify()` to verify the notification type is enabled. If yes, a record is inserted into `fileshare_notifications`.

2. **Display** — The client polls for unread count and fetches the notification list when the dropdown is opened.

3. **Read** — Clicking a notification sends `POST /files/api/notifications/read` with `{notification_id}`. The `is_read` flag is set and `read_at` timestamp is recorded.

4. **Cleanup** — A monthly cron job deletes notifications older than `retention_days` (default 90 days).

The maximum displayed unread count is capped at `max_unread` (default 100) to prevent UI issues if a user ignores notifications for a long time.

---

## 15. File Locking

### 15.1 Purpose

File locking prevents concurrent modifications to a file by indicating that a user is actively working on it. When a file is locked, other users cannot rename, move, delete, or overwrite it.

### 15.2 Lock Types

**Manual lock** — The user explicitly locks a file through the UI. This is the primary use case: a user locks a file before downloading it to edit locally, then unlocks it after re-uploading the modified version.

**Auto lock** — Optionally triggered when a file is downloaded (if `auto_lock_on_download` is enabled in settings). This automatically protects files being edited but requires users to explicitly unlock when done.

### 15.3 Acquiring a Lock

**Trigger:** Right-click a file → "Lock file", or details panel → "Lock" button

**Behavior:** Sends `POST /files/api/lock/acquire` with `{file_id}` and optional `{reason, timeout_minutes}`.

- If the file is **not locked**: a lock record is created with the current user as holder, and expiry set to `default_timeout_minutes` from now (default: 120 minutes). The user can specify a custom timeout up to `max_timeout_minutes` (default: 1440 / 24 hours). An optional reason text can be provided (e.g., "Editing in Word").
- If the file is **already locked by the current user**: the lock is refreshed (expiry extended).
- If the file is **locked by another user**: the request fails with an error indicating who holds the lock and when it expires.

### 15.4 Releasing a Lock

**Trigger:** Right-click a locked file → "Unlock file", or details panel → "Unlock" button

**Behavior:** Sends `POST /files/api/lock/release` with `{file_id}`. Only the lock holder or an administrator can release a lock.

Administrators have a "Force unlock" option that overrides any lock. Force unlocks are logged in the activity stream with the admin's user ID.

### 15.5 Lock Display

Locked files display a 🔒 badge in the file listing. Hovering over the lock icon shows a tooltip with:
- Lock holder name
- Reason (if provided)
- Time locked
- Expiry time

In the details side panel "Info" tab, the lock section shows full details with an "Unlock" button (for the holder or admin).

### 15.6 Lock Enforcement

The following operations check lock status server-side before proceeding and are rejected if the file is locked by another user:

- Rename
- Move (cut + paste)
- Delete (soft-delete)
- Upload overwrite (same filename)

These operations are allowed regardless of lock status:
- Copy
- Download
- Preview
- Share (creating shares does not modify the file)
- Comment

### 15.7 Lock Expiry

Locks automatically expire after the configured timeout. A cron job running every 15 minutes (`cleanup_expired_locks`) deletes lock records where `expires_at < NOW()`.

When a lock expires, a `lock_released` notification is dispatched to users who attempted to operate on the file while it was locked (if the notification type is enabled).

### 15.8 Lock Status API

`GET /files/api/lock/status/{file_id}` returns the current lock state:

```json
{
  "locked": true,
  "holder": { "id": 12, "name": "Dr. Smith" },
  "reason": "Editing in Word",
  "locked_at": "2026-02-14T10:30:00Z",
  "expires_at": "2026-02-14T12:30:00Z",
  "is_mine": false
}
```

Or `{"locked": false}` if the file is not locked.

---

## 16. Patient Record Linking

### 16.1 Purpose

Patient record linking creates a bidirectional association between files in the file share and patient records in the ZPMS patient module. This allows clinical staff to organize files by patient and quickly access relevant documents from either the file browser or the patient record.

### 16.2 Link Types

Files can be classified by their clinical relevance when linked to a patient. Available link types (configurable):

| Type | Description |
|------|-------------|
| `general` | General document, no specific classification |
| `lab_result` | Laboratory test results |
| `imaging` | Medical imaging (X-ray, MRI, CT, etc.) |
| `consent` | Consent forms signed by the patient |
| `insurance` | Insurance documents, authorizations |
| `referral` | Referral letters to/from other providers |
| `prescription` | Prescription documents |

### 16.3 Creating a Link

**Trigger:** Right-click a file → "Link to patient…", or details panel → "Patient links" section → "Link" button

**Patient link dialog** contains:
- **Patient search** — An autocomplete input that searches ZPMS patient records by name, ID, or date of birth. Results show patient name, ID, and date of birth for disambiguation.
- **Link type** — Dropdown selecting the document classification
- **Notes** — Optional text field for additional context (e.g., "Follow-up blood work from Jan visit")
- **Link button** — Creates the association

Sends `POST /files/api/patient/link` with `{file_id, patient_id, link_type, notes}`.

A single file can be linked to multiple patients (e.g., a family consent form), and a single patient can have many linked files.

### 16.4 Viewing Links

**From the file browser:** Files with patient links display a 🏥 badge. The details panel "Info" tab shows linked patients with their name, ID, link type, and an "Unlink" button.

**From the patient record:** The patient detail page in the ZPMS patient module includes a "Files" tab (or section) listing all files linked to that patient. The list can be filtered by link type. Each entry shows the file name, type icon, size, link date, and actions (preview, download, unlink). This is served by `GET /files/api/patient/files/{patient_id}`.

### 16.5 Removing a Link

Clicking "Unlink" on a patient link entry sends `POST /files/api/patient/unlink` with `{link_id}`. This removes the association but does not delete the file or affect the patient record. The action is logged in the activity stream.

### 16.6 Badge Count

`PatientLinkManager::getLinkedPatientsCount(file_id)` returns the number of patients linked to a file, used for the 🏥 badge display. If the count is greater than 1, the badge may show a number.

---

## 17. Audit Trail & Compliance Export

### 17.1 Purpose

The audit system provides administrators with a complete record of all file access, sharing activity, and patient file interactions. This supports regulatory compliance requirements (e.g., HIPAA-style access logging) and internal security reviews.

### 17.2 Audit Data Sources

Two database tables capture audit information:

**`fileshare_share_access_log`** — Records every instance of a shared file being accessed:
- Share ID and type (external/internal)
- User ID (for authenticated access)
- IP address and user agent
- Action (view, download, upload, password attempt, password failure)
- Timestamp

**`fileshare_activity`** — Records all file operations by authenticated users (see Section 13 for details).

### 17.3 Audit Trail Page

Accessible at `/files/audit`, restricted to users with the `fileshare.admin` permission. The page displays a filterable, paginated table of audit events.

**Filters:**
- Date range (from/to date pickers)
- User (dropdown or autocomplete)
- Action type (dropdown: upload, download, share, delete, etc.)
- File (search by filename)
- IP address (text input)

**Table columns:**
- Timestamp
- User
- Action
- File
- Details (expanded JSON details)
- IP Address

### 17.4 Export Formats

The "Export" button on the audit page generates downloadable reports:

**CSV export** — All audit records matching the current filters, one row per event. Suitable for import into spreadsheets for further analysis. Exported via `POST /files/audit/export` with filter parameters.

Available export scopes:
- **Share access log** — All external and internal share access events
- **Activity log** — All file operation events
- **Per-patient file access** — All activity and share access related to files linked to a specific patient. This is the primary compliance export: given a patient ID, produce a complete record of who accessed their files, when, and how.

**Compliance summary report (PDF)** — A formatted summary including:
- Total files in the system
- Active shares count
- Total downloads in the period
- User activity summary (most active users, files accessed per user)
- Any security events (password failures, unusual access patterns)

### 17.5 Per-Patient Audit

For healthcare compliance, the most critical export is the per-patient file access report. Accessed via `AuditExporter::exportPatientFileLog(patient_id)`, this produces a CSV containing every action performed on any file linked to the specified patient:

- Who accessed the file (user name or "Anonymous" for public share access)
- What action was performed (view, download, share, etc.)
- When (timestamp)
- How (direct access, share link, internal share)
- From where (IP address)

This report can be generated on demand for individual patients during audits or investigations.

---

## 18. Public Share Access

### 18.1 Purpose

Public share access pages are the unauthenticated frontend that external users see when they visit a share link. These pages are served by `PublicShareController` and do not require ZPMS login (unless the share is configured as "Authenticated only").

### 18.2 Access Flow

```
Visit /s/{token}
    │
    ├─ Share not found or revoked → "Share Unavailable" page
    ├─ Share expired → "Share Unavailable" page
    ├─ Share download limit reached → "Share Unavailable" page (downloads disabled)
    ├─ Share requires password and no session → "Password Required" page
    │       │
    │       └─ Submit password
    │           ├─ Incorrect → Error + rate limit check
    │           └─ Correct → Set session → Redirect to view
    │
    └─ Valid, no password / session valid → File/Folder view page
```

### 18.3 File View Page

For a shared file, the page displays:
- ZPMS branding (logo and application name)
- File thumbnail (large size, 1024px) if available
- Filename, file size, MIME type description
- Sharer name (e.g., "Shared by Dr. Smith")
- Expiry date (if set)
- "Preview" button (opens inline preview if the file type supports it)
- "Download" button (if download permission is granted)

The page uses the ZPMS design system but is a standalone page without the sidebar or topbar (since the visitor is not logged in).

### 18.4 Folder View Page

For a shared folder, the page displays a read-only mini file browser:
- Breadcrumb navigation within the shared folder (cannot navigate above the shared root)
- File listing with thumbnails, names, sizes, and modification dates
- Per-file download buttons
- "Download All as ZIP" button to download the entire folder
- If upload permission is enabled: a drag-and-drop upload zone

### 18.5 Password Protection

If the share has a password set, the visitor first sees a password prompt page. The page shows:
- ZPMS branding
- Lock icon
- "This file is protected" message
- Password input field
- "Unlock" button

On submission, `POST /s/{token}/auth` verifies the password using `password_verify()`. On success, `$_SESSION['share_access'][$token] = time()` is set with a 1-hour TTL. The visitor is redirected to the file view. Subsequent accesses within the session skip the password prompt.

On failure, an error message is displayed. After 5 failed attempts from the same IP address within 15 minutes, further attempts are blocked with a "Too many attempts. Please try again later." message. This rate limiting is implemented using a simple IP+token counter in the session or a lightweight database record.

### 18.6 Share Unavailable Page

Displayed when a share is expired, revoked, not found, or over its download limit. Shows:
- ZPMS branding
- Warning icon
- "Share Unavailable" heading
- "This link has expired or been revoked by the owner." message
- "Contact Administrator" link (configurable destination)

### 18.7 Short Code Access

Shares with codes can be accessed via `/s/code/{code}`. The `PublicShareController::viewByCode` method looks up the share by code, validates it, and redirects to the canonical token URL (`/s/{token}`). This redirect ensures the password session and all other logic works identically.

### 18.8 Access Logging

Every public share page view and download triggers a record in `fileshare_share_access_log` with the visitor's IP address, user agent, action, and timestamp. This happens regardless of whether the visitor is anonymous or a logged-in ZPMS user.

---

## 19. Scheduled Tasks (Cron)

### 19.1 Purpose

Scheduled tasks handle background maintenance operations that keep the file share module running efficiently: cleaning up expired resources, enforcing retention policies, and dispatching time-based notifications.

### 19.2 Registered Tasks

All tasks are defined in `config/fileshare/cron.yml` and executed by the ZPMS cron system.

| Task | Schedule | Description |
|------|----------|-------------|
| `cleanup_temp_uploads` | Every 4 hours | Deletes upload sessions in `active` status older than `temp_cleanup_hours` (24h). Removes the temp directory and all chunk files. Updates the `fileshare_uploads` record status to `expired`. |
| `cleanup_trash` | Daily at 2:00 AM | Permanently deletes all trashed files where `trashed_at` is older than `trash_retention_days` (30 days). Removes physical files, version files, cached thumbnails, and all related database records. |
| `cleanup_expired_shares` | Every 6 hours | Scans `fileshare_shares` for records where `is_active = 1` and `expires_at < NOW()`. Sets `is_active = 0` to deactivate them. The share link immediately stops working on next access. |
| `share_expiry_notifications` | Daily at 8:00 AM | Checks active shares for upcoming expiry. Dispatches `share_expiring` notifications to share owners at configured intervals (default: 7 days and 1 day before expiry). Skips shares that have already triggered a notification at that interval. |
| `cleanup_expired_locks` | Every 15 minutes | Deletes `fileshare_locks` records where `expires_at < NOW()`. Dispatches `lock_released` notifications to users who were blocked by the lock. |
| `cleanup_versions` | Weekly, Sunday at 3:00 AM | For each file, if the version count exceeds `max_versions_per_file` (50), deletes the oldest versions. Also deletes any version older than `retention_days` (365 days). Removes version files from disk and database records. |
| `cleanup_thumbnails` | Weekly, Sunday at 4:00 AM | Scans the thumbnail cache directory. For each thumbnail file, checks if its hash matches any active file. Orphaned thumbnails (from deleted or updated files) are removed. Also removes thumbnails not accessed within `cache_ttl_days` (90 days). |
| `cleanup_notifications` | Monthly, 1st at 5:00 AM | Deletes notifications older than `retention_days` (90 days) from `fileshare_notifications`. Both read and unread notifications are removed. |
| `cleanup_activity` | Monthly, 1st at 5:00 AM | Deletes activity records older than the configured retention period from `fileshare_activity`. Default retention is 365 days. |
| `quota_warnings` | Weekly, Monday at 9:00 AM | Checks each user's storage usage against their quota. If usage exceeds `threshold_percent` (90%), dispatches a `quota_warning` notification. Skips users who already received a warning within the past 7 days. |

### 19.3 Manual Execution

All cron tasks can also be executed manually via the ZPMS CLI framework:

```bash
php zpms cron:run cleanup_trash
php zpms cron:run cleanup_expired_shares
php zpms cron:run --all    # Run all fileshare cron tasks
```

This is useful for testing, immediate cleanup after bulk operations, or recovery situations.

### 19.4 Logging

Each cron run logs its actions: number of items processed, items deleted, errors encountered, and execution duration. Logs are written to the ZPMS system log and are reviewable by administrators.
