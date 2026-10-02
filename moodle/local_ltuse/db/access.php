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
];
