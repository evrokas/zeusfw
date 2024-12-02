#!/bin/bash

echo Update Zeus installtion

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

tfile="$(mktemp /tmp/sql-temp.XXXXXXXX)" || exit 1

php $MAKER --app-dir=$BASEDIR diff:sql:all > $tfile
cat $tfile
echo "Do you want to apply the above chnages in the database? (y/n) (Ctrl-C to exit)"
read answer

popd

if [[ $answer == [yY] ]]; then
    echo "Updating database"
    sql/msql.sh < $tfile
else
  rm $tfile
fi


# update classes in userspace
pushd web/classes

echo "web -> spill:sql:all"
php $MAKER spill:sql:all

echo "web -> spill:class:all"
php $MAKER spill:class:all

echo "web -> update:bootstrap"
php $MAKER  update:bootstrap

php $MAKER  diff:sql:all > $tfile
cat $tfile
echo "Do you want to apply the above chnages in the database? (y/n) (Ctrl-C to exit)"
read answer

popd

if [[ $answer == [yY] ]]; then
    echo "Updating database";
    sql/msql.sh < $tfile
else
    rm $tfile
fi


echo "Do you want to update content? (y/n) (Ctrl-C to exit)"
read answer
if [[ $answer == [yY] ]]; then
    
    echo "Updating database content"
    # update userspace content
    pushd web/content

    for temp in `ls *.feeder.yaml`; do 
	    echo Update feeder $temp; 
	    php $MAKER --name $temp --dir ../classes/yaml feed:gen:yaml
	    php $MAKER --name $temp --dir ../classes/yaml feed:load
    done

    popd
fi
