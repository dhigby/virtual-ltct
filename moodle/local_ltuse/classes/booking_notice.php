<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse;

defined('MOODLE_INTERNAL') || die();

use core_user;

/**
 * Booking messages to both sides of an office-hours appointment (spec 011, FR-007, research
 * R20; plan decision 5).
 *
 * Every booking, cancellation and change of time is emailed to both the mentee and the mentor:
 * a confirmation to whoever made it, a notice to the other. The office-hours scheduler sends no
 * booking messages of its own (`allownotifications` 0), so this is the only sender.
 *
 * The scheduler keeps a calendar event per learner per booked slot (eventtype `SSstu:<slotid>`,
 * modulename `scheduler`, classes/model/slot.php in v5.2-r1), and rewrites it through core's
 * calendar API on every save, so core's calendar_event_created/_updated/_deleted say when a
 * booking starts, moves or ends. Core keeps no "before" row, so local_ltuse_booking holds each
 * booking's last notified time: an update that leaves the time alone (a note edit, spec 016
 * re-saving a renamed user) sends nothing. Deleting a whole slot removes its events with a raw
 * delete that fires nothing, so the scheduler's own slot_deleted event is observed instead.
 *
 * The pure parts, decide(), slot_id(), is_office_hours() and message_string(), make no Moodle
 * calls; tests/booking_notice_harness.php tests them.
 *
 * Principle XI exception (moodle/local_ltuse/README.md): the mentor is read from
 * scheduler_slots.teacherid by primary key, and the SSstu: convention is the plugin's own.
 * Quickstart V13 is re-run on every scheduler re-pin.
 */
class booking_notice {

    /** This plugin's record of each booking's last notified time. */
    const TABLE = 'local_ltuse_booking';

    /** The scheduler's learner-event prefix. */
    const STUDENT_PREFIX = 'SSstu:';

    /** Kinds of calendar change. */
    const CREATED = 'created';
    const UPDATED = 'updated';
    const DELETED = 'deleted';

    /** Actions a message announces. */
    const BOOKED = 'booked';
    const CHANGED = 'changed';
    const CANCELLED = 'cancelled';

    // --- pure ----------------------------------------------------------------------------

    /**
     * The slot id in an `SSstu:<slotid>` eventtype, or null for anything else.
     *
     * @param string $eventtype
     * @return int|null
     */
    public static function slot_id(string $eventtype): ?int {
        if (strpos($eventtype, self::STUDENT_PREFIX) !== 0) {
            return null;
        }
        $id = substr($eventtype, strlen(self::STUDENT_PREFIX));
        return ctype_digit($id) && (int)$id > 0 ? (int)$id : null;
    }

    /**
     * Whether a calendar event is a learner's booking in the office-hours scheduler.
     *
     * @param \stdClass $event the event row
     * @param int $instance the office-hours scheduler's instance id, 0 when it does not exist
     * @return bool
     */
    public static function is_office_hours(\stdClass $event, int $instance): bool {
        return $instance > 0
            && (string)($event->modulename ?? '') === 'scheduler'
            && (int)($event->instance ?? 0) === $instance
            && self::slot_id((string)($event->eventtype ?? '')) !== null;
    }

    /**
     * What one calendar change announces.
     *
     * @param string $kind created, updated or deleted
     * @param \stdClass|null $row the booking's local_ltuse_booking row, or null
     * @param \stdClass $event the event row (after the change; before it, for a delete)
     * @return string|null booked, changed, cancelled, or null for nothing to say
     */
    public static function decide(string $kind, ?\stdClass $row, \stdClass $event): ?string {
        if ($kind === self::CREATED) {
            return $row === null ? self::BOOKED : null;
        }
        if ($kind === self::DELETED) {
            return self::CANCELLED;
        }
        if ($kind !== self::UPDATED || $row === null) {
            // An update with no record is a booking made before this plugin version: it is
            // recorded quietly, since its old time is unknown.
            return null;
        }
        $moved = (int)$row->timestart !== (int)($event->timestart ?? 0)
            || (int)$row->timeduration !== (int)($event->timeduration ?? 0);
        return $moved ? self::CHANGED : null;
    }

    /**
     * The lang string for one recipient's message.
     *
     * @param string $action booked, changed or cancelled
     * @param int $recipientid
     * @param int $actorid who made the change
     * @param bool $slotdeleted true when the mentor deleted the whole slot
     * @return string a local_ltuse string id, e.g. bookingnotice:booked:you
     */
    public static function message_string(string $action, int $recipientid, int $actorid, bool $slotdeleted = false): string {
        if ($slotdeleted && $action === self::CANCELLED) {
            return $recipientid === $actorid ? 'bookingnotice:slotdeleted:you' : 'bookingnotice:slotdeleted:notice';
        }
        return 'bookingnotice:' . $action . ':' . ($recipientid === $actorid ? 'you' : 'notice');
    }

    // --- Moodle --------------------------------------------------------------------------

    /**
     * Handle one calendar event for an office-hours booking.
     *
     * @param string $kind created, updated or deleted
     * @param \stdClass $event the event row
     * @param int $actorid the user who made the change
     */
    public static function from_calendar(string $kind, \stdClass $event, int $actorid): void {
        global $DB;
        if (!self::is_office_hours($event, officehours::scheduler_instance())) {
            return;
        }
        $row = $DB->get_record(self::TABLE, ['eventid' => (int)$event->id]) ?: null;
        $action = self::decide($kind, $row, $event);
        $slotid = (int)self::slot_id((string)$event->eventtype);

        if ($kind === self::DELETED) {
            if ($row) {
                $DB->delete_records(self::TABLE, ['id' => $row->id]);
            }
        } else if ($row === null) {
            $row = (object)['eventid' => (int)$event->id, 'slotid' => $slotid,
                'learnerid' => (int)$event->userid, 'mentorid' => self::mentor_of_slot($slotid),
                'timestart' => (int)$event->timestart, 'timeduration' => (int)$event->timeduration,
                'timecreated' => time()];
            $row->id = $DB->insert_record(self::TABLE, $row);
        } else if ($action === self::CHANGED) {
            $DB->update_record(self::TABLE, (object)['id' => $row->id,
                'timestart' => (int)$event->timestart, 'timeduration' => (int)$event->timeduration]);
        }
        if ($action === null) {
            return;
        }
        $learnerid = (int)$event->userid;
        $mentorid = $row ? (int)$row->mentorid : self::mentor_of_slot($slotid);
        $time = ['start' => (int)$event->timestart, 'duration' => (int)$event->timeduration];
        foreach (array_unique(array_filter([$learnerid, $mentorid])) as $recipientid) {
            self::send($recipientid, $action, $actorid, $learnerid, $mentorid, $time, false);
        }
    }

    /**
     * Handle the scheduler's slot_deleted, which fires before the slot and its events go.
     *
     * @param int $slotid
     * @param int $mentorid the slot's teacher (the event's relateduserid)
     * @param int $actorid
     */
    public static function from_slot_deleted(int $slotid, int $mentorid, int $actorid): void {
        global $DB;
        $rows = $DB->get_records(self::TABLE, ['slotid' => $slotid]);
        $DB->delete_records(self::TABLE, ['slotid' => $slotid]);
        foreach ($rows as $row) {
            $time = ['start' => (int)$row->timestart, 'duration' => (int)$row->timeduration];
            self::send((int)$row->learnerid, self::CANCELLED, $actorid, (int)$row->learnerid, $mentorid, $time, true);
        }
        if ($rows && $mentorid) {
            // One summary to the mentor, however many bookings the slot held.
            $first = reset($rows);
            $time = ['start' => (int)$first->timestart, 'duration' => (int)$first->timeduration, 'count' => count($rows)];
            self::send($mentorid, self::CANCELLED, $actorid, 0, $mentorid, $time, true);
        }
    }

    /**
     * The teacher of a slot. A raw read of the scheduler's own table by primary key: the plugin
     * has no API that returns a slot's teacher without loading its internal model classes.
     * Listed in the README (Principle XI).
     *
     * @param int $slotid
     * @return int the teacher's user id, or 0
     */
    public static function mentor_of_slot(int $slotid): int {
        global $DB;
        if ($slotid <= 0 || !$DB->get_manager()->table_exists('scheduler_slots')) {
            return 0;
        }
        return (int)$DB->get_field('scheduler_slots', 'teacherid', ['id' => $slotid]);
    }

    /**
     * Send one message.
     *
     * @param int $recipientid
     * @param string $action booked, changed or cancelled
     * @param int $actorid
     * @param int $learnerid 0 for the mentor's slot-deleted summary
     * @param int $mentorid
     * @param array $time ['start' => int, 'duration' => int, 'count' => ?int]
     * @param bool $slotdeleted
     */
    protected static function send(int $recipientid, string $action, int $actorid, int $learnerid, int $mentorid,
            array $time, bool $slotdeleted): void {
        $recipient = core_user::get_user($recipientid);
        if (!$recipient || !empty($recipient->deleted) || !empty($recipient->suspended)) {
            return;
        }
        // The other side, named through fullname(), so spec 016's protected display applies.
        $otherid = $recipientid === $learnerid ? $mentorid : $learnerid;
        $actor = core_user::get_user($actorid);
        $other = $otherid ? core_user::get_user($otherid) : null;
        $zone = \core_date::get_user_timezone($recipient);
        $a = (object)[
            'when' => userdate($time['start'], get_string('strftimedaydatetime', 'langconfig'), $zone),
            'zone' => $zone,
            'minutes' => (int)round($time['duration'] / MINSECS),
            'actor' => $actor ? fullname($actor) : '',
            'other' => $other ? fullname($other) : '',
            'count' => (int)($time['count'] ?? 1),
        ];
        $key = self::message_string($action, $recipientid, $actorid, $slotdeleted);
        if ($slotdeleted && $recipientid === $mentorid) {
            $key = 'bookingnotice:slotdeleted:mentor';
        }
        $course = officehours::course();
        $cm = officehours::scheduler_cm();

        $message = new \core\message\message();
        $message->component = 'local_ltuse';
        $message->name = 'bookingnotice';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $recipient;
        $message->subject = get_string($key . ':subject', 'local_ltuse', $a);
        $message->fullmessage = get_string($key, 'local_ltuse', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->courseid = $course ? (int)$course->id : SITEID;
        if ($cm) {
            $message->contexturl = (new \moodle_url('/mod/scheduler/view.php', ['id' => $cm->id]))->out(false);
            $message->contexturlname = get_string('officehours', 'local_ltuse');
        }
        message_send($message);
    }
}
