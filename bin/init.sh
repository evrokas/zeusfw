#!/bin/bash
echo Zeus Framework Website builder
echo

# The old `cat $ifile | sed -s "s/<<host>>/$host/g" - | sed ... > $ofile`
# chain breaks the instant any credential contains a `/` -- sed's `s///`
# uses `/` as its delimiter, so e.g. a password of `Ab/c123` gets read as
# `s/<<pass>>/Ab` (delimiter), `c123` (a 4th, extra field), which sed
# rejects outright: "sed: -e expression #1, char N: unknown option to
# `s'". Confirmed directly against a real run. Worse than just an error
# message: the pipeline's stdout was empty when sed errored, so `> $ofile`
# still truncated the file to zero bytes -- and this block never checked
# sed's exit status at all, so it printed "$ofile created succesfully!"
# immediately after, while having just emptied a previously-working file.
# `&` (sed's "whole match" backreference in the replacement) and a literal
# backslash are the same class of hazard, just silent instead of a hard
# error -- a password containing either would have substituted something
# other than what was typed, with no error at all.
#
# First fix attempt was pure bash substitution (`${content//<<pass>>/$password}`,
# no sed at all) on the theory that literal string replacement has no
# delimiter/metacharacter hazard -- wrong, caught by testing an `&`
# password against it directly, not assumed safe: bash 5.2 (this sandbox's
# version) gives `&` in a parameter-substitution REPLACEMENT the exact
# same "insert whatever the pattern matched" meaning sed does (confirmed
# in isolation: `x="X"; r="A&B"; echo "${x/X/$r}"` prints `AXB`, not
# `A&B`) -- so a password containing `&` would have silently substituted
# the literal placeholder text (`<<pass>>`) into the credential instead of
# the `&`, the same "wrong file, no error" failure mode as the sed bug
# above, just via a different mechanism. A literal backslash in the
# replacement is *also* special (consumed as an escape character) even
# with no `&` nearby, confirmed the same way.
#
# Fixed the substitution-mechanism hazard by escaping both metacharacters
# in each value before it reaches the substitution -- backslash first
# (`\` -> `\\`), then ampersand (`&` -> `\&`, using the now-doubled
# backslash from the first step to escape it).
sed_bash_escape() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//&/\\&}"
    printf '%s' "$s"
}

# A THIRD, independent layer, found only by testing the fix above against
# a real live database, not by reasoning about the script in isolation:
# host/user/pass all land inside a single-quoted string literal in BOTH
# generated files (`'<<pass>>'` in admin.sql's SQL, `'<<pass>>'` in
# db.php's PHP) -- and MySQL's and PHP's own single-quoted-literal parsers
# each have their OWN escaping rules, applied when *they* read the file,
# completely independent of how the file got written. Confirmed directly:
# `sed_bash_escape` alone correctly writes a password containing `\&`
# byte-for-byte into both files, but MySQL string literals silently drop
# a backslash before any character it doesn't recognize as an escape
# sequence (confirmed against a real server: the literal bytes
# `'Re/check\&2026'` in a .sql file parse to the *value* `Re/check&2026`,
# backslash gone) -- so the account MySQL actually creates ends up with a
# different password than the byte-identical string db.php holds, and a
# real login with the "correct" (db.php's) password then fails outright
# (confirmed: `ERROR 1045 Access denied`, reproduced against a real
# MariaDB server before touching this). PHP's single-quoted strings have
# the same rule (only `\\` and `\'` are recognized escapes), so db.php has
# the identical exposure for a password containing a raw `'`.
#
# Fixed by escaping for that target-language literal FIRST (backslash
# doubled, single quote escaped -- the one rule both MySQL and PHP
# single-quoted strings share), then layering the bash-substitution
# escaping above on top of that already-escaped value, not the raw one --
# verified by actually letting MySQL and PHP parse the generated files
# back, not just diffing bytes, since correct output here is expected to
# differ from the raw input (a lone `\` legitimately becomes `\\` on disk).
# `database` is exempt -- it's the one placeholder that's never inside
# quotes in either template (`CREATE DATABASE IF NOT EXISTS <<db>>;`, a
# bare identifier), so literal-escaping it would be wrong, not just
# unnecessary; it still gets the bash-substitution layer like the others.
escape_for_quoted_literal() {
    local s="$1"
    s="${s//\\/\\\\}"
    s="${s//\'/\\\'}"
    printf '%s' "$s"
}

# Exact inverse of escape_for_quoted_literal() above, in the opposite
# order (undo the quote-escaping first, then the backslash-doubling --
# reversing operations in the opposite order they were applied, same as
# any composed transform). Used when re-reading an already-generated
# admin.sql to offer its real values back as this run's defaults.
unescape_quoted_literal() {
    local s="$1"
    s="${s//\\\'/\'}"
    s="${s//\\\\/\\}"
    printf '%s' "$s"
}

render_template() {
    local ifile="$1"
    local ofile="$2"
    local content
    local h u p d
    h=$(sed_bash_escape "$(escape_for_quoted_literal "$host")")
    u=$(sed_bash_escape "$(escape_for_quoted_literal "$username")")
    p=$(sed_bash_escape "$(escape_for_quoted_literal "$password")")
    d=$(sed_bash_escape "$database")
    content=$(<"$ifile")
    content="${content//<<host>>/$h}"
    content="${content//<<user>>/$u}"
    content="${content//<<pass>>/$p}"
    content="${content//<<db>>/$d}"
    printf '%s\n' "$content" > "$ofile"
}

cd sql

ifile=admin.sql.in
ofile=admin.sql

if [ ! -f $ifile ]; then
    echo "Please execute the script from the root folder"
    exit 1
fi

# Check if admin.sql exists
if [ -f $ofile ]; then
    # Read the SQL statement from the file
    sql=$(<$ofile)
    # echo $sql;

    # Extract values using regex with 'grep'. These come back as the RAW
    # on-disk SQL literal, e.g. a real backslash is 2 characters here
    # (`\\`) -- unescape_quoted_literal() below reverses exactly what
    # escape_for_quoted_literal() applies on write, so a value re-read as
    # this run's default is the real original again, not the escaped
    # form. Found by testing a re-run (accept every default) against a
    # password containing a backslash, not assumed: without this, the
    # escaped-on-disk bytes got fed straight back through
    # escape_for_quoted_literal() a second time on the next write,
    # doubling the backslash count on every successive re-run that
    # accepts the existing password unchanged. (Known, narrower,
    # pre-existing limitation this doesn't attempt to fix: a password
    # containing a literal `'` is re-extracted truncated at that quote,
    # since this grep's `[^']+` has no way to tell an escaped `\'` apart
    # from the literal closing quote -- unchanged from before today,
    # and only reachable by re-running init.sh against an already-saved
    # quote-containing password.)
    username_in=$(echo "$sql" | grep -oP "CREATE USER IF NOT EXISTS '\K[^']+(?='@)")
    host_in=$(echo "$sql" | grep -oP "@'\K[^']+(?=' IDENTIFIED)")
    password_in=$(echo "$sql" | grep -oP "IDENTIFIED BY '\K[^']+(?=')")
    database_in=$(echo "$sql" | grep -oP "CREATE DATABASE IF NOT EXISTS \K\w+(?=;)")
    username_in=$(unescape_quoted_literal "$username_in")
    host_in=$(unescape_quoted_literal "$host_in")
    password_in=$(unescape_quoted_literal "$password_in")

else
    host_in="localhost"
    username_in=""
    password_in=""
    database_in=""
fi

# Display extracted values
# echo "Username: $username"
# echo "Host: $host"
# echo "Password: $password"
# echo "Database: $database"


# `read -e -i "$default"` only pre-fills the shown default via GNU readline,
# which needs a real interactive TTY -- confirmed the hard way: piping answers
# into this script (or running it from a terminal readline treats as
# non-interactive, which several remote/CI-style shells do) makes the -i
# default silently vanish, so pressing Enter to "accept" the bracketed
# default produces an EMPTY string instead, not the value shown -- every
# one of the 4 prompts below reproduced this, not just one. That empty
# string then flows straight into admin.sql/db.php's sed substitution with
# no further validation, so the very next run's "created succesfully!"
# writes out a real, wrong file (e.g. `DB_HOST` = '') while still reporting
# success. Fixed with an explicit `${var:-$default}` fallback on each one --
# this doesn't depend on readline/TTY behavior at all, so accepting a
# default now works the same whether this script is run interactively or
# not.
#
# `-r` added to all 4: without it, bash's `read` treats a backslash in the
# typed line itself as an escape character and silently drops it (`Ab\c123`
# read back as `Ab c123` -> actually collapses to `Abc123`) -- confirmed in
# isolation, independent of and before either fix above ever sees the
# value. `sql/msql.sh`/`msqldump.sh` already use `-r` for this same reason
# on their own reads; this script's prompts never had it.
read -e -r -i "$host_in" -p "Please enter the database host: [$host_in] " host
host="${host:-$host_in}"
read -e -r -i "$database_in" -p "Please enter database: [$database_in] " database
database="${database:-$database_in}"
read -e -r -i "$username_in" -p "Please enter database user name: [$username_in] " username
username="${username:-$username_in}"
read -e -r -s -i "$password_in" -p "Please enter database user password: [$password_in] " password
password="${password:-$password_in}"
echo		# new line to correct password input (was: echo "\n", which
		# without -e prints the two literal characters \n instead of
		# an actual newline -- confirmed in this same investigation)

# echo "Host: " $host
# echo "DB: " $database
# echo "User: " $username
# echo "Pass: " $password

read -e -p "Do you want to update $ofile? [y/N] " adminupdate

if [[ $adminupdate == [yY] ]]; then
    render_template "$ifile" "$ofile"
    #cat $ofile
    echo $ofile created succesfully!
fi


cd ../config

ifile="db.php.in"
ofile="db.php"

read -e -p "Do you want to update $ofile? [y/N] " dbupdate

if [[ $dbupdate == [yY] ]]; then
    render_template "$ifile" "$ofile"
    # cat $ofile
    echo $ofile created succesfully!
fi

cd ../sql

read -e -p "Do you want to create the database? [y/N] " createdb

if [[ $createdb == [yY] ]]; then
    echo Creating the database using the root credentials ...

    # `mysql -u root -p < admin.sql` (the previous version of this line)
    # asks mysql to *interactively* prompt for a password on the same
    # stdin admin.sql is already redirected into -- there is only one
    # stdin, so the prompt silently reads (garbles) admin.sql's own first
    # line as the password instead of ever running the script, and mysql
    # still exits 0 regardless. Confirmed against a real MariaDB server:
    # this printed "created succesfully" while creating neither the user
    # nor the database, every single time -- not a rare edge case, this
    # is what happens on every real run. Fixed by prompting for the
    # password into a shell variable *first*, then feeding admin.sql on
    # a stdin nothing else is competing for, via MYSQL_PWD -- the same
    # convention sql/msql.sh/msqldump.sh already use for this exact
    # reason (see their own comments).
    read -srp "Enter the MySQL/MariaDB root password (leave empty if root needs none): " root_password
    echo
    if [ -n "$root_password" ]; then
        MYSQL_PWD="$root_password" mysql -u root < admin.sql
    else
        mysql -u root < admin.sql
    fi

    if [ $? -eq 0 ]; then
	    echo User and database created succesfully.
    else
    	echo User and databases creation failed.
    fi
fi

cd ..

read -e -p "Do you want to create links to Zeus Framework folder? [y/N] " create_links

zfwdir_in="/var/www/html/apps/zeusfw"

if [[ $create_links == [yY] ]]; then
    echo Creating links ...

    while true; do

        read -e -i "$zfwdir_in" -p "Enter Zeus Framework folder: [$zfwdir_in] " zfwdir
        if [ -d $zfwdir ]; then
            # folder exists, test for contents
            if [ -f $zfwdir"/core/bootstrap.php" ]; then
                # bootstrap.php found, folder is correct, proceed(!)

                echo Creating link of Zeus FW folder $zfwdir to ./fw
                ln -sfn "$zfwdir" ./fw

                echo Creating link in ./web/core to ./fw
                mkdir -p ./web
                ln -sfn ../fw/core ./web/core

                break;
            else
                echo Folder is not a valid Zeus Framework folder. bootstrap.php was not found in $zfwdir
            fi
        else
            echo Folder does not exist.
        fi

        # Ask if the user wants to try again
        read -erp "Would you like to try again? (Y/n): " retry
        if [[ "$retry" == [Nn] ]]; then
            echo "No links created."
            break;
        fi
    done
fi
