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

# echo "Host: " $host
# echo "DB: " $database
# echo "User: " $username
# echo "Pass: " $password

ready -e -p "Do you want to update $ofile? [y/N] " adminupdate

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

cd ..

read -e -p "Do you want to create the database? [y/N] " createdb

if [[ $createdb == [yY] ]]; then
    echo -n Creating the database ...
    ./sql/msql.sh < admin.sql
    echo OK
fi
