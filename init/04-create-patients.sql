-- Ensure `patients` table exists and populate from `uw_patients2` if missing
CREATE TABLE IF NOT EXISTS `patients` (
  `id` int(10) unsigned NOT NULL,
  `site_patient_id` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
  `site` varchar(20) NOT NULL,
  `last_update` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `create_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `patients`
SELECT * FROM `uw_patients2`
WHERE NOT EXISTS (SELECT 1 FROM `patients` LIMIT 1) AND id <> 0;

