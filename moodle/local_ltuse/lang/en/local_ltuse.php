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

// Spec 016: identity protection. No string names a person, an organisation or a level as held
// by anyone; levels are described by what others see.
$string['ltuse:viewidentity'] = 'See a protected person\'s real identity';
$string['ltuse:manageprotection'] = 'Change a person\'s identity protection';
$string['ltuse:manageorgprotection'] = 'Set an organisation\'s minimum identity protection';
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
$string['protection:orgtitle'] = 'Organisation identity protection';
$string['protection:supported'] = 'People I support';
$string['protection:supportedintro'] = 'The protected people you support. Everyone else on the site sees them only by the name in the second column. Keep anything you download from here to yourself.';
$string['protection:supportednone'] = 'You support no one who is protected.';
$string['protection:downloadcsv'] = 'Download as CSV';
$string['protection:yourprotection'] = 'Your identity protection';
$string['protection:yourprofile'] = 'Your profile';
$string['protection:level'] = 'Protection level';
$string['protection:level_help'] = 'Each level includes the ones before it. Email hidden: no one else sees the email address. First name only: also no surname, picture, location, organisation, role or expertise. Pseudonym: a chosen name replaces the first name too. The person takes part exactly as before.';
$string['protection:level:none'] = 'None';
$string['protection:level:email'] = 'Email hidden';
$string['protection:level:firstname'] = 'First name only';
$string['protection:level:pseudonym'] = 'Pseudonym';
$string['protection:others:none'] = 'Others see your name, email address and profile as usual.';
$string['protection:others:email'] = 'Others see your name and profile, but not your email address.';
$string['protection:others:firstname'] = 'Others see only your first name: no surname, email address, picture, location, organisation, role or expertise.';
$string['protection:others:pseudonym'] = 'Others see only your pseudonym: no real name, email address, picture, location, organisation, role or expertise.';
$string['protection:yourlevel'] = 'Your protection level: {$a}.';
$string['protection:othersee'] = 'What other people on this site see of you:';
$string['protection:preview:name:full'] = 'Your full name';
$string['protection:preview:name:firstname'] = 'Your first name only';
$string['protection:preview:name:pseudonym'] = 'Your pseudonym, not your name';
$string['protection:preview:email:shown'] = 'Your email address, where your profile settings allow it';
$string['protection:preview:email:hidden'] = 'Not your email address';
$string['protection:preview:details:shown'] = 'Your location, organisation and the details on your profile';
$string['protection:preview:details:hidden'] = 'Not your location, organisation, role or expertise';
$string['protection:preview:picture:shown'] = 'Your profile picture';
$string['protection:preview:picture:hidden'] = 'Not your picture: everyone sees the default';
$string['protection:whosees'] = 'Who sees your real identity: the site team, your mentors, the course mentors of the courses you take, and the managers of your own organisation. For SIL and SIL partner learners, those managers are your Area\'s Language Technology Coordinators.';
$string['protection:howtoask'] = 'To ask for protection, or to change it, contact your organisation\'s manager or the site team. You can ask at any time, and you do not need to say why.';
$string['protection:current'] = 'Applied now: {$a}.';
$string['protection:source:organisation'] = '(from the organisation\'s minimum)';
$string['protection:source:organisation-kept'] = '(kept after leaving a protected organisation; lower it here if it is no longer needed)';
$string['protection:withheldfromyou'] = 'The organisation does not share this person\'s real identity with its managers. You can still change their protection.';
$string['protection:notyet'] = '(not available yet)';
$string['protection:notready'] = 'First name only and Pseudonym are not available yet: the organisation field must first be hidden from everyone (spec 016, research R11). Email hidden is available.';
$string['protection:orgminimumnote'] = 'The organisation\'s minimum is {$a}; this person\'s own level cannot be looser.';
$string['protection:pseudonym'] = 'Pseudonym';
$string['protection:newusername'] = 'New username';
$string['protection:usernamenote'] = 'The current username contains part of the person\'s real name, so it must change for First name only or Pseudonym. The person is told their new login.';
$string['protection:usernamenote:neutral'] = 'First name only and Pseudonym need a new, neutral username for this person. The person is told their new login.';
$string['protection:realidentity'] = 'Real identity';
$string['protection:realfirstname'] = 'Real first name';
$string['protection:reallastname'] = 'Real surname';
$string['protection:heldfield'] = 'Held value: {$a}';
$string['protection:history'] = 'Earlier activity';
$string['protection:historynote'] = 'This person has already posted, submitted or sent messages under their current name. Changing the level relabels that history, so anyone who saw it can link the old name to the new one, in both directions. Emails and downloads already sent cannot be changed. A fresh account is safer.';
$string['protection:acknowledge'] = 'I understand that earlier activity will be linked to the new name';
$string['protection:ownwords'] = 'The site does not rewrite what the person has written themselves: their profile description, interests, posts and submissions. They are told to review these, and that a picture removed by protection must be uploaded again if the level is lowered.';
$string['protection:save'] = 'Save protection';
$string['protection:saved'] = 'Protection saved: {$a} is applied now.';
$string['protection:organisation'] = 'Organisation';
$string['protection:orgminimum'] = 'Minimum level';
$string['protection:managersseeidentity'] = 'The organisation\'s own managers see real identities';
$string['protection:orgacknowledge'] = 'I understand that members with earlier activity will be linked to their new name';
$string['protection:orgnote'] = 'A minimum is kept only in this site, never in the repository. Lowering it lowers no one: members keep their level until it is lowered one by one. A minimum cannot be Pseudonym, which is chosen per person.';
$string['protection:orgsaved'] = 'Saved. {$a->members} members are being updated; {$a->withactivity} of them had earlier activity.';
$string['protection:members'] = 'Members';
$string['protection:warn:norecall'] = 'Copies already sent (emails, exports, synced calendars) cannot be recalled.';
$string['protection:warn:newlogin'] = 'The username changed; the person has been told their new login.';
$string['protection:notice:subject'] = 'Your identity protection changed';
$string['protection:notice:body'] = 'Your identity protection is now: {$a->level}. {$a->others}

Please review what you have written yourself, such as your profile description and interests: the site does not change it. A picture removed by protection must be uploaded again if your level is lowered.';
$string['protection:notice:history'] = 'Emails, downloads and calendar copies sent before this change cannot be recalled.';
$string['protection:notice:newlogin'] = 'Your username is now {$a}. Use it the next time you log in.';
$string['protection:pseudonym:empty'] = 'it is empty';
$string['protection:pseudonym:toolong'] = 'it is longer than 100 characters';
$string['protection:pseudonym:isrealname'] = 'it is the real first name';
$string['protection:pseudonym:hasrealname'] = 'it contains the real surname';
$string['protection:pseudonym:taken'] = 'another protected person already uses it';
$string['protection:err:level'] = 'That is not a protection level.';
$string['protection:err:nouser'] = 'That user does not exist.';
$string['protection:err:noconfig'] = 'Identity protection is not configured on this site yet: run site_config.py apply.';
$string['protection:err:looser'] = 'The organisation\'s minimum is {$a}; a person\'s own level cannot be looser.';
$string['protection:err:notready'] = 'This level is not available until the organisation field is hidden from everyone (spec 016, research R11).';
$string['protection:err:pseudonym'] = 'That pseudonym cannot be used: {$a}.';
$string['protection:err:username'] = 'The username contains the real first name or surname. Give a neutral username first.';
$string['protection:err:badusername'] = 'That username is not valid: lowercase letters, digits and . - _ @ only.';
$string['protection:err:usernametaken'] = 'That username is already taken.';
$string['protection:err:needsack'] = 'This person has earlier activity. Confirm that you understand it will be linked to the new name.';
$string['protection:err:orgneedsack'] = '{$a} members have earlier activity. Confirm that you understand it will be linked to their new names.';
$string['protection:err:noorg'] = 'That organisation is not declared in organisations.yaml.';
$string['protection:err:orglevel'] = 'An organisation minimum is None, Email hidden or First name only.';
$string['protection:err:busy'] = 'This person\'s protection is being changed already. Try again in a moment.';
$string['protection:err:nopermission'] = 'You cannot change this person\'s protection.';
$string['protection:err:refused'] = 'That pseudonym or username cannot be used. Ask the site team to set it.';
$string['protection:err:nocorrect'] ='You cannot correct a real identity you are not allowed to see.';
$string['privacy:metadata:protection'] = 'A protected user\'s protection level, pseudonym and real identity (spec 016).';
$string['privacy:metadata:protection:userid'] = 'The protected user.';
$string['privacy:metadata:protection:ownlevel'] = 'The user\'s own protection level.';
$string['privacy:metadata:protection:effectivelevel'] = 'The protection level applied to the account.';
$string['privacy:metadata:protection:pseudonym'] = 'The pseudonym shown in place of the user\'s name.';
$string['privacy:metadata:protection:realfirstname'] = 'The user\'s real first name.';
$string['privacy:metadata:protection:reallastname'] = 'The user\'s real surname.';
$string['privacy:metadata:protection:realfields'] = 'The real values of the profile details the level withholds from others.';
$string['privacy:metadata:protection:usermodified'] = 'Who last changed the protection.';
$string['privacy:metadata:protectionlog'] = 'Every change of a user\'s protection level (spec 016).';
$string['privacy:metadata:protectionlog:userid'] = 'The user whose protection changed.';
$string['privacy:metadata:protectionlog:actorid'] = 'Who made the change.';
$string['privacy:metadata:protectionlog:fromlevel'] = 'The level before.';
$string['privacy:metadata:protectionlog:tolevel'] = 'The level after.';
$string['privacy:metadata:protectionlog:timecreated'] = 'When it changed.';
$string['privacy:metadata:orgprotection'] = 'An organisation\'s minimum protection level, and who set it (spec 016).';
$string['privacy:metadata:orgprotection:usermodified'] = 'The site-team member who set it.';
$string['privacy:metadata:orgprotection:timemodified'] = 'When it was set.';
$string['privacy:metadata:protectionchanged'] = 'Tells a user that their own identity protection changed.';
$string['privacy:path:protection'] = 'Identity protection';
