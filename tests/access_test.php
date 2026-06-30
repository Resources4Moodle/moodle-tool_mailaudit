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

use tool_mailaudit\local\access;

/**
 * Tests for mail audit access control.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mailaudit\local\access
 */
final class access_test extends \advanced_testcase {
    /**
     * A site admin sees everything: predicate is a tautology and course scope is unbounded.
     */
    public function test_admin_sees_everything(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        [$sql, $params] = access::visibility_sql();
        $this->assertSame('1 = 1', $sql);
        $this->assertSame([], $params);
        $this->assertNull(access::viewable_course_ids());
        $this->assertTrue(access::can_view_all());
    }

    /**
     * A user with no capability sees nothing.
     */
    public function test_no_capability_sees_nothing(): void {
        $this->resetAfterTest();
        $this->setUser($this->getDataGenerator()->create_user());

        [$sql] = access::visibility_sql();
        $this->assertSame('0 = 1', $sql);
        $this->assertSame([], access::viewable_course_ids());
    }

    /**
     * A course teacher is scoped to their course and to non-sensitive kinds only.
     */
    public function test_teacher_course_scope(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $this->assertSame([(int)$course->id], access::viewable_course_ids());

        [$sql, $params] = access::visibility_sql();
        $this->assertStringContainsString('courseid', $sql);
        $this->assertStringContainsString('kind', $sql);
        $this->assertContains((int)$course->id, $params);
        // No sensitive kind may appear in the bound visible-kind params.
        foreach (access::ADMIN_ONLY_KINDS as $sensitive) {
            $this->assertNotContains($sensitive, $params);
        }
    }

    /**
     * Sensitive kinds are always excluded from the non-admin visible set.
     */
    public function test_non_admin_visible_kinds_excludes_sensitive(): void {
        $this->resetAfterTest();
        $visible = access::non_admin_visible_kinds();
        foreach (access::ADMIN_ONLY_KINDS as $sensitive) {
            $this->assertNotContains($sensitive, $visible);
        }
    }

    /**
     * A teacher cannot open a sensitive record even within their course.
     */
    public function test_teacher_blocked_from_sensitive_record(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);

        $record = (object)['id' => 1, 'kind' => 'password_reset', 'courseid' => $course->id, 'fromuserid' => 0];
        $this->expectException(\required_capability_exception::class);
        access::require_view_record($record);
    }
}
