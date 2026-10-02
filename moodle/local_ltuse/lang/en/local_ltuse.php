<?php
// English strings for local_ltuse.

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'LTC curriculum publishing';
$string['ltuse:publish'] = 'Publish LTC curriculum content over the web service';
$string['ltuse:viewmenteeprogress'] = 'See the courses and progress of a learner you mentor';

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
$string['error:nohidecertificate'] = 'Cannot retire "{$a}": it is the course certificate, '
    . 'and retiring or deleting it would lose every issued certificate code.';
$string['error:nohideqbank'] = 'Cannot hide "{$a}": it is the course question bank, which the '
    . 'publisher manages itself.';
$string['error:completionoff'] = 'Completion tracking is off for the site or for course "{$a}". '
    . 'Turn it on with site_config.py apply and republish.';
$string['error:passnograde'] = 'Quiz "{$a}" asks for a pass rule but has no pass mark.';
$string['error:availabilityoff'] = 'Restricted access (enableavailability) is off, so the '
    . 'certificate could not be locked until the course is completed. Run site_config.py apply.';
$string['error:badgewording'] = 'The badge text breaks the CBC wording rule, so nothing was '
    . 'written for it: {$a}';
$string['error:badimage'] = 'Image "{$a}" in the payload does not match its checksum.';
$string['error:nogd'] = 'The GD image library is not available, so the badge image could not '
    . 'be made. Install the PHP gd extension.';
$string['error:recognitionnotapplied'] = 'recognition-not-applied: the badge template or the '
    . 'certificate template is not on this site. Run site_config.py apply.';
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

// Spec 003: the Mentoring page, its navigation and the app handler. Course completion only;
// no string here names a CBC level or says "certified" (FR-013).
$string['mentoring'] = 'Mentoring';
$string['mentoring:empty'] = 'Nobody is linked to you as a mentor or learner yet.';
$string['mentoring:learners'] = 'Learners you mentor';
$string['mentoring:mentors'] = 'Your mentors';
$string['mentoring:profile'] = 'Profile';
$string['mentoring:grades'] = 'Grades';
$string['mentoring:message'] = 'Message';
$string['mentoring:hidden'] = 'Hidden course';
$string['mentoring:nocourses'] = 'Not enrolled in any course yet.';
$string['mentoring:completed'] = 'Completed on {$a}';
$string['mentoring:inprogress'] = 'In progress, {$a}%';
$string['mentoring:notstarted'] = 'Not started';
$string['mentoring:nottracked'] = 'Completion not tracked';
$string['mentoring:enrolmentsuspended'] = 'enrolment suspended';
$string['mentoring:enrolmentremoved'] = 'no longer enrolled';
$string['mentoring:thislearner'] = 'Mentoring';
$string['nomentoring'] = 'Nobody is linked to you as a mentor or learner yet.';
$string['privacy:metadata:mentor_contact'] = 'The message contacts this plugin made between a mentor and their learner, so that ending the mentor relationship removes them again.';
$string['privacy:metadata:mentor_contact:mentorid'] = 'The mentor.';
$string['privacy:metadata:mentor_contact:learnerid'] = 'The learner.';
$string['privacy:metadata:mentor_contact:timecreated'] = 'When the contact was made.';
$string['privacy:path:mentorcontacts'] = 'Mentor message contacts';
$string['privacy:metadata:mentor_contact:contactid'] = 'The message contact this plugin made, so that only that contact is ever removed.';
$string['privacy:metadata:core_message'] = 'While a mentor relationship lasts, the mentor and the learner are made message contacts of each other. The contact is removed when the relationship ends.';
