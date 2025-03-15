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


read -e -i "$host_in" -p "Please enter the database host: [$host_in] " host
read -e -i "$database_in" -p "Please enter database: [$database_in] " database
read -e -i "$username_in" -p "Please enter database user name: [$username_in] " username
read -e -s -i "$password_in" -p "Please enter database user password: [$password_in] " password
echo "\n"		# new line to correct password input

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
    sudo mysql -u root -p  < admin.sql
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
                echo Creating link in ./web/core to ./fw

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
