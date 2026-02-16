# DICOM Module — ZPMS Implementation Plan

## 1. Overview

Adaptation of the DICOM imaging module for the ZPMS framework. This plan translates the generic DICOM plan into ZPMS-native patterns: module 3-file structure, YAML entity schemas with auto-generated classes, ZETEM templates, `settings.info.yaml` configuration, route handlers in the module file, `SecurityClass` permissions, and the design system CSS variables.

---

## 2. File Structure (ZPMS-adapted)

```
web/modules/dicom/
├── dicom.php                 # Module class + all route handler functions
├── dicom.info.yaml           # Module metadata (name, template)
├── dicom.yaml                # Routes + library definitions (merged into kernel config)
├── DicomParser.php           # Pure-PHP binary DICOM tag reader
├── DicomDirParser.php        # DICOMDIR index reader
└── DicomConverter.php        # DCMTK wrapper + GD thumbnails

web/classes/yaml/
├── dicom_exams.yaml          # Entity schema → auto-generates dicom_examsClass
├── dicom_series.yaml         # Entity schema → auto-generates dicom_seriesClass
├── dicom_images.yaml         # Entity schema → auto-generates dicom_imagesClass
└── dicom_shares.yaml         # Entity schema → auto-generates dicom_sharesClass

web/ClassesEx.php             # Add dicom_examsClassEx, dicom_seriesClassEx, etc.

web/templates/
├── content/
│   ├── dicom_upload.zetem    # Upload page with drag-drop + progress
│   ├── dicom_list.zetem      # Exam list (table with filters)
│   └── dicom_viewer.zetem    # Series viewer (thumb grid + lightbox)
└── blocks/
    └── dicom_exam_row.zetem  # Reusable exam table row partial

web/css/dicom.css             # Module-specific styles (uses design system vars)
web/js/dicom-upload.js        # Chunked upload JS
web/js/dicom-viewer.js        # Gallery + lightbox + keyboard nav

sql/dicom.sql                 # Raw SQL backup (for reference / msql.sh import)

data/dicom/                   # Filesystem storage (outside web root via .htaccess)
├── uploads/                  # Temporary chunked upload assembly
│   └── {token}/
│       ├── chunks/
│       └── assembled.*
├── exams/
│   └── {exam_id}/
│       ├── original/         # Raw DICOM files
│       └── images/
│           └── {series_id}/
│               ├── thumb/    # 200px thumbnails (jpg)
│               └── full/     # Full-resolution (png)
└── tmp/                      # ZIP extraction scratch
```

---

## 3. YAML Entity Schemas

These go in `web/classes/yaml/` and the framework auto-generates entity classes with getters, setters, `sgetById()`, `sgetAll()`, `insert()`, `update()`, `delete()`.

### 3.1 `dicom_exams.yaml`

```yaml
---
table:
  name: dicom_exams
  class: dicom_examsClass
  extention: __APPDIR__ . '/web/ClassesEx.php'

  fields:
  - name: study_uid
    type: varchar(128)
    default: null

  - name: patient_name
    type: varchar(255)
    default: null

  - name: patient_id_dcm
    type: varchar(64)
    default: null

  - name: study_date
    type: date
    default: null

  - name: study_time
    type: time
    default: null

  - name: study_desc
    type: varchar(255)
    default: null

  - name: accession_no
    type: varchar(64)
    default: null

  - name: modality
    type: varchar(16)
    default: null

  - name: file_count
    type: int
    default: 0

  - name: disk_size
    type: bigint
    default: 0

  - name: storage_path
    type: varchar(512)

  - name: status
    type: varchar(20)
    default: uploading

  - name: error_message
    type: text
    default: null

  - name: uploaded_by
    type: int
    default: null

  - name: created_at
    type: '@cdate'

  - name: updated_at
    type: datetime
    default: current_timestamp
...
```

### 3.2 `dicom_series.yaml`

```yaml
---
table:
  name: dicom_series
  class: dicom_seriesClass
  extention: __APPDIR__ . '/web/ClassesEx.php'

  fields:
  - name: exam_id
    type: int

  - name: series_uid
    type: varchar(128)
    default: null

  - name: series_number
    type: int
    default: null

  - name: series_desc
    type: varchar(255)
    default: null

  - name: modality
    type: varchar(16)
    default: null

  - name: frame_count
    type: int
    default: 0

  - name: images_path
    type: varchar(512)
    default: null

  - name: status
    type: varchar(20)
    default: pending

  - name: created_at
    type: '@cdate'
...
```

### 3.3 `dicom_images.yaml`

```yaml
---
table:
  name: dicom_images
  class: dicom_imagesClass
  extention: __APPDIR__ . '/web/ClassesEx.php'

  fields:
  - name: series_id
    type: int

  - name: instance_number
    type: int
    default: 0

  - name: sop_instance_uid
    type: varchar(128)
    default: null

  - name: dcm_filename
    type: varchar(255)

  - name: thumb_filename
    type: varchar(255)
    default: null

  - name: full_filename
    type: varchar(255)
    default: null

  - name: width
    type: int
    default: null

  - name: height
    type: int
    default: null

  - name: created_at
    type: '@cdate'
...
```

### 3.4 `dicom_shares.yaml`

```yaml
---
table:
  name: dicom_shares
  class: dicom_sharesClass
  extention: __APPDIR__ . '/web/ClassesEx.php'

  fields:
  - name: exam_id
    type: int

  - name: token
    type: varchar(64)

  - name: created_by
    type: int
    default: null

  - name: expires_at
    type: datetime
    default: null

  - name: view_count
    type: int
    default: 0

  - name: is_active
    type: tinyint
    default: 1

  - name: created_at
    type: '@cdate'
...
```

---

## 4. SQL Schema (for `sql/dicom.sql`)

Direct SQL file for manual import via `sql/msql.sh`. Includes indexes and foreign keys that the YAML auto-generator may not create.

```sql
CREATE TABLE IF NOT EXISTS dicom_exams (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_uid       VARCHAR(128) DEFAULT NULL,
    patient_name    VARCHAR(255) DEFAULT NULL,
    patient_id_dcm  VARCHAR(64)  DEFAULT NULL,
    study_date      DATE         DEFAULT NULL,
    study_time      TIME         DEFAULT NULL,
    study_desc      VARCHAR(255) DEFAULT NULL,
    accession_no    VARCHAR(64)  DEFAULT NULL,
    modality        VARCHAR(16)  DEFAULT NULL,
    file_count      INT UNSIGNED DEFAULT 0,
    disk_size       BIGINT UNSIGNED DEFAULT 0,
    storage_path    VARCHAR(512) NOT NULL DEFAULT '',
    status          VARCHAR(20)  DEFAULT 'uploading',
    error_message   TEXT DEFAULT NULL,
    uploaded_by     INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_study_uid (study_uid),
    INDEX idx_patient (patient_name, patient_id_dcm),
    INDEX idx_date (study_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dicom_series (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    series_uid      VARCHAR(128) DEFAULT NULL,
    series_number   INT DEFAULT NULL,
    series_desc     VARCHAR(255) DEFAULT NULL,
    modality        VARCHAR(16)  DEFAULT NULL,
    frame_count     INT UNSIGNED DEFAULT 0,
    images_path     VARCHAR(512) DEFAULT NULL,
    status          VARCHAR(20)  DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES dicom_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dicom_images (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series_id       INT UNSIGNED NOT NULL,
    instance_number INT DEFAULT 0,
    sop_instance_uid VARCHAR(128) DEFAULT NULL,
    dcm_filename    VARCHAR(255) NOT NULL,
    thumb_filename  VARCHAR(255) DEFAULT NULL,
    full_filename   VARCHAR(255) DEFAULT NULL,
    width           INT UNSIGNED DEFAULT NULL,
    height          INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_series (series_id),
    INDEX idx_instance (series_id, instance_number),
    FOREIGN KEY (series_id) REFERENCES dicom_series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dicom_shares (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,
    created_by  INT UNSIGNED DEFAULT NULL,
    expires_at  DATETIME DEFAULT NULL,
    view_count  INT UNSIGNED DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES dicom_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 5. Module Configuration Files

### 5.1 `dicom.info.yaml`

```yaml
name: dicom
template: dicom.zetem
```

### 5.2 `dicom.yaml` (routes + libraries)

This file is loaded by the module constructor and merged into the kernel config — same pattern as `pdflib.yaml` and `location.yaml`.

```yaml
routes:
  # ─── Exam list page ───
  dicom-list:
    title:
      gr: "DICOM Απεικονίσεις"
      en: "DICOM Imaging"
    name: dicom_list
    url: ["/dicom", "/dicom/page/{page}"]
    handler: dicom_list
    access: dicom-view
    method: get

  # ─── Upload page (form) ───
  dicom-upload:
    title:
      gr: "Μεταφόρτωση DICOM"
      en: "DICOM Upload"
    name: dicom_upload
    url: /dicom/upload
    handler: dicom_upload
    access: dicom-upload
    method: get

  # ─── AJAX: upload init ───
  dicom-upload-init:
    title: "DICOM upload init"
    name: dicom_upload_init
    url: /dicom/upload/init
    handler: dicom_upload_init
    access: dicom-upload
    method: post

  # ─── AJAX: upload chunk ───
  dicom-upload-chunk:
    title: "DICOM upload chunk"
    name: dicom_upload_chunk
    url: /dicom/upload/chunk
    handler: dicom_upload_chunk
    access: dicom-upload
    method: post

  # ─── AJAX: upload finalize ───
  dicom-upload-finalize:
    title: "DICOM upload finalize"
    name: dicom_upload_finalize
    url: /dicom/upload/finalize
    handler: dicom_upload_finalize
    access: dicom-upload
    method: post

  # ─── Viewer page ───
  dicom-view:
    title:
      gr: "Προβολή εξέτασης"
      en: "View Exam"
    name: dicom_view
    url: /dicom/view/{id}
    handler: dicom_view_exam
    access: dicom-view
    method: get

  # ─── Image serving (auth-gated) ───
  dicom-image:
    title: "DICOM image serve"
    name: dicom_image
    url: /dicom/image/{series_id}/{type}/{filename}
    handler: dicom_serve_image
    method: get

  # ─── AJAX: create share link ───
  dicom-share-create:
    title: "Create DICOM share"
    name: dicom_share_create
    url: /dicom/share/{id}
    handler: dicom_share_create
    access: dicom-share
    method: post

  # ─── Public shared viewer (no auth) ───
  dicom-shared:
    title: "Shared DICOM exam"
    name: dicom_shared
    url: /dicom/shared/{token}
    handler: dicom_shared_view
    method: get

  # ─── Delete exam ───
  dicom-delete:
    title: "Delete DICOM exam"
    name: dicom_delete
    url: /dicom/exam/{id}/delete
    handler: dicom_delete_exam
    access: dicom-delete
    method: get

libraries:
  dicom-library:
    css:
      - dicom:
        src: "@app/css/dicom.css"
    foot_script:
      - dicom-upload:
        src: "@app/js/dicom-upload.js"
        defer:
      - dicom-viewer:
        src: "@app/js/dicom-viewer.js"
        defer:
```

---

## 6. Changes to `config/settings.info.yaml`

### 6.1 Register the module

```yaml
modules:
  modules:
    # ... existing modules ...
    - dicom
```

### 6.2 Add permissions to roles

```yaml
roles:
  # existing roles stay, update power-user to include dicom permissions:
  power-user: [
    patients-view-list,
    patients-new-patient,
    patients-delete-patient,
    patients-edit-patient,
    appointment-edit,
    pdflib-access,
    backup-access,
    administer_translations,
    dicom-view,
    dicom-upload,
    dicom-share
    ]
  # administrator already has 'all'
```

### 6.3 Add menu entry

```yaml
menu:
  main:
    - applications:
      submenu:
        # ... existing items ...
        - dicom:
          text:
            gr: "DICOM Απεικονίσεις"
            en: "DICOM Imaging"
          url: /dicom
          access: power-user
```

---

## 7. Module PHP — `dicom.php`

### 7.1 Module Class + Registration

Follows the exact pattern of `pdflib.php`: constructor loads `dicom.yaml`, merges config, re-initializes router.

```php
<?php

require_once __DIR__ . '/DicomParser.php';
require_once __DIR__ . '/DicomDirParser.php';
require_once __DIR__ . '/DicomConverter.php';

class dicomModule extends moduleClass {

    // Module configuration defaults
    const CONFIG = [
        'storage_base'             => 'data/dicom',
        'max_upload_size_mb'       => 500,
        'allowed_extensions'       => ['dcm', 'zip', 'gz'],
        'chunk_size_bytes'         => 2097152,    // 2 MB
        'dcmtk_bin_path'           => '/usr/bin',
        'thumbnail_width'          => 200,
        'full_image_format'        => 'png',
        'thumb_quality'            => 80,
        'share_default_expiry_days' => 30,
    ];

    public function __construct($adir, $amodule, $atemplate) {
        parent::__construct($adir, $amodule, $atemplate);

        $rt = yaml_parse_file(__DIR__ . '/dicom.yaml');

        global $kernel;
        $srt = $kernel->resolveModuleDir($rt, $adir, $amodule);
        $kernel->addConfig($srt);

        global $router;
        $router->initRouteTable($kernel->getConfig('routes'));
    }

    function render($params = array()) {
        return '';  // No region rendering — DICOM uses dedicated pages
    }

    /**
     * Get module config, merging defaults with any overrides.
     */
    static function getConfig() {
        return self::CONFIG;
    }

    /**
     * Get absolute storage base path.
     */
    static function getStorageBase() {
        return __APPDIR__ . '/' . self::CONFIG['storage_base'];
    }
}

function register_dicom_module() {
    global $kernel;
    $kernel->registerModule(new dicomModule(__DIR__, 'dicom', 'dicom.zetem'));
}
```

### 7.2 Route Handler Functions

All handler functions live in `dicom.php` (same file, below the class), following the pdflib pattern.

```php
// ─── EXAM LIST ─────────────────────────────────────────

function dicom_list($params) {
    if ($ret = SecurityClass::require('dicom-view')) return $ret;

    $page = (int)($params['page'] ?? 1);
    $per_page = 20;
    $offset = ($page - 1) * $per_page;

    $exams = dicom_examsClassEx::getExamList($per_page, $offset);
    $total = dicom_examsClassEx::getExamCount();
    $total_pages = ceil($total / $per_page);

    return Renderer::render('dicom_list.zetem', [
        'exams'       => $exams,
        'page'        => $page,
        'total_pages' => $total_pages,
        'total'       => $total,
    ]);
}


// ─── UPLOAD PAGE ───────────────────────────────────────

function dicom_upload($params) {
    if ($ret = SecurityClass::require('dicom-upload')) return $ret;

    $config = dicomModule::getConfig();
    return Renderer::render('dicom_upload.zetem', [
        'max_size_mb'   => $config['max_upload_size_mb'],
        'chunk_size'    => $config['chunk_size_bytes'],
        'allowed_ext'   => implode(', ', $config['allowed_extensions']),
    ]);
}


// ─── AJAX: UPLOAD INIT ────────────────────────────────

function dicom_upload_init($params) {
    if ($ret = SecurityClass::require('dicom-upload')) {
        dicom_json(['error' => 'Unauthorized'], 403);
    }

    $config   = dicomModule::getConfig();
    $filename = $_POST['filename'] ?? '';
    $filesize = (int)($_POST['filesize'] ?? 0);
    $total_chunks = (int)($_POST['total_chunks'] ?? 1);

    // Validate extension
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (!in_array($ext, $config['allowed_extensions'])) {
        dicom_json(['error' => 'File type not allowed'], 400);
    }

    // Validate size
    $max_bytes = $config['max_upload_size_mb'] * 1024 * 1024;
    if ($filesize > $max_bytes) {
        dicom_json(['error' => 'File too large'], 400);
    }

    // Create upload session
    $token = bin2hex(random_bytes(16));
    $upload_dir = dicomModule::getStorageBase() . '/uploads/' . $token . '/chunks';
    mkdir($upload_dir, 0755, true);

    $session = [
        'token'        => $token,
        'filename'     => $filename,
        'filesize'     => $filesize,
        'total_chunks' => $total_chunks,
        'received'     => 0,
        'created_at'   => date('Y-m-d H:i:s'),
    ];
    file_put_contents(
        dicomModule::getStorageBase() . '/uploads/' . $token . '/session.json',
        json_encode($session)
    );

    dicom_json([
        'upload_token' => $token,
        'chunk_size'   => $config['chunk_size_bytes'],
    ]);
}


// ─── AJAX: UPLOAD CHUNK ───────────────────────────────

function dicom_upload_chunk($params) {
    if ($ret = SecurityClass::require('dicom-upload')) {
        dicom_json(['error' => 'Unauthorized'], 403);
    }

    $token = $_POST['upload_token'] ?? '';
    $index = (int)($_POST['chunk_index'] ?? 0);
    $base  = dicomModule::getStorageBase();

    $session_file = $base . '/uploads/' . $token . '/session.json';
    if (!file_exists($session_file)) {
        dicom_json(['error' => 'Invalid upload token'], 400);
    }

    if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
        dicom_json(['error' => 'Chunk upload failed'], 400);
    }

    $chunk_path = $base . '/uploads/' . $token . '/chunks/'
                . str_pad($index, 6, '0', STR_PAD_LEFT);
    move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path);

    $session = json_decode(file_get_contents($session_file), true);
    $session['received']++;
    file_put_contents($session_file, json_encode($session));

    dicom_json([
        'received' => $session['received'],
        'total'    => $session['total_chunks'],
        'complete' => ($session['received'] >= $session['total_chunks']),
    ]);
}


// ─── AJAX: UPLOAD FINALIZE ────────────────────────────

function dicom_upload_finalize($params) {
    if ($ret = SecurityClass::require('dicom-upload')) {
        dicom_json(['error' => 'Unauthorized'], 403);
    }

    $config = dicomModule::getConfig();
    $base   = dicomModule::getStorageBase();
    $token  = $_POST['upload_token'] ?? '';
    $base_dir     = $base . '/uploads/' . $token;
    $session_file = $base_dir . '/session.json';

    if (!file_exists($session_file)) {
        dicom_json(['error' => 'Invalid upload token'], 400);
    }
    $session = json_decode(file_get_contents($session_file), true);

    // 1. Assemble chunks into single file
    $ext = strtolower(pathinfo($session['filename'], PATHINFO_EXTENSION));
    $assembled = $base_dir . '/assembled.' . $ext;
    $out = fopen($assembled, 'wb');
    $chunks = glob($base_dir . '/chunks/*');
    sort($chunks);
    foreach ($chunks as $chunk) {
        $in = fopen($chunk, 'rb');
        stream_copy_to_stream($in, $out);
        fclose($in);
    }
    fclose($out);

    // 2. Create exam record using entity class
    $db = dbConnection::getConnection();

    $exam = new dicom_examsClass();
    $exam->setstorage_path('');
    $exam->setstatus('processing');
    $exam->setuploaded_by($_SESSION['user_id'] ?? null);
    $exam->setdisk_size(filesize($assembled));
    $exam->setcreated_at(getDBtime());
    $exam->setupdated_at(getDBtime());
    $exam->insert();
    $exam_id = $db->lastInsertId();

    // 3. Create directory structure
    $exam_dir   = $base . '/exams/' . $exam_id;
    $orig_dir   = $exam_dir . '/original';
    $images_dir = $exam_dir . '/images';
    mkdir($orig_dir, 0755, true);
    mkdir($images_dir, 0755, true);

    // Update storage path
    $exam = dicom_examsClass::sgetById($exam_id);
    $exam->setstorage_path('exams/' . $exam_id);
    $exam->update();

    // 4. Extract ZIP or copy single DCM
    if ($ext === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($assembled) === true) {
            $zip->extractTo($orig_dir);
            $zip->close();
        } else {
            dicom_update_exam_status($exam_id, 'error', 'ZIP extraction failed');
            dicom_json(['error' => 'ZIP extraction failed'], 500);
        }
    } else {
        copy($assembled, $orig_dir . '/' . $session['filename']);
    }

    // 5. Verify DCMTK is available
    $converter = new DicomConverter($config);
    if (!$converter->checkDcmtk()) {
        dicom_update_exam_status($exam_id, 'error', 'DCMTK not found on server');
        dicom_json(['error' => 'DCMTK not installed'], 500);
    }

    // 6. Parse study metadata from first file
    $first_dcm = dicom_find_first_dcm($orig_dir);
    if ($first_dcm) {
        $parser = new DicomParser($first_dcm);
        $tags = $parser->parse();
        dicom_update_exam_meta($exam_id, $tags);
    }

    // 7. Count DICOM files
    $dcm_count = count($converter->findDcmFilesPublic($orig_dir));
    $exam = dicom_examsClass::sgetById($exam_id);
    $exam->setfile_count($dcm_count);
    $exam->update();

    // 8. Process: group by series → convert → thumbnails → DB
    $converter->processExam($exam_id, $orig_dir, $images_dir, $db);

    // 9. Mark ready + cleanup
    dicom_update_exam_status($exam_id, 'ready');
    dicom_delete_directory($base_dir);

    dicom_json([
        'exam_id'  => $exam_id,
        'status'   => 'ready',
        'redirect' => rel_url('/dicom/view/' . $exam_id),
    ]);
}


// ─── VIEWER PAGE ──────────────────────────────────────

function dicom_view_exam($params) {
    if ($ret = SecurityClass::require('dicom-view')) return $ret;

    $exam_id = (int)($params['id'] ?? 0);
    $exam = dicom_examsClass::sgetById($exam_id);
    if (!$exam) {
        global $kernel;
        $kernel->addStatus('error', 'Exam not found');
        return Renderer::render('dicom_list.zetem', ['exams' => [], 'page' => 1, 'total_pages' => 0, 'total' => 0]);
    }

    $series_list = dicom_seriesClassEx::getByExamId($exam_id);
    $exam_data = dicom_build_viewer_data($exam, $series_list);

    return Renderer::render('dicom_viewer.zetem', [
        'exam'      => $exam,
        'exam_data' => $exam_data,
        'series'    => $series_list,
    ]);
}


// ─── IMAGE SERVING (auth-gated) ───────────────────────

function dicom_serve_image($params) {
    $series_id = (int)($params['series_id'] ?? 0);
    $type      = $params['type'] ?? '';
    $filename  = $params['filename'] ?? '';
    $share_token = $_GET['share_token'] ?? null;

    // Validate type
    if (!in_array($type, ['thumb', 'full'])) {
        http_response_code(400);
        exit;
    }

    // Sanitize filename
    if (!preg_match('/^[\w\-]+\.(jpg|jpeg|png)$/', $filename)) {
        http_response_code(400);
        exit;
    }

    // Auth: logged in OR valid share token
    if (!dicom_authorize_image($series_id, $share_token)) {
        http_response_code(403);
        exit;
    }

    // Resolve file path
    $series = dicom_seriesClass::sgetById($series_id);
    if (!$series || !$series->getimages_path()) {
        http_response_code(404);
        exit;
    }

    $base = dicomModule::getStorageBase();
    $file_path = $base . '/' . $series->getimages_path() . '/' . $type . '/' . $filename;
    if (!file_exists($file_path)) {
        http_response_code(404);
        exit;
    }

    $ext  = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private, max-age=86400');
    readfile($file_path);
    exit;
}


// ─── SHARE: CREATE ────────────────────────────────────

function dicom_share_create($params) {
    if ($ret = SecurityClass::require('dicom-share')) {
        dicom_json(['error' => 'Unauthorized'], 403);
    }

    $config  = dicomModule::getConfig();
    $exam_id = (int)($params['id'] ?? 0);
    $days    = (int)($_POST['expiry_days'] ?? $config['share_default_expiry_days']);

    $exam = dicom_examsClass::sgetById($exam_id);
    if (!$exam) {
        dicom_json(['error' => 'Exam not found'], 404);
    }

    $token = bin2hex(random_bytes(24));
    $expires_at = ($days > 0) ? date('Y-m-d H:i:s', time() + $days * 86400) : null;

    $share = new dicom_sharesClass();
    $share->setexam_id($exam_id);
    $share->settoken($token);
    $share->setcreated_by($_SESSION['user_id'] ?? null);
    $share->setexpires_at($expires_at);
    $share->setcreated_at(getDBtime());
    $share->insert();

    $share_url = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://'
               . $_SERVER['HTTP_HOST'] . rel_url('/dicom/shared/' . $token);

    dicom_json([
        'token'      => $token,
        'url'        => $share_url,
        'expires_at' => $expires_at,
    ]);
}


// ─── SHARE: PUBLIC VIEW ───────────────────────────────

function dicom_shared_view($params) {
    $token = $params['token'] ?? '';

    $share = dicom_sharesClassEx::getByToken($token);
    if (!$share || !$share->getis_active()) {
        return Renderer::render('dicom_shared_error.zetem', ['error' => 'not_found']);
    }

    if ($share->getexpires_at() && strtotime($share->getexpires_at()) < time()) {
        return Renderer::render('dicom_shared_error.zetem', ['error' => 'expired']);
    }

    // Bump view count
    $share->setview_count($share->getview_count() + 1);
    $share->update();

    $exam = dicom_examsClass::sgetById($share->getexam_id());
    if (!$exam || $exam->getstatus() !== 'ready') {
        return Renderer::render('dicom_shared_error.zetem', ['error' => 'not_found']);
    }

    $series_list = dicom_seriesClassEx::getByExamId($exam->getid());
    $exam_data = dicom_build_viewer_data($exam, $series_list, $token);

    return Renderer::render('dicom_viewer.zetem', [
        'exam'        => $exam,
        'exam_data'   => $exam_data,
        'series'      => $series_list,
        'share_token' => $token,
        'readonly'    => true,
    ]);
}


// ─── DELETE EXAM ──────────────────────────────────────

function dicom_delete_exam($params) {
    if ($ret = SecurityClass::require('dicom-delete')) return $ret;

    global $kernel;
    $exam_id = (int)($params['id'] ?? 0);
    $exam = dicom_examsClass::sgetById($exam_id);

    if (!$exam) {
        $kernel->addStatus('error', 'Exam not found');
    } else {
        // Delete files from disk
        $exam_dir = dicomModule::getStorageBase() . '/' . $exam->getstorage_path();
        if (is_dir($exam_dir)) {
            dicom_delete_directory($exam_dir);
        }
        // DB cascade handles series, images, shares
        $exam->delete();
        $kernel->addStatus('notice', 'DICOM exam deleted');
    }

    header('location: ' . rel_url('/dicom'));
    exit;
}


// ═══ HELPER FUNCTIONS ═════════════════════════════════

function dicom_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function dicom_update_exam_status($exam_id, $status, $error = null) {
    $exam = dicom_examsClass::sgetById($exam_id);
    if ($exam) {
        $exam->setstatus($status);
        $exam->seterror_message($error);
        $exam->setupdated_at(getDBtime());
        $exam->update();
    }
}

function dicom_update_exam_meta($exam_id, $tags) {
    $exam = dicom_examsClass::sgetById($exam_id);
    if (!$exam) return;

    $study_date = null;
    if (!empty($tags['study_date']) && strlen($tags['study_date']) === 8) {
        $study_date = substr($tags['study_date'], 0, 4) . '-'
                    . substr($tags['study_date'], 4, 2) . '-'
                    . substr($tags['study_date'], 6, 2);
    }

    $exam->setstudy_uid($tags['study_instance_uid'] ?? null);
    $exam->setpatient_name($tags['patient_name'] ?? null);
    $exam->setpatient_id_dcm($tags['patient_id'] ?? null);
    $exam->setstudy_date($study_date);
    $exam->setstudy_desc($tags['study_description'] ?? null);
    $exam->setaccession_no($tags['accession_number'] ?? null);
    $exam->setmodality($tags['modality'] ?? null);
    $exam->setupdated_at(getDBtime());
    $exam->update();
}

function dicom_authorize_image($series_id, $share_token) {
    // Logged-in user with view permission
    if (SecurityClass::userLoggedIn()) return true;

    // Valid share token
    if ($share_token) {
        $share = dicom_sharesClassEx::getByToken($share_token);
        if (!$share || !$share->getis_active()) return false;
        if ($share->getexpires_at() && strtotime($share->getexpires_at()) < time()) return false;

        // Verify series belongs to the shared exam
        $series = dicom_seriesClass::sgetById($series_id);
        if (!$series || $series->getexam_id() != $share->getexam_id()) return false;

        return true;
    }
    return false;
}

function dicom_build_viewer_data($exam, $series_list, $share_token = null) {
    $token_param = $share_token ? '?share_token=' . urlencode($share_token) : '';
    $data = ['series' => []];

    foreach ($series_list as $s) {
        if ($s->getstatus() !== 'ready') continue;

        $images = dicom_imagesClassEx::getBySeries($s->getid());
        $img_list = [];
        foreach ($images as $img) {
            $img_list[] = [
                'thumb_url' => rel_url('/dicom/image/' . $s->getid() . '/thumb/' . $img->getthumb_filename()) . $token_param,
                'full_url'  => rel_url('/dicom/image/' . $s->getid() . '/full/' . $img->getfull_filename()) . $token_param,
            ];
        }

        $data['series'][] = [
            'id'     => $s->getid(),
            'name'   => $s->getseries_desc() ?: ($s->getmodality() . ' Series ' . $s->getseries_number()),
            'images' => $img_list,
        ];
    }

    return json_encode($data);
}

function dicom_find_first_dcm($dir) {
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iter as $f) {
        if (!$f->isFile()) continue;
        if (strtolower($f->getExtension()) === 'dcm') return $f->getPathname();
        if ($f->getExtension() === '' && DicomParser::isDicom($f->getPathname())) return $f->getPathname();
    }
    return null;
}

function dicom_delete_directory($dir) {
    if (!is_dir($dir)) return;
    $iter = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iter as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}
```

---

## 8. ClassesEx Extensions

Add to `web/ClassesEx.php`:

```php
// ─── DICOM EXAMS ──────────────────────────────────────

class dicom_examsClassEx extends dicom_examsClass {

    static function getExamList($limit = 20, $offset = 0) {
        $sql = "SELECT * FROM dicom_exams ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':limit', $limit, PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, PDO::PARAM_INT);
        $st->execute();
        $list = [];
        while ($row = $st->fetch()) {
            $r = new dicom_examsClass();
            $r->loadFields($row);
            $list[] = $r;
        }
        return $list;
    }

    static function getExamCount() {
        $sql = "SELECT COUNT(*) as cnt FROM dicom_exams";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute();
        return (int)$st->fetch()['cnt'];
    }

    static function searchExams($term, $modality = null) {
        $sql = "SELECT * FROM dicom_exams WHERE (patient_name LIKE :term OR study_desc LIKE :term)";
        $params = [':term' => '%' . $term . '%'];
        if ($modality) {
            $sql .= " AND modality = :modality";
            $params[':modality'] = $modality;
        }
        $sql .= " ORDER BY created_at DESC";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->execute($params);
        $list = [];
        while ($row = $st->fetch()) {
            $r = new dicom_examsClass();
            $r->loadFields($row);
            $list[] = $r;
        }
        return $list;
    }
}


// ─── DICOM SERIES ─────────────────────────────────────

class dicom_seriesClassEx extends dicom_seriesClass {

    static function getByExamId($exam_id) {
        $sql = "SELECT * FROM dicom_series WHERE exam_id = :eid ORDER BY series_number ASC";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':eid', $exam_id, PDO::PARAM_INT);
        $st->execute();
        $list = [];
        while ($row = $st->fetch()) {
            $r = new dicom_seriesClass();
            $r->loadFields($row);
            $list[] = $r;
        }
        return $list;
    }
}


// ─── DICOM IMAGES ─────────────────────────────────────

class dicom_imagesClassEx extends dicom_imagesClass {

    static function getBySeries($series_id) {
        $sql = "SELECT * FROM dicom_images WHERE series_id = :sid ORDER BY instance_number ASC";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':sid', $series_id, PDO::PARAM_INT);
        $st->execute();
        $list = [];
        while ($row = $st->fetch()) {
            $r = new dicom_imagesClass();
            $r->loadFields($row);
            $list[] = $r;
        }
        return $list;
    }
}


// ─── DICOM SHARES ─────────────────────────────────────

class dicom_sharesClassEx extends dicom_sharesClass {

    static function getByToken($token) {
        $sql = "SELECT * FROM dicom_shares WHERE token = :token";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':token', $token, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();
        if ($row) {
            $r = new dicom_sharesClass();
            $r->loadFields($row);
            return $r;
        }
        return null;
    }
}
```

---

## 9. ZETEM Templates

### 9.1 `dicom_list.zetem` — Exam List Page

```zetem
{% attach_library('dicom-library') %}
{% attach_library('boxicons') %}

<div class="page-title">
    <h2>{{t("DICOM Imaging")}}</h2>
</div>

<div class="filter-bar">
    <div class="filter-group" id="modality-filters">
        <button class="filter-chip active" data-modality="">{{t("All")}}</button>
        <button class="filter-chip" data-modality="CT">CT</button>
        <button class="filter-chip" data-modality="MR">MR</button>
        <button class="filter-chip" data-modality="XR">XR</button>
        <button class="filter-chip" data-modality="US">US</button>
    </div>
    <div style="margin-left: auto;">
        <a href="{{rel_url('/dicom/upload')}}" class="action-btn">
            <i class="bx bx-upload"></i> {{t("Upload")}}
        </a>
    </div>
</div>

<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th>{{t("Patient")}}</th>
                <th>{{t("Study")}}</th>
                <th>{{t("Date")}}</th>
                <th>{{t("Modality")}}</th>
                <th>{{t("Files")}}</th>
                <th>{{t("Status")}}</th>
                <th>{{t("Actions")}}</th>
            </tr>
        </thead>
        <tbody>
            {% if count($exams) > 0 %}
                {% foreach($exams as $exam): %}
                <tr data-modality="{{$exam->getmodality()}}">
                    <td>{{$exam->getpatient_name() ?: '—'}}</td>
                    <td>
                        <a href="{{rel_url('/dicom/view/' . $exam->getid())}}">
                            {{$exam->getstudy_desc() ?: 'Exam #' . $exam->getid()}}
                        </a>
                    </td>
                    <td>{{$exam->getstudy_date() ?: '—'}}</td>
                    <td><span class="filter-chip">{{$exam->getmodality() ?: '—'}}</span></td>
                    <td>{{$exam->getfile_count()}}</td>
                    <td>
                        {% if $exam->getstatus() === 'ready' %}
                            <span class="badge success">{{t("Ready")}}</span>
                        {% elseif $exam->getstatus() === 'processing' %}
                            <span class="badge warning">{{t("Processing")}}</span>
                        {% elseif $exam->getstatus() === 'error' %}
                            <span class="badge danger" title="{{$exam->geterror_message()}}">{{t("Error")}}</span>
                        {% else %}
                            <span class="badge">{{$exam->getstatus()}}</span>
                        {% endif %}
                    </td>
                    <td class="actions">
                        <a href="{{rel_url('/dicom/view/' . $exam->getid())}}"><i class="bx bx-show"></i></a>
                        <a href="{{rel_url('/dicom/exam/' . $exam->getid() . '/delete')}}" confirmation>
                            <i class="bx bx-trash"></i>
                        </a>
                    </td>
                </tr>
                {% endforeach %}
            {% else %}
                <tr>
                    <td colspan="7">
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="bx bx-image"></i></div>
                            <div class="empty-state-title">{{t("No DICOM exams")}}</div>
                            <div class="empty-state-description">{{t("Upload a DICOM file or ZIP to get started")}}</div>
                        </div>
                    </td>
                </tr>
            {% endif %}
        </tbody>
    </table>
</div>

{% if $total_pages > 1 %}
<div class="pagination">
    {% if $page > 1 %}
        <a href="{{rel_url('/dicom/page/' . ($page-1))}}" class="pagination-btn">&laquo;</a>
    {% endif %}
    {% for $i = 1; $i <= $total_pages; $i++ %}
        <a href="{{rel_url('/dicom/page/' . $i)}}" class="pagination-btn {{ ($i === $page) ? 'active' : '' }}">{{$i}}</a>
    {% endfor %}
    {% if $page < $total_pages %}
        <a href="{{rel_url('/dicom/page/' . ($page+1))}}" class="pagination-btn">&raquo;</a>
    {% endif %}
</div>
{% endif %}
```

### 9.2 `dicom_upload.zetem` — Upload Page

```zetem
{% attach_library('dicom-library') %}
{% attach_library('boxicons') %}

<div class="page-title">
    <h2>{{t("DICOM Upload")}}</h2>
</div>

<div class="card">
    <div class="card-body">
        <div id="upload-dropzone" class="dicom-dropzone">
            <div class="dicom-dropzone-icon"><i class="bx bx-cloud-upload"></i></div>
            <p class="dicom-dropzone-text">{{t("Drag & drop DICOM or ZIP file here")}}</p>
            <p class="dicom-dropzone-hint">{{t("Allowed")}}: {{$allowed_ext}} | {{t("Max")}}: {{$max_size_mb}} MB</p>
            <label class="action-btn dicom-browse-btn">
                {{t("Browse Files")}}
                <input type="file" id="upload-file-input" accept=".dcm,.zip,.gz" hidden>
            </label>
        </div>

        <div id="upload-file-info" class="dicom-file-info"></div>

        <div id="upload-progress" class="dicom-progress" style="display:none;">
            <div class="dicom-progress-bar">
                <div id="upload-bar" class="dicom-progress-fill"></div>
            </div>
            <div class="dicom-progress-info">
                <span id="upload-pct">0%</span>
                <span id="upload-status"></span>
            </div>
        </div>

        <div class="dicom-upload-actions">
            <a href="{{rel_url('/dicom')}}" class="action-btn">{{t("Cancel")}}</a>
            <button id="upload-btn" class="action-btn primary" disabled>
                <i class="bx bx-upload"></i> {{t("Upload")}}
            </button>
        </div>
    </div>
</div>
```

### 9.3 `dicom_viewer.zetem` — Exam Viewer Page

```zetem
{% attach_library('dicom-library') %}
{% attach_library('boxicons') %}

<div class="dicom-viewer-header">
    <div class="dicom-viewer-title">
        <h2>
            {{$exam->getpatient_name() ?: 'Unknown'}} —
            {{$exam->getstudy_desc() ?: 'Exam #' . $exam->getid()}}
            {% if $exam->getstudy_date() %}
                ({{$exam->getstudy_date()}})
            {% endif %}
        </h2>
    </div>
    <div class="dicom-viewer-actions">
        {% if !isset($readonly) || !$readonly %}
            <button id="share-btn" class="action-btn" data-exam-id="{{$exam->getid()}}">
                <i class="bx bx-share-alt"></i> {{t("Share")}}
            </button>
        {% endif %}
        <a href="{{rel_url('/dicom')}}" class="action-btn">
            <i class="bx bx-arrow-back"></i> {{t("Back")}}
        </a>
    </div>
</div>

<div class="dicom-exam-meta">
    {% if $exam->getmodality() %}
        <span class="filter-chip">{{$exam->getmodality()}}</span>
    {% endif %}
    {% if $exam->getaccession_no() %}
        <span class="dicom-meta-item">Acc#: {{$exam->getaccession_no()}}</span>
    {% endif %}
    <span class="dicom-meta-item">{{$exam->getfile_count()}} {{t("files")}}</span>
</div>

<div id="series-tabs" class="dicom-series-tabs"></div>

<div id="thumb-grid" class="dicom-thumb-grid"></div>

{# Share dialog (hidden) #}
<div id="share-dialog" class="dicom-share-dialog" style="display:none;">
    <div class="dicom-share-dialog-content">
        <h3>{{t("Share Link")}}</h3>
        <input type="text" id="share-url" readonly>
        <button id="share-copy-btn" class="action-btn">{{t("Copy")}}</button>
        <button id="share-close-btn" class="action-btn">{{t("Close")}}</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var examData = {{ $exam_data }};
    DicomViewer.init(examData);

    {% if !isset($readonly) || !$readonly %}
    // Share button
    var shareBtn = document.getElementById('share-btn');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            var examId = this.dataset.examId;
            var fd = new FormData();
            fetch('{{ rel_url("/dicom/share/") }}' + examId, {
                method: 'POST', body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('share-url').value = data.url;
                document.getElementById('share-dialog').style.display = 'flex';
            });
        });

        document.getElementById('share-copy-btn').addEventListener('click', function() {
            var input = document.getElementById('share-url');
            input.select();
            document.execCommand('copy');
        });

        document.getElementById('share-close-btn').addEventListener('click', function() {
            document.getElementById('share-dialog').style.display = 'none';
        });
    }
    {% endif %}
});
</script>
```

### 9.4 `dicom.zetem` — Module template (required by framework, minimal)

```zetem
{# DICOM module base template — content is rendered via dedicated page templates #}
```

---

## 10. CSS — `web/css/dicom.css`

Uses design system CSS variables (`--space-*`, `--slate-*`, `--primary-*`, `--radius-*`, etc.) and existing component classes (`.filter-chip`, `.empty-state`, `.pagination-btn`, `.table-wrapper`, etc.) from `components.css`.

```css
/* ===== DICOM MODULE ===== */

/* ── Dropzone ── */
.dicom-dropzone {
    border: 2px dashed var(--slate-300);
    border-radius: var(--radius-xl);
    padding: var(--space-12) var(--space-6);
    text-align: center;
    cursor: pointer;
    transition: all var(--transition-fast);
}

.dicom-dropzone:hover,
.dicom-dropzone.drag-over {
    border-color: var(--primary-500);
    background: var(--primary-50);
}

.dicom-dropzone-icon {
    font-size: 3rem;
    color: var(--slate-400);
    margin-bottom: var(--space-4);
}

.dicom-dropzone.drag-over .dicom-dropzone-icon {
    color: var(--primary-500);
}

.dicom-dropzone-text {
    font-size: var(--text-lg);
    font-weight: var(--font-weight-medium);
    color: var(--slate-700);
    margin-bottom: var(--space-2);
}

.dicom-dropzone-hint {
    font-size: var(--text-sm);
    color: var(--slate-500);
}

.dicom-browse-btn {
    display: inline-block;
    margin-top: var(--space-4);
    cursor: pointer;
}

/* ── File info ── */
.dicom-file-info {
    padding: var(--space-3) 0;
    font-size: var(--text-sm);
    color: var(--slate-600);
}

/* ── Progress bar ── */
.dicom-progress {
    margin: var(--space-4) 0;
}

.dicom-progress-bar {
    height: 8px;
    background: var(--slate-200);
    border-radius: var(--radius-full);
    overflow: hidden;
}

.dicom-progress-fill {
    height: 100%;
    background: var(--primary-600);
    border-radius: var(--radius-full);
    transition: width 0.3s ease;
    width: 0%;
}

.dicom-progress-info {
    display: flex;
    justify-content: space-between;
    margin-top: var(--space-2);
    font-size: var(--text-sm);
    color: var(--slate-600);
}

/* ── Upload actions ── */
.dicom-upload-actions {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
    margin-top: var(--space-6);
    padding-top: var(--space-4);
    border-top: 1px solid var(--slate-200);
}

/* ── Viewer header ── */
.dicom-viewer-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: var(--space-4);
    flex-wrap: wrap;
    gap: var(--space-3);
}

.dicom-viewer-title h2 {
    margin: 0;
    font-size: var(--text-xl);
}

.dicom-viewer-actions {
    display: flex;
    gap: var(--space-2);
}

.dicom-exam-meta {
    display: flex;
    gap: var(--space-3);
    align-items: center;
    margin-bottom: var(--space-4);
    font-size: var(--text-sm);
    color: var(--slate-600);
}

/* ── Series tabs ── */
.dicom-series-tabs {
    display: flex;
    gap: var(--space-2);
    margin-bottom: var(--space-6);
    flex-wrap: wrap;
}

/* ── Thumbnail grid ── */
.dicom-thumb-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: var(--space-3);
}

.dicom-thumb {
    position: relative;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-md);
    overflow: hidden;
    cursor: pointer;
    transition: all var(--transition-fast);
    aspect-ratio: 1;
    background: var(--slate-100);
}

.dicom-thumb:hover {
    border-color: var(--primary-400);
    box-shadow: var(--shadow-md);
    transform: translateY(-2px);
}

.dicom-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.dicom-thumb-label {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    padding: var(--space-1);
    background: rgba(0, 0, 0, 0.6);
    color: white;
    font-size: var(--text-xs);
    text-align: center;
}

/* ── Lightbox ── */
.dicom-lightbox {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 9999;
    align-items: center;
    justify-content: center;
}

.dicom-lightbox.active {
    display: flex;
}

.dicom-lightbox-backdrop {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.9);
}

.dicom-lightbox-content {
    position: relative;
    display: flex;
    align-items: center;
    gap: var(--space-4);
    max-width: 95vw;
    max-height: 95vh;
}

.dicom-lightbox-close {
    position: absolute;
    top: -2rem;
    right: 0;
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    cursor: pointer;
    z-index: 1;
}

.dicom-lightbox-prev,
.dicom-lightbox-next {
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: white;
    font-size: 3rem;
    padding: var(--space-4) var(--space-2);
    cursor: pointer;
    border-radius: var(--radius-md);
    transition: background var(--transition-fast);
    flex-shrink: 0;
}

.dicom-lightbox-prev:hover,
.dicom-lightbox-next:hover {
    background: rgba(255, 255, 255, 0.2);
}

.dicom-lightbox-img-wrap {
    max-width: 80vw;
    max-height: 85vh;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dicom-lightbox-img-wrap img {
    max-width: 100%;
    max-height: 85vh;
    object-fit: contain;
}

.dicom-lightbox-info {
    position: absolute;
    bottom: -2rem;
    left: 50%;
    transform: translateX(-50%);
    color: white;
    font-size: var(--text-sm);
}

/* ── Share dialog ── */
.dicom-share-dialog {
    position: fixed;
    inset: 0;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.5);
}

.dicom-share-dialog-content {
    background: white;
    padding: var(--space-6);
    border-radius: var(--radius-xl);
    min-width: 24rem;
    display: flex;
    flex-direction: column;
    gap: var(--space-4);
}

.dicom-share-dialog-content input[type="text"] {
    width: 100%;
    padding: var(--space-3);
    border: 1px solid var(--slate-300);
    border-radius: var(--radius-md);
    font-family: monospace;
    font-size: var(--text-sm);
}

/* ── Status badges ── */
.badge {
    display: inline-flex;
    align-items: center;
    padding: var(--space-1) var(--space-2);
    border-radius: var(--radius-full);
    font-size: var(--text-xs);
    font-weight: var(--font-weight-medium);
}

.badge.success {
    background: var(--success-100);
    color: var(--success-700);
}

.badge.warning {
    background: var(--warning-100);
    color: var(--warning-700);
}

.badge.danger {
    background: var(--danger-100);
    color: var(--danger-700);
}

/* ── Card (reusable) ── */
.card {
    background: white;
    border: 1px solid var(--slate-200);
    border-radius: var(--radius-xl);
    overflow: hidden;
}

.card-body {
    padding: var(--space-6);
}

/* ── Action btn primary ── */
.action-btn.primary {
    background: var(--primary-600);
    color: white;
    border-radius: var(--radius-md);
    padding: var(--space-2) var(--space-4);
    border: none;
    cursor: pointer;
    transition: background var(--transition-fast);
}

.action-btn.primary:hover {
    background: var(--primary-700);
}

.action-btn.primary:disabled {
    background: var(--slate-300);
    cursor: not-allowed;
}
```

---

## 11. JavaScript Files

### 11.1 `web/js/dicom-upload.js`

Identical to the original plan (Section 5.5), with one change — the `baseUrl` uses ZPMS relative URL helper:

```javascript
// The base URL is set inline by the template, or defaults to /dicom/upload
const DicomUploader = { /* ... same as original plan Section 5.5 ... */ };
```

**Key change:** The `baseUrl` is hardcoded to `/dicom/upload` but the template's `onComplete` callback uses `result.redirect` which already contains the `rel_url()` value from the server.

### 11.2 `web/js/dicom-viewer.js`

Identical to original plan (Section 5.6). No changes needed — it's framework-agnostic vanilla JS.

---

## 12. Core PHP Components (Unchanged from Original Plan)

These three files go into `web/modules/dicom/` and are `require_once`'d by `dicom.php`:

| File | Source Section | Changes |
|------|---------------|---------|
| `DicomParser.php` | Plan Section 5.1 | None — standalone class |
| `DicomDirParser.php` | Plan Section 5.2 | None — standalone class |
| `DicomConverter.php` | Plan Section 5.3 | **Minor**: `processExam()` uses raw PDO `$db` param (already compatible with `dbConnection::getConnection()`) |

The `DicomConverter::processExam()` method receives `$db` (a PDO connection) directly. In ZPMS, we pass `dbConnection::getConnection()` — this is already a PDO instance, so no adaptation is needed.

---

## 13. Data Directory Setup

Create `data/dicom/` with proper permissions and an `.htaccess` to block direct access:

```bash
mkdir -p data/dicom/{uploads,exams,tmp}
chmod -R 755 data/dicom
```

**`data/dicom/.htaccess`:**
```
Deny from all
```

All images are served via `dicom_serve_image()` — never directly from the filesystem.

---

## 14. Implementation Phases

### Phase 1 — Foundation (Database + Core Parsers)

| # | Task | Files |
|---|------|-------|
| 1 | Create YAML entity schemas | `web/classes/yaml/dicom_*.yaml` |
| 2 | Run SQL schema import | `sql/dicom.sql` via `sql/msql.sh` |
| 3 | Regenerate entity classes | `web/classes/dicom_*.php` (auto) |
| 4 | Add ClassesEx extensions | `web/ClassesEx.php` |
| 5 | Copy core parsers into module | `web/modules/dicom/DicomParser.php`, `DicomDirParser.php`, `DicomConverter.php` |
| 6 | Create data directory + .htaccess | `data/dicom/` |

### Phase 2 — Module Skeleton + Upload

| # | Task | Files |
|---|------|-------|
| 7 | Create module files (php, info.yaml, yaml) | `web/modules/dicom/dicom.*` |
| 8 | Register module in settings.info.yaml | `config/settings.info.yaml` |
| 9 | Add permissions to roles | `config/settings.info.yaml` |
| 10 | Upload page template + JS | `dicom_upload.zetem`, `dicom-upload.js` |
| 11 | Upload handlers (init, chunk, finalize) | In `dicom.php` |

### Phase 3 — Viewer + List

| # | Task | Files |
|---|------|-------|
| 12 | Exam list template | `dicom_list.zetem` |
| 13 | Viewer template + JS | `dicom_viewer.zetem`, `dicom-viewer.js` |
| 14 | Image serving handler | In `dicom.php` |
| 15 | Module CSS | `web/css/dicom.css` |

### Phase 4 — Sharing + Menu

| # | Task | Files |
|---|------|-------|
| 16 | Share create/resolve handlers | In `dicom.php` |
| 17 | Shared viewer error template | `dicom_shared_error.zetem` |
| 18 | Add menu entry | `config/settings.info.yaml` |
| 19 | Delete exam handler | In `dicom.php` |

### Phase 5 — Polish + Testing

| # | Task | Files |
|---|------|-------|
| 20 | Verify DCMTK installed (`apt install dcmtk`) | Server |
| 21 | Test upload flow with real DICOM files | Manual |
| 22 | Test ZIP upload with DICOMDIR | Manual |
| 23 | Test share links (create, view, expiry) | Manual |
| 24 | Test modality filter on exam list | Manual |
| 25 | Responsive CSS review | `dicom.css` |

---

## 15. Architecture Decisions — Why This Approach

| Decision | Rationale |
|----------|-----------|
| All handlers in `dicom.php` | Follows pdflib pattern — no separate controller classes |
| YAML schemas for entities | Auto-generates classes with getters/setters/CRUD — less boilerplate |
| Raw SQL file too | YAML may not generate indexes/FKs — SQL as authoritative schema |
| DicomConverter uses raw PDO | `processExam()` is complex with many queries; raw PDO is cleaner than entity classes here |
| CSS uses design system vars | Visual consistency with rest of ZPMS |
| `data/dicom/` outside web root | Security — images served via PHP auth check |
| Module-owned routes in `dicom.yaml` | Self-contained — adding/removing module doesn't require editing global routes |
| `dicom_` prefix on all functions | Namespace collision avoidance (PHP has no real namespaces in this codebase) |

---

## 16. Server Requirements

```
PHP ≥ 8.0
  ext-zip, ext-gd, ext-pdo_mysql, ext-yaml
  php.ini:
    upload_max_filesize = 10M    (chunk size, not total)
    post_max_size = 12M
    max_execution_time = 300     (for finalize/processing)
    memory_limit = 256M

DCMTK:
  apt install dcmtk              (provides dcm2pnm, dcmdump)

Disk: plan ~2x raw DICOM size per exam (originals + converted images)
```

---

## 17. Future Enhancements (Phase 2+)

- Background processing queue for large exams (avoid PHP timeout)
- Window/Level controls in viewer (brightness/contrast)
- DICOM metadata panel (all parsed tags)
- Mousewheel scroll-through images
- ZPMS patient record linking (match `patient_id_dcm` → `patients.pamka`)
- Share link management page (list/revoke/edit)
- Cornerstone.js integration for proper DICOM viewport

---

## 18. Addendum — Post-Implementation Notes (February 2026)

### 18.1 Entity Classes — Manual vs. Generated

The YAML entity schemas in `web/classes/yaml/dicom_*.yaml` are used for documentation and as input to the Zeus Framework class generator (`fw/core/maker/maker.php`). Because the generator was **not run** during implementation (to avoid side effects), the four PHP entity classes were written manually following the `patients.php` pattern.

**If you ever need to regenerate them via the maker tool**, run these commands from `web/classes/`:

```bash
# Run from: /var/www/html/apps/zpms/web/classes/
# The maker expects to be run from the classes directory so it can find config/db.php by walking up

php /var/www/html/apps/zeusfw/core/maker/maker.php class:gen:yaml \
    --app-dir=/var/www/html/apps/zpms \
    yaml/dicom_exams.yaml

php /var/www/html/apps/zeusfw/core/maker/maker.php class:gen:yaml \
    --app-dir=/var/www/html/apps/zpms \
    yaml/dicom_series.yaml

php /var/www/html/apps/zeusfw/core/maker/maker.php class:gen:yaml \
    --app-dir=/var/www/html/apps/zpms \
    yaml/dicom_images.yaml

php /var/www/html/apps/zeusfw/core/maker/maker.php class:gen:yaml \
    --app-dir=/var/www/html/apps/zpms \
    yaml/dicom_shares.yaml
```

> **Note:** The exact command name (`class:gen:yaml`) may differ — check available commands with:
> ```bash
> php /var/www/html/apps/zeusfw/core/maker/maker.php
> ```
> If regenerating, the auto-generated files will **overwrite** `web/classes/dicom_*.php`. The manually written classes are structurally identical to what the generator produces, so regeneration is safe. Afterwards, verify that `web/classes/bootstrap_classes.php` still includes all four files.

### 18.2 Database Schema Import

Before the module works, the four tables must exist in MySQL. Import with:

```bash
# From the zpms app root:
bash sql/msql.sh sql/dicom.sql

# Or directly with mysql:
mysql -u <user> -p <dbname> < sql/dicom.sql
```

### 18.3 Files Created During Implementation

| File | Purpose |
|------|---------|
| `web/classes/yaml/dicom_exams.yaml` | Entity schema (documentation + generator input) |
| `web/classes/yaml/dicom_series.yaml` | Entity schema |
| `web/classes/yaml/dicom_images.yaml` | Entity schema |
| `web/classes/yaml/dicom_shares.yaml` | Entity schema |
| `web/classes/dicom_exams.php` | Auto-generated entity class (written manually) |
| `web/classes/dicom_series.php` | Auto-generated entity class (written manually) |
| `web/classes/dicom_images.php` | Auto-generated entity class (written manually) |
| `web/classes/dicom_shares.php` | Auto-generated entity class (written manually) |
| `web/classes/bootstrap_classes.php` | **Modified** — added 4 `require_once` lines |
| `web/ClassesEx.php` | **Modified** — appended 4 ClassEx classes |
| `web/modules/dicom/dicom.php` | Module class + all route handlers + helpers |
| `web/modules/dicom/dicom.info.yaml` | Module metadata |
| `web/modules/dicom/dicom.yaml` | Module routes + library definitions |
| `web/modules/dicom/dicom.zetem` | Module base template (minimal) |
| `web/modules/dicom/DicomParser.php` | Pure-PHP DICOM tag reader |
| `web/modules/dicom/DicomDirParser.php` | DICOMDIR index reader |
| `web/modules/dicom/DicomConverter.php` | DCMTK wrapper + GD thumbnails |
| `web/templates/content/dicom_list.zetem` | Exam list page template |
| `web/templates/content/dicom_upload.zetem` | Upload page template |
| `web/templates/content/dicom_viewer.zetem` | Viewer page template |
| `web/templates/content/dicom_shared_error.zetem` | Shared link error page |
| `web/css/dicom.css` | Module CSS (uses design system vars) |
| `web/js/dicom-upload.js` | Chunked upload JS |
| `web/js/dicom-viewer.js` | Thumbnail grid + lightbox JS |
| `sql/dicom.sql` | Raw SQL schema (import via msql.sh) |
| `data/dicom/.htaccess` | Blocks direct HTTP access to DICOM files |
| `data/dicom/uploads/` | Temp chunked upload directory (created) |
| `data/dicom/exams/` | Exam storage directory (created) |
| `data/dicom/tmp/` | ZIP scratch directory (created) |
| `config/settings.info.yaml` | **Modified** — module registered, permissions added, menu entry added |

### 18.4 Required Server Setup (one-time)

```bash
# Install DCMTK
apt install dcmtk

# Verify PHP extensions
php -m | grep -E 'zip|gd|pdo_mysql|yaml'

# Import DB schema
bash sql/msql.sh sql/dicom.sql

# Check data directory is writable by web server
chown -R www-data:www-data data/dicom/
```
