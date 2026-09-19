-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: localhost    Database: mydb
-- ------------------------------------------------------
-- Server version	8.0.46-0ubuntu0.24.04.4

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
) ENGINE=InnoDB AUTO_INCREMENT=115 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `sensorconfig`
--

LOCK TABLES `sensorconfig` WRITE;
/*!40000 ALTER TABLE `sensorconfig` DISABLE KEYS */;
INSERT INTO `sensorconfig` VALUES (1,'16261054761367928446','Ute','blue','True','temp'),(2,'11311998247970688570','Fry_gr','darkorchid','True','temp'),(3,'9095587817331319166','Inne','green','True','temp'),(4,'10631191365266147712','El','black','True','power'),(5,'213136193804146860','Garage','black','True','temp'),(6,'12837279452181235050','Heater','black','True','power'),(8,'7851977845202606380','Skorst','red','True','temp'),(9,'702045547157631543','Sovrum','cadetblue4','True','temp'),(15,'11440049530512997765','Tryck','black','false','bar'),(16,'10832894972928239946','Fukt','black','True','moisture'),(17,'2705388970248215848','Kontor','black','false','temp'),(18,'12580286349677670678','FuktKon','black','false','moisture'),(19,'15555407530156859778','WiSpeed','black','false','Wind'),(20,'14287746912078928553','WiSMax','black','false','Wind'),(21,'12805902924744758861','WiSDir','black','false','Wind'),(22,'3142761097160776728','Fry_ko','deepskyblue3','True','temp'),(26,'16287663478246657457','Kyl_ko','deepskyblue1','True','temp'),(28,'1300729859917990278','Kyl_gr','darkorchid4','True','temp'),(36,'9276034250560746343','vaxthus','black','True','temp'),(61,'15050001762446072976','back___8','black','false','soilmoist'),(62,'8035609657829439818','back___3','black','false','soilmoist'),(63,'11417017316545152923','back___4','black','false','soilmoist'),(64,'15716539391930188350','back___6','black','false','soilmoist'),(65,'5297168414477736841','back___7','black','false','soilmoist'),(66,'8192219494370953084','back___1','black','false','soilmoist'),(69,'15384184899465171632','Palett_5','black','false','soilmoist'),(70,'15741507826216356597','Fl.Lisa2','black','false','soilmoist'),(71,'4284021386854942413','name','black','false','default'),(73,'2140482302588567291','Fry_ga','white','True','temp'),(75,'10936074920700898100','Regn','royalblue4','True','rain'),(81,'18432709685998635173','regnW','white','True','temp'),(82,'14748571628222628275','regnM','white','True','temp'),(83,'16531815346788690238','name','black','false','default'),(84,'1256721048045950402','name','black','false','default'),(85,'12873561500153519717','name','black','false','default'),(86,'1825262919257248179','name','black','false','default'),(87,'15596246877695699263','name','black','false','default'),(88,'12533144863253358689','name','black','false','default'),(89,'14496576840487990388','name','black','false','default'),(90,'16585154686760632335','name','black','false','default'),(91,'11256239885095200201','name','black','false','default'),(92,'2935638007747236222','name','black','false','default'),(93,'7340854215287122329','name','black','false','default'),(94,'1717478912561242427','name','black','false','default'),(95,'12824239163780944267','name','black','false','default'),(96,'13468779402107539408','name','black','false','default'),(97,'319470988601036892','name','black','false','default'),(98,'2045203724601485461','name','black','false','default'),(99,'17090200471541902054','name','black','false','default'),(100,'1291528723191873272','name','black','false','default'),(101,'626725413786941418','name','black','false','default'),(102,'8541734903298535395','name','black','false','default'),(103,'12483174112334542332','name','black','false','default'),(104,'5721874432656300885','name','black','false','default'),(105,'13305996785600206791','name','black','false','default'),(106,'14928745923121341348','name','black','false','default'),(107,'9387140092204259246','name','black','false','default'),(108,'790896559150094464','name','black','false','default'),(109,'16064891713450093144','name','black','false','default'),(110,'13465003325475488906','name','black','false','default'),(111,'7110415279208543420','name','black','false','default'),(112,'2622148211830434888','name','black','false','default'),(113,'1455443341562541178','name','black','false','default'),(114,'213136193804146860','Garage','black','True','temp');
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

-- Dump completed on 2026-09-06 15:57:43
