#echo "use mydb; select * from sensorconfig;" | mysql
echo "CREATE DATABASE IF NOT EXISTS mydb;use mydb; drop table if exists sensorconfig; CREATE TABLE sensorconfig (id int NOT NULL AUTO_INCREMENT,  sensorid text NOT NULL,  sensorname text NOT NULL,  color text NOT NULL,  visible text NOT NULL,  type text NOT NULL,  PRIMARY KEY (id));" | mysql
#echo "use mydb; INSERT INTO sensorconfig VALUES (1,'11958917567994305401','Ute','blue','True','temp'),(2,'5712026116554055813','kylFrys','darkorchid','True','temp'),(3,'16640609015724705805','Inne','green','True','temp'),(4,'11502682451740542577','El','black','True','power'),(5,'6401056855341373761','Garage','black','True','temp'),(6,'10871475366841829943','Heater','black','True','power'),(7,'10584112306151241934','Regn','royalblue4','True','rain'),(8,'3107542916437853282','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'745766427539473096','Tryck','black','false','bar'),(16,'9679930675992349171','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'10320934655164167190','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'405851264624315958','WiSDir','black','false','Wind'),(22,'451768614604584088','Fry_ko','deepskyblue3','True','temp'),(26,'2286664644031231946','Kyl_ko','deepskyblue1','True','temp'),(28,'13628543737832316140','Kyl_gr','darkorchid4','True','temp');" | mysql
echo "use mydb; INSERT INTO sensorconfig VALUES (1,'11958917567994305401','Ute','blue','True','temp'),(2,'5712026116554055813','kylFrys','darkorchid','True','temp'),(3,'16640609015724705805','Inne','green','True','temp'),(4,'11502682451740542577','El','black','True','power'),(5,'6401056855341373761','Garage','black','True','temp'),(6,'10871475366841829943','Heater','black','True','power'),(7,'10584112306151241934','Regn','royalblue4','True','rain'),(8,'3107542916437853282','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'745766427539473096','Tryck','black','false','bar'),(16,'9679930675992349171','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'10320934655164167190','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'405851264624315958','WiSDir','black','false','Wind'),(22,'451768614604584088','Fry_ko','deepskyblue3','True','temp'),(26,'2286664644031231946','Kyl_ko','deepskyblue1','True','temp'),(28,'13628543737832316140','Kyl_gr','darkorchid4','True','temp'),(36,'14879744748110755475','vaxthus','black','True','temp'),(61,'1627857537984973127','back___8','black','false','soilmoist'),(62,'5940545310998240980','back___3','black','false','soilmoist'),(63,'14749547047390813894','back___4','black','false','soilmoist'),(64,'12263795416595930691','back___6','black','false','soilmoist'),(65,'11173720249716080573','back___7','black','false','soilmoist'),(66,'2582455000113499891','back___1','black','false','soilmoist'),(69,'338262872391407756','Palett_5','black','false','soilmoist'),(70,'1821716308317460291','Fl.Lisa2','black','false','soilmoist'),(71,'4284021386854942413','name','black','false','default'),(73,'14919029620662611901','Fry_ga','white','True','temp')"  | mysql 
