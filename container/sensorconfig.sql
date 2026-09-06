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
  `id2` text,
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
INSERT INTO `sensorconfig` VALUES (1,'16261054761367928446','11958917567994305401','Ute','blue','True','temp'),(2,'11311998247970688570','5712026116554055813','Fry_gr','darkorchid','True','temp'),(3,'9095587817331319166','16640609015724705805','Inne','green','True','temp'),(4,'10631191365266147712','11502682451740542577','El','black','True','power'),(5,'6401056855341373761',NULL,'Garage','black','True','temp'),(6,'12837279452181235050','10871475366841829943','Heater','black','True','power'),(8,'7851977845202606380','3107542916437853282','Skorst','red','True','temp'),(9,'702045547157631543',NULL,'Sovrum','cadetblue4','True','temp'),(15,'11440049530512997765','745766427539473096','Tryck','black','false','bar'),(16,'10832894972928239946','9679930675992349171','Fukt','black','True','moisture'),(17,'2705388970248215848',NULL,'Kontor','black','false','temp'),(18,'12580286349677670678',NULL,'FuktKon','black','false','moisture'),(19,'15555407530156859778','9792998465063376449','WiSpeed','black','false','Wind'),(20,'14287746912078928553',NULL,'WiSMax','black','false','Wind'),(21,'12805902924744758861','1472279130030819157','WiSDir','black','false','Wind'),(22,'3142761097160776728','451768614604584088','Fry_ko','deepskyblue3','True','temp'),(26,'16287663478246657457','2286664644031231946','Kyl_ko','deepskyblue1','True','temp'),(28,'1300729859917990278','13628543737832316140','Kyl_gr','darkorchid4','True','temp'),(36,'9276034250560746343','14879744748110755475','vaxthus','black','True','temp'),(61,'15050001762446072976','1627857537984973127','back___8','black','false','soilmoist'),(62,'8035609657829439818','5940545310998240980','back___3','black','false','soilmoist'),(63,'11417017316545152923','14749547047390813894','back___4','black','false','soilmoist'),(64,'15716539391930188350','12263795416595930691','back___6','black','false','soilmoist'),(65,'5297168414477736841','11173720249716080573','back___7','black','false','soilmoist'),(66,'8192219494370953084','2582455000113499891','back___1','black','false','soilmoist'),(69,'15384184899465171632','338262872391407756','Palett_5','black','false','soilmoist'),(70,'15741507826216356597','1821716308317460291','Fl.Lisa2','black','false','soilmoist'),(71,'4284021386854942413',NULL,'name','black','false','default'),(73,'2140482302588567291','14919029620662611901','Fry_ga','white','True','temp'),(75,'10936074920700898100','13709717348313496200','Regn','royalblue4','True','rain'),(81,'18432709685998635173',NULL,'regnW','white','True','temp'),(82,'14748571628222628275',NULL,'regnM','white','True','temp'),(83,'16531815346788690238','7058401096628718200','name','black','false','default'),(84,'1256721048045950402','15569228657293478981','name','black','false','default'),(85,'12873561500153519717','13331682480771181571','name','black','false','default'),(86,'1825262919257248179','11149592510292905950','name','black','false','default'),(87,'15596246877695699263','5300159395003925836','name','black','false','default'),(88,'12533144863253358689','14167515361198392664','name','black','false','default'),(89,'14496576840487990388','3305728008162482822','name','black','false','default'),(90,'16585154686760632335','11535835558912384656','name','black','false','default'),(91,'11256239885095200201','11195013259385697476','name','black','false','default'),(92,'2935638007747236222','5114028333750381596','name','black','false','default'),(93,'7340854215287122329','10307590641465086041','name','black','false','default'),(94,'1717478912561242427','18436685849338134057','name','black','false','default'),(95,'12824239163780944267','17753173462721763453','name','black','false','default'),(96,'13468779402107539408','1243260570372180604','name','black','false','default'),(97,'319470988601036892','11422268573761494261','name','black','false','default'),(98,'2045203724601485461','13861890031764065925','name','black','false','default'),(99,'17090200471541902054','1188138124788668737','name','black','false','default'),(100,'1291528723191873272','18211666114048784735','name','black','false','default'),(101,'626725413786941418','3893387308799240727','name','black','false','default'),(102,'8541734903298535395','14330865298505281815','name','black','false','default'),(103,'12483174112334542332','3761548075915089214','name','black','false','default'),(104,'5721874432656300885','1487077879308828126','name','black','false','default'),(105,'13305996785600206791','15194954361965929252','name','black','false','default'),(106,'14928745923121341348','5870100343527018041','name','black','false','default'),(107,'9387140092204259246','16115547914082222317','name','black','false','default'),(108,'790896559150094464','10317759217024095682','name','black','false','default'),(109,'16064891713450093144','16380245931803387527','name','black','false','default'),(110,'13465003325475488906','18360848019279852877','name','black','false','default'),(111,'7110415279208543420','4780511667212011040','name','black','false','default'),(112,'2622148211830434888','15945396522219284078','name','black','false','default'),(113,'1455443341562541178','752508111650159390','name','black','false','default'),(114,'213136193804146860','927197891228557615','Garage','black','True','temp');
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
