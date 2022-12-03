-- MySQL dump 10.13  Distrib 8.0.31, for Linux (x86_64)
--
-- Host: localhost    Database: mydb
-- ------------------------------------------------------
-- Server version	8.0.31-0ubuntu0.20.04.1

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `sensorconfig`
--

DROP TABLE IF EXISTS `sensorconfig`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sensorconfig` (
  `id` int NOT NULL AUTO_INCREMENT,
  `sensorid` text NOT NULL,
  `sensorname` text NOT NULL,
  `color` text NOT NULL,
  `visible` text NOT NULL,
  `type` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sensorconfig`
--

LOCK TABLES `sensorconfig` WRITE;
/*!40000 ALTER TABLE `sensorconfig` DISABLE KEYS */;
INSERT INTO `sensorconfig` VALUES (1,'11958917567994305401','Ute','blue','True','temp'),(2,'5712026116554055813','kylFrys','darkorchid','True','temp'),(3,'16640609015724705805','Inne','green','True','temp'),(4,'11502682451740542577','El','black','True','power'),(5,'6401056855341373761','Garage','black','True','temp'),(6,'10871475366841829943','Heater','black','True','power'),(7,'10584112306151241934','Regn','royalblue4','True','rain'),(8,'3107542916437853282','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'745766427539473096','Tryck','black','false','bar'),(16,'9679930675992349171','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'10320934655164167190','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'405851264624315958','WiSDir','black','false','Wind'),(22,'451768614604584088','Fry_ko','deepskyblue3','True','temp'),(26,'2286664644031231946','Kyl_ko','deepskyblue1','True','temp'),(28,'13628543737832316140','Kyl_gr','darkorchid4','True','temp');
/*!40000 ALTER TABLE `sensorconfig` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2022-12-03 13:36:10
