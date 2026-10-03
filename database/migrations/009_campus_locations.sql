-- =====================================================================
--  009 — where an item was found or lost is now picked from a fixed list
--  (CAMPUS_LOCATIONS in config/config.php) instead of typed freely.
--  Moves the old suggested locations onto the closest new one. Any other
--  free-text value stays as it is; the edit form asks for a new choice.
--  updated_at is kept, so the change does not look like a user's edit.
-- =====================================================================

UPDATE found_items SET updated_at = updated_at, location_found = CASE location_found
    WHEN 'Cafeteria'      THEN 'Canteen & Café'
    WHEN 'North Building' THEN 'Classrooms'
    WHEN 'South Building' THEN 'Classrooms'
    WHEN 'Admin Building' THEN 'Student Services Office'
    WHEN 'Chapel'         THEN 'Prayer Room'
    WHEN 'Covered Court'  THEN 'Gymnasium'
    ELSE location_found END;

UPDATE lost_reports SET updated_at = updated_at, location_lost = CASE location_lost
    WHEN 'Cafeteria'      THEN 'Canteen & Café'
    WHEN 'North Building' THEN 'Classrooms'
    WHEN 'South Building' THEN 'Classrooms'
    WHEN 'Admin Building' THEN 'Student Services Office'
    WHEN 'Chapel'         THEN 'Prayer Room'
    WHEN 'Covered Court'  THEN 'Gymnasium'
    ELSE location_lost END;
