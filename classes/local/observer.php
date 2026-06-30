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
 * Moodle event observers for plugin-owned mail capture.
 *
 * All message-API capture is event-driven (no lib.php message callback): the observers read the
 * persisted core notifications/messages row for content. See {@see capture} for the channel
 * limitations imposed by Moodle 5.2.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Capture a dispatched notification.
     *
     * @param \core\event\notification_sent $event Notification event.
     */
    public static function notification_sent(\core\event\notification_sent $event): void {
        capture::capture_notification((int)$event->objectid, (int)($event->other['courseid'] ?? 0));
    }

    /**
     * Capture a dispatched private (1:1) message.
     *
     * @param \core\event\message_sent $event Message event.
     */
    public static function message_sent(\core\event\message_sent $event): void {
        capture::capture_message(
            (int)$event->objectid,
            (int)$event->relateduserid,
            (int)($event->other['courseid'] ?? 0)
        );
    }

    /**
     * Capture a dispatched group-conversation message.
     *
     * @param \core\event\group_message_sent $event Group message event.
     */
    public static function group_message_sent(\core\event\group_message_sent $event): void {
        capture::capture_group_message(
            (int)$event->objectid,
            (int)($event->other['conversationid'] ?? 0),
            (int)($event->other['courseid'] ?? 0)
        );
    }

    /**
     * Capture or mark a failed direct email attempt.
     *
     * @param \core\event\email_failed $event Email failed event.
     */
    public static function email_failed(\core\event\email_failed $event): void {
        capture::record_email_failed_event($event);
    }
}
