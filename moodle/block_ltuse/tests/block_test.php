<?php
// PHPUnit tests for the learner home block's web and app views (spec 007, contracts/learner-ui.md).
//
// Synthetic data only, never run on the shared host: every name here is a fixture value, never
// a real learner or course. Where this runs is spec 004's plan.md "Testing".

namespace block_ltuse;

/**
 * The block renders one mode from local_ltuse\learner_home, every visible word a lang string,
 * shows nothing to the site team, and can be placed on the Dashboard only. The app view shows the
 * same mode from the same context, plus the two offline hints the web never shows (R7, R8).
 *
 * Strings are asserted as the template outputs them, s(get_string(...)), because mustache
 * escapes the apostrophe in empty:who.
 *
 * @package    block_ltuse
 * @category   test
 * @covers     \block_ltuse
 * @covers     \block_ltuse\output\mobile
 */
final class block_test extends \advanced_testcase {

    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/blocks/moodleblock.class.php');
        require_once($CFG->dirroot . '/blocks/ltuse/block_ltuse.php');
        require_once($CFG->libdir . '/completionlib.php');
        parent::setUpBeforeClass();
    }

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('enablecompletion', 1);
    }

    /**
     * The block's web content for the current user.
     *
     * @return \stdClass
     */
    private function content(): \stdClass {
        return (new \block_ltuse())->get_content();
    }

    public function test_empty_state_names_who_to_ask_and_links_support(): void {
        $this->setUser($this->getDataGenerator()->create_user());
        $text = $this->content()->text;
        $this->assertStringContainsString(s(get_string('empty', 'block_ltuse')), $text);
        $this->assertStringContainsString(s(get_string('empty:who', 'block_ltuse')), $text);
        $this->assertStringContainsString(s(get_string('contactsupport', 'block_ltuse')), $text);
        $this->assertStringContainsString('href="' . (new \moodle_url('/user/contactsitesupport.php'))->out(false) . '"',
            $text);
        $this->assertStringContainsString('ltuse-home', $text);
    }

    public function test_start_button_links_to_the_first_lesson(): void {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['idnumber' => 'ltct:fixture-a', 'fullname' => 'Fixture course A',
            'enablecompletion' => 1]);
        $first = $gen->create_module('page', ['course' => $course->id, 'name' => 'Fixture page 1',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED]);
        $gen->create_module('page', ['course' => $course->id, 'name' => 'Fixture page 2',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);

        $text = $this->content()->text;
        $this->assertStringContainsString(s(get_string('start', 'block_ltuse', 'Fixture page 1')), $text);
        $this->assertStringContainsString(s(get_string('coursename', 'block_ltuse', 'Fixture course A')), $text);
        $url = (new \moodle_url('/mod/page/view.php', ['id' => $first->cmid]))->out(false);
        $this->assertMatchesRegularExpression('~<a [^>]*class="btn btn-primary"[^>]*href="' . preg_quote($url, '~') . '"~',
            $text);
        $this->assertStringNotContainsString(s(get_string('empty', 'block_ltuse')), $text);
    }

    public function test_site_admin_sees_nothing(): void {
        $this->setAdminUser();
        $content = $this->content();
        $this->assertSame('', $content->text);
        $this->assertSame('', $content->footer);
    }

    public function test_only_the_dashboard_can_hold_it(): void {
        $this->assertSame(['all' => false, 'my' => true], (new \block_ltuse())->applicable_formats());
    }

    /**
     * A learner on one published course, not yet started, set as the current user.
     *
     * @return string the first lesson's URL, which the start button links to
     */
    private function start_learner(): string {
        $gen = $this->getDataGenerator();
        $course = $gen->create_course(['idnumber' => 'ltct:fixture-a', 'fullname' => 'Fixture course A',
            'enablecompletion' => 1]);
        $first = $gen->create_module('page', ['course' => $course->id, 'name' => 'Fixture page 1',
            'completion' => COMPLETION_TRACKING_AUTOMATIC, 'completionview' => COMPLETION_VIEW_REQUIRED]);
        $user = $gen->create_user();
        $gen->enrol_user($user->id, $course->id, 'student');
        $this->setUser($user);
        return (new \moodle_url('/mod/page/view.php', ['id' => $first->cmid]))->out(false);
    }

    /**
     * The block's app content for the current user, as tool_mobile_get_content would return it.
     *
     * @return array
     */
    private function app_view(): array {
        return \block_ltuse\output\mobile::mobile_block_view([]);
    }

    public function test_app_view_returns_the_same_mode_as_the_web(): void {
        $url = $this->start_learner();
        $start = s(get_string('start', 'block_ltuse', 'Fixture page 1'));
        $this->assertStringContainsString($start, $this->content()->text);

        $response = $this->app_view();
        $this->assertCount(1, $response['templates']);
        $this->assertSame('main', $response['templates'][0]['id']);
        $html = $response['templates'][0]['html'];
        $this->assertStringContainsString($start, $html);
        $this->assertStringContainsString(s(get_string('coursename', 'block_ltuse', 'Fixture course A')), $html);
        $this->assertStringContainsString('href="' . $url . '"', $html);
        $this->assertStringContainsString('core-link', $html);
        $this->assertStringContainsString(s(get_string('offline:course', 'block_ltuse')), $html);
        $this->assertStringContainsString(s(get_string('offline:quiz', 'block_ltuse')), $html);
        $this->assertSame('', $response['javascript']);
    }

    public function test_app_view_is_empty_for_a_site_admin(): void {
        $this->setAdminUser();
        $response = $this->app_view();
        $this->assertCount(1, $response['templates']);
        $this->assertSame('', $response['templates'][0]['html']);
    }

    public function test_web_view_has_no_offline_hint(): void {
        $this->start_learner();
        $text = $this->content()->text;
        $this->assertNotSame('', $text);
        $this->assertStringNotContainsString(s(get_string('offline:course', 'block_ltuse')), $text);
        $this->assertStringNotContainsString(s(get_string('offline:quiz', 'block_ltuse')), $text);
    }
}
