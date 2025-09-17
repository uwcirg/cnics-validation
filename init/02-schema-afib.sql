-- AFIB Study Schema
-- This schema is commented out until AFIB study migration is needed
-- Uncomment and modify as needed for AFIB study deployment
-- Note: No immediate plans for use per Heidi

/*
-- AFIB-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `afib_type` enum('Paroxysmal','Persistent','Permanent','Unknown') DEFAULT NULL,
  `ecg_evidence` tinyint(1) DEFAULT NULL,
  `duration_hours` int(4) DEFAULT NULL,
  `anticoagulation` tinyint(1) DEFAULT NULL,
  `rate_control` tinyint(1) DEFAULT NULL,
  `rhythm_control` tinyint(1) DEFAULT NULL,
  `chads2_score` int(1) DEFAULT NULL,
  `chads2vasc_score` int(2) DEFAULT NULL,
  `bleeding_risk` enum('Low','Moderate','High') DEFAULT NULL,
  `cardioversion` tinyint(1) DEFAULT NULL,
  `ablation` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- AFIB-specific derived data
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Definite','Probable','Possible','No') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('SVT','Atrial_flutter','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('SVT','Atrial_flutter','Other') DEFAULT NULL,
  `ecg_type` enum('AFIB','Atrial_flutter','SVT','Normal','Other') DEFAULT NULL,
  `heart_rate` int(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
