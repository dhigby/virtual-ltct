<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

/**
 * Which calendar changes are announced, and how a series collapses into one notice (spec 011,
 * FR-006, research R15).
 *
 * Core sends nothing when an event changes. observer::calendar_event_changed() buffers each
 * request's announced changes here by key, and one shutdown callback then queues one
 * task\event_change_notice per key. Nothing here calls Moodle, so
 * tests/calendar_notify_harness.php tests it without Moodle.
 *
 * Not announced:
 *   - an event from an activity (modulename) or a plugin (component), such as the scheduler's
 *     bookings, which booking_notice handles, or an assignment's due date;
 *   - an event from a calendar subscription (subscriptionid), which a sync rewrites;
 *   - a user's own event, and any type other than site, course and group;
 *   - the N-1 `updated` events core fires when the first event of a series is deleted alone,
 *     whose `other.repeatid` is the old parent while the row holds the new one;
 *   - an event that is now hidden (an update that hides it cannot be told from an edit);
 *   - an `updated` for an event created in the same request: core's form web service saves a
 *     new event twice when its description carries a file.
 *
 * New events are never announced (plan decision 5): only changes and cancellations.
 */
class calendar_notify {

    /** Event types whose changes are announced. */
    const TYPES = ['site', 'course', 'group'];

    /** Actions, as the notice's customdata carries them. */
    const CHANGED = 'changed';
    const CANCELLED = 'cancelled';

    /** Scope of a change: one occurrence, or more than one in the same request. */
    const OCCURRENCE = 'occurrence';
    const SERIES = 'series';

    /**
     * Whether one calendar_event_updated or _deleted is announced.
     *
     * @param \stdClass|null $snapshot the event row: after the change for an update, before it
     *                                 for a delete; null when core has none
     * @param array $other the log event's `other`: repeatid, name, timestart
     * @param bool $deleted true for calendar_event_deleted
     * @param bool $createdthisrequest whether this event id was created in the same request
     * @return bool
     */
    public static function decide(?\stdClass $snapshot, array $other, bool $deleted, bool $createdthisrequest): bool {
        if ($snapshot === null) {
            return false;
        }
        foreach (['modulename', 'component', 'subscriptionid'] as $field) {
            if (!empty($snapshot->$field)) {
                return false;
            }
        }
        if (!in_array((string)($snapshot->eventtype ?? ''), self::TYPES, true)) {
            return false;
        }
        if ((int)($other['repeatid'] ?? 0) !== (int)($snapshot->repeatid ?? 0)) {
            return false;
        }
        if (!$deleted && (empty($snapshot->visible) || $createdthisrequest)) {
            return false;
        }
        return true;
    }

    /**
     * The key a series shares: `r<repeatid>`, or `e<id>` for an event with no series.
     *
     * @param \stdClass $snapshot
     * @return string
     */
    public static function key(\stdClass $snapshot): string {
        $repeatid = (int)($snapshot->repeatid ?? 0);
        return $repeatid > 0 ? 'r' . $repeatid : 'e' . (int)$snapshot->id;
    }

    /**
     * Fold one more announced event into the request's pending notice for its key.
     *
     * @param array|null $pending the notice already buffered for this key, or null
     * @param bool $deleted true for a deletion
     * @param \stdClass $snapshot the event row
     * @param int $actorid the user who made the change
     * @return array the notice's customdata: action, scope, key, eventtype, courseid, groupid,
     *               name, firststart, actorid
     */
    public static function merge(?array $pending, bool $deleted, \stdClass $snapshot, int $actorid): array {
        $action = $deleted ? self::CANCELLED : self::CHANGED;
        if ($pending === null) {
            return [
                'action' => $action,
                'scope' => self::OCCURRENCE,
                'key' => self::key($snapshot),
                'eventtype' => (string)$snapshot->eventtype,
                'courseid' => (int)($snapshot->courseid ?? 0),
                'groupid' => (int)($snapshot->groupid ?? 0),
                'name' => (string)($snapshot->name ?? ''),
                'firststart' => (int)($snapshot->timestart ?? 0),
                'actorid' => $actorid,
            ];
        }
        // A second event of the same key in one request touches more than one occurrence. A
        // cancellation outranks a change: there is nothing left to have changed.
        $pending['scope'] = self::SERIES;
        if ($action === self::CANCELLED) {
            $pending['action'] = self::CANCELLED;
        }
        return $pending;
    }
}
