#!/bin/bash

N_O_FILES=`ls /usr/storage/*.tar | wc -w` 
ARR=($(ls -tr /usr/storage/*.tar))
i=0
NEW_FILE=""

echo $N_O_FILES
echo $ARR

if [[ $N_O_FILES -ge 10 ]]
then
  echo "Removing ${ARR[0]}"
  rm -f ${ARR[0]}
  ((N_O_FILES--))
  ((i++))
fi

if [[ $N_O_FILES -ge 1 ]]
then
  ((N_O_FILES++))
  while [ $N_O_FILES -ge 2 ]
  do
#    echo ${ARR[$i]} `echo ${ARR[$i]} | sed -r "s/[0-9]+/$N_O_FILES/g"`
    mv ${ARR[$i]} `echo ${ARR[$i]} | sed -r "s/[0-9]+/$N_O_FILES/g"`

    ((i++))
    ((N_O_FILES--))
  done
fi
#mysqldump  mydb > test1.sql
mysqldump -u dbuser -pkmjmkm54C#  mydb > /usr/storage/test1.sql
tar -czf /usr/storage/test1.tar /usr/storage/test1.sql
rm -f /usr/storage/test1.sql



