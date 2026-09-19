#!/bin/bash
# Create sensorconfig (6-column: id, sensorid, sensorname, color, visible, type).
# sensorid = deterministic FNV-1a id (canonical, portable across builds/arch).
#
# Load order:
#   1) if /usr/storage/sensorconfig.sql exists, load it;
#   2) if that leaves the table with no named rows (missing/stale/degenerate dump),
#      fall back to the hardcoded seed so the table is NEVER left empty
#      (an empty table lets eds repopulate placeholder 'name' rows).

echo "CREATE DATABASE IF NOT EXISTS mydb;use mydb; drop table if exists sensorconfig; CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT,  sensorid text NOT NULL,  sensorname text NOT NULL,  color text NOT NULL,  visible text NOT NULL,  type text NOT NULL,  PRIMARY KEY (id));" | mysql

# Hardcoded seed of the known named sensors keyed on the canonical FNV sensorid.
SEED="use mydb; INSERT INTO sensorconfig (id,sensorid,sensorname,color,visible,type) VALUES (1,'16261054761367928446','Ute','blue','True','temp'),(2,'11311998247970688570','Fry_gr','darkorchid','True','temp'),(3,'9095587817331319166','Inne','green','True','temp'),(4,'10631191365266147712','El','black','True','power'),(5,'213136193804146860','Garage','black','True','temp'),(6,'12837279452181235050','Heater','black','True','power'),(8,'7851977845202606380','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'11440049530512997765','Tryck','black','false','bar'),(16,'10832894972928239946','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'15555407530156859778','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'12805902924744758861','WiSDir','black','false','Wind'),(22,'3142761097160776728','Fry_ko','deepskyblue3','True','temp'),(26,'16287663478246657457','Kyl_ko','deepskyblue1','True','temp'),(28,'1300729859917990278','Kyl_gr','darkorchid4','True','temp'),(36,'9276034250560746343','vaxthus','black','True','temp'),(61,'15050001762446072976','back___8','black','false','soilmoist'),(62,'8035609657829439818','back___3','black','false','soilmoist'),(63,'11417017316545152923','back___4','black','false','soilmoist'),(64,'15716539391930188350','back___6','black','false','soilmoist'),(65,'5297168414477736841','back___7','black','false','soilmoist'),(66,'8192219494370953084','back___1','black','false','soilmoist'),(69,'15384184899465171632','Palett_5','black','false','soilmoist'),(70,'15741507826216356597','Fl.Lisa2','black','false','soilmoist'),(71,'4284021386854942413','name','black','false','default'),(73,'2140482302588567291','Fry_ga','white','True','temp'),(75,'10936074920700898100','Regn','royalblue4','True','rain'),(81,'18432709685998635173','regnW','white','True','temp'),(82,'14748571628222628275','regnM','white','True','temp')"

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
  echo "CREATE DATABASE IF NOT EXISTS mydb;use mydb; drop table if exists sensorconfig; CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT,  sensorid text NOT NULL,  sensorname text NOT NULL,  color text NOT NULL,  visible text NOT NULL,  type text NOT NULL,  PRIMARY KEY (id));" | mysql
  echo "$SEED" | mysql
fi
