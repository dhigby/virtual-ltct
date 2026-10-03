<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\task;

defined('MOODLE_INTERNAL') || die();

use core_user;
use local_ltuse\calendar_notify;

/**
 * Tells the people a calendar event reaches that it changed or was cancelled (spec 011, FR-006,
 * research R15).
 *
 * Queued by observer::queue_calendar_notices() at the end of a request, one per key (a series
 * or a single event), two minutes ahead, with reschedule_or_queue_adhoc_task(). Its customdata
 * is calendar_notify::merge()'s: action, scope, key, eventtype, courseid, groupid, name,
 * firststart, actorid, and `offset` when a site-wide notice continues in a later run.
 *
 *   recipients  site: every active, confirmed account, 500 per run (the task re-queues itself
 *               with the next offset); course: the course's active enrolments; group: the
 *               group's active members (get_enrolled_users(), lib/enrollib.php). Never the
 *               person who made the change.
 *   changed     re-reads the event or the series; sends nothing if it is gone or hidden, since
 *               a later cancellation covers it
 *   cancelled   the row is gone, so the name comes from customdata; a series key with rows
 *               left means one date was cancelled, none left means the whole series
 *
 * Each message comes from the no-reply user and names no person; the time is shown in each
 * recipient's own zone, with the zone named (D5). Logs carry counts only.
 */
class event_change_notice extends \core\task\adhoc_task {

    /** Accounts per run for a site-wide notice. */
    const BATCH = 500;

    /**
     * @return string
     */
    public function get_name(): string {
        return get_string('task:eventchange', 'local_ltuse');
    }

    public function execute() {
        global $DB;
        $data = (array)$this->get_custom_data();
        $key = (string)($data['key'] ?? '');
        $id = (int)substr($key, 1);
        if ($id <= 0) {
            return;
        }
        $series = $key[0] === 'r';
        $action = (string)($data['action'] ?? '');

        if ($action === calendar_notify::CHANGED) {
            $events = $series ? $DB->get_records('event', ['repeatid' => $id], 'timestart, id')
                : $DB->get_records('event', ['id' => $id]);
            $events = array_filter($events, function($e) {
                return !empty($e->visible);
            });
            if (!$events) {
                return; // Gone or hidden since: a cancellation, if any, says so.
            }
            $first = reset($events);
            $isseries = $series && ($data['scope'] ?? '') === calendar_notify::SERIES;
            $string = $isseries ? 'eventchange:changedseries' : 'eventchange:changed';
            $start = $isseries ? (int)$first->timestart : (int)self::occurrence($events, (int)$data['firststart'])->timestart;
            $name = (string)$first->name;
            $url = new \moodle_url('/calendar/view.php', ['view' => 'day', 'time' => $start]);
        } else if ($action === calendar_notify::CANCELLED) {
            $left = $series ? $DB->count_records('event', ['repeatid' => $id]) : 0;
            $string = !$series ? 'eventchange:cancelled' : ($left ? 'eventchange:cancelleddate' : 'eventchange:cancelledseries');
            $start = (int)($data['firststart'] ?? 0);
            $name = (string)($data['name'] ?? '');
            $url = new \moodle_url('/calendar/view.php', ['view' => 'upcoming']);
        } else {
            return;
        }

        $offset = (int)($data['offset'] ?? 0);
        $recipients = self::recipients($data, $offset);
        $actorid = (int)($data['actorid'] ?? 0);
        $courseid = (string)($data['eventtype'] ?? '') === 'site' ? SITEID : (int)($data['courseid'] ?? SITEID);
        $sent = 0;
        foreach ($recipients as $userid) {
            if ($userid === $actorid) {
                continue;
            }
            if (self::send($userid, $string, $name, $start, $courseid, $url)) {
                $sent++;
            }
        }
        mtrace("local_ltuse: event change notice {$key} ({$action}): {$sent} sent");

        if ((string)($data['eventtype'] ?? '') === 'site' && count($recipients) === self::BATCH) {
            $next = new self();
            $data['offset'] = $offset + self::BATCH;
            $next->set_custom_data($data);
            $next->set_component('local_ltuse');
            \core\task\manager::queue_adhoc_task($next);
        }
    }

    /**
     * The occurrence a single-date change is about: the one nearest the first start the
     * observer saw, so a moved date is reported at its new time.
     *
     * @param \stdClass[] $events
     * @param int $firststart
     * @return \stdClass
     */
    protected static function occurrence(array $events, int $firststart): \stdClass {
        $best = reset($events);
        foreach ($events as $event) {
            if (abs((int)$event->timestart - $firststart) < abs((int)$best->timestart - $firststart)) {
                $best = $event;
            }
        }
        return $best;
    }

    /**
     * The user ids the event reaches.
     *
     * @param array $data
     * @param int $offset for a site-wide notice
     * @return int[]
     */
    protected static function recipients(array $data, int $offset): array {
        global $DB, $CFG;
        $type = (string)($data['eventtype'] ?? '');
        if ($type === 'site') {
            return array_map('intval', array_keys($DB->get_records_select('user',
                'deleted = 0 AND suspended = 0 AND confirmed = 1 AND id <> :guest',
                ['guest' => (int)$CFG->siteguest], 'id', 'id', $offset, self::BATCH)));
        }
        $courseid = (int)($data['courseid'] ?? 0);
        $context = $courseid ? \context_course::instance($courseid, IGNORE_MISSING) : null;
        if (!$context) {
            return [];
        }
        $groupid = $type === 'group' ? (int)($data['groupid'] ?? 0) : 0;
        if ($type === 'group' && !$groupid) {
            return [];
        }
        return array_map('intval', array_keys(get_enrolled_users($context, '', $groupid, 'u.id', null, 0, 0, true)));
    }

    /**
     * @param int $userid
     * @param string $string the lang string id
     * @param string $name the event's name
     * @param int $start
     * @param int $courseid
     * @param \moodle_url $url
     * @return bool whether a message was sent
     */
    protected static function send(int $userid, string $string, string $name, int $start, int $courseid,
            \moodle_url $url): bool {
        $user = core_user::get_user($userid);
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return false;
        }
        $zone = \core_date::get_user_timezone($user);
        $a = (object)['name' => format_string($name, true, ['context' => \context_system::instance()]),
            'when' => userdate($start, get_string('strftimedaydatetime', 'langconfig'), $zone), 'zone' => $zone];

        $message = new \core\message\message();
        $message->component = 'local_ltuse';
        $message->name = 'eventchange';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $user;
        $message->subject = get_string($string . ':subject', 'local_ltuse', $a);
        $message->fullmessage = get_string($string, 'local_ltuse', $a);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = text_to_html($message->fullmessage, false, false, true);
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->courseid = $courseid;
        $message->contexturl = $url->out(false);
        $message->contexturlname = get_string('calendar', 'calendar');
        return (bool)message_send($message);
    }
}
