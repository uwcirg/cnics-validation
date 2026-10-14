-- Study-specific schema for STUDY_TYPE=mci (MI / myocardial infarction).
--
-- Applied at database initialization by init/04-study-schema.sh, and only in
-- a deployment whose STUDY_TYPE is mci, or whose study has no usable schema
-- file (the mci fallback). See constitution Principle IV, "Study-specific
-- tables": a deployment's database holds only its own study's
-- study-specific tables.
--
-- Both tables were moved verbatim from init/02-schema.sql by spec 012. That
-- file is a dump of the legacy cnics-mci_prod database, so these definitions
-- are the legacy MI schema; keep them unchanged (Principle III).

/*!40101 SET NAMES utf8mb4 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;

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

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
