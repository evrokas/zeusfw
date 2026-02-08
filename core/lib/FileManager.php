<?php
/**
 * File Management System
 *
 * Provides stream wrapper URI abstraction (public://, private://, temp://, cache://)
 * with database-backed metadata (filesClass) and reference counting (fileUsageClass).
 *
 * Classes:
 *   FileManager          - base: stream resolution, CRUD, conflict handling
 *   ManagedFileManager   - permanent files with DB tracking and reference counting
 *   TemporaryFileManager - upload staging with random names and TTL cleanup
 *   AssetManager         - CSS/JS bundle aggregation (cache layer)
 *   FileEntity           - value object returned by file operations
 */

class FileManager {

    protected array $streams = [];
    protected string $base_path = '';

    public function __construct(array $fs_config = []) {
        $this->base_path = realpath(__APPDIR__ . '/' . ($fs_config['base_path'] ?? '../files'));

        $stream_defs = $fs_config['streams'] ?? [
            'public'  => ['path' => 'public'],
            'private' => ['path' => 'private'],
            'temp'    => ['path' => 'temp'],
            'cache'   => ['path' => 'cache'],
        ];

        foreach ($stream_defs as $scheme => $def) {
            $this->streams[$scheme] = $this->base_path . '/' . $def['path'];
        }

        $this->ensureDirectories();
    }

    protected function ensureDirectories(): void {
        foreach ($this->streams as $path) {
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
    }

    /**
     * Resolve a stream URI (e.g. public://foo/bar.pdf) to a real filesystem path.
     */
    public function resolvePath(string $uri): string {
        if (!str_contains($uri, '://')) {
            throw new RuntimeException("Invalid stream URI: $uri");
        }
        [$scheme, $path] = explode('://', $uri, 2);
        if (!isset($this->streams[$scheme])) {
            throw new RuntimeException("Unknown stream scheme: $scheme");
        }
        return $this->streams[$scheme] . '/' . ltrim($path, '/');
    }

    /**
     * Save a file from a source path to a destination URI.
     */
    public function save(string $source, string $destination_uri, bool $replace = true): FileEntity {
        $dest_path = $this->resolvePath($destination_uri);
        $dest_dir  = dirname($dest_path);

        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }

        if (file_exists($dest_path) && !$replace) {
            $dest_path = $this->resolveConflict($dest_path);
            // Recompute URI to match the new path
            $destination_uri = $this->pathToUri($dest_path);
        }

        if (!copy($source, $dest_path)) {
            throw new RuntimeException("Failed to save file to: $destination_uri");
        }

        return new FileEntity($destination_uri, $dest_path);
    }

    /**
     * Copy a file from one URI to another.
     */
    public function copy(string $source_uri, string $dest_uri): FileEntity {
        return $this->save($this->resolvePath($source_uri), $dest_uri);
    }

    /**
     * Move a file from one URI to another.
     */
    public function move(string $source_uri, string $dest_uri): FileEntity {
        $source_path = $this->resolvePath($source_uri);
        $dest_path   = $this->resolvePath($dest_uri);
        $dest_dir    = dirname($dest_path);

        if (!is_dir($dest_dir)) {
            mkdir($dest_dir, 0755, true);
        }

        if (!rename($source_path, $dest_path)) {
            throw new RuntimeException("Failed to move file from $source_uri to $dest_uri");
        }

        return new FileEntity($dest_uri, $dest_path);
    }

    /**
     * Delete a file by URI (filesystem only — no DB).
     */
    public function deleteFile(string $uri): bool {
        $path = $this->resolvePath($uri);
        if (!file_exists($path)) {
            return true;
        }
        if (!unlink($path)) {
            throw new RuntimeException("Failed to delete file: $uri");
        }
        return true;
    }

    /**
     * Resolve filename conflicts by appending _1, _2, etc.
     */
    protected function resolveConflict(string $filepath): string {
        $info    = pathinfo($filepath);
        $counter = 1;
        $new     = $filepath;
        while (file_exists($new)) {
            $new = $info['dirname'] . '/' . $info['filename'] . '_' . $counter . '.' . $info['extension'];
            $counter++;
        }
        return $new;
    }

    /**
     * Convert a real path back to a stream URI.
     */
    protected function pathToUri(string $path): string {
        foreach ($this->streams as $scheme => $base) {
            if (str_starts_with($path, $base . '/')) {
                return $scheme . '://' . substr($path, strlen($base) + 1);
            }
        }
        throw new RuntimeException("Path does not belong to any known stream: $path");
    }

    /**
     * Return the web-accessible URL for a URI (public:// only).
     */
    public function getUrl(string $uri): string {
        if (str_starts_with($uri, 'public://')) {
            return '/files/public/' . substr($uri, 9);
        }
        if (str_starts_with($uri, 'private://')) {
            return '/files/get/' . substr($uri, 10);
        }
        return $uri;
    }
}


/**
 * ManagedFileManager — permanent files with DB metadata and reference counting.
 */
class ManagedFileManager extends FileManager {

    /**
     * Create a managed file: save to filesystem + insert DB record + register usage.
     *
     * @param string      $source        Path to source file (e.g. $_FILES['f']['tmp_name'])
     * @param string      $destination   Stream URI (e.g. public://patient-docs/42/report.pdf)
     * @param string|null $entity_type   e.g. 'patient', 'appointment'
     * @param string|null $entity_id     The entity's guid
     * @param string      $usage_type    e.g. 'attachment', 'avatar', 'export'
     * @return FileEntity
     */
    public function create(
        string  $source,
        string  $destination,
        ?string $entity_type = null,
        ?string $entity_id   = null,
        string  $usage_type  = 'attachment'
    ): FileEntity {
        $entity = $this->save($source, $destination);
        $this->insertFileRecord($entity);

        if ($entity_type && $entity_id) {
            $this->addUsage($destination, $entity_type, $entity_id, $usage_type);
        }

        return $entity;
    }

    /**
     * Delete a managed file. Refuses if reference count > 0.
     */
    public function delete(string $uri): bool {
        $count = $this->getUsageCount($uri);
        if ($count > 0) {
            throw new RuntimeException("File '$uri' is still in use ($count references) and cannot be deleted.");
        }
        $this->deleteFile($uri);
        $this->markFileDeleted($uri);
        return true;
    }

    /**
     * Add a usage reference (entity → file link).
     */
    public function addUsage(string $uri, string $entity_type, string $entity_id, string $usage_type = 'attachment'): void {
        $rec = new fileUsageClass();
        $rec->setguid(guid());
        $rec->setcdate(date('Y-m-d H:i:s'));
        $rec->setfile_guid($this->getFileGuid($uri));
        $rec->setentity_type($entity_type);
        $rec->setentity_id($entity_id);
        $rec->setusage_type($usage_type);
        $rec->insert();
    }

    /**
     * Remove a usage reference (soft-delete).
     */
    public function removeUsage(string $uri, string $entity_type, string $entity_id): void {
        $file_guid = $this->getFileGuid($uri);
        $sql = "UPDATE file_usage SET deleted = :now
                WHERE file_guid = :fguid
                  AND entity_type = :etype
                  AND entity_id = :eid
                  AND deleted IS NULL";
        $st = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':now',   date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $st->bindValue(':fguid', $file_guid,          PDO::PARAM_STR);
        $st->bindValue(':etype', $entity_type,        PDO::PARAM_STR);
        $st->bindValue(':eid',   $entity_id,          PDO::PARAM_STR);
        $st->execute();
    }

    /**
     * Get active reference count for a file URI.
     */
    public function getUsageCount(string $uri): int {
        $file_guid = $this->getFileGuid($uri);
        if (!$file_guid) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM file_usage WHERE file_guid = :fguid AND deleted IS NULL";
        $st  = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':fguid', $file_guid, PDO::PARAM_STR);
        $st->execute();
        return (int) $st->fetchColumn();
    }

    /**
     * Get the DB guid for a file by its URI. Returns null if not found.
     */
    protected function getFileGuid(string $uri): ?string {
        $sql = "SELECT guid FROM files WHERE furi = :uri AND deleted IS NULL LIMIT 1";
        $st  = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':uri', $uri, PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['guid'] : null;
    }

    /**
     * Insert a new file record into the DB.
     */
    protected function insertFileRecord(FileEntity $entity): void {
        $rec = new filesClass();
        $rec->setguid(guid());
        $rec->setcdate(date('Y-m-d H:i:s'));
        $rec->setcuser($_SESSION['user_guid'] ?? 'system');
        $rec->setfuri($entity->uri);
        $rec->setfpath($entity->filepath);
        $rec->setfname($entity->fname);
        $rec->setfmime($entity->fmime);
        $rec->setfsize($entity->fsize);
        $rec->setfhash($entity->fhash);
        $rec->setfstatus('active');
        $rec->insert();
    }

    /**
     * Soft-delete the file DB record.
     */
    protected function markFileDeleted(string $uri): void {
        $sql = "UPDATE files SET fstatus = 'deleted', deleted = :now WHERE furi = :uri AND deleted IS NULL";
        $st  = dbConnection::getConnection()->prepare($sql);
        $st->bindValue(':now', date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $st->bindValue(':uri', $uri,                 PDO::PARAM_STR);
        $st->execute();
    }
}


/**
 * TemporaryFileManager — upload staging with random names and time-based cleanup.
 * Temp files are NOT tracked in the DB.
 */
class TemporaryFileManager extends FileManager {

    private int $ttl;

    public function __construct(array $fs_config = []) {
        parent::__construct($fs_config);
        $this->ttl = (int) ($fs_config['cleanup']['temp_ttl'] ?? 86400);
    }

    /**
     * Stage an uploaded file under temp:// with a random name.
     * Returns the FileEntity (uri = temp://{random}.{ext}).
     */
    public function stage(string $source): FileEntity {
        $ext      = pathinfo($source, PATHINFO_EXTENSION);
        $suffix   = $ext ? '.' . $ext : '';
        $name     = bin2hex(random_bytes(16)) . $suffix;
        $temp_uri = 'temp://' . $name;
        return $this->save($source, $temp_uri);
    }

    /**
     * Promote a temp file to a permanent managed file with DB tracking.
     */
    public function promote(
        string  $temp_uri,
        string  $permanent_uri,
        ?string $entity_type = null,
        ?string $entity_id   = null,
        string  $usage_type  = 'attachment'
    ): FileEntity {
        $entity = $this->move($temp_uri, $permanent_uri);
        $manager = new ManagedFileManager(['base_path' => $this->base_path] + $this->streamsConfig());
        $manager->insertFileRecord($entity);
        if ($entity_type && $entity_id) {
            $manager->addUsage($permanent_uri, $entity_type, $entity_id, $usage_type);
        }
        return $entity;
    }

    /**
     * Delete temp files older than TTL. Returns number of files cleaned.
     */
    public function cleanup(?int $older_than = null): int {
        $threshold = $older_than ?? (time() - $this->ttl);
        $temp_dir  = $this->streams['temp'] ?? null;
        if (!$temp_dir || !is_dir($temp_dir)) {
            return 0;
        }
        $count = 0;
        foreach (glob($temp_dir . '/*') as $file) {
            if (is_file($file) && filemtime($file) < $threshold) {
                unlink($file);
                $count++;
            }
        }
        return $count;
    }

    /**
     * Return streams as config array for passing to ManagedFileManager.
     */
    protected function streamsConfig(): array {
        $cfg = [];
        foreach ($this->streams as $scheme => $path) {
            // Reverse: path back to relative portion isn't needed — pass full base_path
        }
        return [];
    }
}


/**
 * AssetManager — CSS/JS bundle aggregation stored in cache://.
 */
class AssetManager extends FileManager {

    /**
     * Concatenate source files into a single bundle at the given cache URI.
     * Returns the path to the written bundle.
     */
    public function bundle(string $type, array $source_paths, string $output_uri): string {
        $output_path = $this->resolvePath($output_uri);
        $output_dir  = dirname($output_path);

        if (!is_dir($output_dir)) {
            mkdir($output_dir, 0755, true);
        }

        $content = '';
        foreach ($source_paths as $path) {
            if (file_exists($path)) {
                $content .= "/* $path */\n" . file_get_contents($path) . "\n";
            }
        }

        file_put_contents($output_path, $content);
        return $output_path;
    }

    /**
     * Check if the bundle is still fresh (newer than all source files).
     */
    public function isFresh(string $output_uri, array $source_paths): bool {
        $output_path = $this->resolvePath($output_uri);
        if (!file_exists($output_path)) {
            return false;
        }
        $bundle_mtime = filemtime($output_path);
        foreach ($source_paths as $path) {
            if (file_exists($path) && filemtime($path) > $bundle_mtime) {
                return false;
            }
        }
        return true;
    }

    /**
     * Return the web URL for a cache bundle.
     * Bundles in cache:// are not web-accessible directly; this is for internal reference.
     */
    public function getBundlePath(string $output_uri): string {
        return $this->resolvePath($output_uri);
    }
}


/**
 * FileEntity — value object representing a file.
 */
class FileEntity {

    public string $uri;
    public string $filepath;
    public string $fname;
    public string $fmime;
    public int    $fsize;
    public string $fhash;
    public int    $created;

    public function __construct(string $uri, string $filepath) {
        $this->uri      = $uri;
        $this->filepath = $filepath;
        $this->fname    = basename($filepath);
        $this->fsize    = filesize($filepath);
        $this->fmime    = mime_content_type($filepath) ?: 'application/octet-stream';
        $this->fhash    = hash_file('sha256', $filepath);
        $this->created  = time();
    }

    /**
     * Verify file integrity by re-hashing.
     */
    public function verify(): bool {
        return file_exists($this->filepath) && hash_file('sha256', $this->filepath) === $this->fhash;
    }
}
