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
 * Value object for sent-mail audit filters.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filters {
    /** Records-per-page sentinel for "show all". */
    public const SHOW_ALL_PERPAGE = 1000000;

    /** @var int */
    public $datefrom = 0;
    /** @var int */
    public $dateto = 0;
    /** @var string */
    public $kind = '';
    /** @var string[] */
    public $kinds = [];
    /** @var string */
    public $status = '';
    /** @var string[] */
    public $statuses = [];
    /** @var int */
    public $courseid = 0;
    /** @var int[] */
    public $courseids = [];
    /** @var bool */
    public $courseidsnot = false;
    /** @var int[] */
    public $fromuserids = [];
    /** @var bool */
    public $fromuseridsnot = false;
    /** @var int[] */
    public $touserids = [];
    /** @var bool */
    public $touseridsnot = false;
    /** @var string */
    public $sender = '';
    /** @var bool */
    public $sendernot = false;
    /** @var string */
    public $recipient = '';
    /** @var bool */
    public $recipientnot = false;
    /** @var string */
    public $subject = '';
    /** @var bool */
    public $subjectnot = false;
    /** @var string */
    public $body = '';
    /** @var bool */
    public $bodynot = false;
    /** @var bool */
    public $includedeleted = false;
    /** @var int */
    public $perpage = 50;
    /** @var string */
    public $sort = 'timecreated';
    /** @var string */
    public $dir = 'DESC';

    /**
     * Build filters from request params.
     *
     * @return self
     */
    public static function from_request(): self {
        $filter = new self();
        $filter->datefrom = self::date_param('datefrom');
        $filter->dateto = self::date_param('dateto', true);
        $filter->kind = optional_param('kind', '', PARAM_ALPHANUMEXT);
        $filter->kinds = self::string_array_param('kinds', PARAM_ALPHANUMEXT);
        if ($filter->kind !== '' && !in_array($filter->kind, $filter->kinds, true)) {
            $filter->kinds[] = $filter->kind;
        }
        $filter->status = optional_param('status', '', PARAM_ALPHA);
        $filter->statuses = self::string_array_param('statuses', PARAM_ALPHA);
        if ($filter->status !== '' && !in_array($filter->status, $filter->statuses, true)) {
            $filter->statuses[] = $filter->status;
        }
        $filter->courseid = max(0, optional_param('courseid', 0, PARAM_INT));
        $filter->courseids = self::int_array_param('courseids');
        if ($filter->courseid > 0 && !in_array($filter->courseid, $filter->courseids, true)) {
            $filter->courseids[] = $filter->courseid;
        }
        $filter->courseidsnot = optional_param('courseidsnot', 0, PARAM_BOOL) ? true : false;
        $filter->fromuserids = self::int_array_param('fromuserids');
        $filter->fromuseridsnot = optional_param('fromuseridsnot', 0, PARAM_BOOL) ? true : false;
        $filter->touserids = self::int_array_param('touserids');
        $filter->touseridsnot = optional_param('touseridsnot', 0, PARAM_BOOL) ? true : false;
        $filter->sender = trim(optional_param('sender', '', PARAM_TEXT));
        $filter->sendernot = optional_param('sendernot', 0, PARAM_BOOL) ? true : false;
        $filter->recipient = trim(optional_param('recipient', '', PARAM_TEXT));
        $filter->recipientnot = optional_param('recipientnot', 0, PARAM_BOOL) ? true : false;
        $filter->subject = trim(optional_param('subject', '', PARAM_TEXT));
        $filter->subjectnot = optional_param('subjectnot', 0, PARAM_BOOL) ? true : false;
        $filter->body = trim(optional_param('body', '', PARAM_TEXT));
        $filter->bodynot = optional_param('bodynot', 0, PARAM_BOOL) ? true : false;
        $filter->includedeleted = optional_param('includedeleted', 0, PARAM_BOOL) ? true : false;
        $perpage = optional_param('perpage', (int)get_config('tool_mailaudit', 'defaultperpage') ?: 50, PARAM_INT);
        $filter->perpage = $perpage >= self::SHOW_ALL_PERPAGE ? self::SHOW_ALL_PERPAGE : max(1, min(200, $perpage));
        $filter->sort = optional_param('tsort', optional_param('sort', 'timecreated', PARAM_ALPHANUMEXT), PARAM_ALPHANUMEXT);
        $filter->dir = strtoupper(optional_param('tdir', optional_param('dir', 'DESC', PARAM_ALPHA), PARAM_ALPHA)) === 'ASC'
            ? 'ASC' : 'DESC';

        return $filter;
    }

    /**
     * Convert filters to URL params.
     *
     * @return array
     */
    public function to_params(): array {
        $params = [
            'kind' => $this->kind,
            'kinds' => $this->kinds,
            'status' => $this->status,
            'statuses' => $this->statuses,
            'courseid' => $this->courseid,
            'courseids' => $this->courseids,
            'courseidsnot' => $this->courseidsnot ? 1 : 0,
            'fromuserids' => $this->fromuserids,
            'fromuseridsnot' => $this->fromuseridsnot ? 1 : 0,
            'touserids' => $this->touserids,
            'touseridsnot' => $this->touseridsnot ? 1 : 0,
            'sender' => $this->sender,
            'sendernot' => $this->sendernot ? 1 : 0,
            'recipient' => $this->recipient,
            'recipientnot' => $this->recipientnot ? 1 : 0,
            'subject' => $this->subject,
            'subjectnot' => $this->subjectnot ? 1 : 0,
            'body' => $this->body,
            'bodynot' => $this->bodynot ? 1 : 0,
            'includedeleted' => $this->includedeleted ? 1 : 0,
            'perpage' => $this->perpage,
            'tsort' => $this->sort,
            'tdir' => $this->dir,
        ];
        if ($this->datefrom) {
            $params['datefrom'] = $this->datefrom;
        }
        if ($this->dateto) {
            $params['dateto'] = $this->dateto;
        }
        return array_filter($params, static function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== '' && $value !== 0 && $value !== null;
        });
    }

    /**
     * Convert filters to audit-log-safe array.
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'datefrom' => $this->datefrom,
            'dateto' => $this->dateto,
            'kind' => $this->kind,
            'kinds' => $this->kinds,
            'status' => $this->status,
            'statuses' => $this->statuses,
            'courseid' => $this->courseid,
            'courseids' => $this->courseids,
            'courseidsnot' => $this->courseidsnot,
            'fromuserids' => $this->fromuserids,
            'fromuseridsnot' => $this->fromuseridsnot,
            'touserids' => $this->touserids,
            'touseridsnot' => $this->touseridsnot,
            'sender' => $this->sender,
            'sendernot' => $this->sendernot,
            'recipient' => $this->recipient,
            'recipientnot' => $this->recipientnot,
            'subject' => $this->subject,
            'subjectnot' => $this->subjectnot,
            'body' => $this->body,
            'bodynot' => $this->bodynot,
            'includedeleted' => $this->includedeleted,
            'perpage' => $this->perpage,
            'sort' => $this->sort,
            'dir' => $this->dir,
        ];
    }

    /**
     * Return an integer array request param.
     *
     * @param string $name Parameter name.
     * @return int[]
     */
    private static function int_array_param(string $name): array {
        return array_values(array_unique(array_filter(array_map(
            'intval',
            optional_param_array($name, [], PARAM_INT)
        ))));
    }

    /**
     * Return a string array request param.
     *
     * @param string $name Parameter name.
     * @param string $type Moodle PARAM_* type.
     * @return string[]
     */
    private static function string_array_param(string $name, string $type): array {
        return array_values(array_unique(array_filter(array_map(
            'trim',
            optional_param_array($name, [], $type)
        ))));
    }

    /**
     * Parse a YYYY-MM-DD date request value.
     *
     * @param string $name Parameter name.
     * @param bool $endofday Whether to return the end of the day.
     * @return int Unix timestamp, or zero if empty/invalid.
     */
    private static function date_param(string $name, bool $endofday = false): int {
        // The date_selector form element submits an array (enabled/year/month/day). When it is
        // present as an array it must be read with optional_param_array(); calling the scalar
        // optional_param() on an array value throws a coding exception.
        if (isset($_REQUEST[$name]) && is_array($_REQUEST[$name])) {
            $values = optional_param_array($name, [], PARAM_INT);
            if (empty($values['enabled'])) {
                return 0;
            }
            $time = make_timestamp(
                (int)($values['year'] ?? 1970),
                (int)($values['month'] ?? 1),
                (int)($values['day'] ?? 1),
                $endofday ? 23 : 0,
                $endofday ? 59 : 0,
                $endofday ? 59 : 0
            );
            return $time ? (int)$time : 0;
        }

        $value = trim(optional_param($name, '', PARAM_TEXT));
        if (is_numeric($value)) {
            return (int)$value;
        }
        if ($value === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return 0;
        }
        $time = strtotime($value . ($endofday ? ' 23:59:59' : ' 00:00:00'));
        return $time ? (int)$time : 0;
    }
}
