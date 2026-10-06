<?php
// This file is part of block_ltuse, the learner home block of the LTC training system.
//
// The block sits on the default Dashboard, placed there by moodle/site/dashboard.yaml, and
// shows one learner where to go next: continue, the empty state and the onward routes (spec
// 007, R3 and R5). Everything it shows comes from local_ltuse\learner_home, so the web block
// and the app handler cannot differ.

defined('MOODLE_INTERNAL') || die();

$plugin->component = 'block_ltuse';
$plugin->version   = 2026100600;   // The first version (spec 007): the block's skeleton.
                                   // moodle/site/site.yaml pins block_ltuse to this stamp,
                                   // and validate fails if they differ.

// As local_ltuse: verified on Moodle 5.2 only, so a major upgrade stops at the plugin check
// instead of running unverified code (constitution XI). Widen the range only after
// re-verifying on the new branch (502 = Moodle 5.2).
$plugin->requires  = 2026042000;   // Moodle 5.2.
$plugin->supported = [502, 502];

$plugin->maturity  = MATURITY_ALPHA;
$plugin->release   = '0.1.0';

// The block renders what local_ltuse\learner_home derives, so it depends on the local_ltuse
// version that adds learner_home (spec 007) and cannot install without it.
$plugin->dependencies = ['local_ltuse' => 2026100901];
