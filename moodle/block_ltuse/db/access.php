<?php
// Capability definitions for block_ltuse.

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    // Spec 007 (R4): the declaration places the block on the default Dashboard, and no learner
    // or teacher adds it. So both are granted to manager alone, and neither clones from
    // moodle/my:manageblocks or moodle/site:manageblocks, as core's blocks do: cloning would
    // grant user, whose archetype allows moodle/my:manageblocks until apply prevents it.
    'block/ltuse:myaddinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'block/ltuse:addinstance' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_BLOCK,
        'archetypes'   => [
            'manager' => CAP_ALLOW,
        ],
    ],
];
