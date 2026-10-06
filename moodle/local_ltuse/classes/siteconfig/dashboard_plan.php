<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

defined('MOODLE_INTERNAL') || die();

/**
 * What apply must change on the default Dashboard page to match dashboard.yaml (spec 007,
 * specs/007-learner-experience/contracts/dashboard-declaration.md).
 *
 * plan() is pure: it is handed what is declared and what is live, as plain arrays, and returns
 * the changes. dashboard.php is its only caller: it calls plan() in check() and again at the
 * start of apply(), because the roles step earlier in the same run may just have prevented
 * editing. tests/dashboard_plan_harness.php tests it without Moodle.
 *
 * The rules:
 * - a declared block with no live instance is added, at its declared weight or 0;
 * - the region of a live instance is not compared (011's present()), so a declared block live
 *   in another region is neither added, deleted nor reweighted;
 * - of several live instances of one block, the one with the lower weight, then the lower id,
 *   is the one kept: it is the one reweighted, and with `complete` the others are deleted;
 * - with `complete`, every live instance of an undeclared block is deleted, in any region;
 * - a declared weight that differs from the kept instance's is reweighted, when that instance
 *   is in the declared region; a block declared without a weight never is;
 * - `reset` with personal dashboards to reset is planned while editing is prevented, and
 *   refused while it is not. With none to reset it plans nothing either way.
 */
final class dashboard_plan {

    /**
     * @param array[] $declared [{block, region, weight?}], the payload's `dashboard`
     * @param array[] $live [{id, block, region, weight}], every instance on the default page
     * @param bool $complete whether the declared list is the whole default page
     * @param string $personal 'reset' or 'keep'
     * @param bool $editingprevented whether moodle/my:manageblocks is prevented for the user role, live
     * @param int $personalcount how many personal dashboards exist
     * @return array add [{block, region, weight}], delete [{id, block}], reweight [{id, block, from, to}],
     *               reset bool, refusereset bool
     */
    public static function plan(array $declared, array $live, bool $complete, string $personal,
            bool $editingprevented, int $personalcount): array {
        $out = ['add' => [], 'delete' => [], 'reweight' => [], 'reset' => false, 'refusereset' => false];

        // Each block's live instances, the one kept first.
        $byblock = [];
        foreach ($live as $instance) {
            $instance = (array)$instance;
            $byblock[(string)$instance['block']][] = ['id' => (int)$instance['id'],
                'block' => (string)$instance['block'], 'region' => (string)$instance['region'],
                'weight' => (int)$instance['weight']];
        }
        foreach ($byblock as $block => $instances) {
            usort($instances, function(array $a, array $b): int {
                return [$a['weight'], $a['id']] <=> [$b['weight'], $b['id']];
            });
            $byblock[$block] = $instances;
        }

        $names = [];
        foreach ($declared as $entry) {
            $entry = (array)$entry;
            $block = (string)$entry['block'];
            $names[$block] = true;
            $hasweight = array_key_exists('weight', $entry) && $entry['weight'] !== null;
            if (empty($byblock[$block])) {
                $out['add'][] = ['block' => $block, 'region' => (string)$entry['region'],
                    'weight' => $hasweight ? (int)$entry['weight'] : 0];
                continue;
            }
            $kept = $byblock[$block][0];
            // A weight orders a block within its region, so one live elsewhere is left alone.
            if ($hasweight && $kept['region'] === (string)$entry['region']
                    && $kept['weight'] !== (int)$entry['weight']) {
                $out['reweight'][] = ['id' => $kept['id'], 'block' => $block, 'from' => $kept['weight'],
                    'to' => (int)$entry['weight']];
            }
        }

        if ($complete) {
            // In the order the instances arrived, so the report reads as the page does.
            $keptids = [];
            foreach ($byblock as $block => $instances) {
                if (isset($names[$block])) {
                    $keptids[$instances[0]['id']] = true;
                }
            }
            foreach ($live as $instance) {
                $instance = (array)$instance;
                if (!isset($keptids[(int)$instance['id']])) {
                    $out['delete'][] = ['id' => (int)$instance['id'], 'block' => (string)$instance['block']];
                }
            }
        }

        if ($personal === 'reset' && $personalcount > 0) {
            $out['reset'] = $editingprevented;
            $out['refusereset'] = !$editingprevented;
        }
        return $out;
    }
}
