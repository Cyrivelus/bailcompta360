-- MySQL dump 10.13  Distrib 8.0.28, for Win64 (x86_64)
--
-- Host: localhost    Database: BD_AD_SCE
-- ------------------------------------------------------
-- Server version	5.5.5-10.4.32-MariaDB

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
-- Table structure for table `agences_sce`
--

DROP TABLE IF EXISTS `agences_sce`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `agences_sce` (
  `CodeAgenceSCE` varchar(3) NOT NULL,
  `LibelleAgenceSCE` varchar(15) NOT NULL,
  `NoCompteComptable` varchar(12) DEFAULT NULL,
  PRIMARY KEY (`CodeAgenceSCE`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `agences_sce`
--

LOCK TABLES `agences_sce` WRITE;
/*!40000 ALTER TABLE `agences_sce` DISABLE KEYS */;
INSERT INTO `agences_sce` VALUES ('002','BERTOUA','454000002000'),('003','EBOLOWA','454000003000'),('004','GAROUA','454000004000'),('005','MAROUA','454000005000'),('006','KRIBI','454000006000'),('007','NGAOUNDERE','454000007000'),('008','SANGMELIMA','454000008000'),('009','YAOUNDE','454000009000'),('011','BAMENDA','454000011000'),('012','BUEA','454000012000'),('013','DOUALA','454000013000'),('018','BAFOUSSAM','454000018000');
mysqldump: Couldn't execute 'SELECT COLUMN_NAME,                       JSON_EXTRACT(HISTOGRAM, '$."number-of-buckets-specified"')                FROM information_schema.COLUMN_STATISTICS                WHERE SCHEMA_NAME = 'BD_AD_SCE' AND TABLE_NAME = 'agences_sce';': Unknown table 'column_statistics' in information_schema (1109)
/*!40000 ALTER TABLE `agences_sce` ENABLE KEYS */;
UNLOCK TABLES;
