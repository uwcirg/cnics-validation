-- Heart Failure Study Schema
-- This schema is commented out until Heart Failure study migration is needed
-- Uncomment and modify as needed for Heart Failure study deployment

/*
-- Heart Failure-specific review criteria (based on actual CakePHP HF review form)
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  
  -- HF Type Classification
  `hf_type` enum('Not HF','Probable HF','Definite HF','Definite/Probable HF') DEFAULT NULL,
  
  -- LVEF (Left Ventricular Ejection Fraction) - range 1-80%
  `lvef` int(3) DEFAULT NULL,
  
  -- HF Classification based on LVEF
  `hf_classification` enum('Preserved (LVEF >=50%)','Intermediate / mid-range EF (LVEF 40-49%)','Reduced (LVEF < 40%)') DEFAULT NULL,
  
  -- Congestion Assessment
  `congestion` enum('Yes','No','N/A - No Xray') DEFAULT NULL,
  
  -- Presentation Types
  `presentation` enum('Predominantly L-sided HF','Predominantly R-sided HF','Combined L- and R-sided dysfunction as contributors to HF') DEFAULT NULL,
  
  -- Lab Values
  `labs` enum('Yes','No','No labs') DEFAULT NULL,
  
  -- Additional HF-specific fields (based on CakePHP constants)
  `not_reported` enum('not reported') DEFAULT NULL,
  
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
