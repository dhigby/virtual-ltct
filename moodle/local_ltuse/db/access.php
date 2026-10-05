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

    // Spec 016 (research R7). Never checked alone: every surface asks
    // local_ltuse\protection\entitlement, which adds the organisation-manager path.
    // See a protected user's real identity and the Protected marker. The site team at system
    // context; the declared mentor role in the learner's user context; the declared teacher
    // role ("Course mentor") in an ltct: course the learner takes (path 4).
    'local/ltuse:viewidentity' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
    // Grant, change or remove one person's protection. The site team; own-organisation
    // managers come through managers-cohort membership, not this capability.
    'local/ltuse:manageprotection' => [
        'riskbitmask'  => RISK_PERSONAL,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_USER,
        'archetypes'   => ['manager' => CAP_ALLOW],
    ],
];
