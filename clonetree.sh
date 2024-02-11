#!/bin/sh
echo Clone Zeus Framework tree

folders='db kernel maker router security tempaltes'

echo "Source Zeus Framework folder $1"

if [ -z $1 ]; then
    echo "Usage:\n\t $0 zeus_fw_folder"
    exit;
fi

if [  ! -d $1 ]; then
  echo "$1 is not directory"
  exit;
fi

folders='';

for temp in `ls -b $1`; do 
    if [ -d $1/$temp ]; then
#        echo $1/$temp;
        if [ $temp != "classes" ]; then
            folders=$folders" "$temp; 
        fi
    fi
done

#echo "Final folders list $folders"

for temp in $folders; do
    if [ -h $temp ]; then
        rm -v $temp;
    fi
    ln -sv $1/$temp $temp
done

if [ -h bootstrap.php ]; then
  rm bootstrap.php
fi
ln -sv $1/bootstrap.php bootstrap.php

  