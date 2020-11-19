#/bin/bash

ALL_SMS_DATA=$(gammu --geteachsms -pkg 2> /dev/null)  

#SMS_ID=$(gammu --geteachsms -pkg 2> /dev/null | awk '$1 == "Plats" {print $2}' | sed 's/,//' | sed 's/10000//')
SMS_ID=($(echo "$ALL_SMS_DATA" | awk '$1 == "Plats" {print $2}' | sed 's/,//' | sed 's/10000//'))

#SMS_COMMAND=$(gammu --geteachsms -pkg 2> /dev/null | awk '$1 == "Do:" {print $2}' | sed 's/,//')
SMS_COMMAND=$(echo "$ALL_SMS_DATA"  | awk '$1 == "Do:" {print $2}' | sed 's/,//' )

#ARR=$( echo $SMS_COMMAND | sed 's/\n//')
ARR=($SMS_COMMAND)

len=${#ARR[@]}

for (( i=0; i<$len; i++))
do
  echo ${SMS_ID[$i]} ${ARR[$i]} 
  #echo "$i ${SMS_ID[$i]}  ${SMS_COMMAND[$i]}"
done

#echo $ALL_SMS_DATA
#echo $SMS_COMMAND
#echo $SMS_ID

