-- Existing historical actions remain unassigned rather than receiving invented shifts.
ALTER TABLE parking_actions ADD COLUMN IF NOT EXISTS shift_id INT NULL;
ALTER TABLE parking_actions ADD COLUMN IF NOT EXISTS authorized_by INT NULL;
CREATE INDEX IF NOT EXISTS idx_action_shift ON parking_actions(shift_id,id);
