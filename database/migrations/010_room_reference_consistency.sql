-- ============================================================================
-- Migration 010: one clear meaning for room references (BV-013). Safe to run more than once.
--
--   bookings.room_id                         = the room CODE (e.g. 'SR-1'). A readable reference used by the API, the
--                                              customer portal and the admin screens. Kept as-is so nothing breaks.
--   appointment_room_assignments.room_id     = the numeric rooms.id. This table is the source of truth for "who has which
--                                              room, when" (including the cleaning buffer); history is kept as RELEASED rows.
--
-- This migration only adds what is missing: an active booking that names a room code but has no ASSIGNED row in
-- appointment_room_assignments (older bookings made before assignments existed) gets one, so conflict checks see it.
-- Nothing is deleted or overwritten. The cleaning buffer used is 30 minutes (BUFFER_MINUTES default).
-- ============================================================================
ALTER TABLE `bookings` MODIFY `room_id` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Room CODE (rooms.room_code), e.g. SR-1. Source of truth for time conflicts: appointment_room_assignments (numeric rooms.id)';

INSERT INTO `appointment_room_assignments` (`booking_id`, `room_id`, `assigned_date`, `start_time`, `end_time`, `cleaning_end_time`, `status`, `assigned_by`)
SELECT b.`id`, r.`id`, b.`preferred_date`, b.`time`,
       TIME_FORMAT(ADDTIME(STR_TO_DATE(b.`time`, '%H:%i'), SEC_TO_TIME(CAST(b.`duration` AS UNSIGNED) * 60)), '%H:%i'),
       TIME_FORMAT(ADDTIME(STR_TO_DATE(b.`time`, '%H:%i'), SEC_TO_TIME((CAST(b.`duration` AS UNSIGNED) + 30) * 60)), '%H:%i'),
       'ASSIGNED', 'Migration 010'
FROM `bookings` b
JOIN `rooms` r ON r.`room_code` = b.`room_id`
WHERE b.`archived` = 0
  AND b.`status` IN ('PENDING', 'CONFIRMED', 'IN_PROGRESS')
  AND b.`duration` REGEXP '^[0-9]+$' AND b.`time` REGEXP '^[0-2][0-9]:[0-5][0-9]$' AND b.`preferred_date` REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'
  AND NOT EXISTS (SELECT 1 FROM `appointment_room_assignments` a WHERE a.`booking_id` = b.`id` AND a.`status` = 'ASSIGNED');
