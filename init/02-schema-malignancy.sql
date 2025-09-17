-- Malignancy Study Schema
-- This schema is commented out until Malignancy study migration is needed
-- Uncomment and modify as needed for Malignancy study deployment
-- Note: Does not use CakePHP, separate architecture

/*
-- Malignancy-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Confirmed','Suspected','No','Indeterminate') NOT NULL,
  `cancer_type` enum('Breast','Lung','Colorectal','Prostate','Lymphoma','Other') DEFAULT NULL,
  `stage` enum('I','II','III','IV','Unknown') DEFAULT NULL,
  `biopsy_confirmed` tinyint(1) DEFAULT NULL,
  `tumor_size` decimal(5,2) DEFAULT NULL,
  `metastasis` tinyint(1) DEFAULT NULL,
  `treatment_type` enum('Surgery','Chemotherapy','Radiation','Immunotherapy','Other') DEFAULT NULL,
  `grade` enum('Well_differentiated','Moderately_differentiated','Poorly_differentiated','Unknown') DEFAULT NULL,
  `molecular_markers` text DEFAULT NULL,
  `recurrence` tinyint(1) DEFAULT NULL,
  `survival_status` enum('Alive','Dead','Unknown') DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- Malignancy-specific derived data
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Confirmed','Suspected','No','Indeterminate') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('Benign_tumor','Infection','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('Benign_tumor','Infection','Other') DEFAULT NULL,
  `imaging_type` enum('CT','MRI','PET','Ultrasound','Other') DEFAULT NULL,
  `imaging_positive` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
