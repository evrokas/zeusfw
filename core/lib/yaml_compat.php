<?php
/*
 * yaml_compat.php - fallback implementations of the three ext/yaml functions
 * ZeusFW relies on, for environments where the PHP yaml extension can't be
 * installed.
 *
 * ZeusFW hard-requires ext/yaml: Kernel::__construct() calls yaml_parse_file()
 * on every request, every module that ships its own routes parses its .yaml on
 * construction, and maker.php reads/writes schema and feed files with it. On a
 * box without the extension the framework doesn't degrade - it fatals.
 *
 * THIS FILE IS A NO-OP WHEN ext/yaml IS PRESENT. Everything below is guarded by
 * function_exists(), so a normal production install with the extension loaded
 * behaves exactly as before and never touches this code. It exists so a dev box
 * or a container that can't get the extension can still run the framework.
 *
 * The fallback shells out to Python's PyYAML rather than hand-rolling a parser.
 * That is a deliberate trade: the YAML actually used across zeusfw/mweb/zpms
 * includes block scalars, explicit document markers, inline maps/lists, nulls
 * as ~, and UTF-8 Greek content, and a half-correct hand-written parser that
 * silently mis-reads one of those is far worse than a subprocess - a wrong
 * config parse would corrupt content or routes without any error. PyYAML is a
 * real YAML implementation, so semantics match ext/yaml closely.
 *
 * Cost is managed with an mtime-keyed cache (see yaml_compat_cache_path()), so
 * repeated parses of the same unchanged file cost one file_get_contents rather
 * than a process spawn.
 *
 * Requires: python3 with PyYAML. If neither ext/yaml nor that is available,
 * these functions raise a RuntimeException naming both options, rather than
 * returning false and letting the caller fail somewhere less obvious.
 */

if (!function_exists('yaml_compat_python')) {
    /**
     * Locate a usable python3 with PyYAML, once per process.
     * Returns the interpreter path, or null if there isn't one.
     */
    function yaml_compat_python(): ?string
    {
        static $resolved = false;
        static $python = null;

        if ($resolved) {
            return $python;
        }
        $resolved = true;

        foreach (['python3', 'python'] as $candidate) {
            $out = yaml_compat_run([$candidate, '-c', 'import yaml; print("ok")'], null, $rc);
            if ($rc === 0 && trim($out) === 'ok') {
                $python = $candidate;
                break;
            }
        }

        return $python;
    }
}

if (!function_exists('yaml_compat_run')) {
    /**
     * Run a command with an ARGUMENT ARRAY (never a shell string), so file
     * paths and YAML payloads are never subject to shell interpretation.
     * $input is written to stdin when not null. $rc receives the exit code.
     */
    function yaml_compat_run(array $argv, ?string $input, ?int &$rc): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($argv, $descriptors, $pipes);
        if (!is_resource($proc)) {
            $rc = -1;
            return '';
        }

        if ($input !== null) {
            fwrite($pipes[0], $input);
        }
        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $rc = proc_close($proc);

        if ($rc !== 0 && $stderr !== '') {
            error_log('yaml_compat: ' . trim($stderr));
        }

        return $stdout;
    }
}

if (!function_exists('yaml_compat_require_python')) {
    function yaml_compat_require_python(): string
    {
        $python = yaml_compat_python();
        if ($python === null) {
            throw new RuntimeException(
                'YAML support is unavailable: the PHP "yaml" extension is not loaded and no '
                . 'python3 with PyYAML was found to fall back on. Install the extension '
                . '(apt install php-yaml / pecl install yaml) or make python3+PyYAML available.'
            );
        }
        return $python;
    }
}

if (!function_exists('yaml_compat_cache_path')) {
    /**
     * Cache file for a parsed YAML file, keyed by realpath + mtime + size, so a
     * changed file always misses and a stale entry can never be served.
     */
    function yaml_compat_cache_path(string $file): ?string
    {
        $real = realpath($file);
        if ($real === false) {
            return null;
        }

        $dir = sys_get_temp_dir() . '/zeusfw-yaml-cache';
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            return null;
        }

        $key = hash('sha256', $real . '|' . filemtime($real) . '|' . filesize($real));

        return $dir . '/' . $key . '.json';
    }
}

if (!function_exists('yaml_compat_decode')) {
    /**
     * Convert YAML text to a PHP value via PyYAML, going through JSON.
     *
     * $pos mirrors ext/yaml: 0 (default) returns the first document, -1 returns
     * every document as a list.
     */
    function yaml_compat_decode(string $yamlText, int $pos = 0)
    {
        $python = yaml_compat_require_python();

        // safe_load_all handles an explicit leading "---" and multi-document
        // files alike; safe_load on its own rejects multi-document input.
        $script = <<<'PY'
import sys, json, yaml
docs = list(yaml.safe_load_all(sys.stdin.read()))
pos = int(sys.argv[1])
if pos == -1:
    out = docs
else:
    out = docs[pos] if len(docs) > pos else None
sys.stdout.write(json.dumps(out, ensure_ascii=False))
PY;

        $json = yaml_compat_run([$python, '-c', $script, (string) $pos], $yamlText, $rc);
        if ($rc !== 0) {
            return false;
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('yaml_compat: could not decode intermediate JSON: ' . json_last_error_msg());
            return false;
        }

        return $decoded;
    }
}

if (!function_exists('yaml_parse_file')) {
    function yaml_parse_file(string $filename, int $pos = 0)
    {
        if (!is_readable($filename)) {
            error_log("yaml_compat: cannot read $filename");
            return false;
        }

        // Only the common case (first document) is cached; -1 is rare and cheap
        // to just re-parse.
        $cache = $pos === 0 ? yaml_compat_cache_path($filename) : null;
        if ($cache !== null && is_file($cache)) {
            $cached = json_decode((string) file_get_contents($cache), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $cached;
            }
        }

        $parsed = yaml_compat_decode((string) file_get_contents($filename), $pos);

        if ($cache !== null && $parsed !== false) {
            // Write via a temp file + rename so a concurrent reader never sees
            // a half-written cache entry.
            $tmp = $cache . '.' . getmypid() . '.tmp';
            if (@file_put_contents($tmp, json_encode($parsed, JSON_UNESCAPED_UNICODE)) !== false) {
                @rename($tmp, $cache);
            }
        }

        return $parsed;
    }
}

if (!function_exists('yaml_parse')) {
    function yaml_parse(string $input, int $pos = 0)
    {
        return yaml_compat_decode($input, $pos);
    }
}

if (!function_exists('yaml_emit')) {
    function yaml_emit($data): string
    {
        $python = yaml_compat_require_python();

        $script = <<<'PY'
import sys, json, yaml
data = json.loads(sys.stdin.read())
sys.stdout.write(yaml.safe_dump(data, allow_unicode=True, default_flow_style=False, sort_keys=False))
PY;

        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('yaml_emit: value could not be JSON-encoded for conversion');
        }

        $out = yaml_compat_run([$python, '-c', $script], $json, $rc);
        if ($rc !== 0) {
            throw new RuntimeException('yaml_emit: PyYAML fallback failed to emit YAML');
        }

        // ext/yaml emits a leading document marker; match that so callers that
        // concatenate or diff the output see the same shape.
        return "---\n" . $out . "...\n";
    }
}

// ext/yaml's encoding/linebreak constants - only the values maker.php actually
// passes (YAML_UTF8_ENCODING) are defined here. yaml_emit()'s PyYAML fallback
// always emits UTF-8 regardless, so $encoding is accepted but ignored below,
// same as $linebreak/$callbacks - a no-op parameter, not a real feature gap.
if (!defined('YAML_ANY_ENCODING')) {
    define('YAML_ANY_ENCODING', 0);
}
if (!defined('YAML_UTF8_ENCODING')) {
    define('YAML_UTF8_ENCODING', 1);
}

if (!function_exists('yaml_emit_file')) {
    function yaml_emit_file(string $filename, $data, int $encoding = YAML_ANY_ENCODING, int $linebreak = 0, array $callbacks = []): bool
    {
        $yaml = yaml_emit($data);

        // Write via a temp file + rename, matching yaml_parse_file()'s own
        // cache-write pattern - no reader ever sees a half-written file.
        $tmp = $filename . '.' . getmypid() . '.tmp';
        if (file_put_contents($tmp, $yaml) === false) {
            return false;
        }
        return rename($tmp, $filename);
    }
}
