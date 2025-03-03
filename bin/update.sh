#!/bin/bash

echo Update Zeus installation

if [ ! -f web/core/bootstrap.php ]; then
    echo "Please execute this script from the installation root folder"
    exit;
fi

#MAKER="../web/core/maker/maker.php"
BASEDIR=`pwd`
MAKER=$BASEDIR"/web/core/maker/maker.php"


echo 'Updating in `core` folders...'

# update classes in framework
pushd ./web/core/classes

echo "core -> spill:sql:all"
php $MAKER --app-dir=$BASEDIR spill:sql:all

echo "core -> spill:class:all"
php $MAKER --app-dir=$BASEDIR spill:class:all

echo "core -> update:bootstrap"
php $MAKER --app-dir=$BASEDIR update:bootstrap

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

echo "core -> diff:sql:all"
#echo "basedir $BASEDIR"

echo "php $MAKER --app-dir=$BASEDIR diff:sql:all"
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


echo 'Updating in `application` folders...'

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


php $MAKER diff:sql:all > $tfile
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

    echo -n "Do you want to generate feeders' .yml files? [y/N/ask] "
    read generate

    echo "Updating database content"
    # update userspace content
    pushd $BASEDIR/web/content

    for temp in `ls *.feeder.yaml`; do
	    echo Processing feeder $temp;

        schema=`cat $temp | gawk ' /schema:/ { print $2 }' -`
        echo "Schema " $schema;

        
        if [[ $generate == [yYaA] ]]; then

            if [[ $generate == [aA] ]]; then
                echo -n "Do you want to update feeder $temp ? [y/N] "
                read confirm
            else 
                confirm="y";
            fi

            if [[ $confirm == [yY] ]]; then 
                if [[ -f ../classes/yaml/$schema ]]; then 
                    echo "Creating feeder data $temp (app)"
                    php $MAKER --name $temp --dir ../classes/yaml feed:gen:yaml
                elif [[ -f ../core/classes/yaml/$schema ]]; then
                    echo "Creating feeder data $temp (core)"
                    php $MAKER --name $temp --dir ../core/classes/yaml feed:gen:yaml
                else
                    echo Could not locate schema file $schema. Aborting...
                    exit
                fi
            fi
        fi

        echo "Loading feeder data $temp"
	    php $MAKER --name $temp --dir ../classes/yaml feed:clean
	    php $MAKER --name $temp --dir ../classes/yaml feed:load
    done

    popd
fi
