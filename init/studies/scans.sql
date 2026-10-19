-- Study-specific schema for STUDY_TYPE=scans (DEXA scan review; spec 012).
--
-- Applied at database initialization by init/04-study-schema.sh, and only in
-- a deployment whose STUDY_TYPE is scans. A deployment's database holds only
-- its own study's study-specific tables (constitution Principle IV).
--
-- * `reviews`: one row per completed review. It meets the shared invariant
--   reviews(id, event_id, reviewer_id), one row per reviewer per event.
-- * `review_regions`: one row per body-region entry, in entry order.
--   NULL = not provided.
--
-- scans has a single reviewer, so there is no `event_derived_datas` (allowed
-- by constitution v1.5.1). `events` and `users` are MyISAM, so there are no
-- foreign keys to them. `review_regions` → `reviews` is a real foreign key,
-- since both are InnoDB.
--
-- Idempotent (IF NOT EXISTS): init/migrations/012-scans-reviews.sh re-applies
-- this file to upgrade existing scans databases.

CREATE TABLE IF NOT EXISTS `reviews` (
  `id`          int(11)  NOT NULL AUTO_INCREMENT,
  `event_id`    int(11)  NOT NULL,
  `reviewer_id` int(11)  NOT NULL,
  `created`     datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `event_reviewer` (`event_id`, `reviewer_id`),
  KEY `reviewer_id` (`reviewer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='scans: one row per completed review';

CREATE TABLE IF NOT EXISTS `review_regions` (
  `id`           int(11)      NOT NULL AUTO_INCREMENT,
  `review_id`    int(11)      NOT NULL,
  `position`     tinyint(3) unsigned NOT NULL COMMENT '1-based entry order within the review',
  `region`       varchar(40)  NOT NULL COMMENT 'dropdown value, e.g. "Lumbar spine"; validated in code (flask_backend/models/studies/scans.py)',
  `region_other` varchar(100) DEFAULT NULL COMMENT 'free text; set only when region = "Other"',
  `laterality`   enum('Left','Right','Both','Unspecified') DEFAULT NULL COMMENT 'NULL = not provided',
  `t_score`      decimal(4,2) DEFAULT NULL COMMENT 'NULL = not provided',
  `z_score`      decimal(4,2) DEFAULT NULL COMMENT 'NULL = not provided',
  PRIMARY KEY (`id`),
  UNIQUE KEY `review_position` (`review_id`, `position`),
  CONSTRAINT `review_regions_review` FOREIGN KEY (`review_id`)
    REFERENCES `reviews` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
  COMMENT='scans: one row per body-region entry';
