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

/**
 * Tests for request filter parsing.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mailaudit\local\filters
 */
final class filters_test extends \advanced_testcase {
    /**
     * Reset request superglobals after each test.
     */
    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        $_REQUEST = [];
        parent::tearDown();
    }

    /**
     * A disabled date_selector submits an array; it must not crash and must yield no date.
     *
     * Regression: optional_param() throws "clean() can not process arrays" when handed the
     * date_selector array, so date_param() must read it with optional_param_array().
     */
    public function test_disabled_date_selector_array_is_safe(): void {
        $this->resetAfterTest();
        $_GET = $_POST = $_REQUEST = [
            'datefrom' => ['enabled' => 0, 'day' => 1, 'month' => 1, 'year' => 2026],
            'dateto' => ['enabled' => 0, 'day' => 1, 'month' => 1, 'year' => 2026],
            'subject' => 'no-such-subject-zzz',
        ];

        $filters = filters::from_request();

        $this->assertSame(0, $filters->datefrom);
        $this->assertSame(0, $filters->dateto);
        $this->assertSame('no-such-subject-zzz', $filters->subject);
    }

    /**
     * An enabled date_selector array is parsed into a timestamp.
     */
    public function test_enabled_date_selector_array_is_parsed(): void {
        $this->resetAfterTest();
        $_GET = $_POST = $_REQUEST = [
            'datefrom' => ['enabled' => 1, 'day' => 15, 'month' => 6, 'year' => 2026],
        ];

        $filters = filters::from_request();

        $this->assertSame((int) make_timestamp(2026, 6, 15, 0, 0, 0), $filters->datefrom);
    }

    /**
     * A scalar ISO date string is still accepted (e.g. from a stat-card drill-down link).
     */
    public function test_scalar_date_string_is_parsed(): void {
        $this->resetAfterTest();
        $_GET = $_POST = $_REQUEST = ['datefrom' => '2026-06-15'];

        $filters = filters::from_request();

        $this->assertSame((int) strtotime('2026-06-15 00:00:00'), $filters->datefrom);
    }
}
