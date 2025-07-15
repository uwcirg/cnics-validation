# Database Maintenance Tasks

The following query finds events that reference a `patient_id` not present in the `patients` view:

```sql
SELECT id, patient_id
FROM events
WHERE patient_id NOT IN (SELECT id FROM patients);
```

Once you identify these rows, update them with a valid patient ID. Examples:

```sql
-- Reassign a single event
UPDATE events
SET patient_id = 1001
WHERE id = 42;

-- Update multiple events at once
UPDATE events
SET patient_id = 1001
WHERE id IN (43, 44);
```

Ensure the new `patient_id` exists in the `patients` view before running the updates.
