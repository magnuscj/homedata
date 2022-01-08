FILE=/usr/storage/test1.tar
if [[ -f "$FILE" ]]; then
  tar -xf /usr/storage/test1.tar --strip-components=2
  echo "CREATE DATABASE IF NOT EXISTS mydb;" | mysql
  cat test1.sql | mysql mydb
fi
