ls -l *.html | awk '{print $9}' | sed 's/tmpl_//g' | xargs -l ./updateip.sh
mv ~/tmpviz/*.html /var/www/html