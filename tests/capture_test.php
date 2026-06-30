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

use ReflectionMethod;
use tool_mailaudit\local\capture;

/**
 * Tests for mail kind classification.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \tool_mailaudit\local\capture
 */
final class capture_test extends \advanced_testcase {
    /**
     * Exercise the private kind classifier through reflection.
     *
     * @dataProvider kind_provider
     * @param string $component Component.
     * @param string $messagename Message provider name.
     * @param string $subject Subject.
     * @param string $expected Expected kind.
     */
    public function test_infer_kind(string $component, string $messagename, string $subject, string $expected): void {
        $method = new ReflectionMethod(capture::class, 'infer_kind');
        $method->setAccessible(true);
        $this->assertSame($expected, $method->invoke(null, $component, $messagename, $subject, [], []));
    }

    /**
     * Classification cases.
     *
     * @return array
     */
    public static function kind_provider(): array {
        return [
            'new login by message name' => ['core', 'newlogin', 'Hello', 'new_ip_login'],
            'new sign in by subject' => ['core', '', 'New sign in to your account', 'new_ip_login'],
            'account locked' => ['core', '', 'Your account is locked out', 'account_security'],
            'risk insight' => ['core', 'insights', 'Students at risk', 'student_risk_alert'],
            'enrol welcome' => ['enrol_manual', 'enrolcoursewelcomemessage', 'Welcome', 'course_registration'],
            'quickmail' => ['block_quickmail', '', 'Notice', 'course_bulk_mail'],
            'message digest' => ['message_email', '', 'Digest', 'message_digest'],
            'password reset' => ['core', '', 'Password reset request', 'password_reset'],
            'fallback other' => ['mod_widget', 'thing', 'Something', 'other'],
        ];
    }

    /**
     * Insert a fake core notifications row.
     *
     * @param int $fromid Sender id.
     * @param int $toid Recipient id.
     * @param array $overrides Field overrides.
     * @return int Notification id.
     */
    private function make_notification(int $fromid, int $toid, array $overrides = []): int {
        global $DB;

        $record = (object) array_merge([
            'useridfrom' => $fromid,
            'useridto' => $toid,
            'subject' => 'Welcome to the course',
            'fullmessage' => 'Plain body',
            'fullmessageformat' => FORMAT_PLAIN,
            'fullmessagehtml' => '<p>HTML body</p>',
            'smallmessage' => 'small',
            'component' => 'enrol_manual',
            'eventtype' => 'enrolcoursewelcomemessage',
            'contexturl' => '',
            'contexturlname' => '',
            'timecreated' => time(),
            'customdata' => null,
        ], $overrides);

        return (int)$DB->insert_record('notifications', $record);
    }

    /**
     * A dispatched notification is captured into the audit table from the event payload.
     *
     * @covers \tool_mailaudit\local\capture::capture_notification
     */
    public function test_capture_notification_records_row(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('storebodytext', 1, 'tool_mailaudit');
        set_config('storebodyhtml', 1, 'tool_mailaudit');

        $from = $this->getDataGenerator()->create_user();
        $to = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $notificationid = $this->make_notification($from->id, $to->id, ['customdata' => json_encode(['courseid' => $course->id])]);

        capture::capture_notification($notificationid, 0);

        $rows = $DB->get_records(\tool_mailaudit\local\repository::TABLE);
        $this->assertCount(1, $rows);
        $row = reset($rows);
        $this->assertSame('sent', $row->status);
        $this->assertSame('course_registration', $row->kind);
        $this->assertEquals($from->id, $row->fromuserid);
        $this->assertEquals($to->id, $row->touserid);
        $this->assertEquals($course->id, $row->courseid);
        $this->assertSame('Welcome to the course', $row->subject);
        $this->assertSame('Plain body', $row->bodytext);
        $this->assertStringContainsString('HTML body', $row->bodyhtml);
        $this->assertSame('notifications', $row->moodlemessagetable);
        $this->assertEquals($notificationid, $row->moodlemessageid);
    }

    /**
     * Capturing the same notification twice is idempotent.
     *
     * @covers \tool_mailaudit\local\capture::capture_notification
     */
    public function test_capture_notification_is_idempotent(): void {
        global $DB;

        $this->resetAfterTest();
        $from = $this->getDataGenerator()->create_user();
        $to = $this->getDataGenerator()->create_user();
        $notificationid = $this->make_notification($from->id, $to->id);

        capture::capture_notification($notificationid, 0);
        capture::capture_notification($notificationid, 0);

        $this->assertEquals(1, $DB->count_records(\tool_mailaudit\local\repository::TABLE));
    }

    /**
     * Body storage toggles keep bodies out of the audit table when disabled.
     *
     * @covers \tool_mailaudit\local\capture::capture_notification
     */
    public function test_body_storage_disabled_by_default(): void {
        global $DB;

        $this->resetAfterTest();
        // Defaults: body storage off (config unset behaves as disabled).
        $from = $this->getDataGenerator()->create_user();
        $to = $this->getDataGenerator()->create_user();
        $notificationid = $this->make_notification($from->id, $to->id);

        capture::capture_notification($notificationid, 0);

        $row = $DB->get_record(\tool_mailaudit\local\repository::TABLE, ['moodlemessageid' => $notificationid]);
        $this->assertSame('', $row->bodytext);
        $this->assertSame('', $row->bodyhtml);
        // Size still reflects the captured payload even when bodies are not stored.
        $this->assertGreaterThan(0, (int)$row->bodybytes);
    }

    /**
     * Disabling capture entirely records nothing.
     *
     * @covers \tool_mailaudit\local\capture::capture_notification
     */
    public function test_capture_disabled_records_nothing(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('enabled', 0, 'tool_mailaudit');
        $from = $this->getDataGenerator()->create_user();
        $to = $this->getDataGenerator()->create_user();
        $notificationid = $this->make_notification($from->id, $to->id);

        capture::capture_notification($notificationid, 0);

        $this->assertEquals(0, $DB->count_records(\tool_mailaudit\local\repository::TABLE));
    }

    /**
     * The capturekinds allowlist suppresses unselected kinds.
     *
     * @covers \tool_mailaudit\local\capture::capture_notification
     */
    public function test_capturekinds_allowlist_filters(): void {
        global $DB;

        $this->resetAfterTest();
        // Only allow password_reset; an enrol welcome (course_registration) must be skipped.
        set_config('capturekinds', 'password_reset', 'tool_mailaudit');
        $from = $this->getDataGenerator()->create_user();
        $to = $this->getDataGenerator()->create_user();
        $notificationid = $this->make_notification($from->id, $to->id);

        capture::capture_notification($notificationid, 0);

        $this->assertEquals(0, $DB->count_records(\tool_mailaudit\local\repository::TABLE));
    }

    /**
     * Password-reset capture redacts the token and never stores it.
     *
     * @covers \tool_mailaudit\local\capture::record_forgot_password_request
     */
    public function test_forgot_password_capture_redacts_token(): void {
        global $DB;

        $this->resetAfterTest();
        set_config('storebodytext', 1, 'tool_mailaudit');

        $user = $this->getDataGenerator()->create_user(['confirmed' => 1, 'auth' => 'manual']);
        $secrettoken = 'SUPERSECRETTOKEN1234567890';
        $DB->insert_record('user_password_resets', (object) [
            'userid' => $user->id,
            'timerequested' => time(),
            'timererequested' => time(),
            'token' => $secrettoken,
        ]);

        capture::record_forgot_password_request((object) ['username' => $user->username]);

        $row = $DB->get_record(\tool_mailaudit\local\repository::TABLE, ['kind' => 'password_reset']);
        $this->assertNotEmpty($row);
        $this->assertEquals($user->id, $row->touserid);
        $haystack = (string)$row->bodytext . (string)$row->subject . (string)$row->metadata . (string)$row->messageid;
        $this->assertStringNotContainsString($secrettoken, $haystack);
        $this->assertStringContainsString('tokenredacted', (string)$row->metadata);
    }
}
