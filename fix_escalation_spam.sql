-- ============================================================
-- FULL CLEANUP — Run this in phpMyAdmin on knowledgebase DB
-- ============================================================
USE knowledgebase;

-- Step 1: Delete ALL duplicate escalated entries.
-- Keep only 1 escalated entry per ticket (the first one ever).
DELETE FROM ticket_activity
WHERE action = 'escalated'
  AND id NOT IN (
      SELECT keep_id FROM (
          SELECT MIN(id) AS keep_id
          FROM ticket_activity
          WHERE action = 'escalated'
          GROUP BY ticket_id
      ) AS t
  );

-- Step 2: Fix tickets stuck open/in_progress past deadline with no next level
-- → mark them unattended so the SLA check never touches them again
UPDATE tickets t
SET t.status = 'unattended'
WHERE t.status IN ('open', 'in_progress')
  AND (
        (t.status = 'open'        AND t.attend_deadline  < NOW() AND t.attended_at IS NULL)
     OR (t.status = 'in_progress' AND t.resolve_deadline < NOW() AND t.resolved_at IS NULL)
  )
  AND NOT EXISTS (
      SELECT 1 FROM ticket_levels tl2
      WHERE tl2.level_order > (
          SELECT level_order FROM ticket_levels tl3 WHERE tl3.id = t.current_level
      )
  );

-- Step 3: Confirm — show activity trail for ticket 1
SELECT id, ticket_id, action, level_id, created_at
FROM ticket_activity
WHERE ticket_id = 1
ORDER BY id;

-- Step 4: Fix Level SLA values so attend != resolve
-- (attend_sla should be shorter than resolve_sla)
UPDATE ticket_levels SET attend_sla = 60,  resolve_sla = 120 WHERE level_name = 'Level 1';
UPDATE ticket_levels SET attend_sla = 90,  resolve_sla = 180 WHERE level_name = 'Level 2';
UPDATE ticket_levels SET attend_sla = 120, resolve_sla = 240 WHERE level_name = 'Level 3';

-- Confirm levels
SELECT id, level_name, attend_sla, resolve_sla FROM ticket_levels;
