#!/bin/bash

echo Update Zeus installation

if [ ! -f web/core/bootstrap.php ]; then
    echo "Please execute this script from the installation root folder"
    exit;
fi

#MAKER="../web/core/maker/maker.php"
BASEDIR=`pwd`
MAKER=$BASEDIR"/web/core/maker/maker.php"


# update classes in framework
pushd ./web/core/classes

echo "core -> spill:sql:all"
php $MAKER spill:sql:all

echo "core -> spill:class:all"
php $MAKER spill:class:all

echo "core -> update:bootstrap"
php $MAKER update:bootstrap

# now table structures are in correct places

# try to make tables if they do not exist in the database
## TODO
db_new_fw_tables=`php $MAKER --app-dir=$BASEDIR tables:new:fw`

# echo "New tables in database (fw): " $db_new_fw_tables
# echo "New tables in database (web): " $db_new_web_tables

# if fw tables is not empty, try to create the table
if [[ ! -z $db_new_fw_tables ]]; then
    echo "The following core tables are missing from the database: " $db_new_fw_tables
    
    read -p "Do you want to import the above tables? [y/N] " import
    echo

    if [[ $import == [yY] ]]; then
        for temp in $db_new_fw_tables; do
            sqlfile="$BASEDIR/web/core/classes/sql/$temp.sql";

            read -p "Do you want to import table $temp? [y/N] " tableimport
            if [[ $tableimport == [yY] ]]; then
                if [ -f $sqlfile ]; then
                    echo "Importing table $temp from $sqlfile"
                    $BASEDIR/sql/msql.sh < $sqlfile
                    if [ $? -eq 0 ]; then
                        echo "Import was successful"
                    else
                        echo "Import failed"
                    fi
                else
                    echo "Could not find SQL file for table $temp in file $sqlfile"
                fi
            fi
        done
    fi
fi


# try to remove tables if they do not exist in the file structure
## TODO


tfile="$(mktemp /tmp/sql-temp.XXXXXXXX)" || exit 1

php $MAKER --app-dir=$BASEDIR diff:sql:all > $tfile
cat $tfile
echo "Do you want to apply the above changes in the database? (y/N) (Ctrl-C to exit)"
read answer


if [[ $answer == [yY] ]]; then
    echo "Updating database"
    $BASEDIR/sql/msql.sh < $tfile
else
  rm $tfile
fi

popd

# update classes in userspace
pushd web/classes

echo "web -> spill:sql:all"
php $MAKER spill:sql:all

echo "web -> spill:class:all"
php $MAKER spill:class:all

echo "web -> update:bootstrap"
php $MAKER  update:bootstrap


db_new_web_tables=`php $MAKER --app-dir=$BASEDIR tables:new:web`

# if web tables is not empty, try to create the table
if [[ ! -z $db_new_web_tables ]]; then
    echo "The following tables are missing from the database: " $db_new_web_tables

    read -p "Do you want to import the above tables? [y/N] " import
    echo
    
    if [[ $import == [yY] ]]; then
        for temp in $db_new_web_tables; do
            sqlfile="$BASEDIR/web/classes/sql/$temp.sql";

            read -p "Do you want to import table $temp? [y/N] " tableimport
            if [[ $tableimport == [yY] ]]; then
                if [ -f $sqlfile ]; then
                    echo "Importing table $temp from $sqlfile"
                    $BASEDIR/sql/msql.sh < $sqlfile
                    if [ $? -eq 0 ]; then
                        echo "Import was successful"
                    else
                        echo "Import failed"
                    fi

                else
                    echo "Could not find SQL file for table $temp in file $sqlfile"
                fi
            fi
        done
    fi
fi


php $MAKER  diff:sql:all > $tfile
cat $tfile
echo "Do you want to apply the above changes in the database? (y/N) (Ctrl-C to exit)"
read answer

popd

if [[ $answer == [yY] ]]; then
    echo "Updating database";
    $BASEDIR/sql/msql.sh < $tfile
else
    rm $tfile
fi


echo "Do you want to update content? (y/N) (Ctrl-C to exit)"
read answer
if [[ $answer == [yY] ]]; then

    echo "Updating database content"
    # update userspace content
    pushd $BASEDIR/web/content

    for temp in `ls *.feeder.yaml`; do
	    echo Update feeder $temp;
	    php $MAKER --name $temp --dir ../classes/yaml feed:gen:yaml
	    php $MAKER --name $temp --dir ../classes/yaml feed:load
    done

    popd
fi
