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
 * Plugin callbacks for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Record successful forgot-password direct email through Moodle's plugin callback.
 *
 * Moodle 5.2 exposes no event or Hook API seam for a successful password-reset send, so this single
 * callback is the only way to audit this sensitive mail without patching core. All other mail
 * capture is event-driven (see db/events.php and {@see \tool_mailaudit\local\observer}).
 *
 * @param \stdClass $data Submitted forgot password form data.
 */
function tool_mailaudit_post_forgot_password_requests(\stdClass $data): void {
    \tool_mailaudit\local\capture::record_forgot_password_request($data);
}

/**
 * Add a course-level sent email audit link for users with scoped visibility.
 *
 * @param navigation_node $navigation Course navigation node.
 * @param stdClass $course Course record.
 * @param context $context Course context.
 */
function tool_mailaudit_extend_navigation_course($navigation, $course, $context): void {
    if (!isloggedin() || isguestuser() || (int)$course->id === SITEID) {
        return;
    }

    $canview = has_capability(\tool_mailaudit\local\access::CAP_VIEWCOURSE, $context)
        || has_capability(\tool_mailaudit\local\access::CAP_VIEWOWN, $context)
        || has_capability(\tool_mailaudit\local\access::CAP_VIEWALL, \context_system::instance());
    if (!$canview) {
        return;
    }

    $node = navigation_node::create(
        get_string('coursemailaudit', 'tool_mailaudit'),
        new moodle_url('/admin/tool/mailaudit/index.php', ['courseid' => (int)$course->id]),
        navigation_node::TYPE_SETTING,
        null,
        'tool_mailaudit_course_' . (int)$course->id,
        new pix_icon('icon', '', 'tool_mailaudit'),
    );

    $reportnode = $navigation->get('coursereports');
    if ($reportnode) {
        $reportnode->add_node($node);
        return;
    }

    $navigation->add_node($node);
}
