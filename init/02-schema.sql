/*M!999999\- enable the sandbox mode */ 
-- MariaDB dump 10.19  Distrib 10.5.29-MariaDB, for debian-linux-gnu (x86_64)
--
-- Host: localhost    Database: cnics-mci_prod
-- ------------------------------------------------------
-- Server version	10.5.29-MariaDB-0+deb11u1-log

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `criterias`
--

DROP TABLE IF EXISTS `criterias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `criterias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key in events table',
  `name` varchar(50) NOT NULL,
  `value` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `name` (`name`),
  KEY `value` (`value`)
) ENGINE=MyISAM AUTO_INCREMENT=7740 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `d`
--

DROP TABLE IF EXISTS `d`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `d` (
  `id` int(11) DEFAULT NULL,
  `type` varchar(16) DEFAULT NULL,
  `assay` varchar(32) DEFAULT NULL,
  `result` float DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `event_derived_datas`
--

DROP TABLE IF EXISTS `event_derived_datas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Definite','Probable','No','No [resuscitated cardiac arrest]') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('MVA','Overdose','Anaphlaxis','GI bleed','Sepsis/bacteremia','Procedure related','Arrhythmia','Cocaine or other illicit drug induced vasospasm','Hypertensive urgency/emergency','Hypoxia','Hypotension','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('Congestive heart failure','Myocarditis','Pericarditis','Pulmonary embolism','Renal failure','Severe sepsis/shock','Other') DEFAULT NULL,
  `ci` tinyint(1) DEFAULT NULL,
  `ci_type` enum('CABG/Surgery','PCI/Angioplasty','Stent','Unknown','NC') DEFAULT NULL,
  `ecg_type` enum('STEMI','non-STEMI','Other/Uninterpretable','New LBBB','Normal','No EKG','NC') DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3063 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `events`
--

DROP TABLE IF EXISTS `events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `events` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `patient_id` int(10) NOT NULL,
  `creator_id` int(11) NOT NULL COMMENT 'foreign key in users table',
  `uploader_id` int(11) DEFAULT NULL COMMENT 'foreign key in users table',
  `file_number` int(10) DEFAULT NULL,
  `original_name` varchar(100) DEFAULT NULL,
  `marker_id` int(11) DEFAULT NULL,
  `scrubber_id` int(11) DEFAULT NULL COMMENT 'foreign key into users table',
  `screener_id` int(11) DEFAULT NULL COMMENT 'foreign key in users table',
  `assigner_id` int(11) DEFAULT NULL COMMENT 'foreign key into users table',
  `sender_id` int(11) DEFAULT NULL COMMENT 'foreign key into users table',
  `reviewer1_id` int(11) DEFAULT NULL,
  `reviewer2_id` int(11) DEFAULT NULL,
  `assigner3rd_id` int(11) DEFAULT NULL COMMENT 'foreign key into users table',
  `reviewer3_id` int(11) DEFAULT NULL,
  `status` enum('created','uploaded','scrubbed','screened','assigned','sent','reviewer1_done','reviewer2_done','third_review_needed','third_review_assigned','done','rejected','no_packet_available') NOT NULL DEFAULT 'created',
  `rescrub_message` varchar(500) DEFAULT NULL,
  `reject_message` varchar(500) DEFAULT NULL,
  `no_packet_reason` enum('Outside hospital','Ascertainment diagnosis error','Ascertainment diagnosis referred to a prior event','Other') DEFAULT NULL COMMENT 'reasons why ''no_packet_available''',
  `two_attempts_flag` tinyint(1) DEFAULT NULL COMMENT 'only set if ''outside hospital''',
  `prior_event_date` varchar(7) DEFAULT NULL COMMENT 'only set if  ''Ascertainment diagnosis referred to a prior event''; null if event date not known',
  `prior_event_onsite_flag` tinyint(1) DEFAULT NULL COMMENT 'only set if ''Ascertainment diagnosis referred to a prior event''',
  `other_cause` varchar(100) DEFAULT NULL COMMENT 'only set if ''other''',
  `add_date` date NOT NULL,
  `upload_date` date DEFAULT NULL,
  `markNoPacket_date` date DEFAULT NULL,
  `scrub_date` date DEFAULT NULL,
  `screen_date` date DEFAULT NULL,
  `assign_date` date DEFAULT NULL,
  `send_date` date DEFAULT NULL,
  `review1_date` date DEFAULT NULL,
  `review2_date` date DEFAULT NULL,
  `assign3rd_date` date DEFAULT NULL,
  `review3_date` date DEFAULT NULL,
  `event_date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `patient_id` (`patient_id`),
  KEY `reviewer1_id` (`reviewer1_id`),
  KEY `reviewer2_id` (`reviewer2_id`),
  KEY `reviewer3_id` (`reviewer3_id`),
  KEY `status` (`status`),
  KEY `marker_id` (`marker_id`)
) ENGINE=MyISAM AUTO_INCREMENT=5848 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `logs`
--

DROP TABLE IF EXISTS `logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `controller` varchar(30) NOT NULL,
  `action` varchar(30) NOT NULL,
  `params` varchar(1000) DEFAULT NULL,
  `time` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=245827 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `reviews`
--

DROP TABLE IF EXISTS `reviews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `mci` enum('Definite','Probable','No','No [resuscitated cardiac arrest]') NOT NULL,
  `abnormal_ce_values_flag` tinyint(1) DEFAULT NULL COMMENT 'abnormal cardiac enzyme values',
  `ce_criteria` enum('Standard criteria','PTCA criteria','CABG criteria','Muscle trauma other than PTCA/CABG') DEFAULT NULL COMMENT 'cardiac enzyme criteria',
  `chest_pain_flag` tinyint(1) DEFAULT NULL,
  `ecg_changes_flag` tinyint(1) DEFAULT NULL,
  `lvm_by_imaging_flag` tinyint(1) DEFAULT NULL COMMENT 'Loss of viable myocardium or regional wall abnormalities by imaging',
  `ci` tinyint(1) DEFAULT NULL COMMENT 'Did the patient have a cardiac intervention (only set if mci = either type of ''no'')',
  `type` enum('Primary','Secondary') DEFAULT NULL,
  `secondary_cause` enum('MVA','Overdose','Anaphlaxis','GI bleed','Sepsis/bacteremia','Procedure related','Arrhythmia','Cocaine or other illicit drug induced vasospasm','Hypertensive urgency/emergency','Hypoxia','Hypotension','COVID','Other') DEFAULT NULL,
  `other_cause` varchar(100) DEFAULT NULL,
  `false_positive_flag` tinyint(1) DEFAULT NULL,
  `false_positive_reason` enum('Congestive heart failure','Myocarditis','Pericarditis','Pulmonary embolism','Renal failure','Severe sepsis/shock','Other') DEFAULT NULL,
  `false_positive_other_cause` varchar(100) DEFAULT NULL,
  `current_tobacco_use_flag` tinyint(1) DEFAULT NULL,
  `past_tobacco_use_flag` tinyint(1) DEFAULT NULL,
  `cocaine_use_flag` tinyint(1) DEFAULT NULL,
  `family_history_flag` tinyint(1) DEFAULT NULL,
  `ci_type` enum('CABG/Surgery','PCI/Angioplasty','Stent','Unknown') DEFAULT NULL,
  `cardiac_cath` tinyint(1) NOT NULL,
  `ecg_type` enum('STEMI','non-STEMI','Other/Uninterpretable','New LBBB','Normal','No EKG') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM AUTO_INCREMENT=7327 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `solicitations`
--

DROP TABLE IF EXISTS `solicitations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `solicitations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key iin events table',
  `date` date NOT NULL,
  `contact` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(200) NOT NULL,
  `login` varchar(200) NOT NULL,
  `first_name` varchar(64) NOT NULL,
  `last_name` varchar(64) NOT NULL,
  `site` varchar(20) NOT NULL,
  `uploader_flag` tinyint(1) NOT NULL DEFAULT 0,
  `reviewer_flag` tinyint(1) NOT NULL DEFAULT 1,
  `third_reviewer_flag` tinyint(1) NOT NULL DEFAULT 0,
  `admin_flag` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `username` (`username`),
  KEY `last_name` (`last_name`),
  KEY `reviewer_flag` (`reviewer_flag`),
  KEY `admin_flag` (`admin_flag`),
  KEY `third_reviewer_flag` (`third_reviewer_flag`),
  KEY `login` (`login`)
) ENGINE=MyISAM AUTO_INCREMENT=155 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `uw_patients`
--

DROP TABLE IF EXISTS `uw_patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uw_patients` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `site_patient_id` varchar(64) NOT NULL DEFAULT '',
  `site` varchar(20) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `Patient_idx` (`site_patient_id`,`site`),
  KEY `site` (`site`)
) ENGINE=InnoDB AUTO_INCREMENT=2949 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci `PAGE_COMPRESSED`='ON';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `uw_patients2`
--

DROP TABLE IF EXISTS `uw_patients2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `uw_patients2` (
  `id` int(10) unsigned NOT NULL DEFAULT 0,
  `site_patient_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `site` varchar(20) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--

--
-- Table structure for table `patients`
--

DROP TABLE IF EXISTS `patients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8mb4 */;
CREATE TABLE `patients` (
  `id` int(10) unsigned NOT NULL,
  `site_patient_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `site` varchar(20) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
