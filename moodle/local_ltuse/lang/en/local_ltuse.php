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
// Spec 003 Phase B: the Manage mentors page, for the site team and a learner's organisation
// manager. Names in {$a} are escaped by the page.
$string['mentors:manage'] = 'Manage mentors';
$string['mentors:heading'] = 'Mentors of {$a}';
$string['mentors:intro'] = 'A mentor sees this learner\'s courses and progress, and the two become message contacts. This is the learner\'s mentor across all their courses.';
$string['mentors:mentor'] = 'Mentor';
$string['mentors:none'] = 'This learner has no mentor yet.';
$string['mentors:add'] = 'Add a mentor';
$string['mentors:choose'] = 'Choose a mentor';
$string['mentors:remove'] = 'Remove';
$string['mentors:nocandidates'] = 'Nobody is available to add. The site team decides who can be chosen as a mentor.';
$string['mentors:confirmadd'] = 'Make {$a->mentor} a mentor of {$a->learner}? {$a->mentor} will see {$a->learner}\'s courses and progress, and the two will become message contacts.';
$string['mentors:confirmremove'] = 'Stop {$a->mentor} mentoring {$a->learner}? {$a->mentor} will no longer see {$a->learner}\'s courses and progress. Nothing of {$a->learner}\'s work or records changes.';
$string['mentors:added'] = '{$a->mentor} is now a mentor of {$a->learner}.';
$string['mentors:removed'] = '{$a->mentor} is no longer a mentor of {$a->learner}.';
$string['mentors:notallowed'] = 'You cannot manage mentors for this person.';
$string['mentors:invalidmentor'] = 'That person cannot be added or removed as a mentor here.';
$string['mentors:back'] = 'Back to the profile';
$string['nomentoring'] = 'Nobody is linked to you as a mentor or learner yet.';
$string['privacy:metadata:mentor_contact'] = 'The message contacts this plugin made between a mentor and their learner, so that ending the mentor relationship removes them again.';
$string['privacy:metadata:mentor_contact:mentorid'] = 'The mentor.';
$string['privacy:metadata:mentor_contact:learnerid'] = 'The learner.';
$string['privacy:metadata:mentor_contact:timecreated'] = 'When the contact was made.';
$string['privacy:path:mentorcontacts'] = 'Mentor message contacts';
$string['privacy:metadata:mentor_contact:contactid'] = 'The message contact this plugin made, so that only that contact is ever removed.';
$string['privacy:metadata:core_message'] = 'While a mentor relationship lasts, the mentor and the learner are made message contacts of each other. The contact is removed when the relationship ends. Calendar changes and office-hours bookings are sent as notifications.';

// Spec 011: calendar change notices, office-hours booking notices, the time zone notice and
// the tasks. Times are always shown in the reader's own zone, with the zone named. No string
// here names a CBC level or says "certified".
$string['officehours'] = 'Mentor office hours';
$string['messageprovider:eventchange'] = 'Changes to calendar events and their cancellation';
$string['messageprovider:bookingnotice'] = 'Office-hours bookings, changes and cancellations';
$string['task:eventchange'] = 'Tell people a calendar event changed or was cancelled';
$string['task:officehoursreconcile'] = 'Keep office-hours groups in step with mentors';

$string['eventchange:changed:subject'] = 'Changed: {$a->name}';
$string['eventchange:changed'] = '"{$a->name}" has changed. It is now on {$a->when} ({$a->zone}). Open your calendar for the details.';
$string['eventchange:changedseries:subject'] = 'Changed: {$a->name}';
$string['eventchange:changedseries'] = 'The repeating event "{$a->name}" has changed. The next one is on {$a->when} ({$a->zone}). Open your calendar for the details.';
$string['eventchange:cancelled:subject'] = 'Cancelled: {$a->name}';
$string['eventchange:cancelled'] = '"{$a->name}" on {$a->when} ({$a->zone}) has been cancelled.';
$string['eventchange:cancelleddate:subject'] = 'Cancelled: {$a->name}, {$a->when}';
$string['eventchange:cancelleddate'] = '"{$a->name}" on {$a->when} ({$a->zone}) has been cancelled. The other dates in the series still stand.';
$string['eventchange:cancelledseries:subject'] = 'Cancelled: {$a->name}';
$string['eventchange:cancelledseries'] = 'The repeating event "{$a->name}" has been cancelled, every date of it.';

$string['bookingnotice:booked:you:subject'] = 'Booked: office hours on {$a->when}';
$string['bookingnotice:booked:you'] = 'You booked time with {$a->other} on {$a->when} ({$a->zone}), for {$a->minutes} minutes. It is in your calendar.';
$string['bookingnotice:booked:notice:subject'] = 'New booking: {$a->actor}, {$a->when}';
$string['bookingnotice:booked:notice'] = '{$a->actor} booked your office hours on {$a->when} ({$a->zone}), for {$a->minutes} minutes. It is in your calendar.';
$string['bookingnotice:changed:you:subject'] = 'Moved: office hours with {$a->other}';
$string['bookingnotice:changed:you'] = 'You moved your time with {$a->other} to {$a->when} ({$a->zone}), for {$a->minutes} minutes.';
$string['bookingnotice:changed:notice:subject'] = 'Moved: office hours on {$a->when}';
$string['bookingnotice:changed:notice'] = '{$a->actor} moved your office hours to {$a->when} ({$a->zone}), for {$a->minutes} minutes. Your calendar shows the new time.';
$string['bookingnotice:cancelled:you:subject'] = 'Cancelled: office hours on {$a->when}';
$string['bookingnotice:cancelled:you'] = 'You cancelled your time with {$a->other} on {$a->when} ({$a->zone}).';
$string['bookingnotice:cancelled:notice:subject'] = 'Cancelled: office hours on {$a->when}';
$string['bookingnotice:cancelled:notice'] = '{$a->actor} cancelled the office hours on {$a->when} ({$a->zone}).';
$string['bookingnotice:slotdeleted:notice:subject'] = 'Cancelled by your mentor: office hours on {$a->when}';
$string['bookingnotice:slotdeleted:notice'] = 'Your mentor, {$a->actor}, cancelled your office hours on {$a->when} ({$a->zone}). You can book another time.';
$string['bookingnotice:slotdeleted:you:subject'] = 'Cancelled: office hours on {$a->when}';
$string['bookingnotice:slotdeleted:you'] = 'You cancelled the office hours on {$a->when} ({$a->zone}).';
$string['bookingnotice:slotdeleted:mentor:subject'] = 'Cancelled: office hours on {$a->when}';
$string['bookingnotice:slotdeleted:mentor'] = 'You deleted office hours on {$a->when} ({$a->zone}). The {$a->count} booking(s) in it were cancelled, and each learner has been told.';

$string['tznotice'] = 'Times on this page are in your time zone: {$a}.';
$string['tznotice:default'] = 'Times on this page are in {$a}, the site default, because you have not chosen a time zone.';
$string['tznotice:change'] = 'Change it';

$string['privacy:metadata:booking'] = 'Each office-hours booking\'s last notified time, so that a booking is announced again only when its time changes.';
$string['privacy:metadata:booking:eventid'] = 'The learner\'s calendar event for the booking.';
$string['privacy:metadata:booking:slotid'] = 'The office-hours slot.';
$string['privacy:metadata:booking:learnerid'] = 'The learner who booked.';
$string['privacy:metadata:booking:mentorid'] = 'The mentor whose slot it is.';
$string['privacy:metadata:booking:timestart'] = 'The booking\'s start, as last notified.';
$string['privacy:metadata:booking:timeduration'] = 'The booking\'s length, as last notified.';
$string['privacy:metadata:booking:timecreated'] = 'When the booking was recorded.';
$string['privacy:path:bookings'] = 'Office-hours bookings';
