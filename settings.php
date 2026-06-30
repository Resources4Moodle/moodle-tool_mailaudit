<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('tools', new admin_category(
        'tool_mailaudit_category',
        get_string('pluginname', 'tool_mailaudit'),
    ));

    $ADMIN->add('tool_mailaudit_category', new admin_externalpage(
        'tool_mailaudit_browse',
        get_string('browsemail', 'tool_mailaudit'),
        new moodle_url('/admin/tool/mailaudit/index.php'),
        'tool/mailaudit:view',
    ));

    $settings = new admin_settingpage('tool_mailaudit', get_string('pluginsettings', 'tool_mailaudit'));
    if ($ADMIN->fulltree) {
        // Capture health and privacy notice, computed only while this settings page renders.
        $settings->add(new admin_setting_heading(
            'tool_mailaudit/capturehealth',
            get_string('capturehealth', 'tool_mailaudit'),
            \tool_mailaudit\local\health::summary_html(),
        ));

        $settings->add(new admin_setting_heading(
            'tool_mailaudit/privacynotice',
            get_string('privacynotice', 'tool_mailaudit'),
            get_string('privacynotice_desc', 'tool_mailaudit'),
        ));

        $kindoptions = [];
        foreach (\tool_mailaudit\local\repository::kind_keys() as $kind) {
            $kindoptions[$kind] = \tool_mailaudit\local\repository::kind_label($kind);
        }
        $allkinds = array_fill_keys(array_keys($kindoptions), 1);
        $nonadminsafe = [
            'course_registration' => 1,
            'course_bulk_mail' => 1,
            'message_digest' => 1,
            'mailtest' => 1,
            'other' => 1,
        ];
        $sensitive = [
            'password_reset' => 1,
            'new_ip_login' => 1,
            'failed_login_digest' => 1,
            'account_security' => 1,
        ];

        $settings->add(new admin_setting_configcheckbox(
            'tool_mailaudit/enabled',
            get_string('enabled', 'tool_mailaudit'),
            get_string('enabled_desc', 'tool_mailaudit'),
            1,
        ));

        $settings->add(new admin_setting_configmulticheckbox(
            'tool_mailaudit/capturekinds',
            get_string('capturekinds', 'tool_mailaudit'),
            get_string('capturekinds_desc', 'tool_mailaudit'),
            $allkinds,
            $kindoptions,
        ));

        $settings->add(new admin_setting_configmulticheckbox(
            'tool_mailaudit/nonadminvisiblekinds',
            get_string('nonadminvisiblekinds', 'tool_mailaudit'),
            get_string('nonadminvisiblekinds_desc', 'tool_mailaudit'),
            $nonadminsafe,
            $kindoptions,
        ));

        $settings->add(new admin_setting_configmulticheckbox(
            'tool_mailaudit/sensitivekinds',
            get_string('sensitivekinds', 'tool_mailaudit'),
            get_string('sensitivekinds_desc', 'tool_mailaudit'),
            $sensitive,
            $kindoptions,
        ));

        $settings->add(new admin_setting_configselect(
            'tool_mailaudit/defaultperpage',
            get_string('defaultperpage', 'tool_mailaudit'),
            get_string('defaultperpage_desc', 'tool_mailaudit'),
            50,
            [25 => 25, 50 => 50, 100 => 100, 200 => 200],
        ));

        $settings->add(new admin_setting_configtext(
            'tool_mailaudit/retentiondays',
            get_string('retentiondays', 'tool_mailaudit'),
            get_string('retentiondays_desc', 'tool_mailaudit'),
            180,
            PARAM_INT,
        ));

        $settings->add(new admin_setting_configtext(
            'tool_mailaudit/autopurgedeleteddays',
            get_string('autopurgedeleteddays', 'tool_mailaudit'),
            get_string('autopurgedeleteddays_desc', 'tool_mailaudit'),
            180,
            PARAM_INT,
        ));

        $settings->add(new admin_setting_configtext(
            'tool_mailaudit/autopurgeactivedays',
            get_string('autopurgeactivedays', 'tool_mailaudit'),
            get_string('autopurgeactivedays_desc', 'tool_mailaudit'),
            0,
            PARAM_INT,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_mailaudit/storebodytext',
            get_string('storebodytext', 'tool_mailaudit'),
            get_string('storebodytext_desc', 'tool_mailaudit'),
            0,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_mailaudit/storebodyhtml',
            get_string('storebodyhtml', 'tool_mailaudit'),
            get_string('storebodyhtml_desc', 'tool_mailaudit'),
            0,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'tool_mailaudit/storestacktrace',
            get_string('storestacktrace', 'tool_mailaudit'),
            get_string('storestacktrace_desc', 'tool_mailaudit'),
            0,
        ));
    }

    $ADMIN->add('tool_mailaudit_category', $settings);
}
