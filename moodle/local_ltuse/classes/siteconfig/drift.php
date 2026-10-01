<?php
// This file is part of local_ltuse, the publish endpoint for the LTC curriculum repo.

namespace local_ltuse\siteconfig;

use core_plugin_manager;

defined('MOODLE_INTERNAL') || die();

/**
 * Reports how this site differs from the declaration, and changes nothing (US2).
 *
 * Three passes:
 *   1. every declared item, through the inspector (changed, missing, unknown, forced, ...);
 *   2. installed plugins that are not standard and not declared (extra);
 *   3. undeclared settings whose value differs from Moodle's default and that ignore.yaml
 *      does not list (unmanaged, research R5).
 *
 * A setting forced in config.php is skipped in pass 3: config.php belongs to provisioning,
 * not to this declaration (R6). Any difference makes the run exit 1 (FR-008).
 */
class drift {

    /** @var inspector */
    protected $inspector;

    /** @var report */
    protected $report;

    /**
     * @param inspector $inspector
     * @param report $report
     */
    public function __construct(inspector $inspector, report $report) {
        $this->inspector = $inspector;
        $this->report = $report;
    }

    /**
     * Run the comparison. The exit code comes from the report.
     */
    public function run(): void {
        foreach ($this->inspector->inspect() as $item) {
            $this->report->add_result($item);
        }
        $declaration = $this->inspector->declaration();
        $this->report_extra_plugins($declaration);
        $this->report_unmanaged_settings($declaration);
    }

    /**
     * @param array $declaration
     */
    protected function report_extra_plugins(array $declaration): void {
        $declared = array_column($declaration['plugins'] ?? [], 'component');
        foreach (core_plugin_manager::instance()->get_plugins() as $plugins) {
            foreach ($plugins as $info) {
                if ($info->is_standard() || $info->versiondb === null
                        || in_array($info->component, $declared, true)) {
                    continue;
                }
                $this->report->add('fail', 'extra', $info->component, null, (string)$info->versiondb,
                    'installed but not declared in site.yaml');
            }
        }
    }

    /**
     * @param array $declaration
     */
    protected function report_unmanaged_settings(array $declaration): void {
        $skip = array_flip(array_merge(
            array_column($declaration['settings'] ?? [], 'name'),
            array_column($declaration['ignore'] ?? [], 'setting')
        ));
        foreach ($this->inspector->all_settings() as $key => $setting) {
            if (isset($skip[$key]) || $setting->is_readonly()) {
                continue;
            }
            $default = $setting->get_defaultsetting();
            $live = $setting->get_setting();
            if ($default === null || $live === null || $live === true) {
                // No default to compare with, never written, or a widget with no stored value
                // (admin_setting_check, the AI provider manager), whose get_setting() is true.
                continue;
            }
            if (inspector::normalise_live($setting, $live) === inspector::normalise_live($setting, $default)) {
                continue;
            }
            $this->report->add('fail', 'unmanaged', $key, null, $live,
                'differs from the Moodle default; declare it or add it to ignore.yaml', $setting);
        }
    }
}
