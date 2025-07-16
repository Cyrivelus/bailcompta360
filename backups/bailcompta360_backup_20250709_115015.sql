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
-- Table structure for table `acknowledged_integrity_issues`
--

DROP TABLE IF EXISTS `acknowledged_integrity_issues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `acknowledged_integrity_issues` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `check_name` varchar(255) NOT NULL,
  `issue_data_json` text NOT NULL,
  `acknowledged_by_user_id` int(11) DEFAULT NULL,
  `acknowledged_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `check_name` (`check_name`,`issue_data_json`(255))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `acknowledged_integrity_issues`
--

LOCK TABLES `acknowledged_integrity_issues` WRITE;
/*!40000 ALTER TABLE `acknowledged_integrity_issues` DISABLE KEYS */;
mysqldump: Couldn't execute 'SELECT COLUMN_NAME,                       JSON_EXTRACT(HISTOGRAM, '$."number-of-buckets-specified"')                FROM information_schema.COLUMN_STATISTICS                WHERE SCHEMA_NAME = 'BD_AD_SCE' AND TABLE_NAME = 'acknowledged_integrity_issues';': Unknown table 'column_statistics' in information_schema (1109)
/*!40000 ALTER TABLE `acknowledged_integrity_issues` ENABLE KEYS */;
UNLOCK TABLES;
