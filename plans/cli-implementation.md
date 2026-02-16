# ZPMS CLI Component Implementation Plan

**Date:** February 10, 2026
**Target System:** ZPMS (Zeus Patient Management System) on Zeus Framework
**Status:** Planning Phase
**Priority:** Medium

---

## Executive Summary

This plan proposes a comprehensive Command-Line Interface (CLI) component for ZPMS that integrates with the Zeus Framework architecture and the completed 13-phase upgrade (Translation System, Design System, File Management).

### Goals

1. **Automation** - Enable scheduled tasks via cron
2. **Administration** - Command-line access to system management
3. **Development** - Code generation and scaffolding tools
4. **Maintenance** - System cleanup, backup, and integrity checks
5. **Efficiency** - Bulk operations faster than web interface

### Integration Points

- ✅ **Translation System** (Phases 1-4, 10-13) - Export/import translations, coverage stats
- ✅ **File Management** (Phase 9) - Cleanup, verification, usage tracking
- ✅ **Database System** - Backup, restore, entity generation
- ✅ **Cache System** - Clear, rebuild, statistics

---

## Context

ZPMS has completed a comprehensive upgrade with the following systems in place:

### Completed Systems
- **Translation System** (Phases 1-4, 10-13)
  - Multilingual support with path-based URLs (`/en/`, `/el/`)
  - YAML-based translation files
  - Database dictionary with import/export
  - ZETEM template integration
  - SEO optimization

- **Design System** (Phases 5-8)
  - Medical teal color scheme
  - Healthcare-themed components
  - Responsive layout system
  - Comprehensive component library

- **File Management** (Phase 9)
  - Stream wrapper abstraction (`public://`, `private://`, `temp://`, `cache://`)
  - Reference counting
  - Automatic cleanup
  - YAML metadata persistence

### CLI Integration Opportunity

A CLI component would provide command-line access to these systems, enabling:
- Translation management automation
- File cleanup scheduling
- Database maintenance
- Cache management
- Development workflows

---

## Proposed Architecture

### Directory Structure

```
/var/www/html/apps/zeusfw/
├── core/
│   └── cli/
│       ├── CLI.php                    # Main CLI manager
│       ├── CLICommand.php             # Base command class
│       ├── CLIOutput.php              # Formatted output
│       ├── CLIInteractive.php         # Interactive mode
│       └── commands/
│           ├── TranslationCommands.php
│           ├── FileCommands.php
│           ├── CacheCommands.php
│           ├── DatabaseCommands.php
│           ├── ModuleCommands.php
│           ├── UserCommands.php
│           └── DevCommands.php
│
├── cli.php                            # CLI entry point (root)
├── cron/
│   └── cli-scheduler.php              # Scheduled command runner
│
└── docs/
    ├── CLI_USAGE.md                   # User guide
    └── CLI_COMMANDS_REFERENCE.md      # Command reference
```

---

## Core Components

### 1. CLI Manager (`fw/core/cli/CLI.php`)

**Purpose:** Central orchestrator for CLI operations

**Key Methods:**
```php
class CLI {
    protected $kernel;
    protected $commands = [];
    protected $output;

    public function __construct($kernel)
    public function run($argv)
    public function registerCommand($name, $class)
    public function registerDefaultCommands()
    public function showHelp()
    public function showVersion()
}
```

**Responsibilities:**
- Parse command-line arguments
- Route to appropriate command
- Handle errors and exceptions
- Provide help system
- Manage command registration

---

### 2. Command Base Class (`fw/core/cli/CLICommand.php`)

**Purpose:** Abstract base for all CLI commands

**Interface:**
```php
abstract class CLICommand {
    protected $kernel;
    protected $output;

    abstract public function execute($args);
    abstract public function getDescription();
    abstract public function getHelp();

    public function __construct($kernel, $output)
    protected function validateArgs($args, $required)
    protected function getOption($args, $name, $default = null)
    protected function hasFlag($args, $name)
}
```

**Features:**
- Access to Kernel and all framework services
- Standardized argument parsing
- Option and flag handling
- Help text generation

---

### 3. Output Formatter (`fw/core/cli/CLIOutput.php`)

**Purpose:** Formatted CLI output with colors and styling

**Methods:**
```php
class CLIOutput {
    protected $colors = true;

    // Message types
    public function success($message)
    public function error($message)
    public function warning($message)
    public function info($message)
    public function debug($message)

    // Formatting
    public function table($headers, $rows)
    public function progressBar($current, $total, $width = 50)
    public function spinner($message)

    // Prompts
    public function ask($question, $default = null)
    public function confirm($question)
    public function choice($question, $options)

    // Colors
    public function color($text, $color)
    public function bold($text)
}
```

**Color Support:**
- Red: Errors
- Green: Success
- Yellow: Warnings
- Blue: Info
- Gray: Debug

---

### 4. CLI Entry Point (`cli.php`)

**Location:** Root directory

```php
#!/usr/bin/env php
<?php
/**
 * ZPMS CLI Entry Point
 *
 * Usage: php cli.php [command] [arguments] [options]
 *
 * Examples:
 *   php cli.php translation:stats
 *   php cli.php file:cleanup temp
 *   php cli.php cache:clear
 *   php cli.php help
 */

// Bootstrap Zeus Framework
require_once __DIR__ . '/fw/bootstrap.php';

// Initialize CLI
require_once __DIR__ . '/fw/core/cli/CLI.php';
$cli = new CLI($kernel);

// Register application-specific commands
if (file_exists(__DIR__ . '/cli_commands.php')) {
    require_once __DIR__ . '/cli_commands.php';
}

// Run CLI
try {
    $exitCode = $cli->run($argv);
    exit($exitCode);
} catch (Exception $e) {
    echo "\033[0;31mError: " . $e->getMessage() . "\033[0m\n";
    if (isset($argv) && in_array('--debug', $argv)) {
        echo $e->getTraceAsString() . "\n";
    }
    exit(1);
}
```

**Exit Codes:**
- `0` - Success
- `1` - General error
- `2` - Invalid arguments
- `3` - Command not found
- `4` - Permission denied

---

## Command Categories

### Translation Management Commands

**File:** `fw/core/cli/commands/TranslationCommands.php`

#### `translation:export`
Export dictionary translations to YAML file.

**Usage:**
```bash
php cli.php translation:export el /path/to/export.yaml
php cli.php translation:export en /backups/en-$(date +%Y%m%d).yaml
```

**Options:**
- `--format=yaml|json` - Export format (default: yaml)
- `--include-metadata` - Include translation metadata

**Integration:**
- Uses `dictionaryClassEx::exportToYAML($lang)`
- Creates backup before export

#### `translation:import`
Import translations from YAML file.

**Usage:**
```bash
php cli.php translation:import el /path/to/import.yaml
php cli.php translation:import en /translations/en.yaml --merge
```

**Options:**
- `--merge` - Merge with existing (don't overwrite)
- `--dry-run` - Show what would be imported
- `--backup` - Create backup before import

**Integration:**
- Uses `dictionaryClassEx::importFromYAML($lang, $file)`
- Returns import statistics

#### `translation:stats`
Show translation coverage statistics.

**Usage:**
```bash
php cli.php translation:stats
php cli.php translation:stats --format=json
```

**Output:**
```
Translation Statistics
======================

Language | Total | Translated | Missing | Coverage
---------|-------|------------|---------|----------
en       | 350   | 350        | 0       | 100.0%
el       | 350   | 320        | 30      | 91.4%
```

**Integration:**
- Uses `dictionaryClassEx::getTranslationStats()`

#### `translation:missing`
Find missing translations for a language.

**Usage:**
```bash
php cli.php translation:missing el
php cli.php translation:missing el --export=/missing-el.txt
```

**Output:**
- Lists all untranslated keys
- Shows English text for reference
- Optionally exports to file

**Integration:**
- Uses `dictionaryClassEx::getUntranslated($lang)`

#### `translation:sync`
Sync translations between languages.

**Usage:**
```bash
php cli.php translation:sync en el
php cli.php translation:sync en el --missing-only
```

**Options:**
- `--missing-only` - Only add missing translations
- `--auto-translate` - Use translation API (future)

---

### File Management Commands

**File:** `fw/core/cli/commands/FileCommands.php`

#### `file:cleanup`
Clean up temporary and cache files.

**Usage:**
```bash
php cli.php file:cleanup temp
php cli.php file:cleanup cache
php cli.php file:cleanup all
```

**Options:**
- `--older-than=1d` - Only files older than duration
- `--dry-run` - Show what would be deleted
- `--verbose` - Show each file being deleted

**Integration:**
- Uses `TemporaryFileManager::cleanup()`
- Uses `CacheFileManager::cleanup()`

#### `file:verify`
Check file integrity using SHA-256 hashes.

**Usage:**
```bash
php cli.php file:verify
php cli.php file:verify public://uploads/document.pdf
```

**Output:**
- Lists files with integrity issues
- Shows expected vs actual hash
- Suggests repair actions

**Integration:**
- Uses `FileManager` metadata
- Verifies SHA-256 hashes

#### `file:usage`
Show file usage and reference counts.

**Usage:**
```bash
php cli.php file:usage
php cli.php file:usage public://uploads/
```

**Output:**
```
File Usage Report
=================

File                          | References | Size    | Status
------------------------------|------------|---------|--------
public://docs/manual.pdf      | 5          | 2.5 MB  | Active
public://images/logo.png      | 12         | 45 KB   | Active
private://reports/jan.pdf     | 0          | 1.2 MB  | Orphan
```

#### `file:orphans`
Find orphaned files (zero references).

**Usage:**
```bash
php cli.php file:orphans
php cli.php file:orphans --delete
```

**Options:**
- `--delete` - Delete orphaned files
- `--move-to=archive://` - Move to archive
- `--older-than=30d` - Only old orphans

---

### Cache Management Commands

**File:** `fw/core/cli/commands/CacheCommands.php`

#### `cache:clear`
Clear system caches.

**Usage:**
```bash
php cli.php cache:clear
php cli.php cache:clear template
php cli.php cache:clear render_array
```

**Cache Types:**
- `template` - ZETEM template cache
- `render_array` - Render array cache
- `translation` - Translation cache
- `all` - All caches

#### `cache:rebuild`
Rebuild caches.

**Usage:**
```bash
php cli.php cache:rebuild
php cli.php cache:rebuild template
```

#### `cache:stats`
Show cache statistics.

**Usage:**
```bash
php cli.php cache:stats
```

**Output:**
- Cache hit/miss ratios
- Cache sizes
- Memory usage

---

### Database Commands

**File:** `fw/core/cli/commands/DatabaseCommands.php`

#### `db:export`
Export database to SQL file.

**Usage:**
```bash
php cli.php db:export /backups/zpms-$(date +%Y%m%d).sql
php cli.php db:export /backups/zpms.sql --gzip
```

**Options:**
- `--gzip` - Compress output
- `--tables=patients,appointments` - Specific tables only
- `--no-data` - Schema only

**Integration:**
- Uses `sql/mysqldump.sh` wrapper
- Includes metadata in comments

#### `db:import`
Import database from SQL file.

**Usage:**
```bash
php cli.php db:import /backups/zpms-20260210.sql
php cli.php db:import /backups/zpms.sql.gz --decompress
```

**Options:**
- `--decompress` - Decompress gzipped file
- `--force` - Skip confirmation

**Warning:** Prompts for confirmation before overwriting

#### `db:generate-entities`
Generate entity classes from YAML schemas.

**Usage:**
```bash
php cli.php db:generate-entities
php cli.php db:generate-entities patients
```

**Integration:**
- Uses Zeus Maker system
- Reads from `web/classes/yaml/`
- Generates to `web/classes/`

#### `db:status`
Show database status and statistics.

**Usage:**
```bash
php cli.php db:status
```

**Output:**
- Database size
- Table count
- Row counts per table
- Index statistics

---

### Module Commands

**File:** `fw/core/cli/commands/ModuleCommands.php`

#### `module:list`
List all modules and their status.

**Usage:**
```bash
php cli.php module:list
php cli.php module:list --enabled
```

**Output:**
```
Modules
=======

Name                 | Status   | Version | Region
---------------------|----------|---------|-------------
mainnavigation       | Enabled  | 1.0     | header
language_selector    | Enabled  | 1.0     | header
translation_admin    | Enabled  | 1.0     | admin
```

#### `module:enable`
Enable a module.

**Usage:**
```bash
php cli.php module:enable translation_admin
```

#### `module:disable`
Disable a module.

**Usage:**
```bash
php cli.php module:disable translation_admin
```

#### `module:generate`
Generate module scaffold.

**Usage:**
```bash
php cli.php module:generate my_module
php cli.php module:generate my_module --region=content
```

**Generated Files:**
- `my_module.info.yaml`
- `my_module.php`
- `my_module.zetem`
- `my_module.css` (optional)

---

### User Management Commands

**File:** `fw/core/cli/commands/UserCommands.php`

#### `user:create`
Create a new user.

**Usage:**
```bash
php cli.php user:create admin admin@example.com password123 --role=administrator
php cli.php user:create doctor doctor@zpms.com secret --role=user
```

**Options:**
- `--role=role_name` - Assign role
- `--email=address` - Email address
- `--active` - Activate immediately

#### `user:password`
Change user password.

**Usage:**
```bash
php cli.php user:password admin new_password
php cli.php user:password admin --generate
```

**Options:**
- `--generate` - Generate random password

#### `user:list`
List all users.

**Usage:**
```bash
php cli.php user:list
php cli.php user:list --role=administrator
```

**Output:**
```
Users
=====

ID  | Username | Email              | Role           | Status
----|----------|--------------------|-----------------|---------
1   | admin    | admin@zpms.com     | administrator  | Active
2   | doctor   | doctor@zpms.com    | user           | Active
```

#### `user:role`
Assign role to user.

**Usage:**
```bash
php cli.php user:role doctor administrator
php cli.php user:role nurse power-user
```

---

### Development Commands

**File:** `fw/core/cli/commands/DevCommands.php`

#### `dev:route`
Generate route handler.

**Usage:**
```bash
php cli.php dev:route patient_view
```

**Generated:**
- Route entry in `settings.info.yaml`
- Handler function in `index.php`
- Template file in `web/templates/content/`

#### `dev:entity`
Generate entity class from template.

**Usage:**
```bash
php cli.php dev:entity appointment
```

**Generated:**
- YAML schema in `web/classes/yaml/`
- Entity class in `web/classes/`
- Ex class in `web/ClassesEx.php`

#### `dev:test`
Run test suite.

**Usage:**
```bash
php cli.php dev:test
php cli.php dev:test --filter=TranslationTest
```

**Options:**
- `--filter=pattern` - Filter tests
- `--coverage` - Generate coverage report

#### `dev:lint`
Check code style.

**Usage:**
```bash
php cli.php dev:lint
php cli.php dev:lint --fix
```

**Options:**
- `--fix` - Automatically fix issues
- `--strict` - Strict mode

---

## Interactive Mode

**File:** `fw/core/cli/CLIInteractive.php`

### Purpose
Provide an interactive shell for ZPMS administration.

### Usage
```bash
php cli.php interactive
```

### Features
- **Command History** - Up/down arrows to navigate history
- **Tab Completion** - Auto-complete commands
- **Multi-line Input** - Support for complex commands
- **Session Variables** - Store values between commands

### Example Session
```
╔════════════════════════════════════════════╗
║   ZPMS Command Line Interface v1.0         ║
║   Zeus Patient Management System           ║
╚════════════════════════════════════════════╝

Type 'help' for available commands or 'exit' to quit.

zpms> translation:stats
Translation Statistics
======================

Language | Total | Translated | Missing | Coverage
---------|-------|------------|---------|----------
en       | 350   | 350        | 0       | 100.0%
el       | 350   | 320        | 30      | 91.4%

zpms> cache:clear
✓ Template cache cleared
✓ Render array cache cleared
✓ Translation cache cleared

zpms> exit
Goodbye!
```

### Implementation
```php
class CLIInteractive {
    protected $cli;
    protected $history = [];
    protected $variables = [];

    public function start() {
        $this->showWelcome();

        while (true) {
            $input = readline("zpms> ");

            if ($input === 'exit' || $input === 'quit') {
                $this->showGoodbye();
                break;
            }

            if (empty($input)) {
                continue;
            }

            readline_add_history($input);
            $this->execute($input);
        }
    }

    protected function execute($input) {
        // Parse and execute command
        // Handle errors gracefully
        // Display output
    }

    protected function showWelcome() {
        // Display banner
    }

    protected function showGoodbye() {
        echo "Goodbye!\n";
    }
}
```

---

## Scheduled Command Runner

**File:** `cron/cli-scheduler.php`

### Purpose
Automate CLI commands via cron scheduling.

### Configuration
```php
<?php
/**
 * CLI Scheduler for automated tasks
 * Run via cron: 0 * * * * php /var/www/html/apps/zpms/cron/cli-scheduler.php
 */

require_once __DIR__ . '/../cli.php';

// Define scheduled commands
$schedule = [
    // Every hour: cleanup temporary files
    '0 * * * *' => 'file:cleanup temp',

    // Daily at 2 AM: clear cache
    '0 2 * * *' => 'cache:clear',

    // Daily at 3 AM: database backup
    '0 3 * * *' => 'db:export /backups/zpms-' . date('Y-m-d') . '.sql --gzip',

    // Weekly on Sunday at 4 AM: check file integrity
    '0 4 * * 0' => 'file:verify',

    // Weekly on Sunday at 5 AM: find orphaned files
    '0 5 * * 0' => 'file:orphans --older-than=30d',

    // Monthly on 1st: export translations
    '0 6 1 * *' => 'translation:export el /backups/translations/el-' . date('Y-m') . '.yaml',
    '0 6 1 * *' => 'translation:export en /backups/translations/en-' . date('Y-m') . '.yaml',
];

foreach ($schedule as $timing => $command) {
    if (shouldRunCommand($timing)) {
        echo "[" . date('Y-m-d H:i:s') . "] Running: $command\n";

        $args = array_merge(['cli.php'], explode(' ', $command));
        $exitCode = $cli->run($args);

        if ($exitCode === 0) {
            echo "[" . date('Y-m-d H:i:s') . "] ✓ Success\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] ✗ Failed (exit code: $exitCode)\n";
        }
    }
}

function shouldRunCommand($cronExpression) {
    // Parse cron expression and check if should run now
    // Implementation of cron expression parser
    return true; // Placeholder
}
```

### Crontab Entry
```bash
# ZPMS CLI Scheduler
0 * * * * php /var/www/html/apps/zpms/cron/cli-scheduler.php >> /var/log/zpms-cli.log 2>&1
```

---

## Configuration

**File:** `config/settings.info.yaml`

Add CLI configuration section:

```yaml
cli:
  # Enable CLI component
  enabled: true

  # Command namespace
  command_namespace: 'ZPMS\\CLI\\Commands'

  # Custom command directories
  command_directories:
    - 'web/cli/commands'

  # Command aliases
  aliases:
    t:export: translation:export
    t:import: translation:import
    t:stats: translation:stats
    t:missing: translation:missing
    cc: cache:clear
    fc: file:cleanup

  # Output formatting
  output:
    colors: true
    verbose: false
    timestamps: false
    format: text  # text, json, yaml

  # Security
  security:
    require_confirmation:
      - db:import
      - file:orphans --delete
      - cache:clear all

    disabled_commands: []

  # Scheduler
  scheduler:
    enabled: true
    log_file: '/var/log/zpms-cli.log'
    email_on_error: admin@zpms.com
```

---

## Implementation Plan

### Phase 1: Core CLI Framework (8-10 hours)

**Objective:** Build foundation for CLI system

**Tasks:**
1. Create `CLI.php` class
   - Command registration
   - Argument parsing
   - Command routing
   - Error handling
   - Help system

2. Create `CLICommand.php` base class
   - Abstract interface
   - Argument validation
   - Option/flag parsing
   - Common utilities

3. Create `CLIOutput.php` class
   - Color support
   - Table formatting
   - Progress bars
   - Prompts and confirmations

4. Create `cli.php` entry point
   - Bootstrap framework
   - Initialize CLI
   - Register commands
   - Handle execution

5. Test core functionality
   - Manual testing with dummy command
   - Verify argument parsing
   - Test output formatting

**Deliverables:**
- ✅ Core CLI classes
- ✅ Entry point script
- ✅ Basic help system
- ✅ Example command

---

### Phase 1.5: Migration of Deployment Scripts (1-2 hours)

**Objective:** Update existing deployment automation to use new CLI

**Context:** The `fw/bin/update.sh` script is a critical deployment automation tool that generates SQL schemas, creates PHP entity classes, updates database tables, and manages content feeders. It currently uses legacy `maker.php` commands that were migrated in Phase 1.

**Critical Issues Identified:**

1. **Missing --new Flag Implementation in tables:list**
   - **Status:** Flag declared but NOT implemented in DataCommands.php (lines 116-122)
   - **Impact:** CRITICAL - Script relies on this to detect tables that exist in YAML but not in database
   - **Required:** Implement logic to:
     - Scan YAML schema files in specified scope directory
     - Extract table names from each YAML file
     - Query database for existing tables
     - Return space-separated list of tables in YAML but NOT in database

2. **Feed Commands Use Positional Arguments**
   - **Current:** `php $MAKER --name $temp feed:clean`
   - **Required:** `php $MAKER feed:clean $temp --scope=web --app-dir=$BASEDIR`
   - **Impact:** 4 feed command calls need syntax update

3. **Command Name Migrations**
   - 15 command calls need updating to new CLI syntax
   - Add explicit `--scope=fw` or `--scope=web` parameters
   - Update CLI entry point path

**Tasks:**

1. **Implement Missing --new Flag** (30 minutes)
   - File: `fw/core/maker/cli/commands/DataCommands.php`
   - Location: Lines 116-122 (currently TODO)
   - Implementation:
     ```php
     if ($showNew) {
         // Get YAML-defined tables
         $yamlTables = [];
         $yamlDirs = [];
         if ($scope === 'fw' || $scope === 'all') {
             $yamlDirs['fw'] = $yaml_dir['fw'];
         }
         if ($scope === 'web' || $scope === 'all') {
             $yamlDirs['web'] = $yaml_dir['web'];
         }

         foreach ($yamlDirs as $scopeName => $dir) {
             if (is_dir($dir)) {
                 foreach (glob($dir . '/*.yaml') as $yamlFile) {
                     $yamlData = yaml_parse_file($yamlFile);
                     if ($yamlData && isset($yamlData['table']['name'])) {
                         $yamlTables[] = $yamlData['table']['name'];
                     }
                 }
             }
         }

         // Filter to show only tables in YAML but NOT in database
         $newTables = array_diff($yamlTables, $tables);

         if (!empty($newTables)) {
             echo implode(' ', $newTables);
         }
         return;
     }
     ```

2. **Update fw/bin/update.sh Script** (45 minutes)
   - Line 12: Update MAKER path to `$BASEDIR"/fw/bin/maker"`
   - 15 command updates:
     - `spill:sql:all` → `schema:generate-sql --all --scope=<fw|web>`
     - `spill:class:all` → `class:generate --all --scope=<fw|web>`
     - `update:bootstrap` → `schema:update-bootstrap --scope=<fw|web>`
     - `tables:new:fw` → `tables:list --new --scope=fw`
     - `tables:new:web` → `tables:list --new --scope=web`
     - `diff:sql:all` → `schema:diff --all --scope=<fw|web>`
     - `feed:clean` → `feed:clean $filename` (positional arg)
     - `feed:gen:yaml` → `feed:generate-yaml $filename` (positional arg)
     - `feed:load` → `feed:load $filename` (positional arg)
   - Add `--app-dir=$BASEDIR` to all commands for consistency

3. **Testing & Verification** (15 minutes)
   - Test `tables:list --new` flag with both scopes
   - Backup original script before modifications
   - Test individual commands before full script run
   - Verify script on development environment
   - Confirm all interactive prompts still work

**Command Mapping Reference:**

| Line(s) | Legacy Command | New Command |
|---------|----------------|-------------|
| 12 | `$BASEDIR"/web/core/maker/maker.php"` | `$BASEDIR"/fw/bin/maker"` |
| 21 | `spill:sql:all` | `schema:generate-sql --all --scope=fw` |
| 24 | `spill:class:all` | `class:generate --all --scope=fw` |
| 27 | `update:bootstrap` | `schema:update-bootstrap --scope=fw` |
| 33 | `tables:new:fw` | `tables:list --new --scope=fw` |
| 82 | `diff:sql:all` | `schema:diff --all --scope=fw` |
| 104 | `spill:sql:all` | `schema:generate-sql --all --scope=web` |
| 107 | `spill:class:all` | `class:generate --all --scope=web` |
| 110 | `update:bootstrap` | `schema:update-bootstrap --scope=web` |
| 113 | `tables:new:web` | `tables:list --new --scope=web` |
| 151 | `diff:sql:all` | `schema:diff --all --scope=web` |
| 183 | `--name $temp feed:clean` | `feed:clean $temp --scope=web` |
| 210, 213 | `--name $temp feed:gen:yaml` | `feed:generate-yaml $temp --scope=web` |
| 233 | `--name $temp feed:load` | `feed:load $temp --scope=web` |

**Deliverables:**
- ✅ --new flag fully implemented in DataCommands.php (lines 109-140)
- ✅ --missing flag fully implemented in DataCommands.php (lines 143-177)
- ✅ update.sh migrated to new CLI commands (all 15 commands updated)
- ✅ MAKER path updated to fw/bin/maker (line 12)
- ✅ All commands use new syntax with --scope parameter
- ✅ Feed commands use positional arguments instead of --name option

**Implementation Verified:**
- ✅ Line 12: `MAKER=$BASEDIR"/fw/bin/maker"`
- ✅ Line 21: `schema:generate-sql --all --scope=fw`
- ✅ Line 24: `class:generate --all --scope=fw`
- ✅ Line 27: `schema:update-bootstrap --scope=fw`
- ✅ Line 33: `tables:list --new --scope=fw`
- ✅ Line 82: `schema:diff --all --scope=fw`
- ✅ Line 104: `schema:generate-sql --all --scope=web`
- ✅ Line 107: `class:generate --all --scope=web`
- ✅ Line 110: `schema:update-bootstrap --scope=web`
- ✅ Line 113: `tables:list --new --scope=web`
- ✅ Line 151: `schema:diff --all --scope=web`
- ✅ Lines 183, 210, 213, 233: Feed commands with positional args

**Status:** ✅ **PHASE 1.5 COMPLETE**

**Risk Assessment:** LOW
- New CLI is production-ready and tested
- Backwards compatibility wrapper exists as fallback
- All critical deployment automation migrated successfully
- Clear rollback procedure available

---

### Phase 2: Translation Commands (4-6 hours)

**Objective:** Implement translation management commands

**Tasks:**
1. Create `TranslationCommands.php`
2. Implement `translation:export`
   - Integration with `dictionaryClassEx::exportToYAML()`
   - YAML/JSON format support
   - Metadata inclusion

3. Implement `translation:import`
   - Integration with `dictionaryClassEx::importFromYAML()`
   - Merge mode
   - Dry-run mode
   - Backup creation

4. Implement `translation:stats`
   - Integration with `dictionaryClassEx::getTranslationStats()`
   - Table output
   - JSON output option

5. Implement `translation:missing`
   - Integration with `dictionaryClassEx::getUntranslated()`
   - Export to file
   - Show English reference

6. Test translation commands
   - Export all languages
   - Import sample file
   - Verify statistics
   - Check missing translations

**Deliverables:**
- ✅ Translation command class
- ✅ All translation commands implemented
- ✅ Integration with existing dictionary system
- ✅ Test exports/imports

---

### Phase 3: File Management Commands (4-6 hours)

**Objective:** Implement file management commands

**Tasks:**
1. Create `FileCommands.php`
2. Implement `file:cleanup`
   - Integration with `TemporaryFileManager`
   - Integration with `CacheFileManager`
   - Age-based filtering
   - Dry-run mode

3. Implement `file:verify`
   - SHA-256 verification
   - Metadata comparison
   - Integrity reporting

4. Implement `file:usage`
   - Reference counting
   - Size reporting
   - Table output

5. Implement `file:orphans`
   - Zero-reference detection
   - Delete option
   - Archive option

6. Test file commands
   - Cleanup temporary files
   - Verify file integrity
   - Check usage reports
   - Find orphans

**Deliverables:**
- ✅ File command class
- ✅ All file commands implemented
- ✅ Integration with FileManager
- ✅ Test cleanup and verification

---

### Phase 4: System Commands (6-8 hours)

**Objective:** Implement cache, database, module, and user commands

**Tasks:**
1. Create `CacheCommands.php`
   - Implement `cache:clear`
   - Implement `cache:rebuild`
   - Implement `cache:stats`

2. Create `DatabaseCommands.php`
   - Implement `db:export`
   - Implement `db:import`
   - Implement `db:generate-entities`
   - Implement `db:status`

3. Create `ModuleCommands.php`
   - Implement `module:list`
   - Implement `module:enable`
   - Implement `module:disable`
   - Implement `module:generate`

4. Create `UserCommands.php`
   - Implement `user:create`
   - Implement `user:password`
   - Implement `user:list`
   - Implement `user:role`

5. Test system commands
   - Test cache operations
   - Test database backup/restore
   - Test module operations
   - Test user management

**Deliverables:**
- ✅ Cache command class
- ✅ Database command class
- ✅ Module command class
- ✅ User command class
- ✅ All commands tested

---

### Phase 5: Interactive Mode (4-5 hours)

**Objective:** Implement interactive CLI shell

**Tasks:**
1. Create `CLIInteractive.php`
   - Interactive loop
   - Command parsing
   - History support
   - Tab completion

2. Implement session variables
   - Variable storage
   - Variable access in commands

3. Implement welcome/goodbye screens
   - ASCII art banner
   - Version information
   - Usage tips

4. Test interactive mode
   - Test command execution
   - Test history navigation
   - Test tab completion
   - Test session variables

**Deliverables:**
- ✅ Interactive mode class
- ✅ Command history
- ✅ Tab completion
- ✅ Session variables

---

### Phase 6: Scheduled Commands & Documentation (3-4 hours)

**Objective:** Implement scheduler and create documentation

**Tasks:**
1. Create `cli-scheduler.php`
   - Cron expression parser
   - Command scheduling
   - Logging
   - Error notifications

2. Create CLI user guide (`docs/CLI_USAGE.md`)
   - Installation instructions
   - Basic usage
   - Common workflows
   - Examples

3. Create command reference (`docs/CLI_COMMANDS_REFERENCE.md`)
   - Complete command list
   - Usage examples
   - Option reference
   - Exit codes

4. Write unit tests
   - Core CLI tests
   - Command tests
   - Output formatting tests

5. Integration testing
   - Test full workflows
   - Test error handling
   - Test scheduler

**Deliverables:**
- ✅ CLI scheduler
- ✅ User guide
- ✅ Command reference
- ✅ Test suite
- ✅ Crontab examples

---

## Estimated Effort

### Total Time: 30-41 hours (approximately 1 week)

| Phase | Hours | Description |
|-------|-------|-------------|
| Phase 1 | 8-10 | Core CLI Framework |
| **Phase 1.5** | **1-2** | **Migration of Deployment Scripts** |
| Phase 2 | 4-6 | Translation Commands |
| Phase 3 | 4-6 | File Management Commands |
| Phase 4 | 6-8 | System Commands |
| Phase 5 | 4-5 | Interactive Mode |
| Phase 6 | 3-4 | Scheduler & Documentation |
| **Total** | **30-41** | **Complete CLI Implementation** |

### Implementation Order

**Recommended sequence:**
1. Phase 1 (Core) - Foundation required for all other phases
2. **Phase 1.5 (Migration) - CRITICAL: Complete CLI migration by updating deployment automation**
3. Phase 2 (Translation) - High-value commands, integrates with recent work
4. Phase 3 (File) - High-value commands, integrates with Phase 9
5. Phase 4 (System) - Essential administrative commands
6. Phase 5 (Interactive) - Nice-to-have enhancement
7. Phase 6 (Scheduler) - Automation and documentation

---

## Files to Create

### Core Framework
1. `fw/core/cli/CLI.php` - CLI manager
2. `fw/core/cli/CLICommand.php` - Command base class
3. `fw/core/cli/CLIOutput.php` - Output formatter
4. `fw/core/cli/CLIInteractive.php` - Interactive mode

### Command Classes
5. `fw/core/cli/commands/TranslationCommands.php` - Translation management
6. `fw/core/cli/commands/FileCommands.php` - File management
7. `fw/core/cli/commands/CacheCommands.php` - Cache management
8. `fw/core/cli/commands/DatabaseCommands.php` - Database operations
9. `fw/core/cli/commands/ModuleCommands.php` - Module management
10. `fw/core/cli/commands/UserCommands.php` - User management
11. `fw/core/cli/commands/DevCommands.php` - Development tools

### Entry Points
12. `cli.php` - CLI entry point (root directory)
13. `cron/cli-scheduler.php` - Scheduled command runner

### Documentation
14. `docs/CLI_USAGE.md` - User guide
15. `docs/CLI_COMMANDS_REFERENCE.md` - Command reference

### Tests
16. `web/test/cli/CLITest.php` - Core CLI tests
17. `web/test/cli/CommandTest.php` - Command tests
18. `web/test/cli/OutputTest.php` - Output tests

### Configuration
19. Modify `config/settings.info.yaml` - Add CLI configuration section

---

## Integration with Existing Systems

### Translation System Integration

**Integration Points:**
- `dictionaryClassEx::exportToYAML($lang)` - Export translations
- `dictionaryClassEx::importFromYAML($lang, $file)` - Import translations
- `dictionaryClassEx::getTranslationStats()` - Get statistics
- `dictionaryClassEx::getUntranslated($lang)` - Get missing translations
- `MultilingualManager` - Translation operations

**Benefits:**
- Automated translation backups
- Bulk import/export workflows
- Coverage monitoring
- Missing translation reports

---

### File Management Integration

**Integration Points:**
- `FileManager` - File operations
- `TemporaryFileManager::cleanup()` - Temp file cleanup
- `CacheFileManager::cleanup()` - Cache file cleanup
- Stream wrapper URIs - File path resolution

**Benefits:**
- Scheduled file cleanup
- Integrity verification
- Usage tracking
- Orphan detection

---

### Database Integration

**Integration Points:**
- `sql/mysqldump.sh` - Database export
- `sql/mysql.sh` - Database import
- Zeus Maker - Entity generation
- PDO - Database queries

**Benefits:**
- Automated backups
- Entity regeneration
- Database maintenance
- Status monitoring

---

### Cache Integration

**Integration Points:**
- Template cache directory
- Render array cache
- Translation cache
- Custom cache implementations

**Benefits:**
- Cache clearing automation
- Cache rebuild on deployment
- Performance monitoring

---

## Security Considerations

### Command Execution
- **Validation** - All arguments validated before execution
- **Confirmation** - Destructive operations require confirmation
- **Logging** - All commands logged with timestamp and user
- **Exit Codes** - Standardized exit codes for monitoring

### File Access
- **Permission Checks** - Verify file permissions before operations
- **Path Validation** - Prevent directory traversal attacks
- **Stream Wrappers** - Use stream wrapper abstraction for safety

### Database Operations
- **Backups** - Always backup before destructive operations
- **Confirmation** - Import operations require explicit confirmation
- **Dry-run Mode** - Test operations before execution

### User Management
- **Password Security** - Passwords hashed with bcrypt
- **Role Validation** - Verify role exists before assignment
- **Audit Logging** - Log all user management operations

---

## Testing Strategy

### Unit Tests

**Test Core CLI:**
```php
class CLITest extends PHPUnit\Framework\TestCase {
    public function testCommandRegistration()
    public function testCommandExecution()
    public function testArgumentParsing()
    public function testErrorHandling()
    public function testHelpSystem()
}
```

**Test Commands:**
```php
class TranslationCommandsTest extends PHPUnit\Framework\TestCase {
    public function testExportCommand()
    public function testImportCommand()
    public function testStatsCommand()
    public function testMissingCommand()
}
```

**Test Output:**
```php
class CLIOutputTest extends PHPUnit\Framework\TestCase {
    public function testColorOutput()
    public function testTableFormatting()
    public function testProgressBar()
    public function testPrompts()
}
```

### Integration Tests

**Test Workflows:**
- Export translations → verify file → import translations
- Cleanup temp files → verify deletion
- Create user → assign role → list users
- Clear cache → rebuild cache → verify

### Manual Testing

**Test Checklist:**
- [ ] All commands execute without errors
- [ ] Help text displays correctly
- [ ] Color output works in terminal
- [ ] Progress bars display correctly
- [ ] Confirmation prompts work
- [ ] Error messages are clear
- [ ] Exit codes are correct
- [ ] Logging works
- [ ] Scheduler executes commands
- [ ] Interactive mode functions

---

## Usage Examples

### Translation Management

**Daily Workflow:**
```bash
# Check translation coverage
php cli.php translation:stats

# Find missing translations
php cli.php translation:missing el

# Export translations for backup
php cli.php translation:export el /backups/el-$(date +%Y%m%d).yaml

# Import updated translations
php cli.php translation:import el /translations/el-updated.yaml --merge
```

### File Management

**Weekly Maintenance:**
```bash
# Clean up old temporary files
php cli.php file:cleanup temp --older-than=7d

# Clean up cache files
php cli.php file:cleanup cache --older-than=30d

# Verify file integrity
php cli.php file:verify

# Find orphaned files
php cli.php file:orphans --older-than=30d
```

### Database Operations

**Backup Routine:**
```bash
# Daily backup
php cli.php db:export /backups/daily/zpms-$(date +%Y%m%d).sql --gzip

# Weekly full backup
php cli.php db:export /backups/weekly/zpms-$(date +%Y-W%V).sql --gzip

# Check database status
php cli.php db:status
```

### Cache Management

**Deployment Routine:**
```bash
# Clear all caches after deployment
php cli.php cache:clear

# Rebuild template cache
php cli.php cache:rebuild template

# Check cache statistics
php cli.php cache:stats
```

### User Management

**User Administration:**
```bash
# Create admin user
php cli.php user:create admin admin@zpms.com password123 --role=administrator

# Change password
php cli.php user:password doctor new_secure_password

# List all users
php cli.php user:list

# Assign role
php cli.php user:role doctor administrator
```

---

## Benefits

### For Administrators
1. **Automation** - Schedule routine tasks via cron
2. **Efficiency** - Bulk operations faster than web UI
3. **Remote Access** - SSH access without web interface
4. **Monitoring** - Script-friendly output for monitoring tools
5. **Backups** - Automated backup routines

### For Developers
1. **Code Generation** - Scaffold routes, entities, modules
2. **Testing** - Run tests from command line
3. **Debugging** - Direct access to framework internals
4. **Deployment** - Post-deployment scripts
5. **Maintenance** - Cache clearing, entity regeneration

### For Operations
1. **Reliability** - Scheduled tasks run automatically
2. **Monitoring** - Exit codes for monitoring systems
3. **Logging** - Centralized command logging
4. **Notifications** - Email alerts on errors
5. **Documentation** - Self-documenting help system

---

## Future Enhancements

### Phase 7 (Future): Advanced Features

**Possible additions:**
- **Job Queue** - Background job processing
- **Cron Builder** - Visual cron expression builder
- **Remote Execution** - Execute commands on remote servers
- **Audit Log** - Detailed command audit trail
- **API Integration** - REST API for command execution
- **Web UI** - Web-based CLI terminal
- **Command Chaining** - Pipe commands together
- **Plugins** - Third-party command plugins

### Phase 8 (Future): AI Integration

**Possible additions:**
- **Natural Language** - "backup the database" → `db:export`
- **Auto-translate** - Automatic translation via API
- **Smart Suggestions** - Context-aware command suggestions
- **Error Recovery** - AI-assisted error resolution

---

## Approval & Next Steps

### Decision Points

**Before Implementation:**
1. **Approve overall architecture** - Core framework design
2. **Prioritize command categories** - Which commands first?
3. **Set timeline** - 1 week full-time or 2-3 weeks part-time?
4. **Resource allocation** - Who will implement?

### Implementation Approach

**Option 1: All at Once (1 week)**
- Implement all phases sequentially
- Complete CLI system in one sprint
- Immediate full functionality

**Option 2: Incremental (2-3 weeks)**
- Phase 1 Week 1: Core + Translation
- Phase 2 Week 2: File + System
- Phase 3 Week 3: Interactive + Scheduler

**Option 3: MVP First (3-4 days)**
- Core framework only
- Translation commands only
- Basic documentation
- Expand later based on usage

### Recommended Approach

**Start with MVP (Option 3):**
- Validates architecture quickly
- Provides immediate value (translation commands)
- Allows feedback before full investment
- Can expand based on actual needs

---

## Conclusion

This CLI implementation plan provides ZPMS with a comprehensive command-line interface that:

✅ Integrates seamlessly with existing systems
✅ Enables automation via scheduling
✅ Improves developer productivity
✅ Facilitates system administration
✅ Supports operational workflows

**Total Effort:** 30-41 hours (1 week full-time)
**Immediate Value:** Translation and file management automation
**Long-term Value:** Complete administrative CLI toolkit

**Recommendation:** Approve for implementation starting with Phase 1 (Core Framework) and Phase 2 (Translation Commands) as MVP.

---

## Current Status

**Plan Status:** ✅ Updated and verified
**Phase 1 Status:** ✅ **COMPLETE** - Core CLI Framework implemented
**Phase 1.5 Status:** ✅ **COMPLETE** - Deployment script migration complete

**Completed Work:**
- ✅ Core CLI framework in `fw/core/maker/cli/`
- ✅ All Phase 1 commands implemented and tested
- ✅ `tables:list --new` flag fully functional (DataCommands.php:109-140)
- ✅ `tables:list --missing` flag fully functional (DataCommands.php:143-177)
- ✅ `fw/bin/update.sh` fully migrated to new CLI commands
- ✅ All 15 command calls updated with new syntax
- ✅ Feed commands using positional arguments
- ✅ Backwards compatibility maintained

**Ready for:** Phase 2 - Translation Commands
**Next Step:** Begin implementing Phase 2 translation management commands:
1. Create `TranslationCommands.php` (if not exists)
2. Implement `translation:export` command
3. Implement `translation:import` command
4. Implement `translation:stats` command
5. Implement `translation:missing` command
6. Implement `translation:sync` command

**Estimated Time for Phase 2:** 4-6 hours
