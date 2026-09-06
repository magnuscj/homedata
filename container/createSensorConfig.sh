#!/bin/bash
# Create sensorconfig (7-column: id, sensorid, id2, sensorname, color, visible, type).
# sensorid = legacy std::hash id; id2 = deterministic FNV-1a id (see migration docs).
#
# id2 is intentionally NOT seeded here. It is populated from the live details.xml
# by the migration tool  edssensors/add_id2.py  (run manually):
#     cd /homedata/edssensors && python3 add_id2.py --apply
# The seed only restores the known names/colors keyed on the legacy sensorid.
#
# Load order:
#   1) if /usr/storage/sensorconfig.sql exists, load it;
#   2) if that leaves the table with no named rows (missing/stale/degenerate dump),
#      fall back to the hardcoded seed so the table is NEVER left empty
#      (an empty table lets eds repopulate placeholder 'name' rows).

echo "CREATE DATABASE IF NOT EXISTS mydb;use mydb; drop table if exists sensorconfig; CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT,  sensorid text NOT NULL,  id2 text NULL,  sensorname text NOT NULL,  color text NOT NULL,  visible text NOT NULL,  type text NOT NULL,  PRIMARY KEY (id));" | mysql

# Hardcoded seed (legacy columns only; id2 left NULL, filled later by add_id2.py).
# Columns are named explicitly since id2 is omitted.
SEED="use mydb; INSERT INTO sensorconfig (id,sensorid,sensorname,color,visible,type) VALUES (1,'11958917567994305401','Ute','blue','True','temp'),(2,'5712026116554055813','Fry_gr','darkorchid','True','temp'),(3,'16640609015724705805','Inne','green','True','temp'),(4,'11502682451740542577','El','black','True','power'),(5,'6401056855341373761','Garage','black','True','temp'),(6,'10871475366841829943','Heater','black','True','power'),(8,'3107542916437853282','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'745766427539473096','Tryck','black','false','bar'),(16,'9679930675992349171','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'9792998465063376449','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'1472279130030819157','WiSDir','black','false','Wind'),(22,'451768614604584088','Fry_ko','deepskyblue3','True','temp'),(26,'2286664644031231946','Kyl_ko','deepskyblue1','True','temp'),(28,'13628543737832316140','Kyl_gr','darkorchid4','True','temp'),(36,'14879744748110755475','vaxthus','black','True','temp'),(61,'1627857537984973127','back___8','black','false','soilmoist'),(62,'5940545310998240980','back___3','black','false','soilmoist'),(63,'14749547047390813894','back___4','black','false','soilmoist'),(64,'12263795416595930691','back___6','black','false','soilmoist'),(65,'11173720249716080573','back___7','black','false','soilmoist'),(66,'2582455000113499891','back___1','black','false','soilmoist'),(69,'338262872391407756','Palett_5','black','false','soilmoist'),(70,'1821716308317460291','Fl.Lisa2','black','false','soilmoist'),(71,'4284021386854942413','name','black','false','default'),(73,'14919029620662611901','Fry_ga','white','True','temp'),(75,'13709717348313496200','Regn','royalblue4','True','rain'),(81,'18432709685998635173','regnW','white','True','temp'),(82,'14748571628222628275','regnM','white','True','temp')"

if [[ -f /usr/storage/sensorconfig.sql ]]; then
  echo "Loading /usr/storage/sensorconfig.sql"
  mysql mydb < /usr/storage/sensorconfig.sql 2>/dev/null
fi

# The persisted dump can be degenerate (empty, or all placeholder 'name' rows)
# if a backup captured a polluted table. Treat the hardcoded SEED as authoritative
# for the known named sensors: if no real (non-'name') row is present, (re)seed so
# names are always restored and eds never sees an unconfigured table.
NAMED=$(mysql mydb -N -e "SELECT COUNT(*) FROM sensorconfig WHERE sensorname <> 'name'" 2>/dev/null)
if [[ -z "$NAMED" || "$NAMED" -eq 0 ]]; then
  echo "sensorconfig has no named rows (missing/stale/degenerate dump); applying hardcoded seed"
  echo "CREATE DATABASE IF NOT EXISTS mydb;use mydb; drop table if exists sensorconfig; CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT,  sensorid text NOT NULL,  id2 text NULL,  sensorname text NOT NULL,  color text NOT NULL,  visible text NOT NULL,  type text NOT NULL,  PRIMARY KEY (id));" | mysql
  echo "$SEED" | mysql
fi
