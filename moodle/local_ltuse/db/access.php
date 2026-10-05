<?php
// Capability definitions for local_ltuse.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // One capability gates the whole service. Deliberately not granted to any archetype:
    // publishing rewrites course content wholesale, so it must be assigned explicitly to
    // the publishing account and to nobody else. Site administration > Users >
    // Permissions > Define roles.
    'local/ltuse:publish' => [
        'riskbitmask'  => RISK_XSS | RISK_DATALOSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [],
    ],
    // Spec 003: a mentor sees this learner on the Mentoring page. Checked in the learner's
    // own user context, so it reaches only the learners someone is assigned to as mentor.
    // Granted only by the declared mentor role (moodle/site/roles.yaml), never an archetype.
    'local/ltuse:viewmenteeprogress' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => [],
    ],

    // Spec 008: administration.
    // The site team's administration service (ltuse_admin): its requiredcapability, re-checked
    // by every local_ltuse_admin_* function before the core capability for its write. It
    // creates accounts that receive email, edits accounts and enrolments, and reads people's
    // details, hence all three risks. No archetype: granted only through the declared
    // ltctadmin role (moodle/site/roles.yaml), held at system level by each site-team member.
    'local/ltuse:administer' => [
        'riskbitmask'  => RISK_PERSONAL | RISK_DATALOSS | RISK_SPAM,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [],
    ],
];
