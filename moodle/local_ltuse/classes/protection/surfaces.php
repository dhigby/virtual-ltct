<?php
namespace local_ltuse\protection;

defined('MOODLE_INTERNAL') || die();

use core_user;
use moodle_url;

/**
 * What the four surfaces of research R7 show (spec 016): the profile node, the Mentoring page
 * and its app handler, "People I support", and the granting page. Each piece of real identity
 * passes entitlement::can_view_identity() first, so a surface cannot show it, or the marker, to
 * anyone else.
 */
class surfaces {

    /**
     * The protected people a viewer is entitled to see, for "People I support". Protected users
     * are few, so each row is checked; nothing is said about anyone the viewer may not see.
     *
     * @param int $viewerid
     * @return array[] {id, realname, display, level, levelname, org, canmanage}, by real name
     */
    public static function supported(int $viewerid): array {
        global $DB;
        if (!service::table_exists()) {
            return [];
        }
        $out = [];
        foreach ($DB->get_records_select(service::TABLE, 'effectivelevel <> :none', ['none' => levels::NONE]) as $row) {
            $userid = (int)$row->userid;
            if ($userid === $viewerid || !entitlement::can_view_identity($viewerid, $userid)) {
                continue;
            }
            $user = core_user::get_user($userid);
            if (!$user || $user->deleted) {
                continue;
            }
            $out[] = [
                'id' => $userid,
                'realname' => service::certname((string)$row->realfirstname, (string)$row->reallastname),
                'display' => fullname($user),
                'level' => (string)$row->effectivelevel,
                'levelname' => get_string('protection:level:' . $row->effectivelevel, 'local_ltuse'),
                'org' => service::user_org($userid),
                'canmanage' => entitlement::can_manage_protection($viewerid, $userid),
            ];
        }
        usort($out, function($a, $b) {
            return strcasecmp($a['realname'], $b['realname']);
        });
        return $out;
    }

    /**
     * Does the viewer support anyone protected? Decides whether the profile offers the page.
     *
     * @param int $viewerid
     * @return bool
     */
    public static function supports_anyone(int $viewerid): bool {
        return (bool)self::supported($viewerid);
    }

    /**
     * The fields a learner row of the Mentoring page gains (spec 003's mentoring::learner()):
     * whether the learner is protected and their real name, both only for an entitled viewer.
     * Read by spec 006's pathway view too.
     *
     * @param int $viewerid
     * @param int $learnerid
     * @return array {protected: bool, realname: string, marker: string}
     */
    public static function mentoring_fields(int $viewerid, int $learnerid): array {
        $entitled = service::is_protected($learnerid) && entitlement::can_view_identity($viewerid, $learnerid);
        $real = $entitled ? service::real_identity($learnerid) : null;
        return [
            'protected' => $entitled,
            'realname' => $real ? service::certname($real['firstname'], $real['lastname']) : '',
            'protectedlabel' => $entitled ? get_string('protection:marker', 'local_ltuse') : '',
        ];
    }

    /**
     * The learner's own view: their level, what others see, and how to ask (FR-012, FR-008).
     *
     * @param int $userid
     * @return string HTML
     */
    public static function own_summary(int $userid): string {
        $row = service::table_exists() ? service::row($userid) : null;
        $level = $row ? (string)$row->effectivelevel : levels::NONE;
        $items = [];
        foreach (levels::preview($level) as $what => $state) {
            $items[] = \html_writer::tag('li', get_string("protection:preview:{$what}:{$state}", 'local_ltuse'));
        }
        $html = \html_writer::tag('p', get_string('protection:yourlevel', 'local_ltuse',
            get_string('protection:level:' . $level, 'local_ltuse')));
        $html .= \html_writer::tag('p', get_string('protection:othersee', 'local_ltuse'));
        $html .= \html_writer::tag('ul', implode('', $items));
        $html .= \html_writer::tag('p', get_string('protection:whosees', 'local_ltuse'));
        $html .= \html_writer::tag('p', get_string('protection:howtoask', 'local_ltuse'));
        return $html;
    }

    /**
     * An entitled viewer's view of a protected user's profile: the marker and the real name.
     *
     * @param int $viewerid
     * @param int $userid
     * @return string HTML, '' when there is nothing the viewer may see
     */
    public static function entitled_summary(int $viewerid, int $userid): string {
        $marker = entitlement::marker($viewerid, $userid);
        if ($marker === '') {
            return '';
        }
        $real = service::real_identity($userid);
        $name = $real ? service::certname($real['firstname'], $real['lastname']) : '';
        return $marker . ' ' . get_string('protection:realname', 'local_ltuse', s($name)) . ' (' .
            get_string('protection:level:' . ($real['level'] ?? levels::NONE), 'local_ltuse') . ')';
    }

    /**
     * @param int $userid
     * @return moodle_url the granting page
     */
    public static function granting_url(int $userid): moodle_url {
        return new moodle_url('/local/ltuse/protection.php', ['id' => $userid]);
    }
}
