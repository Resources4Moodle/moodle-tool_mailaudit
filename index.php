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
 * Browse audited sent email.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/formslib.php');

use tool_mailaudit\form\filter_form;
use tool_mailaudit\local\access;
use tool_mailaudit\local\filters;
use tool_mailaudit\local\repository;
use tool_mailaudit\table\mail_table;

require_login();

$filters = filters::from_request();
if (optional_param('resetbutton', '', PARAM_RAW) !== '') {
    $resetparams = $filters->courseid ? ['courseid' => $filters->courseid] : [];
    redirect(new moodle_url('/admin/tool/mailaudit/index.php', $resetparams));
}

$context = \context_system::instance();
$filtercourse = null;
if ($filters->courseid) {
    $filtercourse = $DB->get_record('course', ['id' => $filters->courseid], '*', IGNORE_MISSING);
    if ($filtercourse) {
        $context = \context_course::instance($filters->courseid);
    }
}

access::require_view($filters->courseid);
$visibility = access::visibility_sql();

$page = optional_param('page', 0, PARAM_INT);
$baseurl = new moodle_url('/admin/tool/mailaudit/index.php', $filters->to_params());
$PAGE->set_url($baseurl);
$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('pluginname', 'tool_mailaudit'));
$PAGE->set_heading(get_string('pluginname', 'tool_mailaudit'));

$form = new filter_form(new moodle_url('/admin/tool/mailaudit/index.php'), [
    'kindoptions' => repository::kind_options(),
    'statusoptions' => repository::status_options(),
    'courseoptions' => repository::course_options(access::viewable_course_ids()),
], 'get');

$form->set_data([
    'datefrom' => $filters->datefrom,
    'dateto' => $filters->dateto,
    'kind' => $filters->kind,
    'kinds' => $filters->kinds,
    'status' => $filters->status,
    'statuses' => $filters->statuses,
    'courseid' => $filters->courseid,
    'courseids' => $filters->courseids,
    'courseidsnot' => $filters->courseidsnot,
    'fromuserids' => $filters->fromuserids,
    'fromuseridsnot' => $filters->fromuseridsnot,
    'touserids' => $filters->touserids,
    'touseridsnot' => $filters->touseridsnot,
    'sender' => $filters->sender,
    'sendernot' => $filters->sendernot,
    'recipient' => $filters->recipient,
    'recipientnot' => $filters->recipientnot,
    'subject' => $filters->subject,
    'subjectnot' => $filters->subjectnot,
    'body' => $filters->body,
    'bodynot' => $filters->bodynot,
    'includedeleted' => $filters->includedeleted,
    'perpage' => $filters->perpage,
]);

$stats = repository::stats_overview($filters, $visibility);
$total = $stats['total'];
access::log('list_viewed', null, $filters->to_array());

$chips = tool_mailaudit_filter_chips($filters);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'tool_mailaudit'));
if ($filtercourse) {
    echo $OUTPUT->heading(format_string($filtercourse->fullname, true, ['context' => $context]), 3);
}

// Filter panel, collapsed by default unless filters are active or there is nothing to show.
echo tool_mailaudit_render_filters($form, $chips, $baseurl, $total === 0);

echo tool_mailaudit_render_stats($stats, $filters);

if (!$total) {
    echo $OUTPUT->notification(get_string('nomessages', 'tool_mailaudit'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

// Results toolbar: count + CSV export.
$exporturl = new moodle_url('/admin/tool/mailaudit/export.php', $filters->to_params());
echo html_writer::div(
    html_writer::span(get_string('matchingcount', 'tool_mailaudit', $total), 'fw-bold') . ' ' .
    html_writer::link(
        $exporturl,
        get_string('exportcsv', 'tool_mailaudit'),
        ['class' => 'btn btn-outline-secondary btn-sm ml-2']
    ),
    'd-flex align-items-center justify-content-between mb-2'
);

$bulk = access::can_delete();
if ($filters->perpage >= filters::SHOW_ALL_PERPAGE) {
    echo $OUTPUT->notification(
        get_string('showallwarning', 'tool_mailaudit', $total),
        \core\output\notification::NOTIFY_WARNING
    );
}
if ($bulk) {
    $PAGE->requires->js_call_amd('tool_mailaudit/selection', 'init', [(int)$total]);
    echo html_writer::start_tag('form', [
        'id' => 'tool-mailaudit-bulkform',
        'method' => 'post',
        'action' => new moodle_url('/admin/tool/mailaudit/bulk.php', $filters->to_params()),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::empty_tag(
        'input',
        ['type' => 'hidden', 'name' => 'selectallmatching', 'value' => '0', 'id' => 'tool-mailaudit-matching']
    );
    echo tool_mailaudit_render_bulk_actions('top', $total);
}

$table = new mail_table('tool-mailaudit-mailbox', $baseurl, $filters, $visibility, $bulk);
$table->out($filters->perpage >= filters::SHOW_ALL_PERPAGE ? max(1, $total) : $filters->perpage, true);

if ($bulk) {
    echo tool_mailaudit_render_bulk_actions('bottom', $total);
    echo html_writer::end_tag('form');

    echo tool_mailaudit_render_matching_actions($filters, $total);
}

echo $OUTPUT->footer();

/**
 * Render the collapsible filter panel with active-filter chips.
 *
 * @param filter_form $form Filter form.
 * @param string $chips Pre-rendered chip HTML.
 * @param moodle_url $baseurl Current URL for the reset link.
 * @param bool $forceopen Whether to force the panel open.
 * @return string
 */
function tool_mailaudit_render_filters(filter_form $form, string $chips, moodle_url $baseurl, bool $forceopen): string {
    ob_start();
    $form->display();
    $formhtml = ob_get_clean();

    $open = ($chips !== '' || $forceopen) ? ['open' => 'open'] : [];
    $summary = html_writer::tag(
        'summary',
        html_writer::span(get_string('filters', 'tool_mailaudit'), 'fw-bold') .
        ($chips !== '' ? ' ' . html_writer::span(
            get_string('filtersactive', 'tool_mailaudit'),
            'badge bg-primary ml-2'
        ) : ''),
        ['class' => 'tool-mailaudit-filters-summary']
    );

    $body = ($chips !== '' ? html_writer::div($chips, 'tool-mailaudit-chips mb-2') : '') . $formhtml;

    return html_writer::tag(
        'details',
        $summary . html_writer::div($body, 'p-2'),
        ['class' => 'tool-mailaudit-filters border rounded p-2 mb-3 bg-light'] + $open
    );
}

/**
 * Build active-filter chips from the current filters.
 *
 * @param filters $filters Current filters.
 * @return string Chip HTML, or empty string when no filters are active.
 */
function tool_mailaudit_filter_chips(filters $filters): string {
    $chips = [];
    $add = function (string $label, string $value) use (&$chips): void {
        $chips[] = html_writer::span(
            html_writer::span(s($label) . ': ', 'text-muted') . s($value),
            'badge bg-secondary me-1 mr-1 mb-1 p-2'
        );
    };

    if ($filters->datefrom) {
        $add(get_string('datefrom', 'tool_mailaudit'), userdate($filters->datefrom, get_string('strftimedate')));
    }
    if ($filters->dateto) {
        $add(get_string('dateto', 'tool_mailaudit'), userdate($filters->dateto, get_string('strftimedate')));
    }
    foreach ($filters->kinds as $kind) {
        $add(get_string('kind', 'tool_mailaudit'), repository::kind_label($kind));
    }
    foreach ($filters->statuses as $status) {
        $add(get_string('status', 'tool_mailaudit'), $status);
    }
    $not = get_string('notprefix', 'tool_mailaudit');
    foreach (
        [
        ['sender', 'sendercontains', $filters->sender, $filters->sendernot],
        ['recipient', 'recipientcontains', $filters->recipient, $filters->recipientnot],
        ['subject', 'subjectcontains', $filters->subject, $filters->subjectnot],
        ['body', 'bodycontains', $filters->body, $filters->bodynot],
        ] as [$key, $stringid, $value, $negate]
    ) {
        if ($value !== '') {
            $add(get_string($stringid, 'tool_mailaudit'), ($negate ? $not . ' ' : '') . $value);
        }
    }
    if ($filters->fromuserids) {
        $add(get_string('sentby', 'tool_mailaudit'), count($filters->fromuserids) . '×');
    }
    if ($filters->touserids) {
        $add(get_string('sentto', 'tool_mailaudit'), count($filters->touserids) . '×');
    }
    if ($filters->courseids) {
        $add(get_string('course', 'tool_mailaudit'), count($filters->courseids) . '×');
    }
    if ($filters->includedeleted) {
        $add(get_string('includedeleted', 'tool_mailaudit'), get_string('yes'));
    }

    if (!$chips) {
        return '';
    }
    $clear = html_writer::link(
        new moodle_url(
            '/admin/tool/mailaudit/index.php',
            $filters->courseid ? ['courseid' => $filters->courseid] : []
        ),
        get_string('clearfilters', 'tool_mailaudit'),
        ['class' => 'btn btn-link btn-sm']
    );

    return implode('', $chips) . $clear;
}

/**
 * Render summary statistics as cards. Cards that map to a filter are clickable and
 * drill the current view down to that dimension (today, month, top sender/recipient/course).
 *
 * @param array $stats Stats from repository.
 * @param filters $filters Current filters (preserved when drilling down).
 * @return string
 */
function tool_mailaudit_render_stats(array $stats, filters $filters): string {
    $base = $filters->to_params();
    $drill = function (array $override) use ($base): moodle_url {
        return new moodle_url('/admin/tool/mailaudit/index.php', array_merge($base, $override));
    };

    $now = time();
    $today = usergetmidnight($now);
    $monthstart = make_timestamp((int)date('Y', $now), (int)date('n', $now), 1);

    // Each item: [label, value-html, drill-down url or null].
    $items = [
        [get_string('stat_total', 'tool_mailaudit'), (string)$stats['total'], null],
        [get_string('stat_today', 'tool_mailaudit'), (string)$stats['today'],
            $drill(['datefrom' => $today, 'dateto' => 0])],
        [get_string('stat_month', 'tool_mailaudit'), (string)$stats['month'],
            $drill(['datefrom' => $monthstart, 'dateto' => 0])],
        [get_string('stat_tablesize', 'tool_mailaudit'), $stats['table_size']['label'], null],
    ];
    if (!empty($stats['peak_hour'])) {
        $items[] = [get_string('stat_peakhour', 'tool_mailaudit'),
            get_string('stat_peakhour_value', 'tool_mailaudit', $stats['peak_hour']), null];
    }
    foreach (
        [
        'top_sender' => ['stat_topsender', 'fromuserids'],
        'top_recipient' => ['stat_toprecipient', 'touserids'],
        'top_course' => ['stat_topcourse', 'courseids'],
        ] as $key => [$string, $param]
    ) {
        if (empty($stats[$key])) {
            continue;
        }
        $label = trim((string)$stats[$key]->label) !== '' ? $stats[$key]->label : get_string('unknown', 'tool_mailaudit');
        $value = s($label) . ' (' . (int)$stats[$key]->mailcount . ')';
        $groupid = (int)($stats[$key]->groupid ?? 0);
        $url = $groupid > 0 ? $drill([$param => [$groupid]]) : null;
        $items[] = [get_string($string, 'tool_mailaudit'), $value, $url];
    }

    $cells = [];
    foreach ($items as [$label, $value, $url]) {
        $inner = html_writer::tag('div', s($label), ['class' => 'text-muted small']) .
            html_writer::tag('div', $value, ['class' => 'h5 mb-0']);
        if ($url instanceof moodle_url) {
            $cells[] = html_writer::link($url, $inner, [
                'class' => 'border rounded p-2 d-block text-reset text-decoration-none tool-mailaudit-statcard',
                'title' => get_string('statcardfilter', 'tool_mailaudit'),
            ]);
        } else {
            $cells[] = html_writer::div($inner, 'border rounded p-2 bg-light');
        }
    }

    return html_writer::div(
        implode('', $cells),
        'd-grid gap-2 mb-3',
        ['style' => 'grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));']
    );
}

/**
 * Render bulk action controls for the selected rows.
 *
 * @param string $position top|bottom.
 * @param int $total Total matching records.
 * @return string
 */
function tool_mailaudit_render_bulk_actions(string $position, int $total): string {
    $selectbuttons = html_writer::tag('button', get_string('selectallpage', 'tool_mailaudit'), [
        'type' => 'button',
        'class' => 'btn btn-link btn-sm',
        'data-mailaudit-select' => 'all',
    ]) . html_writer::tag('button', get_string('selectnonepage', 'tool_mailaudit'), [
        'type' => 'button',
        'class' => 'btn btn-link btn-sm',
        'data-mailaudit-select' => 'none',
    ]);
    $count = html_writer::span(
        get_string('selectedcount', 'tool_mailaudit', html_writer::tag('span', '0', ['data-mailaudit-count' => 1])),
        'badge bg-info text-dark mr-2 me-2'
    );
    $select = html_writer::select([
        'delete' => get_string('softdelete_selected', 'tool_mailaudit'),
        'purge' => get_string('purge_selected', 'tool_mailaudit'),
    ], 'bulkaction', '', ['' => get_string('choose')], ['class' => 'custom-select mr-2 me-2']);
    $button = html_writer::empty_tag('input', [
        'type' => 'submit',
        'class' => 'btn btn-secondary',
        'value' => get_string('apply'),
        'data-mailaudit-apply' => 1,
        'disabled' => 'disabled',
    ]);

    $matching = '';
    if ($position === 'top') {
        $matching = html_writer::div(
            html_writer::tag('button', get_string('selectallmatching', 'tool_mailaudit', $total), [
                'type' => 'button',
                'class' => 'btn btn-link btn-sm',
                'data-mailaudit-selectmatching' => 1,
            ]) .
            html_writer::div(
                get_string('matchingselected', 'tool_mailaudit', $total) . ' ' .
                html_writer::tag('button', get_string('clearselection', 'tool_mailaudit'), [
                    'type' => 'button',
                    'class' => 'btn btn-link btn-sm p-0',
                    'data-mailaudit-clearmatching' => 1,
                ]),
                'alert alert-warning py-1 px-2 mt-1 mb-0 d-none',
                ['id' => 'tool-mailaudit-matchingbanner']
            ),
            'mt-1'
        );
    }

    $stickyclass = $position === 'bottom' ? ' tool-mailaudit-sticky d-none' : '';
    $idattr = $position === 'bottom' ? ['id' => 'tool-mailaudit-sticky'] : [];
    return html_writer::div(
        html_writer::div($count . $selectbuttons . ' ' . $select . $button, 'd-flex align-items-center flex-wrap') . $matching,
        'mb-2 tool-mailaudit-bulk tool-mailaudit-bulk-' . $position . $stickyclass,
        $idattr
    );
}

/**
 * Render the separated, clearly-labelled whole-result-set destructive actions.
 *
 * @param filters $filters Current filters.
 * @param int $total Total matching records.
 * @return string
 */
function tool_mailaudit_render_matching_actions(filters $filters, int $total): string {
    global $OUTPUT;

    $deleteurl = new moodle_url('/admin/tool/mailaudit/delete.php', $filters->to_params());
    $purgeurl = new moodle_url('/admin/tool/mailaudit/purge.php', $filters->to_params() + ['includedeleted' => 1]);

    $body = html_writer::tag('p', get_string('matchingactions_desc', 'tool_mailaudit', $total), ['class' => 'small mb-2']) .
        html_writer::div(
            $OUTPUT->single_button($deleteurl, get_string('softdeletematching', 'tool_mailaudit'), 'get') . ' ' .
            $OUTPUT->single_button($purgeurl, get_string('purgematching', 'tool_mailaudit'), 'get'),
            'd-flex gap-2'
        );

    return html_writer::div(
        html_writer::tag('h5', get_string('matchingactions', 'tool_mailaudit'), ['class' => 'text-danger']) . $body,
        'tool-mailaudit-dangerzone border border-danger rounded p-3 mt-4'
    );
}
