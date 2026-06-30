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

namespace tool_mailaudit\local;

/**
 * Operator-visible capture health summary for the mail audit tool.
 *
 * Surfaces the most recent successful capture and the most recent capture failure so that a
 * silently broken auditor (broken event, full disk, renamed core seam) is noticeable rather than
 * failing closed without a trace. Capture failures are also reported via {@see debugging()}.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class health {
    /**
     * Build an HTML capture-health summary for the settings page.
     *
     * @return string
     */
    public static function summary_html(): string {
        global $DB, $OUTPUT;

        $lines = [];

        $lastcaptured = 0;
        try {
            $lastcaptured = (int)$DB->get_field_sql('SELECT MAX(timecreated) FROM {' . repository::TABLE . '}');
        } catch (\Throwable $e) {
            $lastcaptured = 0;
        }

        if ($lastcaptured > 0) {
            $lines[] = $OUTPUT->notification(
                get_string('capturehealth_ok', 'tool_mailaudit', userdate($lastcaptured)),
                \core\output\notification::NOTIFY_SUCCESS,
                false
            );
        } else {
            $lines[] = $OUTPUT->notification(
                get_string('capturehealth_none', 'tool_mailaudit'),
                \core\output\notification::NOTIFY_INFO,
                false
            );
        }

        $lasterror = get_config('tool_mailaudit', 'lastcaptureerror');
        $lasterrortime = (int)get_config('tool_mailaudit', 'lastcaptureerrortime');
        if ($lasterror !== false && $lasterror !== '' && $lasterrortime > 0) {
            $lines[] = $OUTPUT->notification(
                get_string('capturehealth_error', 'tool_mailaudit', (object) [
                    'time' => userdate($lasterrortime),
                    'error' => s($lasterror),
                ]),
                \core\output\notification::NOTIFY_WARNING,
                false
            );
        }

        return implode('', $lines);
    }
}
