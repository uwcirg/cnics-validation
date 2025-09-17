-- VTE (Venothromboembolic) Study Schema
-- This schema is commented out until VTE study migration is needed
-- Uncomment and modify as needed for VTE study deployment

/*
-- VTE-specific review criteria
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `outcome` enum('Definite','Probable','No') NOT NULL,
  `vte_type` enum('DVT','PE','Both','Other') DEFAULT NULL,
  `dvt_location` enum('Proximal','Distal','Upper','Other') DEFAULT NULL,
  `pe_severity` enum('Massive','Submassive','Low_risk') DEFAULT NULL,
  `imaging_evidence` tinyint(1) DEFAULT NULL,
  `anticoagulation` tinyint(1) DEFAULT NULL,
  `thrombophilia_workup` tinyint(1) DEFAULT NULL,
  `dvt_symptoms` tinyint(1) DEFAULT NULL,
  `pe_symptoms` tinyint(1) DEFAULT NULL,
  `risk_factors` text DEFAULT NULL,
  `treatment_duration_days` int(4) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_id` (`event_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- VTE-specific event statuses including prescrub
ALTER TABLE `events` MODIFY `status` enum('created','uploaded','prescrubbed','prescrub_rejected','scrubbed','screened','assigned','sent','reviewer1_done','reviewer2_done','third_review_needed','third_review_assigned','done','rejected','no_packet_available') NOT NULL DEFAULT 'created';

-- VTE-specific derived data
CREATE TABLE `event_derived_datas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL COMMENT 'foreign key into events table',
  `outcome` enum('Definite','Probable','No') DEFAULT NULL,
  `primary_secondary` enum('Primary','Secondary') DEFAULT NULL,
  `false_positive_event` tinyint(1) DEFAULT NULL,
  `secondary_cause` enum('Surgery','Trauma','Cancer','Pregnancy','Immobility','Other','NC') DEFAULT NULL,
  `secondary_cause_other` varchar(100) DEFAULT NULL,
  `false_positive_reason` enum('Cellulitis','Lymphedema','Baker_cyst','Other') DEFAULT NULL,
  `imaging_type` enum('Ultrasound','CT','MRI','VQ_scan','Other') DEFAULT NULL,
  `imaging_positive` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_id` (`event_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
*/
