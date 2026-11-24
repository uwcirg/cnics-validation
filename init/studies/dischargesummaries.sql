-- Study-specific schema for STUDY_TYPE=dischargesummaries (spec 013).
--
-- Applied at database initialization by init/04-study-schema.sh, and only in
-- a deployment whose STUDY_TYPE is dischargesummaries. A deployment's
-- database holds only its own study's study-specific tables (constitution
-- Principle IV).
--
-- Phase A: `reviews` holds only the shared columns, meeting the invariant
-- reviews(id, event_id, reviewer_id), one row per reviewer per event. The
-- answer columns arrive with the review form (spec 013 FR-011, research D5).
-- Until then the backend refuses every review submission, so this table
-- stays empty and the Phase B migration needs to move no data.
--
-- dischargesummaries has a single reviewer, so there is no
-- `event_derived_datas` (allowed by constitution v1.5.1). `events` and
-- `users` are MyISAM, so there are no foreign keys to them.
--
-- Idempotent (IF NOT EXISTS).

CREATE TABLE IF NOT EXISTS `reviews` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `event_id`    int(11)  NOT NULL,
  `reviewer_id` int(11)  NOT NULL,
  `created`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified`    datetime NULL DEFAULT NULL, -- last edit by the author; NULL = never edited (spec 014)
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_reviewer` (`event_id`, `reviewer_id`),
  KEY `event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='dischargesummaries: one row per completed review';
