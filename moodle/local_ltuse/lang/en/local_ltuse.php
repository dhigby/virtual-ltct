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
$string['privacy:metadata:core_message'] = 'While a mentor relationship lasts, the mentor and the learner are made message contacts of each other. The contact is removed when the relationship ends. An organisation\'s managers and the people in it are made contacts in the same way while both are in the organisation. Calendar changes and office-hours bookings are sent as notifications.';

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

// Spec 002 (amendment 2026-10-02, research R10-R12): the "My organisation" page, its actions,
// the organisation-enrolment instance, organisation-only course placement and the contacts
// task. Course completion only; no string here names a CBC level or says "certified".
$string['organisation'] = 'My organisation';
$string['organisation:empty'] = 'You do not manage an organisation yet, or its people have not been set up.';
$string['organisation:nopeople'] = 'Nobody is in this organisation yet.';
$string['organisation:notmanager'] = 'This page is for organisation managers.';
$string['organisation:notyours'] = 'You cannot manage this person\'s account: they are not a learner in an organisation you manage. The site team can help.';
$string['organisation:notthiscourse'] = 'You cannot enrol this person in that course: only published courses, or courses of their own organisation, are open to managers.';
$string['organisation:notorgenrolment'] = 'This person was not enrolled in that course by a manager, so the enrolment is the site team\'s to change.';
$string['organisation:selfdisabled'] = 'Organisation enrolment is not available on this site (the self enrolment method is turned off). The site team can turn it on.';
$string['organisation:siteteam'] = 'Staff, mentors and managers are managed by the site team.';
$string['organisation:suspended'] = 'Suspended';
$string['organisation:mentors'] = 'Mentors';
$string['organisation:enrolin'] = 'Course to enrol them in';
$string['organisation:action:enrol'] = 'Enrol';
$string['organisation:action:unenrol'] = 'Unenrol';
$string['organisation:action:reset'] = 'Send a password reset link';
$string['organisation:action:suspend'] = 'Suspend account';
$string['organisation:action:reactivate'] = 'Reactivate account';
$string['organisation:confirm:enrol'] = 'Enrol {$a->person} in "{$a->course}" as a student? They will see the course on their dashboard.';
$string['organisation:confirm:unenrol'] = 'Unenrol {$a->person} from "{$a->course}"? If this is their only enrolment in the course, their grades and group places in it are removed. Their activity and completion records are kept.';
$string['organisation:confirm:reset'] = 'Send {$a->person} a password reset link? It goes to their own email address only; you will not see it.';
$string['organisation:confirm:suspend'] = 'Suspend {$a->person}\'s account? This applies to the whole site, not just your organisation\'s courses: they are signed out now and cannot sign in until the account is reactivated.';
$string['organisation:confirm:reactivate'] = 'Reactivate {$a->person}\'s account? They will be able to sign in again.';
$string['organisation:done:enrol'] = 'Enrolled.';
$string['organisation:done:unenrol'] = 'Unenrolled.';
$string['organisation:done:suspend'] = 'The account is suspended.';
$string['organisation:done:reactivate'] = 'The account is active again.';
$string['organisation:reset:sent'] = 'A password reset email was sent to their own address.';
$string['organisation:reset:alreadysent'] = 'Nothing was sent: a reset link was already sent to them twice recently. They can use the last one, or try again later.';
$string['organisation:reset:notconfirmed'] = 'Nothing was sent: their account has not been confirmed yet. The site team can help.';
$string['organisation:reset:noemail'] = 'Nothing was sent: their account has no email address. The site team can help.';
$string['organisation:reset:notfound'] = 'Nothing was sent: no active account could be found for them. The site team can help.';
$string['organisation:reset:maybesent'] = 'The request was made. This site does not say whether an email was sent; ask them to check their inbox.';
$string['organisation:reset:suspended'] = 'Nothing was sent: their account is suspended. Reactivate it first.';
$string['organisation:reset:failed'] = 'The reset email could not be sent. The site team can help.';
$string['organisation:reset:unknown'] = 'The request was made, but the result is not known. Ask them to check their inbox.';
$string['orgenrol:name'] = 'Organisation enrolment';
$string['error:nocategoryidnumber'] = 'No single course category has idnumber "{$a}", or the course could not be moved there.';
$string['task:reconcileorgcontacts'] = 'Keep organisation managers\' contacts and organisation-only enrolments in step';
$string['privacy:metadata:org_contact'] = 'The message contacts this plugin made between an organisation\'s manager and a person in that organisation, so that leaving the organisation removes them again.';
$string['privacy:metadata:org_contact:managerid'] = 'The organisation manager.';
$string['privacy:metadata:org_contact:memberid'] = 'The person in their organisation.';
$string['privacy:metadata:org_contact:contactid'] = 'The message contact this plugin made, so that only that contact is ever removed.';
$string['privacy:metadata:org_contact:timecreated'] = 'When the contact was made.';
$string['privacy:path:orgcontacts'] = 'Organisation message contacts';

// Spec 006: pathways. A pathway lists the courses that aim at a competency, grouped by the
// CBC level each course aims at. No string here names a level as anyone's, or says a learner
// reached, achieved or attained anything, or is competent, or certified;
// tests/test_pathway_wording.py checks every string in this block (R13, SC-004).
$string['pathways'] = 'Pathways';
$string['pathway:mine'] = 'Your pathways';
$string['pathway:none'] = 'No pathway has been given to you yet. You can browse every pathway.';
$string['pathway:noneforlearner'] = 'No pathway has been given to this learner yet.';
$string['pathway:donelearner'] = 'The training on this pathway is completed.';
$string['pathway:nocohorts'] = 'There is no cohort you can give a pathway to.';
$string['pathway:assignhelp'] = 'Giving a pathway to a cohort shows it to every member, now and when they join. It does not enrol anyone in its courses.';
$string['pathway:browse'] = 'Browse all pathways';
$string['pathway:aimsat'] = 'Aims at {$a}';
$string['pathway:nocourseyet'] = 'No course yet';
$string['pathway:nocourseyetlink'] = 'See what this competency involves';
$string['pathway:next'] = 'Next';
$string['pathway:completed'] = 'Completed';
$string['pathway:inprogress'] = 'In progress';
$string['pathway:notstarted'] = 'Not started';
$string['pathway:done'] = 'You have completed the training on this pathway.';
$string['pathway:roletotal'] = '{$a->done} of {$a->total} courses completed';
$string['pathway:unknown'] = 'This pathway does not exist.';
$string['pathway:manage'] = 'Assign pathways';
$string['pathway:assign'] = 'Assign';
$string['pathway:unassign'] = 'Remove';
$string['pathway:cohortprogress'] = 'Pathway progress: {$a}';
$string['pathway:nolevels'] = 'Pathways cannot be shown yet: their headings have not been set up on this site. Ask the site team to apply the site configuration.';
$string['pathway:roles'] = 'Role pathways';
// End of the spec 006 pathways block.

$string['eventpathwaycourseschanged'] = 'Pathway courses changed';
$string['eventpathwayassigned'] = 'Pathway assigned to a cohort';
$string['eventpathwayunassigned'] = 'Pathway removed from a cohort';

$string['privacy:metadata:local_ltuse_pathway_cohort'] = 'Each pathway given to a cohort, with the user who made the link. The link belongs to the cohort, so deleting that user\'s data keeps it and forgets who made it.';
$string['privacy:metadata:local_ltuse_pathway_cohort:pathwaykey'] = 'The pathway given to the cohort.';
$string['privacy:metadata:local_ltuse_pathway_cohort:cohortid'] = 'The cohort the pathway was given to.';
$string['privacy:metadata:local_ltuse_pathway_cohort:usermodified'] = 'The user who made or last changed the link.';
$string['privacy:metadata:local_ltuse_pathway_cohort:timecreated'] = 'When the link was made.';
$string['privacy:metadata:local_ltuse_pathway_cohort:timemodified'] = 'When the link was last changed.';
$string['privacy:path:pathways'] = 'Pathways given to cohorts';

// Spec 016: identity protection. No string names a person, an organisation or a level as held
// by anyone; levels are described by what others see.
$string['ltuse:viewidentity'] = 'See a protected person\'s real identity';
$string['ltuse:manageprotection'] = 'Change a person\'s identity protection';
$string['messageprovider:protectionchanged'] = 'Your identity protection changed';
$string['task:applyprotection'] = 'Apply one person\'s identity protection';
$string['task:reconcileprotection'] = 'Repair identity protection';
$string['protection:category'] = 'Identity protection';
$string['protection:marker'] = 'Protected';
$string['protection:identity'] = 'Real identity';
$string['protection:realname'] = 'Real name: {$a}';
$string['protection:realname:col'] = 'Real name';
$string['protection:display:col'] = 'Name others see';
$string['protection:manage'] = 'Identity protection';
$string['protection:title'] = 'Identity protection';
$string['protection:supported'] = 'People I support';
$string['protection:supportedintro'] = 'The protected people you support. Everyone else on the site sees them only by the name in the second column. Keep what you see here to yourself.';
$string['protection:supportednone'] = 'You support no one who is protected.';
$string['protection:yourprotection'] = 'Your identity protection';
$string['protection:yourprofile'] = 'Your profile';
$string['protection:level'] = 'Protection level';
$string['protection:level_help'] = 'Each level includes the ones before it. Email hidden: classmates do not see the email address. First name only: also no surname, picture, location, role or expertise. Pseudonym: a chosen name replaces the first name too. The organisation still shows at every level. The person takes part exactly as before.';
$string['protection:level:none'] = 'None';
$string['protection:level:email'] = 'Email hidden';
$string['protection:level:firstname'] = 'First name only';
$string['protection:level:pseudonym'] = 'Pseudonym';
$string['protection:others:none'] = 'Others see your name, email address and profile as usual.';
$string['protection:others:email'] = 'Others see your name and profile. Classmates do not see your email address; the people who run your courses and your organisation\'s managers still can.';
$string['protection:others:firstname'] = 'Others see only your first name and your organisation: no surname, picture, location, role or expertise. Classmates do not see your email address.';
$string['protection:others:pseudonym'] = 'Others see only your pseudonym and your organisation: no real name, picture, location, role or expertise. Classmates do not see your email address.';
$string['protection:yourlevel'] = 'Your protection level: {$a}.';
$string['protection:othersee'] = 'What other people on this site see of you:';
$string['protection:preview:name:full'] = 'Your full name';
$string['protection:preview:name:firstname'] = 'Your first name only';
$string['protection:preview:name:pseudonym'] = 'Your pseudonym, not your name';
$string['protection:preview:email:shown'] = 'Your email address, where your profile settings allow it';
$string['protection:preview:email:hidden'] = 'Not your email address to classmates. The people who run your courses and your organisation\'s managers still see it, so it must not identify you.';
$string['protection:preview:details:shown'] = 'Your location, organisation and the details on your profile';
$string['protection:preview:details:hidden'] = 'Not your location, role or expertise. Your organisation still shows.';
$string['protection:preview:picture:shown'] = 'Your profile picture';
$string['protection:preview:picture:hidden'] = 'Not your picture: everyone sees the default';
$string['protection:whosees'] = 'Who sees your real identity: the site team, your mentors, the course mentor who assesses your work in a course, and the managers of your own organisation. For SIL and SIL partner learners, those managers are your Area\'s Language Technology Coordinators.';
$string['protection:howtoask'] = 'To change your protection, contact the site team. You do not need to say why.';
$string['protection:current'] = 'Applied now: {$a}.';
$string['protection:intakeonly'] = 'As their organisation\'s manager you can grant protection now, before they start. Lowering, removing and correcting it go to the site team.';
$string['protection:siteteamonly'] = 'This person has already signed in or been enrolled, so only the site team can change their protection now. Contact the site team.';
$string['protection:pseudonym'] = 'Pseudonym';
$string['protection:requested'] = 'The person asked for this protection';
$string['protection:emailwarn:name'] = 'The part of the email address before the @ seems to contain the person\'s name. Have it changed to an address that does not identify them before saving.';
$string['protection:emailwarn:organisation'] = 'The email address\'s domain seems to name the person\'s organisation. Have it changed to an address that does not identify them before saving.';
$string['protection:emailchecked'] = 'I have checked that the email address on this account identifies neither the person nor their organisation';
$string['protection:realidentity'] = 'Real identity';
$string['protection:realfirstname'] = 'Real first name';
$string['protection:reallastname'] = 'Real surname';
$string['protection:heldfield'] = 'Held value: {$a}';
$string['protection:history'] = 'Earlier activity';
$string['protection:historynote'] = 'This person has already signed in or been enrolled under their current name. Changing the level relabels what they did, so anyone who saw it can link the old name to the new one, in both directions. Emails and downloads already sent cannot be changed. Talk with the person about whether a fresh account would suit them better.';
$string['protection:acknowledge'] = 'I understand that earlier activity will be linked to the new name';
$string['protection:hidelogs'] = 'The person asked for their location to be hidden from course staff: block course logs in the courses they take';
$string['protection:picturewarn'] = 'This person has a profile picture. Saving at {$a} deletes it, and it is not restored if the level is lowered later.';
$string['protection:ownwords'] = 'The site does not rewrite what the person has written themselves: their profile description, interests, posts and submissions. They are told to review these, and that a picture removed by protection must be uploaded again if the level is lowered.';
$string['protection:save'] = 'Save protection';
$string['protection:saved'] = 'Protection saved: {$a} is applied now.';
$string['protection:organisation'] = 'Organisation';
$string['protection:warn:logblock'] = 'The course-log block could not be applied now; the hourly repair applies it.';
$string['protection:warn:norecall'] = 'Copies already sent (emails, exports, synced calendars) cannot be recalled.';
$string['protection:notice:subject'] = 'Your identity protection changed';
$string['protection:notice:body'] = 'Your identity protection is now: {$a->level}. {$a->others}

Please review what you have written yourself, such as your profile description and interests: the site does not change it. A picture removed by protection must be uploaded again if your level is lowered.';
$string['protection:notice:history'] = 'Emails, downloads and calendar copies sent before this change cannot be recalled.';
$string['protection:pseudonym:empty'] = 'it is empty';
$string['protection:pseudonym:toolong'] = 'it is longer than 100 characters';
$string['protection:pseudonym:isrealname'] = 'it is the real first name';
$string['protection:pseudonym:hasrealname'] = 'it contains the real surname';
$string['protection:pseudonym:taken'] = 'another protected person already uses it';
$string['protection:err:level'] = 'That is not a protection level.';
$string['protection:err:nouser'] = 'That user does not exist.';
$string['protection:err:notrequested'] = 'Protection is set only for someone who asked for it. Confirm that the person asked.';
$string['protection:err:emailnotchecked'] = 'Confirm that the email address on this account identifies neither the person nor their organisation.';
$string['protection:err:siteteam'] = 'Only the site team can make this change. An organisation\'s manager grants protection before the person starts; later raises, lowering, removal and corrections go to the site team.';
$string['protection:err:noconfig'] = 'Identity protection is not configured on this site yet: run site_config.py apply.';
$string['protection:err:pseudonym'] = 'That pseudonym cannot be used: {$a}.';
$string['protection:err:needsack'] = 'This person has earlier activity. Confirm that you understand it will be linked to the new name.';
$string['protection:err:reconcile'] = '{$a} protected accounts could not be repaired. The next run tries again; see the task log.';
$string['protection:err:busy'] = 'This person\'s protection is being changed already. Try again in a moment.';
$string['protection:err:nopermission'] = 'You cannot change this person\'s protection.';
$string['protection:err:nocorrect'] ='You cannot correct a real identity you are not allowed to see.';
$string['privacy:metadata:protection'] = 'A protected user\'s protection level, pseudonym and real identity (spec 016).';
$string['privacy:metadata:protection:userid'] = 'The protected user.';
$string['privacy:metadata:protection:ownlevel'] = 'The user\'s own protection level.';
$string['privacy:metadata:protection:effectivelevel'] = 'The protection level applied to the account.';
$string['privacy:metadata:protection:pseudonym'] = 'The pseudonym shown in place of the user\'s name.';
$string['privacy:metadata:protection:realfirstname'] = 'The user\'s real first name.';
$string['privacy:metadata:protection:reallastname'] = 'The user\'s real surname.';
$string['privacy:metadata:protection:realfields'] = 'The real values of the profile details the level withholds from others.';
$string['privacy:metadata:protection:hidelogs'] = 'Whether the user asked for course logs to be blocked in the courses they take.';
$string['privacy:metadata:protection:usermodified'] = 'Who last changed the protection.';
$string['privacy:metadata:protectionlog'] = 'Every change of a user\'s protection level (spec 016).';
$string['privacy:metadata:protectionlog:userid'] = 'The user whose protection changed.';
$string['privacy:metadata:protectionlog:actorid'] = 'Who made the change.';
$string['privacy:metadata:protectionlog:fromlevel'] = 'The level before.';
$string['privacy:metadata:protectionlog:tolevel'] = 'The level after.';
$string['privacy:metadata:protectionlog:requested'] = 'Whether the user asked for the change.';
$string['privacy:metadata:protectionlog:emailchecked'] = 'Whether the person granting it confirmed the user\'s email address does not identify them.';
$string['privacy:metadata:protectionlog:timecreated'] = 'When it changed.';
$string['privacy:metadata:protectionchanged'] = 'Tells a user that their own identity protection changed.';
$string['privacy:path:protection'] = 'Identity protection';

// Spec 008: administration. The site team's tool and its service; never shown to a learner.
// No string here names a person, an organisation or a protection level.
$string['ltuse:administer'] = 'Administer LTC learners through the administration service';
$string['setting:coursementorsync'] = 'Enrol course mentors automatically';
$string['setting:coursementorsync_desc'] = 'Keep each learner\'s course mentors enrolled as Course mentor in the courses they take, and remove them as soon as the reason ends. Declared in moodle/site/settings/admin.yaml; change it there.';

// Spec 008: why a row or a whole command is refused. Shown by ltct_admin.py to the site team.
// {$a} is only ever an idnumber, an organisation key or a course idnumber, never a person.
$string['admin:refusal:missing'] = 'not found in Moodle: {$a}. Check the names with "ltct_admin.py list", or apply the site declaration first';
$string['admin:refusal:nopathways'] = 'learning pathways are not installed on this site (spec 006), so nothing was done';
$string['admin:refusal:pathwaykey'] = '"{$a}" is not a pathway that can be assigned';
$string['admin:refusal:target'] = 'name exactly one course or one pathway, with ensure or remove; a pathway can only be enrolled';
$string['admin:reason:bad_email'] = 'the email is not an address Moodle can send to; nothing was done';
$string['admin:reason:facts'] = 'the server could not read everything it needs about this row; nothing was done';
$string['admin:reason:duplicate_accounts'] = 'two accounts already share this email; the site team must merge or change one by hand first';
$string['admin:reason:login_clash'] = 'another account uses this email as its username, so this person could not sign in with it; the site team must change that account\'s username first';
$string['admin:reason:suspended'] = 'the account exists and is suspended; reactivate it deliberately if that is right';
$string['admin:reason:other_org'] = 'the account is already in {$a}; use "move" if this is right';
$string['admin:reason:course_not_allowed'] = 'a listed course is not one this organisation may be enrolled into';
$string['admin:reason:protection_absent'] = 'protection is asked for, and identity protection (spec 016) is not installed; the row waits';
$string['admin:reason:email_reveals'] = 'the email address looks like it names this person or their organisation, and others in a course will see it; use another address, or put yes in email_checked once you have confirmed it does not identify them';
$string['admin:reason:email_unchecked'] = 'protection is asked for, and others in a course will see this email address; confirm with the person that it identifies neither them nor their organisation, then put yes in email_checked, or give another address';
$string['admin:reason:pseudonym_invalid'] = 'the pseudonym cannot be used: it must not be empty or longer than 100 characters, must not be the person\'s first name or contain their last name, and must not be one someone else already has; change it in the file';
$string['admin:reason:protection_unavailable'] = 'the protection this row needs cannot be set on this site yet; the row waits';
$string['admin:reason:protection_below'] = 'the account\'s protection is below what this row needs; raise it on the protection page first';
$string['admin:reason:course_not_ltct'] = 'the course is not one this repository publishes (ltct:<slug>), or is the office-hours course';
$string['admin:reason:course_category'] = 'the course is neither shared (ltct:published) nor an organisation\'s own; pilot courses are never enrolled this way';
$string['admin:reason:other_org_course'] = 'the course belongs to another organisation';
$string['admin:reason:managers_shared'] = 'a managers cohort is never enrolled into a shared course';
$string['admin:reason:mentors_cohort'] = 'the mentors cohort is never enrolled into a course; course mentors are enrolled automatically';
$string['admin:reason:cohort_kind'] = 'only an organisation\'s cohort, or its managers cohort in its own course, can be enrolled';
$string['admin:reason:role_mismatch'] = 'this cohort already has an enrolment method in the course with another role; the site team decides by hand';
$string['admin:reason:changed'] = 'changed since the preview; preview again';
$string['admin:reason:busy'] = 'another run is working on this row now; run the same command again in a minute';
$string['admin:reason:protection_not_permitted'] = 'your account may not set protection for a new account; the account was made, and the row resumes once you may';
$string['admin:reason:protection_failed'] = 'identity protection refused the level; the account was made, is in no organisation, and the row resumes when run again';
$string['admin:reason:protection_unsettled'] = 'protection has not settled yet; the account is in no organisation, and the row resumes when run again';
$string['admin:reason:org_not_saved'] = 'the organisation field did not accept this key; check the site declaration has been applied';
$string['admin:reason:enrol_unavailable'] = 'course enrolment through the Organisation enrolment is not installed (spec 002)';
$string['admin:reason:enrol_failed'] = 'a listed course could not be enrolled; the rest of the row is done';
$string['admin:reason:not_in_pathway'] = 'the course is no longer on that pathway';
$string['admin:reason:pathway_course_refused'] = 'the cohort may not be enrolled into {$a}, which is on the pathway, so the pathway was not assigned';
$string['admin:reason:pathway_unavailable'] = 'the pathway cannot be used now';

// Spec 008: routine changes (user story 3): suspension, moves, enrol mirror, managers cohorts.
$string['admin:refusal:mirror'] = 'a mirror goes from one organisation key to another organisation\'s cohort (ltct:org:<key>), never to itself';
$string['admin:reason:no_account'] = 'no account has this email';
$string['admin:reason:site_admin'] = 'the account is a site administrator; this tool never changes one';
$string['admin:reason:own_account'] = 'this is your own account; ask another member of the site team';
$string['admin:reason:actions_unavailable'] = 'the organisation actions (spec 002) are not installed, so nothing was done';
$string['admin:reason:action_refused'] = 'Moodle refused the change for this account; nothing was done';
$string['admin:reason:no_org'] = 'the account has no organisation yet; bring it on with "intake", not "move"';
$string['admin:reason:lost'] = 'the move would leave them without access to {$a}; run "enrol mirror" first';
$string['admin:reason:org_cohort_members'] = 'an organisation\'s own cohort follows the organisation field; change that with "move", never by hand';
$string['admin:reason:cohort_not_managed'] = 'only a managers cohort (ltct:org:<key>:managers) or ltct:mentors can be changed this way';
$string['admin:reason:cohort_component'] = 'a plugin manages this cohort\'s members, so it cannot be changed by hand';
$string['admin:reason:manages_own'] = 'note: this person is in {$a}, the organisation they will manage; from now on only the site team can manage their account';

// Spec 008: mentors (user story 5): mentor relationships in bulk, one-course and cohort mentors,
// and the course-mentor sync.
$string['admin:refusal:mentorsmode'] = 'give either a mentors file or one mentor whose relationships all end, not both';
$string['admin:refusal:mentornone'] = 'no account has the mentor\'s email';
$string['admin:refusal:mentorduplicate'] = 'two accounts share the mentor\'s email; the site team must merge or change one by hand first';
$string['admin:reason:mentor_no_account'] = 'no account has the mentor\'s email';
$string['admin:reason:mentor_duplicate_accounts'] = 'two accounts share the mentor\'s email; the site team must merge or change one by hand first';
$string['admin:reason:own_mentor'] = 'a learner cannot be their own mentor';
$string['admin:reason:not_a_mentor'] = 'the mentor is not in ltct:mentors; add them with "managers" first';
$string['admin:reason:learner_or_cohort'] = 'give exactly one of a learner or a cohort';
$string['admin:reason:cohort_not_enrolled'] = 'note: this cohort is not enrolled in the course yet; its mentors are enrolled once it is';
$string['admin:reason:coursementorsync_off'] = 'recorded; course mentors are not enrolled automatically on this site yet (local_ltuse/coursementorsync is off)';
$string['task:coursementorreconcile'] = 'Keep course mentors and pathway enrolments in step';

$string['privacy:metadata:course_mentor'] = 'Course mentors recorded by the site team who are not a learner\'s default mentor: a mentor for one learner in one course, or the mentors of a cohort in a course. Course mentors are enrolled in those courses as Course mentor while their learners are.';
$string['privacy:metadata:course_mentor:courseid'] = 'The course.';
$string['privacy:metadata:course_mentor:mentorid'] = 'The course mentor.';
$string['privacy:metadata:course_mentor:learnerid'] = 'The learner, for a mentor of one learner in one course.';
$string['privacy:metadata:course_mentor:cohortid'] = 'The cohort, for the mentors of a cohort.';
$string['privacy:metadata:course_mentor:usermodified'] = 'Who recorded it.';
$string['privacy:metadata:course_mentor:timecreated'] = 'When it was recorded.';
$string['privacy:metadata:course_mentor:timemodified'] = 'When it last changed.';
$string['privacy:path:coursementors'] = 'Course mentors';
$string['privacy:metadata:local_ltuse_digest_override'] = 'The per-forum email digest settings the course-mentor sync set for a person, so that removing them never undoes a choice the person made themselves.';
$string['privacy:metadata:local_ltuse_digest_override:userid'] = 'The person the setting was made for.';
$string['privacy:metadata:local_ltuse_digest_override:forumid'] = 'The forum the setting is on.';
$string['privacy:metadata:local_ltuse_digest_override:value'] = 'The email digest setting the sync made.';
$string['privacy:metadata:local_ltuse_digest_override:released'] = 'Whether the person has since set the forum back to their own default, so the sync never sets it again.';
$string['privacy:metadata:local_ltuse_digest_override:timecreated'] = 'When the setting was made.';
$string['privacy:path:digestoverrides'] = 'Forum email settings made by the course-mentor sync';

// Spec 007: learner experience. Learner-facing navigation; no string here names a CBC level or says "certified".
$string['backtocourse'] = 'Back to the course';
$string['nextlesson'] = 'Next: {$a}';
// End of the spec 007 learner experience block.
