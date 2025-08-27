-- Helpful indexes to support common queries and reduce timeouts
-- MariaDB before 10.5 may not support IF NOT EXISTS on CREATE INDEX; these are safe to re-run otherwise

-- Events phase/status queries
CREATE INDEX idx_events_upload_date ON events (upload_date);
CREATE INDEX idx_events_scrub_date ON events (scrub_date);
CREATE INDEX idx_events_screen_date ON events (screen_date);
CREATE INDEX idx_events_assign_date ON events (assign_date);
CREATE INDEX idx_events_send_date ON events (send_date);

-- Reviewer awaiting
CREATE INDEX idx_events_reviewer1_review1 ON events (reviewer1_id, review1_date);
CREATE INDEX idx_events_reviewer2_review2 ON events (reviewer2_id, review2_date);
CREATE INDEX idx_events_reviewer3_review3 ON events (reviewer3_id, review3_date);

-- Patients filters
CREATE INDEX idx_patients_site ON patients (site);
CREATE INDEX idx_patients_site_patient_id ON patients (site_patient_id);

-- Criteria lookup by event and name/value (for search EXISTS)
CREATE INDEX idx_criterias_event ON criterias (event_id);
CREATE INDEX idx_criterias_event_name ON criterias (event_id, name);
