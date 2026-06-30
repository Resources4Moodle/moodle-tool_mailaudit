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

namespace tool_mailaudit;

use tool_mailaudit\local\filters;
use tool_mailaudit\local\repository;

/**
 * Tests for the mail audit repository.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mailaudit\local\repository
 */
final class repository_test extends \advanced_testcase {
    /**
     * A bare filter set restricts to non-deleted records and binds no params.
     */
    public function test_where_sql_defaults(): void {
        [$where, $params] = repository::where_sql(new filters());
        $this->assertStringContainsString('deleted = 0', $where);
        $this->assertSame([], $params);
    }

    /**
     * Date, multi-kind and multi-status filters produce bound conditions.
     */
    public function test_where_sql_filters(): void {
        $filters = new filters();
        $filters->datefrom = 1000;
        $filters->dateto = 2000;
        $filters->kinds = ['password_reset', 'other'];
        $filters->statuses = ['sent'];

        [$where, $params] = repository::where_sql($filters);

        $this->assertStringContainsString('timecreated >=', $where);
        $this->assertStringContainsString('timecreated <=', $where);
        $this->assertContains(1000, $params);
        $this->assertContains(2000, $params);
        $this->assertContains('sent', $params);
        $this->assertContains('password_reset', $params);
    }

    /**
     * A negated "contains" search produces a NOT LIKE clause.
     */
    public function test_where_sql_negated_like(): void {
        $filters = new filters();
        $filters->subject = 'welcome';
        $filters->subjectnot = true;

        [$where, $params] = repository::where_sql($filters);

        $this->assertStringContainsString('NOT (', $where);
        $this->assertContains('%welcome%', $params);
    }

    /**
     * includedeleted drops the deleted = 0 restriction unless forced active.
     */
    public function test_where_sql_includedeleted(): void {
        $filters = new filters();
        $filters->includedeleted = true;

        [$where] = repository::where_sql($filters);
        $this->assertStringNotContainsString('deleted = 0', $where);

        [$whereforced] = repository::where_sql($filters, true);
        $this->assertStringContainsString('deleted = 0', $whereforced);
    }

    /**
     * Visibility predicate is appended when supplied.
     */
    public function test_where_sql_visibility(): void {
        [$where, $params] = repository::where_sql(
            new filters(),
            false,
            ['courseid = :v', ['v' => 42]]
        );
        $this->assertStringContainsString('courseid = :v', $where);
        $this->assertSame(42, $params['v']);
    }

    /**
     * course_options is scoped to the allowed course id list.
     */
    public function test_course_options_scoping(): void {
        $this->resetAfterTest();
        $c1 = $this->getDataGenerator()->create_course();
        $c2 = $this->getDataGenerator()->create_course();
        $this->insert_mail(['courseid' => $c1->id, 'coursefullname' => 'C1', 'courseshortname' => 'c1']);
        $this->insert_mail(['courseid' => $c2->id, 'coursefullname' => 'C2', 'courseshortname' => 'c2']);

        $this->assertCount(2, repository::course_options(null));
        $this->assertCount(1, repository::course_options([(int)$c1->id]));
        $this->assertSame([], repository::course_options([]));
    }

    /**
     * Stats overview returns the expected shape and peak-hour bucket range.
     */
    public function test_stats_overview(): void {
        $this->resetAfterTest();
        $this->insert_mail(['timecreated' => time()]);
        $stats = repository::stats_overview(new filters());

        $this->assertSame(1, $stats['total']);
        $this->assertArrayHasKey('table_size', $stats);
        if ($stats['peak_hour']) {
            $this->assertGreaterThanOrEqual(0, $stats['peak_hour']->hour);
            $this->assertLessThanOrEqual(23, $stats['peak_hour']->hour);
        }
    }

    /**
     * Insert a minimal mail audit row.
     *
     * @param array $overrides Field overrides.
     */
    private function insert_mail(array $overrides = []): void {
        global $DB;
        $DB->insert_record(repository::TABLE, (object)($overrides + [
            'timecreated' => time(),
            'timemodified' => time(),
            'status' => 'sent',
            'kind' => 'other',
            'bodybytes' => 0,
            'deleted' => 0,
        ]));
    }
}
