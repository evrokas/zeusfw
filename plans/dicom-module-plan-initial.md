# ZPMS DICOM Module — Implementation Plan

## 1. Overview

A module for uploading, storing, and viewing DICOM medical imaging exams. The system uses AJAX chunked uploads with progress, DCMTK for image conversion, thumbnail/full-size viewing grouped by series, keyboard navigation, and shareable exam links. All metadata is stored in MySQL.

The module intelligently discovers DICOM files regardless of file extension (many DICOM files have no extension at all — e.g. `IM000001`, `000032`). When a DICOMDIR index file is present (common in CD/DVD/ZIP exports), it is parsed first to efficiently resolve the entire study→series→image hierarchy in one pass, avoiding individual file scanning.

---

## 2. Module File Structure

```
modules/dicom/
├── dicom.module.yml              # Module config (storage paths, limits, DCMTK path)
├── dicom.install.php             # DB schema creation (runs once)
├── src/
│   ├── DicomManager.php          # Core: exam CRUD, file handling, sharing
│   ├── DicomParser.php           # Pure-PHP binary DICOM tag reader
│   ├── DicomDirParser.php        # DICOMDIR index reader (study→series→image tree)
│   └── DicomConverter.php        # DCMTK wrapper: dcm→png/jpg + thumbnails
├── controllers/
│   ├── UploadController.php      # AJAX chunked upload endpoint
│   ├── ExamListController.php    # Exam browser page
│   ├── ViewerController.php      # Series viewer page
│   ├── ImageController.php       # Serves images (auth-gated)
│   └── ShareController.php       # Public share link resolver
├── templates/
│   ├── upload.html.php           # Upload form with drag-drop + progress
│   ├── exam-list.html.php        # Grid/table of uploaded exams
│   └── viewer.html.php           # Series browser + full-size image overlay viewer
├── js/
│   ├── dicom-upload.js           # Chunked upload, progress bar, validation
│   └── dicom-viewer.js           # Gallery, image overlay, keyboard nav, zoom
└── css/
    └── dicom.css                 # Module-specific styles (extends design system)
```

### Filesystem Storage Layout

```
data/dicom/
├── uploads/                      # Temporary chunked upload assembly area
│   └── {upload_token}/
│       ├── chunks/               # Individual chunks during upload
│       └── assembled.zip|.dcm    # Assembled final file
├── exams/
│   └── {exam_id}/                # Numeric auto-increment ID
│       ├── original/             # Raw DICOM files (kept for reprocessing)
│       │   ├── DICOMDIR          # Index file (if present in upload)
│       │   ├── file001.dcm
│       │   ├── IM000032          # Extensionless DICOM (also supported)
│       │   └── CT/               # Subdirectories (common in DICOM exports)
│       │       └── ...
│       └── images/
│           └── {series_id}/      # DB series ID
│               ├── thumb/        # Thumbnails (e.g. 200px wide)
│               │   ├── 0001.jpg
│               │   └── 0002.jpg
│               └── full/         # Full-resolution converted images
│                   ├── 0001.png
│                   └── 0002.png
└── tmp/                          # ZIP extraction scratch space
```

---

## 3. MySQL Database Schema

```sql
-- ─── EXAMS ─────────────────────────────────────────────
CREATE TABLE dicom_exams (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    study_uid       VARCHAR(128) DEFAULT NULL,       -- DICOM StudyInstanceUID
    patient_name    VARCHAR(255) DEFAULT NULL,
    patient_id_dcm  VARCHAR(64)  DEFAULT NULL,       -- DICOM PatientID tag
    study_date      DATE         DEFAULT NULL,        -- from DICOM tag
    study_time      TIME         DEFAULT NULL,
    study_desc      VARCHAR(255) DEFAULT NULL,
    accession_no    VARCHAR(64)  DEFAULT NULL,
    modality        VARCHAR(16)  DEFAULT NULL,        -- CT, MR, XR, US, etc.
    file_count      INT UNSIGNED DEFAULT 0,           -- total .dcm files
    disk_size       BIGINT UNSIGNED DEFAULT 0,        -- bytes
    storage_path    VARCHAR(512) NOT NULL,             -- relative path under data/dicom/exams/
    status          ENUM('uploading','processing','ready','error') DEFAULT 'uploading',
    error_message   TEXT DEFAULT NULL,
    uploaded_by     INT UNSIGNED DEFAULT NULL,         -- FK to users table
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_study_uid (study_uid),
    INDEX idx_patient (patient_name, patient_id_dcm),
    INDEX idx_date (study_date),
    INDEX idx_status (status),
    INDEX idx_uploaded_by (uploaded_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SERIES ────────────────────────────────────────────
CREATE TABLE dicom_series (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id         INT UNSIGNED NOT NULL,
    series_uid      VARCHAR(128) DEFAULT NULL,        -- DICOM SeriesInstanceUID
    series_number   INT DEFAULT NULL,                 -- DICOM SeriesNumber
    series_desc     VARCHAR(255) DEFAULT NULL,
    modality        VARCHAR(16) DEFAULT NULL,
    frame_count     INT UNSIGNED DEFAULT 0,           -- number of images
    images_path     VARCHAR(512) DEFAULT NULL,         -- relative path to images folder
    status          ENUM('pending','converting','ready','error') DEFAULT 'pending',
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES dicom_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── INDIVIDUAL IMAGES (frames) ────────────────────────
CREATE TABLE dicom_images (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    series_id       INT UNSIGNED NOT NULL,
    instance_number INT DEFAULT 0,                    -- DICOM InstanceNumber (sort order)
    sop_instance_uid VARCHAR(128) DEFAULT NULL,
    dcm_filename    VARCHAR(255) NOT NULL,             -- original .dcm filename
    thumb_filename  VARCHAR(255) DEFAULT NULL,         -- thumbnail jpg
    full_filename   VARCHAR(255) DEFAULT NULL,         -- full-res png/jpg
    width           INT UNSIGNED DEFAULT NULL,
    height          INT UNSIGNED DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_series (series_id),
    INDEX idx_instance (series_id, instance_number),
    FOREIGN KEY (series_id) REFERENCES dicom_series(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── SHARE LINKS ───────────────────────────────────────
CREATE TABLE dicom_shares (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    exam_id     INT UNSIGNED NOT NULL,
    token       VARCHAR(64) NOT NULL UNIQUE,           -- random URL-safe token
    created_by  INT UNSIGNED DEFAULT NULL,
    expires_at  DATETIME DEFAULT NULL,                 -- NULL = no expiry
    view_count  INT UNSIGNED DEFAULT 0,
    is_active   TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_token (token),
    INDEX idx_exam (exam_id),
    FOREIGN KEY (exam_id) REFERENCES dicom_exams(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 4. Module Configuration — `dicom.module.yml`

```yaml
name: dicom
label: "DICOM Imaging"
version: "1.0.0"

settings:
  storage_base: "data/dicom"
  max_upload_size_mb: 500
  allowed_extensions: ["dcm", "zip", "gz"]
  chunk_size_bytes: 2097152          # 2 MB per AJAX chunk
  dcmtk_bin_path: "/usr/bin"         # where dcm2pnm, dcmdump live
  thumbnail_width: 200               # px
  full_image_format: "png"           # png or jpg
  thumb_image_format: "jpg"
  thumb_quality: 80
  share_default_expiry_days: 30
  max_concurrent_conversions: 4      # parallel dcm2pnm processes

permissions:
  upload:  ["admin", "doctor"]
  view:    ["admin", "doctor", "nurse"]
  share:   ["admin", "doctor"]
  delete:  ["admin"]
```

---

## 5. Core Components — Key Code

### 5.1 DicomParser.php — Pure-PHP DICOM Tag Reader

Reads binary DICOM headers to extract study/series/instance metadata. Handles Explicit & Implicit VR Little Endian (the most common transfer syntaxes).

```php
<?php
/**
 * Lightweight DICOM tag parser — reads essential metadata from .dcm file headers.
 * No external dependencies. Handles Explicit & Implicit VR Little Endian.
 */
class DicomParser {

    const TAGS = [
        '00080016' => 'sop_class_uid',
        '00080018' => 'sop_instance_uid',
        '00080020' => 'study_date',
        '00080030' => 'study_time',
        '00080050' => 'accession_number',
        '00080060' => 'modality',
        '00081030' => 'study_description',
        '0008103E' => 'series_description',
        '00100010' => 'patient_name',
        '00100020' => 'patient_id',
        '00100030' => 'patient_birth_date',
        '00100040' => 'patient_sex',
        '0020000D' => 'study_instance_uid',
        '0020000E' => 'series_instance_uid',
        '00200011' => 'series_number',
        '00200013' => 'instance_number',
        '00280010' => 'rows',
        '00280011' => 'columns',
        '00280100' => 'bits_allocated',
        '00280004' => 'photometric_interpretation',
    ];

    const SHORT_VR = [
        'AE','AS','AT','CS','DA','DS','DT','FL','FD','IS',
        'LO','LT','PN','SH','SL','SS','ST','TM','UI','UL','US'
    ];

    private $file;
    private $fh;
    private $explicit_vr = true;

    public function __construct($filepath) {
        $this->file = $filepath;
    }

    public function parse() {
        $result = [];
        $this->fh = fopen($this->file, 'rb');
        if (!$this->fh) return $result;

        fseek($this->fh, 128);
        $magic = fread($this->fh, 4);
        if ($magic !== 'DICM') {
            fseek($this->fh, 0); // try without preamble
        }

        $max_offset = min(filesize($this->file), 65536);

        while (ftell($this->fh) < $max_offset && !feof($this->fh)) {
            $tag_data = $this->readTag();
            if ($tag_data === false) break;

            list($tag_hex, $value, $vr) = $tag_data;

            if ($tag_hex === '00020010') {
                // Transfer Syntax UID — detect implicit VR
                $this->explicit_vr = (trim($value, " \0") !== '1.2.840.10008.1.2');
            }
            if ($tag_hex === '7FE00010') break; // pixel data — stop

            $tag_upper = strtoupper($tag_hex);
            if (isset(self::TAGS[$tag_upper])) {
                $result[self::TAGS[$tag_upper]] = trim($value, " \0");
            }
        }

        fclose($this->fh);
        return $result;
    }

    private function readTag() {
        $raw = fread($this->fh, 4);
        if (strlen($raw) < 4) return false;

        $group   = unpack('v', substr($raw, 0, 2))[1];
        $element = unpack('v', substr($raw, 2, 2))[1];
        $tag_hex = sprintf('%04X%04X', $group, $element);

        $is_meta  = ($group === 0x0002);
        $explicit = $is_meta || $this->explicit_vr;
        $vr = '';

        if ($explicit) {
            $vr = fread($this->fh, 2);
            if (strlen($vr) < 2) return false;

            if (in_array($vr, self::SHORT_VR)) {
                $length = unpack('v', fread($this->fh, 2))[1];
            } else {
                fread($this->fh, 2); // reserved
                $length = unpack('V', fread($this->fh, 4))[1];
            }
        } else {
            $length = unpack('V', fread($this->fh, 4))[1];
        }

        if ($length === 0xFFFFFFFF || $length > 65536) return false;

        $value = ($length > 0) ? fread($this->fh, $length) : '';

        // Unpack numeric VRs
        if ($vr === 'US' && $length === 2)      $value = (string)unpack('v', $value)[1];
        elseif ($vr === 'UL' && $length === 4)   $value = (string)unpack('V', $value)[1];
        elseif ($vr === 'SS' && $length === 2)   $value = (string)unpack('s', $value)[1];
        elseif ($vr === 'SL' && $length === 4)   $value = (string)unpack('l', $value)[1];

        return [$tag_hex, $value, $vr];
    }

    public static function isDicom($filepath) {
        $fh = fopen($filepath, 'rb');
        if (!$fh) return false;
        fseek($fh, 128);
        $magic = fread($fh, 4);
        fclose($fh);
        if ($magic === 'DICM') return true;
        // Fallback: check for group 0002 or 0008 at byte 0
        $fh = fopen($filepath, 'rb');
        $first = fread($fh, 2);
        fclose($fh);
        $group = unpack('v', $first)[1];
        return ($group === 0x0002 || $group === 0x0008);
    }
}
```

### 5.2 DicomDirParser.php — DICOMDIR Index Reader

DICOMDIR is a special DICOM file that acts as a directory/manifest for a DICOM dataset. It contains a hierarchical record structure: Patient → Study → Series → Image, with each Image record pointing to a relative file path. Parsing DICOMDIR first avoids scanning and individually parsing every file in a large dataset.

```php
<?php
/**
 * Parses a DICOMDIR file to extract the study→series→image hierarchy.
 * Returns a structured tree with file paths for each image.
 * Falls back gracefully — caller should use file-scanning if this returns empty.
 */
class DicomDirParser {

    // DICOMDIR-specific tags
    const TAG_DIRECTORY_RECORD_TYPE  = '00041430'; // CS: PATIENT, STUDY, SERIES, IMAGE
    const TAG_REF_FILE_ID           = '00041500'; // CS: path components (backslash-separated)
    const TAG_PATIENT_NAME          = '00100010';
    const TAG_PATIENT_ID            = '00100020';
    const TAG_STUDY_INSTANCE_UID    = '0020000D';
    const TAG_STUDY_DATE            = '00080020';
    const TAG_STUDY_DESCRIPTION     = '00081030';
    const TAG_MODALITY              = '00080060';
    const TAG_SERIES_INSTANCE_UID   = '0020000E';
    const TAG_SERIES_NUMBER         = '00200011';
    const TAG_SERIES_DESCRIPTION    = '0008103E';
    const TAG_INSTANCE_NUMBER       = '00200013';

    private $base_dir;  // directory containing the DICOMDIR file

    public function __construct($dicomdir_path) {
        $this->base_dir = dirname($dicomdir_path);
        $this->file = $dicomdir_path;
    }

    /**
     * Parse the DICOMDIR and return structured tree.
     *
     * Returns: [
     *   'patient_name' => '...',
     *   'patient_id'   => '...',
     *   'studies' => [
     *     [
     *       'study_uid' => '...', 'study_date' => '...', 'study_desc' => '...',
     *       'series' => [
     *         [
     *           'series_uid' => '...', 'series_number' => ..., 'series_desc' => '...',
     *           'modality' => '...',
     *           'images' => [
     *             ['instance_number' => ..., 'file_path' => '/absolute/path/to/file.dcm'],
     *             ...
     *           ]
     *         ], ...
     *       ]
     *     ], ...
     *   ]
     * ]
     */
    public function parse() {
        // Use dcmdump if available for reliable parsing, otherwise use DicomParser
        $records = $this->extractRecords();
        if (empty($records)) return null;

        return $this->buildTree($records);
    }

    /**
     * Extract flat list of directory records using DicomParser line-by-line.
     * DICOMDIR is a valid DICOM file — we parse its tags, looking for
     * sequences of DirectoryRecordType + associated data tags.
     */
    private function extractRecords() {
        $records = [];
        $current = [];

        // Use dcmdump for reliable DICOMDIR parsing (handles sequences properly)
        $dcmdump = $this->findDcmdump();
        if ($dcmdump) {
            return $this->extractWithDcmdump($dcmdump);
        }

        // Fallback: basic tag scanning (works for simple DICOMDIR files)
        return $this->extractWithParser();
    }

    /**
     * Parse DICOMDIR using dcmdump CLI tool.
     * dcmdump outputs human-readable tag listings that are easier to parse
     * for the nested sequence structure in DICOMDIR files.
     */
    private function extractWithDcmdump($dcmdump_bin) {
        $cmd = escapeshellarg($dcmdump_bin) . ' +L +P 00041430 +P 00041500'
             . ' +P 00100010 +P 00100020 +P 0020000d +P 00080020'
             . ' +P 00081030 +P 00080060 +P 0020000e +P 00200011'
             . ' +P 0008103e +P 00200013'
             . ' ' . escapeshellarg($this->file) . ' 2>/dev/null';

        exec($cmd, $lines, $code);
        if ($code !== 0 || empty($lines)) return [];

        $records = [];
        $current = [];

        foreach ($lines as $line) {
            // dcmdump output format: (gggg,eeee) VR [value] # ... name
            if (preg_match('/\(([0-9a-f]{4}),([0-9a-f]{4})\)\s+\w+\s+\[([^\]]*)\]/', $line, $m)) {
                $tag = strtoupper($m[1] . $m[2]);
                $val = trim($m[3]);

                if ($tag === '00041430') {
                    // New record starts — save previous
                    if (!empty($current)) $records[] = $current;
                    $current = ['type' => $val];
                } else {
                    $current[$tag] = $val;
                }
            }
        }
        if (!empty($current)) $records[] = $current;

        return $records;
    }

    /**
     * Fallback: parse DICOMDIR with DicomParser.
     * This reads the DICOMDIR as a regular DICOM file. It won't handle
     * deeply nested sequences perfectly but works for typical CD exports.
     */
    private function extractWithParser() {
        $fh = fopen($this->file, 'rb');
        if (!$fh) return [];

        fseek($fh, 128);
        $magic = fread($fh, 4);
        if ($magic !== 'DICM') fseek($fh, 0);

        $records = [];
        $current = [];
        $tags_of_interest = [
            self::TAG_DIRECTORY_RECORD_TYPE, self::TAG_REF_FILE_ID,
            self::TAG_PATIENT_NAME, self::TAG_PATIENT_ID,
            self::TAG_STUDY_INSTANCE_UID, self::TAG_STUDY_DATE,
            self::TAG_STUDY_DESCRIPTION, self::TAG_MODALITY,
            self::TAG_SERIES_INSTANCE_UID, self::TAG_SERIES_NUMBER,
            self::TAG_SERIES_DESCRIPTION, self::TAG_INSTANCE_NUMBER,
        ];

        $size = filesize($this->file);
        while (ftell($fh) < $size && !feof($fh)) {
            $raw = fread($fh, 4);
            if (strlen($raw) < 4) break;

            $group   = unpack('v', substr($raw, 0, 2))[1];
            $element = unpack('v', substr($raw, 2, 2))[1];
            $tag_hex = sprintf('%04X%04X', $group, $element);

            // Skip sequence delimiters and items
            if ($group === 0xFFFE) {
                $len = unpack('V', fread($fh, 4))[1];
                if ($len !== 0xFFFFFFFF && $len > 0) fseek($fh, $len, SEEK_CUR);
                continue;
            }

            // Read VR + length (explicit VR assumed for DICOMDIR)
            $vr = fread($fh, 2);
            if (strlen($vr) < 2) break;

            if (in_array($vr, DicomParser::SHORT_VR)) {
                $length = unpack('v', fread($fh, 2))[1];
            } else {
                fread($fh, 2);
                $length = unpack('V', fread($fh, 4))[1];
            }

            if ($length === 0xFFFFFFFF || $length > 65536) continue;

            $value = ($length > 0) ? trim(fread($fh, $length), " \0") : '';

            if ($tag_hex === self::TAG_DIRECTORY_RECORD_TYPE) {
                if (!empty($current)) $records[] = $current;
                $current = ['type' => $value];
            } elseif (in_array($tag_hex, $tags_of_interest)) {
                $current[$tag_hex] = $value;
            }
        }
        if (!empty($current)) $records[] = $current;

        fclose($fh);
        return $records;
    }

    /**
     * Build hierarchical tree from flat record list.
     */
    private function buildTree($records) {
        $result = [
            'patient_name' => '',
            'patient_id'   => '',
            'studies'       => [],
        ];

        $current_study  = null;
        $current_series = null;

        foreach ($records as $rec) {
            $type = strtoupper(trim($rec['type'] ?? ''));

            switch ($type) {
                case 'PATIENT':
                    $result['patient_name'] = $rec[self::TAG_PATIENT_NAME] ?? '';
                    $result['patient_id']   = $rec[self::TAG_PATIENT_ID] ?? '';
                    break;

                case 'STUDY':
                    // Save previous study's last series
                    if ($current_study !== null && $current_series !== null) {
                        $current_study['series'][] = $current_series;
                        $current_series = null;
                    }
                    if ($current_study !== null) {
                        $result['studies'][] = $current_study;
                    }
                    $current_study = [
                        'study_uid'  => $rec[self::TAG_STUDY_INSTANCE_UID] ?? '',
                        'study_date' => $rec[self::TAG_STUDY_DATE] ?? '',
                        'study_desc' => $rec[self::TAG_STUDY_DESCRIPTION] ?? '',
                        'series'     => [],
                    ];
                    break;

                case 'SERIES':
                    if ($current_series !== null && $current_study !== null) {
                        $current_study['series'][] = $current_series;
                    }
                    $current_series = [
                        'series_uid'    => $rec[self::TAG_SERIES_INSTANCE_UID] ?? '',
                        'series_number' => $rec[self::TAG_SERIES_NUMBER] ?? '',
                        'series_desc'   => $rec[self::TAG_SERIES_DESCRIPTION] ?? '',
                        'modality'      => $rec[self::TAG_MODALITY] ?? '',
                        'images'        => [],
                    ];
                    break;

                case 'IMAGE':
                    if ($current_series !== null) {
                        // ReferencedFileID uses backslashes as path separator
                        $ref_file = $rec[self::TAG_REF_FILE_ID] ?? '';
                        $rel_path = str_replace('\\', DIRECTORY_SEPARATOR, $ref_file);
                        $abs_path = $this->base_dir . DIRECTORY_SEPARATOR . $rel_path;

                        $current_series['images'][] = [
                            'instance_number' => (int)($rec[self::TAG_INSTANCE_NUMBER] ?? 0),
                            'file_path'       => $abs_path,
                            'rel_path'        => $rel_path,
                        ];
                    }
                    break;
            }
        }

        // Flush remaining
        if ($current_series !== null && $current_study !== null) {
            $current_study['series'][] = $current_series;
        }
        if ($current_study !== null) {
            $result['studies'][] = $current_study;
        }

        return (!empty($result['studies'])) ? $result : null;
    }

    /**
     * Find a DICOMDIR file in a directory (case-insensitive).
     */
    public static function findDicomDir($dir) {
        // Common names: DICOMDIR, dicomdir, DICOMDIR.
        $candidates = ['DICOMDIR', 'dicomdir', 'Dicomdir'];
        foreach ($candidates as $name) {
            $path = $dir . '/' . $name;
            if (file_exists($path)) return $path;
        }
        // Scan top-level for any case variation
        $files = scandir($dir);
        foreach ($files as $f) {
            if (strtoupper($f) === 'DICOMDIR') return $dir . '/' . $f;
        }
        return null;
    }

    private function findDcmdump() {
        $paths = ['/usr/bin/dcmdump', '/usr/local/bin/dcmdump'];
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        exec('which dcmdump 2>/dev/null', $out, $code);
        return ($code === 0 && !empty($out[0])) ? $out[0] : null;
    }
}
```

### 5.3 DicomConverter.php — DCMTK Wrapper

Converts `.dcm` files to viewable images using `dcm2pnm` and creates thumbnails with GD.

```php
<?php
class DicomConverter {

    private $dcmtk_path;
    private $thumb_width;
    private $thumb_quality;
    private $full_format;

    public function __construct($config) {
        $this->dcmtk_path   = rtrim($config['dcmtk_bin_path'], '/');
        $this->thumb_width   = $config['thumbnail_width'] ?? 200;
        $this->thumb_quality = $config['thumb_quality'] ?? 80;
        $this->full_format   = $config['full_image_format'] ?? 'png';
    }

    /**
     * Verify dcmtk is installed and accessible.
     */
    public function checkDcmtk() {
        $bin = $this->dcmtk_path . '/dcm2pnm';
        if (!file_exists($bin)) {
            exec('which dcm2pnm 2>/dev/null', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $this->dcmtk_path = dirname($out[0]);
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     * Convert a single .dcm to full-resolution image.
     */
    public function convertToImage($dcm_path, $output_path) {
        $bin = escapeshellarg($this->dcmtk_path . '/dcm2pnm');
        $in  = escapeshellarg($dcm_path);
        $out = escapeshellarg($output_path);

        // +Wm = apply modality LUT (proper windowing)
        $format_flag = ($this->full_format === 'jpg') ? '+oj' : '+op';
        $cmd = "$bin $format_flag +Wm $in $out 2>&1";
        exec($cmd, $output, $code);

        if ($code !== 0 || !file_exists($output_path)) {
            // Fallback: try with histogram-based auto-windowing
            $cmd = "$bin $format_flag +Wh $in $out 2>&1";
            exec($cmd, $output, $code);
        }

        return ($code === 0 && file_exists($output_path)) ? $output_path : false;
    }

    /**
     * Create a thumbnail from a full-size image using GD.
     */
    public function createThumbnail($source_path, $thumb_path) {
        $info = getimagesize($source_path);
        if (!$info) return false;

        $src_w = $info[0];
        $src_h = $info[1];
        $mime  = $info['mime'];
        $new_w = $this->thumb_width;
        $new_h = (int)round($src_h * ($new_w / $src_w));

        switch ($mime) {
            case 'image/png':  $src = imagecreatefrompng($source_path);  break;
            case 'image/jpeg': $src = imagecreatefromjpeg($source_path); break;
            default: return false;
        }

        $dst = imagecreatetruecolor($new_w, $new_h);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $new_w, $new_h, $src_w, $src_h);

        $dir = dirname($thumb_path);
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $result = imagejpeg($dst, $thumb_path, $this->thumb_quality);
        imagedestroy($src);
        imagedestroy($dst);
        return $result;
    }

    /**
     * Process all DICOM files for an exam: parse → group by series → convert → DB.
     * Strategy: try DICOMDIR first (fast, pre-indexed), fall back to file scanning.
     */
    public function processExam($exam_id, $dcm_dir, $images_base_dir, $db) {

        // Strategy A: Try DICOMDIR first (fast path)
        $series_map = $this->processViaDicomDir($dcm_dir);

        // Strategy B: Fall back to scanning all files individually
        if (empty($series_map)) {
            $series_map = $this->processViaFileScan($dcm_dir);
        }

        // Phase 2: Per series — DB insert → convert → thumbnails
        foreach ($series_map as $series_uid => $series_data) {
            $tags  = $series_data['tags'];
            $files = $series_data['files'];

            // Sort by instance number for correct slice ordering
            usort($files, function($a, $b) {
                return $a['instance_number'] - $b['instance_number'];
            });

            $stmt = $db->prepare("INSERT INTO dicom_series
                (exam_id, series_uid, series_number, series_desc, modality, frame_count, status)
                VALUES (?, ?, ?, ?, ?, ?, 'converting')");
            $stmt->execute([
                $exam_id, $series_uid,
                $tags['series_number'] ?? null,
                $tags['series_description'] ?? null,
                $tags['modality'] ?? null,
                count($files),
            ]);
            $series_id = $db->lastInsertId();

            $full_dir  = $images_base_dir . '/' . $series_id . '/full';
            $thumb_dir = $images_base_dir . '/' . $series_id . '/thumb';
            mkdir($full_dir, 0755, true);
            mkdir($thumb_dir, 0755, true);

            $rel_path = 'exams/' . $exam_id . '/images/' . $series_id;
            $db->prepare("UPDATE dicom_series SET images_path = ? WHERE id = ?")
               ->execute([$rel_path, $series_id]);

            // Convert each DICOM file
            $frame_num = 0;
            foreach ($files as $file_info) {
                $frame_num++;
                $frame_str = str_pad($frame_num, 4, '0', STR_PAD_LEFT);
                $ext = $this->full_format;

                $full_filename  = $frame_str . '.' . $ext;
                $thumb_filename = $frame_str . '.jpg';
                $full_path  = $full_dir  . '/' . $full_filename;
                $thumb_path = $thumb_dir . '/' . $thumb_filename;

                $converted = $this->convertToImage($file_info['path'], $full_path);
                if ($converted) {
                    $this->createThumbnail($full_path, $thumb_path);
                    $dims = getimagesize($full_path);

                    $stmt = $db->prepare("INSERT INTO dicom_images
                        (series_id, instance_number, sop_instance_uid, dcm_filename,
                         thumb_filename, full_filename, width, height)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $series_id,
                        $file_info['instance_number'],
                        $file_info['sop_instance_uid'],
                        basename($file_info['path']),
                        $thumb_filename, $full_filename,
                        $dims[0] ?? null, $dims[1] ?? null,
                    ]);
                }
            }

            $db->prepare("UPDATE dicom_series SET status = 'ready' WHERE id = ?")
               ->execute([$series_id]);
        }

        return $series_map;
    }

    /**
     * Strategy A: Parse DICOMDIR to get pre-indexed series→image hierarchy.
     * Returns series_map compatible with processExam's Phase 2, or empty array.
     */
    private function processViaDicomDir($dcm_dir) {
        $dicomdir_path = DicomDirParser::findDicomDir($dcm_dir);
        if (!$dicomdir_path) return [];

        $parser = new DicomDirParser($dicomdir_path);
        $tree = $parser->parse();
        if (!$tree || empty($tree['studies'])) return [];

        // Convert DICOMDIR tree into the series_map format
        $series_map = [];
        foreach ($tree['studies'] as $study) {
            foreach ($study['series'] as $series) {
                $series_uid = $series['series_uid'] ?: 'unknown_' . count($series_map);

                // Verify referenced files actually exist
                $valid_images = [];
                foreach ($series['images'] as $img) {
                    if (file_exists($img['file_path'])) {
                        $valid_images[] = [
                            'path' => $img['file_path'],
                            'instance_number' => $img['instance_number'],
                            'sop_instance_uid' => '',
                        ];
                    }
                }
                if (empty($valid_images)) continue;

                $series_map[$series_uid] = [
                    'tags' => [
                        'series_instance_uid' => $series['series_uid'],
                        'series_number'       => $series['series_number'],
                        'series_description'  => $series['series_desc'],
                        'modality'            => $series['modality'],
                        'study_instance_uid'  => $study['study_uid'],
                        'study_date'          => $study['study_date'],
                        'study_description'   => $study['study_desc'],
                        'patient_name'        => $tree['patient_name'],
                        'patient_id'          => $tree['patient_id'],
                    ],
                    'files' => $valid_images,
                ];
            }
        }

        return $series_map;
    }

    /**
     * Strategy B: Scan all files, parse each individually, group by series.
     * Used as fallback when DICOMDIR is absent or incomplete.
     */
    private function processViaFileScan($dcm_dir) {
        $dcm_files = $this->findDcmFiles($dcm_dir);
        $series_map = [];

        foreach ($dcm_files as $dcm_file) {
            $parser = new DicomParser($dcm_file);
            $tags = $parser->parse();

            $series_uid = $tags['series_instance_uid'] ?? 'unknown_series';
            if (!isset($series_map[$series_uid])) {
                $series_map[$series_uid] = ['tags' => $tags, 'files' => []];
            }
            $series_map[$series_uid]['files'][] = [
                'path' => $dcm_file,
                'instance_number' => (int)($tags['instance_number'] ?? 0),
                'sop_instance_uid' => $tags['sop_instance_uid'] ?? '',
            ];
        }

        return $series_map;
    }

    /**
     * Recursively find DICOM files regardless of extension.
     * Handles: .dcm files, extensionless files, and files with non-standard
     * extensions that are actually valid DICOM (verified via magic bytes).
     * Skips known non-DICOM files (DICOMDIR, .jpg, .png, .xml, .txt, etc.)
     */
    private function findDcmFiles($dir) {
        $result = [];
        $skip_extensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'tiff',
                            'xml', 'txt', 'html', 'pdf', 'csv', 'json',
                            'zip', 'gz', 'tar', 'log', 'ini', 'yml', 'yaml'];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;

            $name = $file->getFilename();
            $ext  = strtolower($file->getExtension());

            // Skip DICOMDIR (handled separately)
            if (strtoupper($name) === 'DICOMDIR') continue;

            // Skip known non-DICOM extensions
            if (in_array($ext, $skip_extensions)) continue;

            if ($ext === 'dcm') {
                // .dcm files are always included
                $result[] = $file->getPathname();
            } else {
                // Extensionless or unknown extension — verify DICOM magic bytes
                if (DicomParser::isDicom($file->getPathname())) {
                    $result[] = $file->getPathname();
                }
            }
        }
        return $result;
    }

    /**
     * Public wrapper for findDcmFiles (used by UploadController for file counting).
     */
    public function findDcmFilesPublic($dir) {
        return $this->findDcmFiles($dir);
    }
}
```

### 5.4 UploadController.php — Chunked AJAX Upload

```php
<?php
/**
 * Endpoints (routed via front controller):
 *   POST /dicom/upload/init     → initialize upload session
 *   POST /dicom/upload/chunk    → receive a chunk
 *   POST /dicom/upload/finalize → assemble, extract, process
 */
class UploadController {

    private $db;
    private $config;
    private $storage_base;

    public function __construct($db, $config) {
        $this->db = $db;
        $this->config = $config;
        $this->storage_base = rtrim($config['storage_base'], '/');
    }

    /** POST /dicom/upload/init */
    public function init() {
        $filename     = $_POST['filename'] ?? '';
        $filesize     = (int)($_POST['filesize'] ?? 0);
        $total_chunks = (int)($_POST['total_chunks'] ?? 1);

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $allowed = $this->config['allowed_extensions'] ?? ['dcm', 'zip', 'gz'];
        if (!in_array($ext, $allowed)) {
            $this->json(['error' => 'File type not allowed'], 400);
            return;
        }

        $max_bytes = ($this->config['max_upload_size_mb'] ?? 500) * 1024 * 1024;
        if ($filesize > $max_bytes) {
            $this->json(['error' => 'File too large'], 400);
            return;
        }

        $token = bin2hex(random_bytes(16));
        $upload_dir = $this->storage_base . '/uploads/' . $token . '/chunks';
        mkdir($upload_dir, 0755, true);

        $session = [
            'token' => $token, 'filename' => $filename,
            'filesize' => $filesize, 'total_chunks' => $total_chunks,
            'received' => 0, 'created_at' => date('Y-m-d H:i:s'),
        ];
        file_put_contents(
            $this->storage_base . '/uploads/' . $token . '/session.json',
            json_encode($session)
        );

        $this->json(['upload_token' => $token, 'chunk_size' => $this->config['chunk_size_bytes']]);
    }

    /** POST /dicom/upload/chunk */
    public function chunk() {
        $token = $_POST['upload_token'] ?? '';
        $index = (int)($_POST['chunk_index'] ?? 0);

        $session_file = $this->storage_base . '/uploads/' . $token . '/session.json';
        if (!file_exists($session_file)) {
            $this->json(['error' => 'Invalid upload token'], 400);
            return;
        }
        if (!isset($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Chunk upload failed'], 400);
            return;
        }

        $chunk_path = $this->storage_base . '/uploads/' . $token . '/chunks/'
                    . str_pad($index, 6, '0', STR_PAD_LEFT);
        move_uploaded_file($_FILES['chunk']['tmp_name'], $chunk_path);

        $session = json_decode(file_get_contents($session_file), true);
        $session['received']++;
        file_put_contents($session_file, json_encode($session));

        $this->json([
            'received' => $session['received'],
            'total'    => $session['total_chunks'],
            'complete' => ($session['received'] >= $session['total_chunks']),
        ]);
    }

    /** POST /dicom/upload/finalize */
    public function finalize() {
        $token = $_POST['upload_token'] ?? '';
        $base_dir = $this->storage_base . '/uploads/' . $token;
        $session_file = $base_dir . '/session.json';

        if (!file_exists($session_file)) {
            $this->json(['error' => 'Invalid upload token'], 400);
            return;
        }
        $session = json_decode(file_get_contents($session_file), true);

        // Assemble chunks
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

        // Create exam DB record
        $stmt = $this->db->prepare("INSERT INTO dicom_exams
            (storage_path, status, uploaded_by, disk_size) VALUES (?, 'processing', ?, ?)");
        $stmt->execute(['', $_SESSION['user_id'] ?? null, filesize($assembled)]);
        $exam_id = $this->db->lastInsertId();

        $exam_dir   = $this->storage_base . '/exams/' . $exam_id;
        $orig_dir   = $exam_dir . '/original';
        $images_dir = $exam_dir . '/images';
        mkdir($orig_dir, 0755, true);
        mkdir($images_dir, 0755, true);

        $this->db->prepare("UPDATE dicom_exams SET storage_path = ? WHERE id = ?")
             ->execute(['exams/' . $exam_id, $exam_id]);

        // Extract ZIP or copy DCM
        if ($ext === 'zip') {
            $zip = new ZipArchive();
            if ($zip->open($assembled) === true) {
                $zip->extractTo($orig_dir);
                $zip->close();
            } else {
                $this->updateStatus($exam_id, 'error', 'ZIP extraction failed');
                $this->json(['error' => 'ZIP extraction failed'], 500);
                return;
            }
        } else {
            copy($assembled, $orig_dir . '/' . $session['filename']);
        }

        // Verify DCMTK
        $converter = new DicomConverter($this->config);
        if (!$converter->checkDcmtk()) {
            $this->updateStatus($exam_id, 'error', 'DCMTK not found');
            $this->json(['error' => 'DCMTK not installed on server'], 500);
            return;
        }

        // Parse study-level metadata from first file
        $first_dcm = $this->findFirstDcm($orig_dir);
        if ($first_dcm) {
            $parser = new DicomParser($first_dcm);
            $tags = $parser->parse();
            $this->updateExamMeta($exam_id, $tags);
        }

        // Count files
        $dcm_count = count($converter->findDcmFilesPublic($orig_dir));
        $this->db->prepare("UPDATE dicom_exams SET file_count = ? WHERE id = ?")
             ->execute([$dcm_count, $exam_id]);

        // Process: group series → convert → thumbnails → DB rows
        $converter->processExam($exam_id, $orig_dir, $images_dir, $this->db);

        $this->updateStatus($exam_id, 'ready');
        $this->deleteDirectory($base_dir); // clean up temp upload

        $this->json([
            'exam_id'  => $exam_id,
            'status'   => 'ready',
            'redirect' => '/dicom/view/' . $exam_id,
        ]);
    }

    // ─── helpers ───

    private function json($data, $code = 200) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    private function updateStatus($exam_id, $status, $error = null) {
        $this->db->prepare("UPDATE dicom_exams SET status = ?, error_message = ? WHERE id = ?")
             ->execute([$status, $error, $exam_id]);
    }

    private function updateExamMeta($exam_id, $tags) {
        $study_date = null;
        if (!empty($tags['study_date']) && strlen($tags['study_date']) === 8) {
            $study_date = substr($tags['study_date'], 0, 4) . '-'
                        . substr($tags['study_date'], 4, 2) . '-'
                        . substr($tags['study_date'], 6, 2);
        }
        $this->db->prepare("UPDATE dicom_exams SET
            study_uid=?, patient_name=?, patient_id_dcm=?, study_date=?,
            study_desc=?, accession_no=?, modality=? WHERE id=?")
             ->execute([
                 $tags['study_instance_uid'] ?? null, $tags['patient_name'] ?? null,
                 $tags['patient_id'] ?? null, $study_date,
                 $tags['study_description'] ?? null, $tags['accession_number'] ?? null,
                 $tags['modality'] ?? null, $exam_id,
             ]);
    }

    private function findFirstDcm($dir) {
        $iter = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iter as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'dcm') return $f->getPathname();
            if ($f->isFile() && $f->getExtension() === '' && DicomParser::isDicom($f->getPathname())) return $f->getPathname();
        }
        return null;
    }

    private function deleteDirectory($dir) {
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
}
```

### 5.5 JavaScript — Chunked Upload with Progress

```javascript
/**
 * dicom-upload.js — Vanilla JS chunked AJAX uploader with progress bar.
 */
const DicomUploader = {

    chunkSize: 2 * 1024 * 1024, // 2 MB default, confirmed by server
    baseUrl: '/dicom/upload',

    async upload(file, callbacks = {}) {
        const { onProgress, onStatus, onComplete, onError } = callbacks;

        try {
            // 1. Init
            if (onStatus) onStatus('Initializing upload…');
            const initResp = await this.postJSON(this.baseUrl + '/init', {
                filename: file.name, filesize: file.size,
                total_chunks: Math.ceil(file.size / this.chunkSize),
            });
            const { upload_token, chunk_size } = initResp;
            if (chunk_size) this.chunkSize = chunk_size;

            // 2. Send chunks
            const totalChunks = Math.ceil(file.size / this.chunkSize);
            if (onStatus) onStatus('Uploading ' + totalChunks + ' chunks…');

            for (let i = 0; i < totalChunks; i++) {
                const start = i * this.chunkSize;
                const end   = Math.min(start + this.chunkSize, file.size);
                const blob  = file.slice(start, end);

                const fd = new FormData();
                fd.append('upload_token', upload_token);
                fd.append('chunk_index', i);
                fd.append('chunk', blob);

                await this.post(this.baseUrl + '/chunk', fd);
                if (onProgress) onProgress(Math.round(((i + 1) / totalChunks) * 90));
            }

            // 3. Finalize — server assembles + processes
            if (onStatus) onStatus('Processing DICOM exam…');
            if (onProgress) onProgress(92);

            const result = await this.postJSON(this.baseUrl + '/finalize', {
                upload_token: upload_token,
            });
            if (onProgress) onProgress(100);
            if (onComplete) onComplete(result);
            return result;

        } catch (err) {
            if (onError) onError(err.message || 'Upload failed');
            throw err;
        }
    },

    async postJSON(url, data) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(data)) fd.append(k, v);
        return this.post(url, fd);
    },

    async post(url, formData) {
        const resp = await fetch(url, { method: 'POST', body: formData });
        const json = await resp.json();
        if (!resp.ok) throw new Error(json.error || 'Request failed');
        return json;
    }
};

// ─── UI Bindings ───────────────────────────────────────

document.addEventListener('DOMContentLoaded', function() {
    const dropzone  = document.getElementById('upload-dropzone');
    const fileInput = document.getElementById('upload-file-input');
    const progress  = document.getElementById('upload-progress');
    const pctEl     = document.getElementById('upload-pct');
    const barEl     = document.getElementById('upload-bar');
    const statusEl  = document.getElementById('upload-status');
    const uploadBtn = document.getElementById('upload-btn');
    const fileInfo  = document.getElementById('upload-file-info');

    let selectedFile = null;

    // Drag-drop
    dropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropzone.classList.add('drag-over');
    });
    dropzone.addEventListener('dragleave', function() {
        dropzone.classList.remove('drag-over');
    });
    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropzone.classList.remove('drag-over');
        if (e.dataTransfer.files.length > 0) selectFile(e.dataTransfer.files[0]);
    });

    // File input
    fileInput.addEventListener('change', function() {
        if (fileInput.files.length > 0) selectFile(fileInput.files[0]);
    });

    function selectFile(file) {
        selectedFile = file;
        fileInfo.textContent = file.name + ' (' + formatBytes(file.size) + ')';
        uploadBtn.disabled = false;
    }

    // Upload button
    uploadBtn.addEventListener('click', function() {
        if (!selectedFile) return;
        uploadBtn.disabled = true;
        progress.style.display = 'block';

        DicomUploader.upload(selectedFile, {
            onProgress: function(pct) {
                barEl.style.width = pct + '%';
                pctEl.textContent = pct + '%';
            },
            onStatus: function(msg) {
                statusEl.textContent = msg;
            },
            onComplete: function(result) {
                statusEl.textContent = 'Complete! Redirecting…';
                window.location.href = result.redirect;
            },
            onError: function(msg) {
                statusEl.textContent = 'Error: ' + msg;
                uploadBtn.disabled = false;
            }
        });
    });

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }
});
```

### 5.6 JavaScript — Series Viewer with Image Overlay

```javascript
/**
 * dicom-viewer.js — Thumbnail grid, series tabs, full-size image overlay, keyboard nav.
 */
const DicomViewer = {

    currentSeries: null,
    currentIndex: 0,
    images: [],
    lightboxEl: null,

    init(examData) {
        // examData = { series: [{ id, name, images: [{thumb_url, full_url}] }] }
        this.examData = examData;
        this.buildSeriesTabs();
        this.buildLightbox();
        this.bindKeys();
        if (examData.series.length > 0) this.selectSeries(0);
    },

    buildSeriesTabs() {
        var container = document.getElementById('series-tabs');
        var self = this;
        this.examData.series.forEach(function(series, idx) {
            var tab = document.createElement('button');
            tab.className = 'filter-chip';
            tab.textContent = series.name || ('Series ' + (idx + 1));
            tab.dataset.index = idx;
            tab.addEventListener('click', function() { self.selectSeries(idx); });
            container.appendChild(tab);
        });
    },

    selectSeries(idx) {
        this.currentSeries = idx;
        this.images = this.examData.series[idx].images;
        document.querySelectorAll('#series-tabs .filter-chip').forEach(function(el, i) {
            el.classList.toggle('active', i === idx);
        });
        this.renderGrid();
    },

    renderGrid() {
        var grid = document.getElementById('thumb-grid');
        grid.innerHTML = '';
        var self = this;
        this.images.forEach(function(img, idx) {
            var cell = document.createElement('div');
            cell.className = 'dicom-thumb';
            cell.innerHTML =
                '<img src="' + img.thumb_url + '" alt="Frame ' + (idx+1) + '" loading="lazy">' +
                '<span class="dicom-thumb-label">' + (idx+1) + '</span>';
            cell.addEventListener('click', function() { self.openLightbox(idx); });
            grid.appendChild(cell);
        });
    },

    buildLightbox() {
        var lb = document.createElement('div');
        lb.className = 'dicom-lightbox';
        lb.id = 'dicom-lightbox';
        lb.innerHTML =
            '<div class="dicom-lightbox-backdrop"></div>' +
            '<div class="dicom-lightbox-content">' +
                '<button class="dicom-lightbox-close" title="Close">&times;</button>' +
                '<button class="dicom-lightbox-prev" title="Previous (←)">&#8249;</button>' +
                '<div class="dicom-lightbox-img-wrap">' +
                    '<img id="dicom-lightbox-img" src="" alt="">' +
                '</div>' +
                '<button class="dicom-lightbox-next" title="Next (→)">&#8250;</button>' +
                '<div class="dicom-lightbox-info">' +
                    '<span id="lightbox-counter"></span>' +
                '</div>' +
            '</div>';
        document.body.appendChild(lb);
        this.lightboxEl = lb;

        var self = this;
        lb.querySelector('.dicom-lightbox-backdrop').addEventListener('click', function() { self.closeLightbox(); });
        lb.querySelector('.dicom-lightbox-close').addEventListener('click', function() { self.closeLightbox(); });
        lb.querySelector('.dicom-lightbox-prev').addEventListener('click', function() { self.navigate(-1); });
        lb.querySelector('.dicom-lightbox-next').addEventListener('click', function() { self.navigate(1); });
    },

    openLightbox(idx) {
        this.currentIndex = idx;
        this.updateLightboxImage();
        this.lightboxEl.classList.add('active');
        document.body.style.overflow = 'hidden';
    },

    closeLightbox() {
        this.lightboxEl.classList.remove('active');
        document.body.style.overflow = '';
    },

    navigate(dir) {
        this.currentIndex += dir;
        if (this.currentIndex < 0) this.currentIndex = this.images.length - 1;
        if (this.currentIndex >= this.images.length) this.currentIndex = 0;
        this.updateLightboxImage();
    },

    updateLightboxImage() {
        var img = document.getElementById('dicom-lightbox-img');
        var counter = document.getElementById('lightbox-counter');
        img.src = this.images[this.currentIndex].full_url;
        counter.textContent = (this.currentIndex + 1) + ' / ' + this.images.length;
    },

    bindKeys() {
        var self = this;
        document.addEventListener('keydown', function(e) {
            if (!self.lightboxEl || !self.lightboxEl.classList.contains('active')) return;
            if (e.key === 'ArrowLeft')  { e.preventDefault(); self.navigate(-1); }
            if (e.key === 'ArrowRight') { e.preventDefault(); self.navigate(1); }
            if (e.key === 'Escape')     self.closeLightbox();
        });
    }
};
```

---

## 6. Image Serving Controller

Images are served through PHP to enforce authentication and share-token validation — never expose `data/dicom/` directly.

```php
<?php
/**
 * Route: /dicom/image/{series_id}/{type}/{filename}
 *   type = "thumb" or "full"
 *   Optional query param: ?share_token=xxx for public access
 */
class ImageController {

    private $db;
    private $storage_base;

    public function __construct($db, $config) {
        $this->db = $db;
        $this->storage_base = rtrim($config['storage_base'], '/');
    }

    public function serve($series_id, $type, $filename) {
        if (!in_array($type, ['thumb', 'full'])) { http_response_code(400); return; }

        // Sanitize filename — only allow alphanumeric, dots, dashes
        if (!preg_match('/^[\w\-]+\.(jpg|jpeg|png)$/', $filename)) {
            http_response_code(400); return;
        }

        // Auth check: logged-in user with permission OR valid share token
        $share_token = $_GET['share_token'] ?? null;
        if (!$this->authorize($series_id, $share_token)) {
            http_response_code(403); return;
        }

        // Resolve path
        $stmt = $this->db->prepare("SELECT images_path FROM dicom_series WHERE id = ?");
        $stmt->execute([$series_id]);
        $series = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$series) { http_response_code(404); return; }

        $file_path = $this->storage_base . '/' . $series['images_path'] . '/' . $type . '/' . $filename;
        if (!file_exists($file_path)) { http_response_code(404); return; }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $mime = ($ext === 'png') ? 'image/png' : 'image/jpeg';

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file_path));
        header('Cache-Control: private, max-age=86400');
        readfile($file_path);
        exit;
    }

    private function authorize($series_id, $share_token) {
        // If user is logged in and has 'view' permission → OK
        if (isset($_SESSION['user_id'])) {
            // check permission via your existing ZPMS auth system
            return true;
        }
        // If share token provided → validate
        if ($share_token) {
            $stmt = $this->db->prepare("SELECT s.exam_id, s.expires_at, s.is_active
                FROM dicom_shares s
                JOIN dicom_series ds ON ds.exam_id = s.exam_id
                WHERE s.token = ? AND ds.id = ? AND s.is_active = 1");
            $stmt->execute([$share_token, $series_id]);
            $share = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$share) return false;
            if ($share['expires_at'] && strtotime($share['expires_at']) < time()) return false;
            return true;
        }
        return false;
    }
}
```

---

## 7. Share Links

### Creating

```php
// POST /dicom/share/{exam_id}
public function createShareLink($exam_id, $user_id, $expiry_days = null) {
    $days = $expiry_days ?? $this->config['share_default_expiry_days'] ?? 30;
    $token = bin2hex(random_bytes(24)); // 48-char URL-safe token
    $expires_at = ($days > 0) ? date('Y-m-d H:i:s', time() + $days * 86400) : null;

    $stmt = $this->db->prepare("INSERT INTO dicom_shares
        (exam_id, token, created_by, expires_at) VALUES (?, ?, ?, ?)");
    $stmt->execute([$exam_id, $token, $user_id, $expires_at]);

    return $token; // Full URL: https://yourdomain.com/dicom/shared/{token}
}
```

### Resolving (public, no auth)

```php
// GET /dicom/shared/{token}
public function resolveShare($token) {
    $stmt = $this->db->prepare("SELECT s.*, e.id as exam_id, e.status
        FROM dicom_shares s
        JOIN dicom_exams e ON e.id = s.exam_id
        WHERE s.token = ? AND s.is_active = 1");
    $stmt->execute([$token]);
    $share = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$share) { /* render 404 */ return; }
    if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
        /* render "link expired" */ return;
    }

    // Bump view count
    $this->db->prepare("UPDATE dicom_shares SET view_count = view_count + 1 WHERE id = ?")
         ->execute([$share['id']]);

    // Render the same viewer template in read-only mode, passing share_token
    // so image URLs include ?share_token=xxx for auth
    $this->renderViewer($share['exam_id'], $token);
}
```

---

## 8. UI Templates — Wireframe Structure

### Upload Page

```
┌──────────────────────────────────────────────┐
│  .card                                       │
│  ┌── .card-header ────────────────────────┐  │
│  │  DICOM Upload                          │  │
│  └────────────────────────────────────────┘  │
│  ┌── .card-body ──────────────────────────┐  │
│  │  ┌── #upload-dropzone ──────────────┐  │  │
│  │  │  Drag & drop DICOM or ZIP here   │  │  │
│  │  │        [Browse Files]            │  │  │
│  │  └──────────────────────────────────┘  │  │
│  │                                        │  │
│  │  #upload-file-info:  CT_exam.zip 124MB │  │
│  │                                        │  │
│  │  ┌── progress bar ─────────────────┐   │  │
│  │  │ ████████████████░░░░  78%       │   │  │
│  │  └─────────────────────────────────┘   │  │
│  │  #upload-status: Uploading chunk 14/18 │  │
│  │                                        │  │
│  │  [Cancel]                   [Upload ▸] │  │
│  └────────────────────────────────────────┘  │
└──────────────────────────────────────────────┘
```

### Exam List Page

```
┌──────────────────────────────────────────────────┐
│  .section-header                                 │
│  DICOM Exams                    [Upload New] btn │
├──────────────────────────────────────────────────┤
│  .filter-bar                                     │
│  [All] [CT] [MR] [XR] [US]    Search [________] │
├──────────────────────────────────────────────────┤
│  .data-table                                     │
│  Patient    │ Study      │ Date    │ Mod │ Status│
│  DOE, JOHN  │ CT Abdomen │ 2026-01 │ CT  │●Ready │
│  SMITH, ANN │ MRI Brain  │ 2026-01 │ MR  │●Ready │
├──────────────────────────────────────────────────┤
│  .pagination  [< 1 2 3 >]                       │
└──────────────────────────────────────────────────┘
```

### Viewer Page

```
┌──────────────────────────────────────────────────┐
│  DOE, JOHN — CT Abdomen (2026-01-15)             │
│  #series-tabs: [Axial●] [Sagittal] [Coronal]    │
│                                    [Share] [Back]│
├──────────────────────────────────────────────────┤
│  #thumb-grid                                     │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐      │
│  │  1  │ │  2  │ │  3  │ │  4  │ │  5  │      │
│  └─────┘ └─────┘ └─────┘ └─────┘ └─────┘      │
│  ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐ ┌─────┐      │
│  │  6  │ │  7  │ │  8  │ │  9  │ │ 10  │      │
│  └─────┘ └─────┘ └─────┘ └─────┘ └─────┘      │
│        48 images in Axial series                 │
└──────────────────────────────────────────────────┘

 Click thumbnail → Full-size image overlay:
┌──────────────────────────────────────────────────┐
│  .dicom-overlay                                  │
│                          [✕]                     │
│   ‹    ┌──────────────────────┐    ›             │
│        │  Full-resolution     │                  │
│        │    DICOM image       │                  │
│        └──────────────────────┘                  │
│              12 / 48                             │
│   ← → arrow keys  │  Esc closes                 │
└──────────────────────────────────────────────────┘
```

---

## 9. Routing Map

```
GET  /dicom                     → ExamListController::index
GET  /dicom/upload              → render upload.html.php
POST /dicom/upload/init         → UploadController::init     (AJAX)
POST /dicom/upload/chunk        → UploadController::chunk    (AJAX)
POST /dicom/upload/finalize     → UploadController::finalize (AJAX)
GET  /dicom/view/{exam_id}      → ViewerController::show
GET  /dicom/image/{series_id}/{type}/{filename}
                                → ImageController::serve
POST /dicom/share/{exam_id}     → ShareController::create    (AJAX)
GET  /dicom/shared/{token}      → ShareController::view      (public)
DELETE /dicom/exam/{exam_id}    → ExamListController::delete
```

---

## 10. Implementation Phases

### Phase 1 — Core (current sprint)

| # | Task | Files |
|---|------|-------|
| 1 | DB schema | `dicom.install.php` |
| 2 | DicomParser | `src/DicomParser.php` |
| 3 | DicomDirParser (DICOMDIR index reader) | `src/DicomDirParser.php` |
| 4 | DicomConverter (DCMTK + GD thumbs) | `src/DicomConverter.php` |
| 5 | Chunked upload controller | `controllers/UploadController.php` |
| 6 | Upload JS + template | `js/dicom-upload.js`, `templates/upload.html.php` |
| 7 | Exam list controller + template | `controllers/ExamListController.php`, `templates/exam-list.html.php` |
| 8 | Viewer controller + template | `controllers/ViewerController.php`, `templates/viewer.html.php` |
| 9 | Viewer JS (grid + image overlay + keys) | `js/dicom-viewer.js` |
| 10 | Image serving controller | `controllers/ImageController.php` |
| 11 | Share links (create + resolve) | `controllers/ShareController.php` |
| 12 | Module CSS | `css/dicom.css` |

### Phase 2 — Enhancements

- Background processing queue (avoid PHP timeout on large exams)
- Window/Level controls (brightness/contrast sliders in image overlay)
- DICOM metadata side panel (all parsed tags)
- Multi-file upload queue
- Exam deletion with cleanup
- Exam list pagination + filters (patient, modality, date range)
- Mousewheel scroll-through in image overlay
- Share link management (list, revoke, edit expiry)

### Phase 3 — Advanced

- Cornerstone.js integration for proper DICOM viewport
- Multi-frame DICOM support (ultrasound cine)
- DICOM SR display
- Side-by-side study comparison
- PACS integration (C-STORE)
- ZPMS patient record linking

---

## 11. Server Requirements

```
PHP ≥ 8.0
  ext-zip, ext-gd, ext-pdo_mysql
  php.ini:
    upload_max_filesize = 10M   (chunk size, not total)
    post_max_size = 12M
    max_execution_time = 300    (for finalize/processing)
    memory_limit = 256M

DCMTK:
  apt install dcmtk             (provides dcm2pnm, dcmdump)

MySQL ≥ 5.7 / MariaDB ≥ 10.3

Disk: plan ~2× raw DICOM size per exam (originals + converted images)
```

---

## 12. Security Considerations

| Area | Measure |
|------|---------|
| File validation | Verify DICM magic bytes; reject non-DICOM |
| Path traversal | Sanitize filenames; use numeric IDs for paths |
| Auth gating | All images served via PHP controller; no direct FS access |
| Share tokens | 48-char hex (192-bit entropy); rate-limit resolution |
| Upload tokens | Auto-expire after 1 hour; cron cleanup |
| Patient data | Parsed DICOM metadata in DB; review data handling policy |
| CSRF | Token on all POST endpoints |
| Chunk integrity | Verify assembled size matches declared filesize |
