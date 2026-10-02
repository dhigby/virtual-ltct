<?php
// English strings for local_ltuse.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LTC curriculum publishing';
$string['ltuse:publish'] = 'Publish LTC curriculum content over the web service';
$string['privacy:metadata'] = 'The LTC curriculum publishing plugin stores no personal data. Its '
    . 'tables hold only the competency framework and which courses aim at each competency.';

$string['error:nocourse'] = 'No course with idnumber "{$a}".';
$string['error:nosection'] = 'Course has no section number {$a}. Create it first '
    . '(local_wsmanagesections_create_sections).';
$string['error:badxml'] = 'The question XML could not be imported: {$a}';
$string['error:noquestions'] = 'No questions were imported from the supplied XML.';
$string['error:nocategory'] = 'Question category "{$a}" could not be created.';
$string['error:modulemissing'] = 'The {$a} activity module is not installed or is '
    . 'disabled on this site.';
$string['error:notoffline'] = 'This quiz could not be made available offline in the Moodle app, '
    . 'because these settings prevent it: {$a}. Check the quiz defaults for this site.';
$string['error:nokeepfile'] = 'Cannot keep "{$a}": this page has no such file. Republish to '
    . 'resend it.';
$string['error:nomodule'] = 'Cannot hide "{$a}": this course has no module with that idnumber.';
$string['error:nohideqbank'] = 'Cannot hide "{$a}": it is the course question bank, which the '
    . 'publisher manages itself.';
$string['error:completionoff'] = 'Completion tracking is off for the site or for course "{$a}". '
    . 'Turn it on with site_config.py apply and republish.';
$string['error:passnograde'] = 'Quiz "{$a}" asks for a pass rule but has no pass mark.';
$string['error:notltctcourse'] = 'Course "{$a}" is not a publisher-owned (ltct:) course.';

// Per-competency report (spec 004 R15, FR-013). Worded as aims: a course aims at a
// competency, and the counts are of courses, enrolments and completions. No string here may
// say a learner reached, achieved or attained anything, or is competent, or mention
// certification; tests/competency_coverage_test.php refuses those words.
$string['datasource:competency_coverage'] = 'Competency coverage';
$string['entity:competency'] = 'Competency';
$string['entity:coverage'] = 'Courses and delivery use';
$string['column:competency_category'] = 'Category';
$string['column:competency_name'] = 'Competency courses aim at';
$string['column:coverage_courses'] = 'Courses that aim at it';
$string['column:coverage_indelivery'] = 'Of which in delivery';
$string['column:coverage_enrolments'] = 'Delivery enrolments (not learners)';
$string['column:coverage_learners'] = 'Delivery learners';
$string['column:coverage_completions'] = 'Delivery course completions';
$string['filter:competency_category'] = 'Category';
$string['filter:competency_name'] = 'Competency courses aim at';
