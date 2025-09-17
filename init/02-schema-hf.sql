-- Heart Failure Study Schema
-- This schema is commented out until Heart Failure study migration is needed
-- Uncomment and modify as needed for Heart Failure study deployment

/*
-- Heart Failure-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','Possible','No') NOT NULL,
  `hf_type` enum('HFrEF','HFpEF','HFmrEF','Unknown') DEFAULT NULL,
  `ejection_fraction` decimal(4,1) DEFAULT NULL,
  `nyha_class` enum('I','II','III','IV','Unknown') DEFAULT NULL,
  `bnp_level` int(6) DEFAULT NULL,
  `hospitalization_required` tinyint(1) DEFAULT NULL,
  `diuretic_use` tinyint(1) DEFAULT NULL,
  `ace_inhibitor` tinyint(1) DEFAULT NULL,
  `beta_blocker` tinyint(1) DEFAULT NULL,
  `aldosterone_antagonist` tinyint(1) DEFAULT NULL,
  `symptoms` enum('Dyspnea','Fatigue','Edema','Other') DEFAULT NULL,
  `etiology` enum('Ischemic','Hypertensive','Valvular','Other') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Heart Failure-specific derived data
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Definite','Probable','Possible','No') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('Pneumonia','COPD','Renal_failure','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('Pneumonia','COPD','Renal_failure','Other') DEFAULT NULL,
  `echo_performed` tinyint(1) DEFAULT NULL,
  `echo_ef` decimal(4,1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
