<?php
namespace block_ltuse\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for block_ltuse.
 *
 * The block has no table and no setting. It renders what local_ltuse\learner_home reads from
 * core and local_ltuse at the moment it is shown, and stores none of it, so it has nothing of
 * its own to export or delete (Principle III).
 */
class provider implements \core_privacy\local\metadata\null_provider {

    /**
     * @return string the lang string that says why the block stores no personal data
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
