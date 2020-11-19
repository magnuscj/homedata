#/bin/bash!

ALL_SMS_DATA=$(gammu --geteachsms -pkg 2> /dev/null)
SMS_COMMAND=($(echo "$ALL_SMS_DATA"  | awk '$1 == "Do:" {print $2}' | sed 's/,//' ))
SMS_ID=($(echo "$ALL_SMS_DATA" | awk '$1 == "Plats" {print $2}' | sed 's/,//' | sed 's/10000//'))
echo ${SMS_COMMAND[0]}
$(gammu --deletesms 3 ${SMS_ID[0]}) 2> /dev/null


