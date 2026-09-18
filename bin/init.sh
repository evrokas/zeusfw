#!/bin/bash
echo Zeus Framework Website builder
echo

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

    # Extract values using regex with 'grep'
    username_in=$(echo "$sql" | grep -oP "CREATE USER IF NOT EXISTS '\K[^']+(?='@)")
    host_in=$(echo "$sql" | grep -oP "@'\K[^']+(?=' IDENTIFIED)")
    password_in=$(echo "$sql" | grep -oP "IDENTIFIED BY '\K[^']+(?=')")
    database_in=$(echo "$sql" | grep -oP "CREATE DATABASE IF NOT EXISTS \K\w+(?=;)")

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
read -e -i "$host_in" -p "Please enter the database host: [$host_in] " host
host="${host:-$host_in}"
read -e -i "$database_in" -p "Please enter database: [$database_in] " database
database="${database:-$database_in}"
read -e -i "$username_in" -p "Please enter database user name: [$username_in] " username
username="${username:-$username_in}"
read -e -s -i "$password_in" -p "Please enter database user password: [$password_in] " password
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
    cat $ifile | sed -s "s/<<host>>/$host/g" - | sed -s "s/<<user>>/$username/g" - | sed -s "s/<<pass>>/$password/g" - | sed -s "s/<<db>>/$database/g" - > $ofile
    #cat $ofile
    echo $ofile created succesfully!
fi


cd ../config

ifile="db.php.in"
ofile="db.php"

read -e -p "Do you want to update $ofile? [y/N] " dbupdate

if [[ $dbupdate == [yY] ]]; then
    cat $ifile | sed -s "s/<<host>>/$host/g" - | sed -s "s/<<user>>/$username/g" - | sed -s "s/<<pass>>/$password/g" - | sed -s "s/<<db>>/$database/g" - > $ofile
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
