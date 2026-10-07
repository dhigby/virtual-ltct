<?php
// PHPUnit tests for spec 005's one-off read-tracking switch (Q18, round 2):
// classes/trackforums.php, which cli/trackforums_existing.php runs.
//
// Synthetic data only, in the PHPUnit database. Where this runs is spec 004's plan.md
// "Testing".

namespace local_ltuse;

/**
 * enable_existing() switches read tracking on for every live account but the guest, counts
 * only, and changes nothing on a second run.
 *
 * @package    local_ltuse
 * @category   test
 * @covers     \local_ltuse\trackforums
 */
final class trackforums_test extends \advanced_testcase {

    public function test_read_tracking_is_switched_on_once_for_live_accounts(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        // The PHPUnit admin is a live account too: switch it on first, so the counts are the
        // fixtures' alone.
        $DB->set_field('user', 'trackforums', 1, ['id' => get_admin()->id]);
        $DB->set_field('user', 'trackforums', 0, ['id' => $CFG->siteguest]);

        $generator = $this->getDataGenerator();
        $off1 = $generator->create_user(['trackforums' => 0]);
        $off2 = $generator->create_user(['trackforums' => 0]);
        $on = $generator->create_user(['trackforums' => 1]);
        $gone = $generator->create_user(['trackforums' => 0]);
        delete_user($gone);
        $DB->set_field('user', 'trackforums', 0, ['id' => $gone->id]);

        // Seen: the admin and the three fixtures, never the deleted account or the guest.
        $this->assertSame(['seen' => 4, 'changed' => 2], trackforums::enable_existing());
        foreach ([$off1, $off2, $on] as $user) {
            $this->assertSame(1, (int)$DB->get_field('user', 'trackforums', ['id' => $user->id]));
        }
        $this->assertSame(0, (int)$DB->get_field('user', 'trackforums', ['id' => $gone->id]));
        $this->assertSame(0, (int)$DB->get_field('user', 'trackforums', ['id' => $CFG->siteguest]));

        $this->assertSame(['seen' => 4, 'changed' => 0], trackforums::enable_existing());
    }
}
