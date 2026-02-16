# Update fw/bin/update.sh for New Maker CLI

## Context

The `fw/bin/update.sh` script is a critical deployment automation script that:
- Generates SQL schemas from YAML files
- Creates PHP entity classes
- Updates database tables
- Manages content feeders
- Provides interactive prompts for safe database modifications

The script currently uses the **legacy maker.php** commands. With the completion of **Phase 1 CLI migration**, all these commands have been migrated to the new modular CLI architecture with new command names and syntax.

**The task:** Update `update.sh` to use the new CLI commands while maintaining all existing functionality.

## Current Script Analysis

### Commands Used in update.sh

| Line(s) | Legacy Command | Usage Context |
|---------|----------------|---------------|
| 21 | `spill:sql:all` | Framework: Generate all SQL from YAML schemas |
| 24 | `spill:class:all` | Framework: Generate all PHP entity classes |
| 27 | `update:bootstrap` | Framework: Update bootstrap_classes.php |
| 33 | `tables:new:fw` | Framework: List new tables not in database |
| 82 | `diff:sql:all` | Framework: Show schema vs database differences |
| 104 | `spill:sql:all` | Application: Generate all SQL from YAML schemas |
| 107 | `spill:class:all` | Application: Generate all PHP entity classes |
| 110 | `update:bootstrap` | Application: Update bootstrap_classes.php |
| 113 | `tables:new:web` | Application: List new tables not in database |
| 151 | `diff:sql:all` | Application: Show schema vs database differences |
| 183 | `feed:clean` | Content: Clean old feed data |
| 210, 213 | `feed:gen:yaml` | Content: Generate feeder YAML files |
| 233 | `feed:load` | Content: Load feeder data into database |

### Options Used

- `--app-dir=$BASEDIR` - Specifies application directory (✅ Supported in new CLI)
- `--name $temp` - Used for feed commands (⚠️ Need to verify support)

## Command Mapping: Legacy → New CLI

Based on Phase 1 completion documentation:

| Legacy Command | New Command | Notes |
|----------------|-------------|-------|
| `spill:sql:all` | `schema:generate-sql --all --scope=<fw\|web>` | Add scope parameter |
| `spill:class:all` | `class:generate --all --scope=<fw\|web>` | Add scope parameter |
| `update:bootstrap` | `schema:update-bootstrap --scope=<fw\|web>` | Add scope parameter |
| `tables:new:fw` | `tables:list --scope=fw --new` | ⚠️ Check --new flag support |
| `tables:new:web` | `tables:list --scope=web --new` | ⚠️ Check --new flag support |
| `diff:sql:all` | `schema:diff --all --scope=<fw\|web>` | Add scope parameter |
| `feed:clean` | `feed:clean` | ✅ Same name |
| `feed:gen:yaml` | `feed:generate-yaml` | Simple rename |
| `feed:load` | `feed:load` | ✅ Same name |

## Issues Discovered & Solutions

### 1. ✅ VERIFIED: Tables:list --new Flag - REQUIRES IMPLEMENTATION

**Status:** The `--new` flag is **declared but NOT fully implemented**.

**Verification from DataCommands.php:**
- ✅ Line 53-56: `--new` option is declared with description "Show only new tables (not in YAML schemas)"
- ✅ Line 64: Handler accepts the flag: `$showNew = $input->option('new');`
- ❌ **Line 116-120: Implementation is TODO** - comment says "// TODO: Implement scope filtering"

**Impact:** This is **CRITICAL** for update.sh functionality. The script relies on this to:
- Lines 33, 113: Capture list of tables that exist in YAML but not in database
- Lines 39-70, 116-148: If list is not empty, prompt user to import them
- Control the entire database migration workflow

**Required Implementation:**
The `--new` flag logic must:
1. Scan YAML schema files in the specified scope directory
2. Extract table names from each YAML file
3. Query database for existing tables
4. Return only table names that are in YAML but NOT in database
5. Output should be space-separated table names (for bash capture)

### 2. ✅ VERIFIED: Feed Commands - USE ARGUMENTS NOT OPTIONS

**Status:** Feed commands use **positional arguments**, not `--name` option.

**Verification from FeedCommands.php:**
- `feed:generate-yaml` - Line 11: Takes filename as required ARGUMENT
- `feed:load` - Line 54: Takes filename as required ARGUMENT
- `feed:clean` - Line 86: Takes filename as required ARGUMENT

**Required Changes:**
```bash
# OLD (Lines 183, 210, 213, 233):
php $MAKER --name $temp feed:clean
php $MAKER --name $temp feed:gen:yaml
php $MAKER --name $temp feed:load

# NEW:
php $MAKER feed:clean $temp --app-dir=$BASEDIR --scope=web
php $MAKER feed:generate-yaml $temp --app-dir=$BASEDIR --scope=web
php $MAKER feed:load $temp --app-dir=$BASEDIR --scope=web
```

### 3. ✅ CONFIRMED: Scope Context in Script
- **Framework section** (lines 18-95): Works with `fw/core/classes` - needs `--scope=fw`
- **Application section** (lines 101-163): Works with `web/classes` - needs `--scope=web`
- **Content section** (lines 169-245): Works with `web/content` - needs `--scope=web`

### 4. ✅ CONFIRMED: New CLI Entry Point
- OLD: `$BASEDIR"/web/core/maker/maker.php"` (line 12)
- NEW: `$BASEDIR"/fw/bin/maker"`

## Implementation Plan

### Step 1: Implement Missing --new Flag in DataCommands.php

**File:** `fw/core/maker/cli/commands/DataCommands.php`

**Current code** (lines 116-122):
```php
foreach ($tables as $table) {
    // Filter by scope if needed
    if ($scope !== 'all') {
        // TODO: Implement scope filtering based on prefix or schema location
    }
    echo "  - $table\n";
}
```

**Required implementation:**
```php
// If --new flag is set, filter to show only tables not in YAML schemas
if ($showNew) {
    // Get list of tables defined in YAML files
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

    // Filter tables to show only those in YAML but NOT in database
    $newTables = array_diff($yamlTables, $tables);

    // Output as space-separated for bash capture
    if (!empty($newTables)) {
        echo implode(' ', $newTables);
    }
    return;
}
```

**Note:** The handler function needs access to `$yaml_dir` array from bootstrap. Verify it's passed via `use` clause.

### Step 2: Update Script Structure

**File:** `fw/bin/update.sh`

**Changes needed:**
1. Update `MAKER` variable to point to new CLI entry point (line 12)
2. Update all command calls with new syntax (13 occurrences)
3. Change feed command syntax from `--name` to positional argument
4. Maintain all interactive prompts and safety checks
5. Keep all bash logic intact (conditionals, loops, user prompts)

### Step 3: Update Script - Line by Line Changes

**Complete list of changes:**

| Line | OLD Command | NEW Command |
|------|-------------|-------------|
| 12 | `MAKER=$BASEDIR"/web/core/maker/maker.php"` | `MAKER=$BASEDIR"/fw/bin/maker"` |
| 21 | `php $MAKER --app-dir=$BASEDIR spill:sql:all` | `php $MAKER schema:generate-sql --all --scope=fw --app-dir=$BASEDIR` |
| 24 | `php $MAKER --app-dir=$BASEDIR spill:class:all` | `php $MAKER class:generate --all --scope=fw --app-dir=$BASEDIR` |
| 27 | `php $MAKER --app-dir=$BASEDIR update:bootstrap` | `php $MAKER schema:update-bootstrap --scope=fw --app-dir=$BASEDIR` |
| 33 | `php $MAKER --app-dir=$BASEDIR tables:new:fw` | `php $MAKER tables:list --new --scope=fw --app-dir=$BASEDIR` |
| 82 | `php $MAKER --app-dir=$BASEDIR diff:sql:all` | `php $MAKER schema:diff --all --scope=fw --app-dir=$BASEDIR` |
| 104 | `php $MAKER spill:sql:all` | `php $MAKER schema:generate-sql --all --scope=web --app-dir=$BASEDIR` |
| 107 | `php $MAKER spill:class:all` | `php $MAKER class:generate --all --scope=web --app-dir=$BASEDIR` |
| 110 | `php $MAKER  update:bootstrap` | `php $MAKER schema:update-bootstrap --scope=web --app-dir=$BASEDIR` |
| 113 | `php $MAKER --app-dir=$BASEDIR tables:new:web` | `php $MAKER tables:list --new --scope=web --app-dir=$BASEDIR` |
| 151 | `php $MAKER diff:sql:all` | `php $MAKER schema:diff --all --scope=web --app-dir=$BASEDIR` |
| 183 | `php $MAKER --name $temp feed:clean` | `php $MAKER feed:clean $temp --scope=web --app-dir=$BASEDIR` |
| 210 | `php $MAKER --name $temp feed:gen:yaml` | `php $MAKER feed:generate-yaml $temp --scope=web --app-dir=$BASEDIR` |
| 213 | `php $MAKER --name $temp feed:gen:yaml` | `php $MAKER feed:generate-yaml $temp --scope=web --app-dir=$BASEDIR` |
| 233 | `php $MAKER --name $temp  feed:load` | `php $MAKER feed:load $temp --scope=web --app-dir=$BASEDIR` |

**Notes on changes:**
- All commands now explicitly use `--app-dir=$BASEDIR` for consistency
- All commands now explicitly use `--scope=fw` or `--scope=web` as appropriate
- Feed commands changed from `--name $temp` to positional argument `$temp`
- Command names updated per Phase 1 migration mappings
- No changes to bash logic, conditionals, loops, or user prompts

## Verification & Testing Strategy

### Phase 1: Test --new Flag Implementation

After implementing the --new flag in DataCommands.php:

```bash
# Test framework tables listing with --new flag
php fw/bin/maker tables:list --new --scope=fw --app-dir=/var/www/html/apps/zpms

# Expected output: Space-separated list of table names that are in YAML but not in database
# If all tables exist: (empty output)
# If some are missing: "table1 table2 table3"

# Test application tables listing with --new flag
php fw/bin/maker tables:list --new --scope=web --app-dir=/var/www/html/apps/zpms
```

### Phase 2: Test Individual Command Conversions

Before running full update.sh, test each command individually:

**Schema commands:**
```bash
php fw/bin/maker schema:generate-sql --all --scope=fw --app-dir=/var/www/html/apps/zpms
php fw/bin/maker class:generate --all --scope=fw --app-dir=/var/www/html/apps/zpms
php fw/bin/maker schema:update-bootstrap --scope=fw --app-dir=/var/www/html/apps/zpms
php fw/bin/maker schema:diff --all --scope=fw --app-dir=/var/www/html/apps/zpms
```

**Feed commands** (from web/content directory):
```bash
cd /var/www/html/apps/zpms/web/content
temp="example.feeder.yaml"
php /var/www/html/apps/zpms/fw/bin/maker feed:clean $temp --scope=web --app-dir=/var/www/html/apps/zpms
php /var/www/html/apps/zpms/fw/bin/maker feed:generate-yaml $temp --scope=web --app-dir=/var/www/html/apps/zpms
php /var/www/html/apps/zpms/fw/bin/maker feed:load $temp --scope=web --app-dir=/var/www/html/apps/zpms
```

### Phase 3: Dry-Run Test of update.sh

After updating update.sh:

1. **Backup original script:**
   ```bash
   cp fw/bin/update.sh fw/bin/update.sh.backup
   ```

2. **Add dry-run mode** (optional) by prefixing database-modifying operations with `echo "[DRY-RUN]"`

3. **Run with echo to see commands:**
   - Temporarily add `set -x` at top of script to see all executed commands
   - Review that all command transformations are correct

### Phase 4: Full Integration Test

Run the updated script on a test/development environment:

```bash
cd /var/www/html/apps/zpms
bash fw/bin/update.sh
```

**Verify at each prompt:**
- Framework SQL generation completes without errors
- Framework class generation completes without errors
- Framework bootstrap updates successfully
- New table detection works (shows correct tables or empty if all exist)
- Table import prompts appear correctly
- Schema diff output is readable
- Application SQL generation completes
- Application class generation completes
- Application bootstrap updates
- Content feeder processing works
- Feed cleaning, generation, and loading all complete successfully

### Expected Outputs

**Tables:list --new output:**
- Should output space-separated table names, e.g., "users patients appointments"
- Should output nothing (empty) if all YAML tables exist in database
- Bash capture should work: `db_new_tables=`php $MAKER tables:list --new ...``

**Schema commands:**
- Should generate .sql files in correct directories (fw/core/classes/sql or web/classes/sql)
- Should generate .php class files in correct directories
- Should update bootstrap_classes.php files

**Feed commands:**
- Should process .feeder.yaml files in web/content
- Should load data into database
- Should output success/failure messages

### Rollback Plan

If issues occur during testing:
```bash
# Restore original script
cp fw/bin/update.sh.backup fw/bin/update.sh

# Use legacy wrapper (still functional)
MAKER=$BASEDIR"/fw/core/maker/maker.php"  # This still works via backwards compat wrapper
```

## Critical Files

### To Read:
- ✅ `fw/bin/update.sh` - The bash script to update
- ✅ `fw/docs/CLI_PHASE1_COMPLETE.md` - Command reference and migration guide
- ✅ `fw/core/maker/cli/commands/DataCommands.php` - Verified --new flag declared but not implemented
- ✅ `fw/core/maker/cli/commands/FeedCommands.php` - Verified commands use positional arguments

### To Modify:
1. **`fw/core/maker/cli/commands/DataCommands.php`** - Implement --new flag logic (lines 116-122)
2. **`fw/bin/update.sh`** - Update 15 lines with new CLI syntax

### To Create:
- `fw/bin/update.sh.backup` - Backup of original script (automatic before modification)

## Expected Outcome

After implementation:

**DataCommands.php:**
- ✅ `tables:list --new` flag fully functional
- ✅ Returns space-separated list of tables in YAML but not in database
- ✅ Works with --scope parameter (fw/web/all)
- ✅ Output format suitable for bash capture

**update.sh:**
- ✅ Uses new CLI commands exclusively (15 command updates)
- ✅ All existing functionality preserved
- ✅ Script works with `fw/bin/maker` entry point
- ✅ Proper scope parameters (`--scope=fw` or `--scope=web`)
- ✅ Feed commands use positional arguments instead of --name
- ✅ All interactive prompts still functional
- ✅ Database safety checks maintained
- ✅ Bash logic unchanged (conditionals, loops, user prompts)
- ✅ Backwards compatible (legacy wrapper still works as fallback)

## Risk Assessment

**Low Risk:**
- New CLI is production-ready and tested (Phase 1 complete)
- Backwards compatibility wrapper exists as fallback
- Script can be tested in isolation before deployment
- Only 2 files being modified, both well-documented

**Mitigation:**
- Automatic backup of original script before modification
- Test on development environment first
- All bash logic preserved unchanged
- Clear rollback procedure available
- Can test individual commands before running full script

---

## Summary

This update will complete the CLI migration by updating the critical `fw/bin/update.sh` deployment script to use the new modular CLI architecture. The implementation requires:

1. **One missing feature:** Implement the `--new` flag in `tables:list` command to detect tables that need to be created
2. **One script update:** Update `fw/bin/update.sh` with 15 command syntax changes

All changes are straightforward command name and syntax updates. No bash logic changes needed. The script's interactive prompts and safety checks remain intact. Total estimated time: 30-45 minutes.
