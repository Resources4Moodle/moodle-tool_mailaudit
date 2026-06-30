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
 * Event observers for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\core\event\notification_sent',
        'callback' => '\tool_mailaudit\local\observer::notification_sent',
    ],
    [
        'eventname' => '\core\event\message_sent',
        'callback' => '\tool_mailaudit\local\observer::message_sent',
    ],
    [
        'eventname' => '\core\event\group_message_sent',
        'callback' => '\tool_mailaudit\local\observer::group_message_sent',
    ],
    [
        'eventname' => '\core\event\email_failed',
        'callback' => '\tool_mailaudit\local\observer::email_failed',
    ],
];
