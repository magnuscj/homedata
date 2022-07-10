ip=$(dig +short myip.opendns.com @resolver1.opendns.com)
sed "s/#IP#/$ip/g" "tmpl_$1" > $1
