-- VTE (Venothromboembolic) Study Schema
-- This schema is commented out until VTE study migration is needed
-- Uncomment and modify as needed for VTE study deployment

/*
-- VTE-specific review criteria (based on actual CakePHP VTE review form)
CREATE TABLE `reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `event_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  
  -- VTE Type Flags
  `pe_flag` tinyint(1) DEFAULT NULL,
  `dvt_flag` tinyint(1) DEFAULT NULL,
  `cat_flag` tinyint(1) DEFAULT NULL,
  `no_vte_flag` tinyint(1) DEFAULT NULL,
  
  -- PE (Pulmonary Embolism) Fields
  `pe_dp` enum('Definite','Probable') DEFAULT NULL,
  `pe_type` enum('Acute','Chronic','Unspecified') DEFAULT NULL,
  `pe_main_flag` tinyint(1) DEFAULT NULL,
  `pe_lobar_flag` tinyint(1) DEFAULT NULL,
  `pe_segmental_flag` tinyint(1) DEFAULT NULL,
  `pe_subsegmental_flag` tinyint(1) DEFAULT NULL,
  `pe_unknown_flag` tinyint(1) DEFAULT NULL,
  
  -- DVT (Deep Vein Thrombosis) Fields
  `dvt_dp` enum('Definite','Probable') DEFAULT NULL,
  `dvt_type` enum('Acute','Chronic','Unspecified') DEFAULT NULL,
  `dvt_ue_flag` tinyint(1) DEFAULT NULL,
  `dvt_le_flag` tinyint(1) DEFAULT NULL,
  `dvt_other_flag` tinyint(1) DEFAULT NULL,
  `dvt_unknown_flag` tinyint(1) DEFAULT NULL,
  `dvt_le_proximal_flag` tinyint(1) DEFAULT NULL,
  `dvt_le_distal_flag` tinyint(1) DEFAULT NULL,
  `dvt_le_unknown_flag` tinyint(1) DEFAULT NULL,
  
  -- Catheter-induced Thrombosis Fields
  `cat_dp` enum('Definite','Probable') DEFAULT NULL,
  `cat_type` enum('Acute','Chronic','Unspecified') DEFAULT NULL,
  `cat_subtype` enum('Central venous access catheter','Dialysis graft/shunt/fistula') DEFAULT NULL,
  
  -- Risk Factors (Contributing Conditions)
  `cc_malignancy_flag` tinyint(1) DEFAULT NULL,
  `cc_chemo_flag` tinyint(1) DEFAULT NULL,
  `cc_heartfailure_flag` tinyint(1) DEFAULT NULL,
  `cc_ns_flag` tinyint(1) DEFAULT NULL,
  `cc_dialysis_flag` tinyint(1) DEFAULT NULL,
  `cc_hosp_flag` tinyint(1) DEFAULT NULL,
  `cc_mt_flag` tinyint(1) DEFAULT NULL,
  `cc_immob_flag` tinyint(1) DEFAULT NULL,
  `cc_longride_flag` tinyint(1) DEFAULT NULL,
  `cc_surgery_flag` tinyint(1) DEFAULT NULL,
  `cc_infection_flag` tinyint(1) DEFAULT NULL,
  `cc_ivdrug_flag` tinyint(1) DEFAULT NULL,
  `cc_transfusion_flag` tinyint(1) DEFAULT NULL,
  `cc_inherited_flag` tinyint(1) DEFAULT NULL,
  `cc_copd_flag` tinyint(1) DEFAULT NULL,
  `cc_ph_flag` tinyint(1) DEFAULT NULL,
  `cc_steroid_flag` tinyint(1) DEFAULT NULL,
  `cc_pregnancy_flag` tinyint(1) DEFAULT NULL,
  `cc_other_flag` tinyint(1) DEFAULT NULL,
  
  -- Management Fields
  `management_at` enum('Yes','Prescribed but not taking or inadequate use with subtherapeutic INR','No','Unknown') DEFAULT NULL,
  `management_managedas` enum('Inpatient','Outpatient only (including ER)','Both','Unknown') DEFAULT NULL,
  `management_duration_days` int(4) DEFAULT NULL,
  
  -- IV Drug Use
  `iv_drug_use` enum('Current','Past') DEFAULT NULL,
  
  -- Smoking
  `smoking_use` enum('Current','Past','Unknown','None') DEFAULT NULL,
  
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
