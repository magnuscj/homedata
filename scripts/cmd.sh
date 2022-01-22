var=$(python getserial.py | sed -z 's/\n//g')
sed "s/#VALUE#/$var/g" details_template.xml > www/details.xml
