#!/bin/bash
#
# Update a Zeus installation: regenerate classes/SQL, apply schema changes,
# load content and webforms.
#
# Run from the installation root.  With no options every step asks, as it
# always did.  Every question has an option so the script can run unattended
# (see --help).

usage() {
cat <<'EOF'
Usage: bin/update.sh [options]        (run from the installation root)

Each step that used to ask a question has an option.  Values:
  ask   ask on the terminal (the default when a terminal is attached)
  yes   do it
  all   do it for every table/feeder/form, without asking one by one
  no    skip it

  --core-tables=ask|yes|all|no      import tables missing from the database (framework)
  --web-tables=ask|yes|all|no       import tables missing from the database (application)
  --core-diff=ask|yes|no            apply the framework database changes (diff:sql)
  --web-diff=ask|yes|no             apply the application database changes (diff:sql)
  --content=ask|yes|no              update database content (feeders)
  --generate-feeders=ask|yes|all|no generate the feeders' .yml files
  --clean-feeds=ask|yes|no          clean old feed data first
  --forms=ask|yes|all|no            load/refresh webforms

  --yes               answer yes to every question EXCEPT the two *-diff ones and
                      --clean-feeds, which change or empty live data and must be
                      given explicitly
  --non-interactive   never read from the terminal; any question not answered by
                      an option above is answered "no"
  --dry-run           print what would change (missing tables, database diffs)
                      and change nothing
  -h, --help          show this text

Exit status: 0 ok, 1 a step failed, 2 bad usage.
EOF
}

# ---- options ---------------------------------------------------------------

OPT_CORE_TABLES=""; OPT_WEB_TABLES=""; OPT_CORE_DIFF=""; OPT_WEB_DIFF=""
OPT_CONTENT=""; OPT_GENERATE=""; OPT_CLEAN=""; OPT_FORMS=""
OPT_YES=0; NONINTERACTIVE=0; DRYRUN=0

check_value() {   # name value allowed...
    local name=$1 value=$2; shift 2
    for a in "$@"; do [[ $value == "$a" ]] && return 0; done
    echo "update.sh: --$name must be one of: $*" >&2
    exit 2
}

while [[ $# -gt 0 ]]; do
    case $1 in
        --core-tables=*)      OPT_CORE_TABLES=${1#*=}; check_value core-tables "$OPT_CORE_TABLES" ask yes all no ;;
        --web-tables=*)       OPT_WEB_TABLES=${1#*=};  check_value web-tables  "$OPT_WEB_TABLES"  ask yes all no ;;
        --core-diff=*)        OPT_CORE_DIFF=${1#*=};   check_value core-diff  "$OPT_CORE_DIFF"  ask yes no ;;
        --web-diff=*)         OPT_WEB_DIFF=${1#*=};    check_value web-diff   "$OPT_WEB_DIFF"   ask yes no ;;
        --content=*)          OPT_CONTENT=${1#*=};     check_value content    "$OPT_CONTENT"    ask yes no ;;
        --generate-feeders=*) OPT_GENERATE=${1#*=};    check_value generate-feeders "$OPT_GENERATE" ask yes all no ;;
        --clean-feeds=*)      OPT_CLEAN=${1#*=};       check_value clean-feeds "$OPT_CLEAN" ask yes no ;;
        --forms=*)            OPT_FORMS=${1#*=};       check_value forms      "$OPT_FORMS"      ask yes all no ;;
        --yes)                OPT_YES=1 ;;
        --non-interactive)    NONINTERACTIVE=1 ;;
        --dry-run)            DRYRUN=1 ;;
        -h|--help)            usage; exit 0 ;;
        *) echo "update.sh: unknown option: $1" >&2; usage >&2; exit 2 ;;
    esac
    shift
done

# A dry run never waits for anybody.
[[ $DRYRUN == 1 ]] && NONINTERACTIVE=1

# decide <option value> <prompt> <yn|yna> <diff:0|1>
# Sets DECISION to y (yes, ask one by one where that makes sense), a (all) or n.
decide() {
    local value=$1 prompt=$2 kind=$3 isdiff=${4:-0} answer
    if [[ -z $value ]]; then
        if [[ $OPT_YES == 1 && $isdiff == 0 ]]; then value=all
        elif [[ $NONINTERACTIVE == 1 ]]; then value=no
        else value=ask; fi
    fi
    case $value in
        yes|all) [[ $kind == yna ]] && DECISION=a || DECISION=y; return ;;
        no)      DECISION=n; return ;;
    esac
    # ask
    if [[ $NONINTERACTIVE == 1 ]]; then DECISION=n; return; fi
    read -r -p "$prompt" answer
    echo
    case $answer in
        [yY]) DECISION=y ;;
        [aA]) [[ $kind == yna ]] && DECISION=a || DECISION=y ;;
        *)    DECISION=n ;;
    esac
}

# ask_item <prompt>: per-item question after a plain "y" (only in ask mode)
ask_item() {
    local answer
    [[ $NONINTERACTIVE == 1 ]] && return 1
    read -r -p "$1" answer
    [[ $answer == [yY] ]]
}

echo Update Zeus installation
[[ $DRYRUN == 1 ]] && echo "(dry run: nothing will be changed)"

if [ ! -f web/core/bootstrap.php ]; then
    echo "Please execute this script from the installation root folder"
    exit 1
fi

#MAKER="../web/core/maker/maker.php"
BASEDIR=`pwd`
MAKER=$BASEDIR"/web/core/maker/maker.php"
FAILED=0

# run a command that changes things; in a dry run only say what it would do
run() {
    if [[ $DRYRUN == 1 ]]; then echo "[dry-run] would run: $*"; return 0; fi
    "$@"
}

tfile="$(mktemp /tmp/sql-temp.XXXXXXXX)" || exit 1
trap 'rm -f "$tfile"' EXIT

# import_tables <label> <sqldir> <option value> <missing tables...>
import_tables() {
    local label=$1 sqldir=$2 value=$3; shift 3
    local missing="$*" temp sqlfile tableimport
    [[ -z $missing ]] && return 0
    echo "The following $label tables are missing from the database: " $missing
    if [[ $DRYRUN == 1 ]]; then echo "[dry-run] would offer to import them"; return 0; fi

    decide "$value" "Do you want to import the above tables? [y/N/all] " yna
    local mode=$DECISION
    [[ $mode == n ]] && return 0
    for temp in $missing; do
        if [[ $mode == y ]]; then
            ask_item "Do you want to import table $temp? [y/N] " && tableimport=y || tableimport=n
        else
            tableimport=y
        fi
        [[ $tableimport == y ]] || continue
        sqlfile="$sqldir/$temp.sql"
        if [ -f "$sqlfile" ]; then
            echo "Importing table $temp from $sqlfile"
            if "$BASEDIR/sql/msql.sh" < "$sqlfile"; then
                echo "Import was successful"
            else
                echo "Import failed"; FAILED=1
            fi
        else
            echo "Could not find SQL file for table $temp in file $sqlfile"
        fi
    done
}

# apply_diff <label> <option value>: $tfile holds the generated diff
apply_diff() {
    local label=$1 value=$2
    cat "$tfile"
    if [[ ! -s $tfile ]]; then echo "No $label database changes."; return 0; fi
    if [[ $DRYRUN == 1 ]]; then echo "[dry-run] would offer to apply the $label changes above"; return 0; fi
    decide "$value" "Do you want to apply the above changes in the database? (y/N) (Ctrl-C to exit) " yn 1
    if [[ $DECISION == y ]]; then
        echo "Updating database"
        if ! "$BASEDIR/sql/msql.sh" < "$tfile"; then echo "Applying the $label changes failed"; FAILED=1; fi
    else
        echo "$label database changes NOT applied"
    fi
}

echo 'Updating in `core` folders...'

# update classes in framework
pushd ./web/core/classes >/dev/null

echo "core -> spill:sql:all"
run php $MAKER --app-dir=$BASEDIR spill:sql:all

echo "core -> spill:class:all"
run php $MAKER --app-dir=$BASEDIR spill:class:all

echo "core -> update:bootstrap"
run php $MAKER --app-dir=$BASEDIR update:bootstrap

# now table structures are in correct places

# try to make tables if they do not exist in the database
db_new_fw_tables=`php $MAKER --app-dir=$BASEDIR tables:new:fw`
import_tables core "$BASEDIR/web/core/classes/sql" "$OPT_CORE_TABLES" $db_new_fw_tables

# try to remove tables if they do not exist in the file structure
## TODO

echo "core -> diff:sql:all"
echo "php $MAKER --app-dir=$BASEDIR diff:sql:all"
php $MAKER --app-dir=$BASEDIR diff:sql:all > "$tfile"
apply_diff core "$OPT_CORE_DIFF"

popd >/dev/null


echo 'Updating in `application` folders...'

# update classes in userspace
pushd web/classes >/dev/null

echo "web -> spill:sql:all"
run php $MAKER spill:sql:all

echo "web -> spill:class:all"
run php $MAKER spill:class:all

echo "web -> update:bootstrap"
run php $MAKER update:bootstrap

db_new_web_tables=`php $MAKER --app-dir=$BASEDIR tables:new:web`
import_tables application "$BASEDIR/web/classes/sql" "$OPT_WEB_TABLES" $db_new_web_tables

php $MAKER diff:sql:all > "$tfile"
apply_diff application "$OPT_WEB_DIFF"

popd >/dev/null


decide "$OPT_CONTENT" "Do you want to update content? (y/N) (Ctrl-C to exit) " yn
if [[ $DRYRUN == 1 ]]; then
    echo "[dry-run] would skip content, feeders and webforms"
elif [[ $DECISION == y ]]; then

    # generate: n = no, a = for every feeder, y = ask for each feeder
    case ${OPT_GENERATE:-$([[ $OPT_YES == 1 ]] && echo all || ([[ $NONINTERACTIVE == 1 ]] && echo no || echo ask))} in
        yes|all) generate=a ;;
        no)      generate=n ;;
        *)
            read -r -p "Do you want to generate feeders' .yml files? [y/N/ask] " ans2
            case $ans2 in [yY]) generate=a ;; [aA]|ask) generate=y ;; *) generate=n ;; esac
            ;;
    esac

    echo "Updating database content"
    # update userspace content
    pushd $BASEDIR/web/content >/dev/null

    decide "$OPT_CLEAN" "Do you want to clean old feed data? [y/N] " yn 1
    feed_clean_no=0
    if [[ $DECISION == y ]]; then
        for temp in `ls *.feeder.yaml`; do
            if php $MAKER --name="$temp" feed:clean; then
                feed_clean_no=$((feed_clean_no+1))
            fi
        done
    fi

    for temp in `ls *.feeder.yaml`; do
        echo Processing feeder $temp

        schema=`cat $temp | awk ' /schema:/ { print $2 }' -`
        echo "Schema " $schema

        if [[ $generate == [ya] ]]; then
            if [[ $generate == y ]]; then
                # y at the prompt above = ask for each feeder
                ask_item "Do you want to update feeder $temp ? [y/N] " && confirm=y || confirm=n
            else
                confirm=y
            fi

            if [[ $confirm == y ]]; then
                if [[ -f ../classes/yaml/$schema ]]; then
                    echo "Creating feeder data $temp (app)"
                elif [[ -f ../core/classes/yaml/$schema ]]; then
                    echo "Creating feeder data $temp (core)"
                else
                    echo Could not locate schema file $schema. Aborting...
                    exit 1
                fi
                if php $MAKER --name="$temp" feed:gen:yaml; then
                    echo "Feed creation was successful"
                else
                    echo "Feed creation failed"
                    exit 1
                fi
            fi
        fi

        echo "Loading feeder data $temp"

        # cannot call feed:clean because it cleans all entries from the table
        if php $MAKER --name="$temp" feed:load; then
            echo "Feed loading succesfull"
        else
            echo "Feed loading failed"
            exit 1
        fi
    done

    echo "Cleaned $feed_clean_no feeds"

    popd >/dev/null
fi


echo 'Updating webforms...'

# Load/refresh the app-specific webforms whose `webforms` DB row a
# Settings-style page's formsClass::renderForm()/renderFormResults() needs
# to actually render (zpms: Clinics/Doctors, Operations -- see zpms's own
# README.md, "Settings page: Clinics/Doctors management"). A webform yaml
# is any web/classes/yaml/*.yaml with a top-level `form:` key -- scanned
# generically rather than by hardcoded name, since any app on the
# framework can define its own. Empty on an app with none, in which case
# nothing below prompts for anything.
webforms=`grep -l '^form:' $BASEDIR/web/classes/yaml/*.yaml 2>/dev/null | xargs -n1 basename 2>/dev/null | sed 's/\.yaml$//'`

if [[ ! -z $webforms ]]; then
    echo "The following webforms were found: " $webforms

    if [[ $DRYRUN == 1 ]]; then
        echo "[dry-run] would offer to update them"
    else
        decide "$OPT_FORMS" "Do you want to update forms? [y/N/all] " yna
        if [[ $DECISION != n ]]; then
            pushd web/classes >/dev/null
            for form in $webforms; do
                if [[ $DECISION == y ]]; then
                    ask_item "Do you want to update form $form? [y/N] " && formimport=y || formimport=n
                else
                    formimport=y
                fi
                if [[ $formimport == y ]]; then
                    echo "web -> form:load $form"
                    php $MAKER form:load yaml/$form.yaml || FAILED=1
                fi
            done
            popd >/dev/null
        fi
    fi
fi

# App-specific steps that need the freshly loaded content (erweb: drawing the
# static location maps from the clinic addresses). Opt-in: runs only if the
# app ships an executable bin/post-update.sh, so other apps are unaffected.
# Its failure is reported but doesn't undo or abort the update above.
if [[ $DRYRUN == 1 ]]; then
    [ -x "$BASEDIR/bin/post-update.sh" ] && echo "[dry-run] would run bin/post-update.sh"
elif [ -x "$BASEDIR/bin/post-update.sh" ]; then
    echo 'Running application post-update steps (bin/post-update.sh)...'
    if ! "$BASEDIR/bin/post-update.sh"; then
        echo "Warning: bin/post-update.sh reported a problem (see above); the rest of the update is complete."
    fi
fi

if [[ $FAILED == 1 ]]; then
    echo "Update finished WITH ERRORS (see above)"
    exit 1
fi
echo "Update finished"
exit 0
