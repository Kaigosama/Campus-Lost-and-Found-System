-- =====================================================================
--  010 — where staff store a found item is now picked from a fixed list
--  (STORAGE_LOCATIONS in config/config.php) instead of typed freely.
--  Moves the one sample value that is not on the list. Any other
--  free-text value stays as it is; the edit form asks for a new choice.
--  updated_at is kept, so the change does not look like a user's edit.
-- =====================================================================

UPDATE found_items SET updated_at = updated_at, storage_location = 'Cabinet A, Shelf 1'
WHERE storage_location = 'Cabinet A, Shelf 3';
