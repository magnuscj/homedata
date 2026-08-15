#!/bin/bash

# Check that the DB has meaningful data before backing up
TABLE="table$(date +%Y%m)"
ROWS=$(mysql -u dbuser -pkmjmkm54C# -N -e "SELECT COUNT(*) FROM mydb.$TABLE" 2>/dev/null)
if [[ -z "$ROWS" || "$ROWS" -lt 1000 ]]; then
  echo "Skipping backup — DB looks incomplete ($ROWS rows in $TABLE)"
  exit 0
fi

mysqldump -u dbuser -pkmjmkm54C# --no-create-info mydb sensorconfig > /usr/storage/sensorconfig.sql

N_O_FILES=`ls /usr/storage/*.tar | wc -w`
ARR=($(ls -tr /usr/storage/*.tar))
i=0

echo $N_O_FILES

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
    mv ${ARR[$i]} `echo ${ARR[$i]} | sed -r "s/[0-9]+/$N_O_FILES/g"`
    ((i++))
    ((N_O_FILES--))
  done
fi

mysqldump -u dbuser -pkmjmkm54C# mydb > /usr/storage/test1.sql
tar -czf /usr/storage/test1.tar /usr/storage/test1.sql
rm -f /usr/storage/test1.sql
echo "Backup complete ($ROWS rows in $TABLE)"
