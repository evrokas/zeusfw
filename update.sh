#!/bin/bash

echo Update Zeus installtion

MAKER="../../fw/maker/maker.php"
BASEDIR=`pwd`


# update classes in framework
pushd fw/classes

echo "fw -> spill:sql:all"
php ../maker/maker.php spill:sql:all

echo "fw -> spill:class:all"
php ../maker/maker.php spill:class:all

echo "fw -> update:bootstrap"
php ../maker/maker.php update:bootstrap

tfile="$(mktemp /tmp/sql-temp.XXXXXXXX)" || exit 1

php ../maker/maker.php --app-dir=$BASEDIR diff:sql:all > $tfile
cat $tfile
echo "Do you want to apply the above chnages in the database? (y/n) (Ctrl-C to exit)"
read answer

popd

if [[ $answer == [yY] ]]; then
    echo "Updating database"
    sql/msql.sh < $tfile
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

