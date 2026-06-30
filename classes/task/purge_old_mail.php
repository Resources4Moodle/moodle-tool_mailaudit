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

namespace tool_mailaudit\task;

use tool_mailaudit\local\repository;

/**
 * Permanently purges old sent-mail audit records.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class purge_old_mail extends \core\task\scheduled_task {
    /**
     * Return task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('taskpurgeoldmail', 'tool_mailaudit');
    }

    /**
     * Execute scheduled purge.
     */
    public function execute(): void {
        $days = (int)get_config('tool_mailaudit', 'autopurgedeleteddays');
        if ($days <= 0) {
            $days = (int)get_config('tool_mailaudit', 'retentiondays');
        }

        if ($days > 0) {
            $cutoff = time() - ($days * DAYSECS);
            $deleted = repository::purge_before($cutoff);
            mtrace('Purged ' . $deleted . ' deleted sent email audit record(s) older than ' . $days . ' day(s).');
        } else {
            mtrace('Mail audit deleted-record retention purge is disabled.');
        }

        $activedays = (int)get_config('tool_mailaudit', 'autopurgeactivedays');
        if ($activedays > 0) {
            $cutoff = time() - ($activedays * DAYSECS);
            $deleted = repository::purge_before($cutoff, true);
            mtrace('Purged ' . $deleted . ' active sent email audit record(s) older than ' . $activedays . ' day(s).');
        }
    }
}
