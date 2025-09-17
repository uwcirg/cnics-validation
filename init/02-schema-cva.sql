-- CVA (Cerebrovascular Events - Stroke) Study Schema
-- This schema is commented out until CVA study migration is needed
-- Uncomment and modify as needed for CVA study deployment

/*
-- CVA-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `stroke_type` enum('Ischemic','Hemorrhagic','TIA','Other') DEFAULT NULL,
  `nihss_score` int(3) DEFAULT NULL,
  `imaging_evidence` tinyint(1) DEFAULT NULL,
  `time_to_treatment` int(4) DEFAULT NULL COMMENT 'minutes',
  `thrombolysis` tinyint(1) DEFAULT NULL,
  `mechanical_thrombectomy` tinyint(1) DEFAULT NULL,
  `stroke_location` enum('Anterior','Posterior','Lacunar','Other') DEFAULT NULL,
  `stroke_mechanism` enum('Large_vessel','Cardioembolic','Small_vessel','Other') DEFAULT NULL,
  `modified_rankin_score` int(1) DEFAULT NULL,
  `discharge_destination` enum('Home','Rehab','SNF','Other') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- CVA questionnaire tables
CREATE TABLE `questionnaires` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `questionnaire_type` enum('baseline','followup','outcome') NOT NULL,
  `completed_date` datetime DEFAULT NULL,
  `data` json DEFAULT NULL,
  `reviewer_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `questionnaire_type` (`questionnaire_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- CVA-specific derived data
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Definite','Probable','Possible','No') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('Seizure','Migraine','Syncope','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('Seizure','Migraine','Syncope','Other') DEFAULT NULL,
  `imaging_type` enum('CT','MRI','CTA','MRA','Other') DEFAULT NULL,
  `imaging_positive` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
