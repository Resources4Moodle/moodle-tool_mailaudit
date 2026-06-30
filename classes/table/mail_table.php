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

namespace tool_mailaudit\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use tool_mailaudit\local\filters;
use tool_mailaudit\local\repository;

/**
 * Sortable mailbox-style table for sent-mail audit rows.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mail_table extends \table_sql {
    /** @var filters */
    private $filters;

    /** @var bool */
    private $bulkselect;

    /**
     * Constructor.
     *
     * @param string $uniqueid Table id.
     * @param \moodle_url $baseurl Base URL.
     * @param filters $filters Current filters.
     * @param array|null $visibility Optional visibility predicate.
     * @param bool $bulkselect Whether to render checkboxes.
     */
    public function __construct(
        string $uniqueid,
        \moodle_url $baseurl,
        filters $filters,
        ?array $visibility,
        bool $bulkselect
    ) {
        parent::__construct($uniqueid);
        $this->filters = $filters;
        $this->bulkselect = $bulkselect;

        $columns = ['timecreated', 'coursefullname', 'kind', 'status', 'fromfirstname', 'tofirstname', 'subject', 'bodybytes'];
        $headers = [
            get_string('senttime', 'tool_mailaudit'),
            get_string('course', 'tool_mailaudit'),
            get_string('kind', 'tool_mailaudit'),
            get_string('status', 'tool_mailaudit'),
            get_string('sender', 'tool_mailaudit'),
            get_string('recipient', 'tool_mailaudit'),
            get_string('subject', 'tool_mailaudit'),
            get_string('size'),
        ];

        if ($bulkselect) {
            array_unshift($columns, 'select');
            array_unshift($headers, \html_writer::checkbox(
                'mailaudit_select_all_rows',
                1,
                false,
                '',
                [
                    'id' => 'tool-mailaudit-select-page',
                    'aria-label' => get_string('selectallpage', 'tool_mailaudit'),
                ]
            ));
        }

        $this->define_columns($columns);
        $this->define_headers($headers);
        $this->define_baseurl($baseurl);
        $this->sortable(true, $filters->sort, strtolower($filters->dir) === 'asc' ? SORT_ASC : SORT_DESC);
        $this->collapsible(false);
        $this->pageable(true);
        $this->is_downloadable(false);
        $this->set_attribute('class', 'generaltable table-sm tool-mailaudit-mailbox');

        if ($bulkselect) {
            $this->no_sorting('select');
        }

        [$fields, $from, $where, $params] = repository::table_sql($filters, $visibility);
        $this->set_sql($fields, $from, $where, $params);
    }

    /**
     * Checkbox column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_select(\stdClass $record): string {
        if (!$this->bulkselect) {
            return '';
        }
        return \html_writer::checkbox(
            'mailids[]',
            (int)$record->id,
            false,
            '',
            [
                'class' => 'tool-mailaudit-rowselect',
                'aria-label' => get_string('selectmail', 'tool_mailaudit', (int)$record->id),
            ]
        );
    }

    /**
     * Sent time column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_timecreated(\stdClass $record): string {
        return userdate((int)$record->timecreated);
    }

    /**
     * Course column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_coursefullname(\stdClass $record): string {
        if (!empty($record->coursefullname)) {
            return s($record->coursefullname);
        }
        if (!empty($record->courseshortname)) {
            return s($record->courseshortname);
        }
        return get_string('site');
    }

    /**
     * Kind column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_kind(\stdClass $record): string {
        return \html_writer::span(repository::kind_label((string)$record->kind), 'badge bg-info text-dark');
    }

    /**
     * Status column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_status(\stdClass $record): string {
        $status = \html_writer::span(s($record->status), 'badge bg-secondary');
        if (!empty($record->deleted)) {
            $status .= ' ' . \html_writer::span(get_string('deleted', 'tool_mailaudit'), 'badge bg-dark');
        }
        return $status;
    }

    /**
     * Sender column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_fromfirstname(\stdClass $record): string {
        return s($this->person_label($record, 'from'));
    }

    /**
     * Recipient column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_tofirstname(\stdClass $record): string {
        return s($this->person_label($record, 'to'));
    }

    /**
     * Subject column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_subject(\stdClass $record): string {
        $subject = trim((string)($record->subject ?? ''));
        if ($subject === '') {
            $subject = '[' . get_string('subject', 'tool_mailaudit') . ']';
        }
        return \html_writer::link(
            new \moodle_url('/admin/tool/mailaudit/view.php', ['id' => (int)$record->id]),
            s($subject)
        );
    }

    /**
     * Size column.
     *
     * @param \stdClass $record Mail row.
     * @return string
     */
    public function col_bodybytes(\stdClass $record): string {
        return display_size((int)($record->bodybytes ?? 0));
    }

    /**
     * Build a display label for a sender/recipient.
     *
     * @param \stdClass $record Mail row.
     * @param string $prefix from|to.
     * @return string
     */
    private function person_label(\stdClass $record, string $prefix): string {
        $name = trim((string)($record->{$prefix . 'firstname'} ?? '') . ' ' .
            (string)($record->{$prefix . 'lastname'} ?? ''));
        if ($name === '') {
            $name = trim((string)($record->{$prefix . 'name'} ?? ''));
        }
        $email = trim((string)($record->{$prefix . 'email'} ?? ''));
        $username = trim((string)($record->{$prefix . 'username'} ?? ''));
        return trim($name . ($username !== '' ? ' ' . $username : '') . ($email !== '' ? ' ' . $email : ''));
    }
}
