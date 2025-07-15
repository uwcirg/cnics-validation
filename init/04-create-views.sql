CREATE OR REPLACE VIEW events_view AS
SELECT e.*, p.site_patient_id, p.site
FROM events e
JOIN uw_patients p ON e.patient_id = p.id;

