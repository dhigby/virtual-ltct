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
];
