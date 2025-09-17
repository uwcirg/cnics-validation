-- AFIB Study Schema
-- This schema is commented out until AFIB study migration is needed
-- Uncomment and modify as needed for AFIB study deployment
-- Note: No immediate plans for use per Heidi

/*
-- AFIB-specific review criteria (based on actual CakePHP AFIB review form)
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  
  -- AFIB/AFlutter Flags
  `afib_flag` tinyint(1) DEFAULT NULL,
  `aflutter_flag` tinyint(1) DEFAULT NULL,
  `af_foundonly_flag` tinyint(1) DEFAULT NULL,
  `no_af_flag` tinyint(1) DEFAULT NULL,
  
  -- Encounter Types
  `afib_encounter_flag` tinyint(1) DEFAULT NULL,
  `afib_history_flag` tinyint(1) DEFAULT NULL,
  `aflutter_encounter_flag` tinyint(1) DEFAULT NULL,
  `aflutter_history_flag` tinyint(1) DEFAULT NULL,
  
  -- AF Timing
  `af_timing` enum('Presented in AF','AF started after admission') DEFAULT NULL,
  
  -- AF Type Classification
  `af_type` enum('paroxysmal','persistent','permanent','unknown') DEFAULT NULL,
  
  -- Associated Conditions
  `assoc_coronary_flag` tinyint(1) DEFAULT NULL,
  `assoc_mi_flag` tinyint(1) DEFAULT NULL,
  `assoc_hf_flag` tinyint(1) DEFAULT NULL,
  `assoc_vhd_flag` tinyint(1) DEFAULT NULL,
  `assoc_copd_flag` tinyint(1) DEFAULT NULL,
  `assoc_stroke_flag` tinyint(1) DEFAULT NULL,
  `assoc_infection_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_flag` tinyint(1) DEFAULT NULL,
  `assoc_pvd_flag` tinyint(1) DEFAULT NULL,
  `assoc_surgery_flag` tinyint(1) DEFAULT NULL,
  `assoc_none_flag` tinyint(1) DEFAULT NULL,
  
  -- Infection Subtypes
  `assoc_infection_sepsis_flag` tinyint(1) DEFAULT NULL,
  `assoc_infection_bacteremia_flag` tinyint(1) DEFAULT NULL,
  `assoc_infection_pneumonia_flag` tinyint(1) DEFAULT NULL,
  `assoc_infection_other_flag` tinyint(1) DEFAULT NULL,
  
  -- Thoracic Subtypes
  `assoc_thoracic_malignancy_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_mass_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_pericarditis_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_ild_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_ph_flag` tinyint(1) DEFAULT NULL,
  `assoc_thoracic_other_flag` tinyint(1) DEFAULT NULL,
  
  -- Substance Use
  `tobacco_use` enum('current','past','unknown','none') DEFAULT NULL,
  `ha_flag` tinyint(1) DEFAULT NULL, -- Heavy Alcohol
  `sub_other_flag` tinyint(1) DEFAULT NULL,
  `sub_other_marijuana_flag` tinyint(1) DEFAULT NULL,
  `sub_other_meth_flag` tinyint(1) DEFAULT NULL,
  `sub_other_cocaine_flag` tinyint(1) DEFAULT NULL,
  `sub_other_opiate_flag` tinyint(1) DEFAULT NULL,
  `sub_other_unspecified_flag` tinyint(1) DEFAULT NULL,
  
  -- Secondary Causes
  `secondary_yes` tinyint(1) DEFAULT NULL,
  `secondary_no` tinyint(1) DEFAULT NULL,
  `secondary_other_flag` tinyint(1) DEFAULT NULL,
  
  -- Echocardiogram
  `echo_flag` tinyint(1) DEFAULT NULL,
  `echo_other_flag` tinyint(1) DEFAULT NULL,
  
  -- Anticoagulation
  `antic_flag` tinyint(1) DEFAULT NULL,
  `antic_already_flag` tinyint(1) DEFAULT NULL,
  `antic_prescribed_flag` tinyint(1) DEFAULT NULL,
  
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
