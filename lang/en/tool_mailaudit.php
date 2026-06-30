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
 * English strings for the mail audit tool.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allkinds'] = 'All kinds';
$string['allstatuses'] = 'All statuses';
$string['attachments'] = 'Attachments';
$string['autopurgeactivedays'] = 'Purge active records after days';
$string['autopurgeactivedays_desc'] = 'Optional high-risk cleanup for records that were never soft-deleted. Keep this as 0 unless there is a formal retention policy.';
$string['autopurgedeleteddays'] = 'Purge deleted records after days';
$string['autopurgedeleteddays_desc'] = 'Scheduled cleanup permanently removes records that were already deleted more than this many days ago. Set to 0 to disable.';
$string['backtolist'] = 'Back to sent email audit';
$string['bodycontains'] = 'Body contains';
$string['browsemail'] = 'Browse sent email';
$string['bulkselected'] = 'Bulk action on selected email';
$string['capturehealth'] = 'Capture health';
$string['capturehealth_error'] = 'The last capture attempt failed {$a->time}: {$a->error}';
$string['capturehealth_none'] = 'No mail has been captured yet.';
$string['capturehealth_ok'] = 'Last mail captured {$a}.';
$string['capturekinds'] = 'Capture mail kinds';
$string['capturekinds_desc'] = 'Only selected mail kinds are stored in the audit database. Failed direct mail may still be recorded when Moodle emits a failure event.';
$string['clearfilters'] = 'Clear all filters';
$string['clearselection'] = 'Clear selection';
$string['component'] = 'Component';
$string['confirmbulkdelete'] = 'Delete {$a} selected active sent email audit record(s)? The records will be hidden from normal views but can still be purged later by an administrator.';
$string['confirmbulkpurge'] = 'Permanently purge {$a} selected already-deleted sent email audit record(s) from the database? Active rows in the selection will be ignored.';
$string['confirmdelete'] = 'Delete {$a} matching active sent email audit record(s)? The records will be hidden from normal views but can still be purged later by an administrator.';
$string['confirmpurge'] = 'Permanently purge {$a} already-deleted sent email audit record(s) from the database? This cannot be undone.';
$string['course'] = 'Course';
$string['coursemailaudit'] = 'Sent email audit';
$string['datefrom'] = 'From date';
$string['dateto'] = 'To date';
$string['defaultperpage'] = 'Default records per page';
$string['defaultperpage_desc'] = 'Number of audited messages shown per page by default.';
$string['deleted'] = 'Deleted';
$string['deletedcount'] = 'Deleted {$a} matching sent email audit record(s).';
$string['deletematching'] = 'Permanently delete matching records';
$string['enabled'] = 'Capture sent email';
$string['enabled_desc'] = 'When enabled, notifications and messages Moodle dispatches are copied into the audit table from core messaging events, and failed direct email is captured from the email_failed event. Password-reset mail is captured from the forgot-password callback.';
$string['eventaccesslogged'] = 'Sent email audit access logged';
$string['eventmaildeleted'] = 'Sent email audit records permanently deleted';
$string['eventmaillistviewed'] = 'Sent email audit list viewed';
$string['eventmailviewed'] = 'Sent email audit message viewed';
$string['exportcsv'] = 'Export CSV';
$string['filter'] = 'Filter';
$string['filters'] = 'Filters';
$string['filtersactive'] = 'Active';
$string['htmlbody'] = 'HTML body';
$string['htmlsource'] = 'HTML source';
$string['includedeleted'] = 'Include deleted records';
$string['kind'] = 'Kind';
$string['kind_account_security'] = 'Account security';
$string['kind_course_bulk_mail'] = 'Course bulk mail';
$string['kind_course_registration'] = 'Course registration';
$string['kind_failed_login_digest'] = 'Failed login digest';
$string['kind_mailtest'] = 'Mail test';
$string['kind_message_digest'] = 'Message digest';
$string['kind_new_ip_login'] = 'New IP login';
$string['kind_other'] = 'Other';
$string['kind_password_reset'] = 'Password reset';
$string['kind_student_risk_alert'] = 'Student risk alert';
$string['mailaudit:delete'] = 'Delete sent email audit records';
$string['mailaudit:view'] = 'View all sent email audit records';
$string['mailaudit:viewcourse'] = 'View course sent email audit records';
$string['mailaudit:viewown'] = 'View own sent email audit records';
$string['matchingactions'] = 'Whole-result-set actions';
$string['matchingactions_desc'] = 'These buttons act on all {$a} record(s) matching the current filters, not on the row selection above. Use with care.';
$string['matchingcount'] = '{$a} matching record(s)';
$string['matchingselected'] = 'All {$a} matching record(s) are selected.';
$string['messageid'] = 'Message ID';
$string['messagename'] = 'Message name';
$string['metadata'] = 'Metadata';
$string['moodlemessage'] = 'Moodle message';
$string['mustnotmatch'] = 'Must not match';
$string['nomessages'] = 'No audited mail matched the current filters.';
$string['nonadminvisiblekinds'] = 'Kinds visible outside site administration';
$string['nonadminvisiblekinds_desc'] = 'Teachers and course-scoped users can see only these kinds when they also have matching own/course permissions. Sensitive kinds are always removed from this list in code.';
$string['nothingselected'] = 'No sent email records were selected.';
$string['nothingtodelete'] = 'There are no matching active records to delete.';
$string['nothingtopurge'] = 'There are no matching deleted records to purge.';
$string['notprefix'] = 'NOT';
$string['origin'] = 'Origin';
$string['perpage'] = 'Records per page';
$string['pluginname'] = 'Sent email audit';
$string['pluginsettings'] = 'Sent email audit settings';
$string['privacy:metadata:tool_mailaudit_access'] = 'Records of access to the sent email audit tool.';
$string['privacy:metadata:tool_mailaudit_access:action'] = 'Action performed in the tool.';
$string['privacy:metadata:tool_mailaudit_access:criteria'] = 'Filter or deletion criteria used.';
$string['privacy:metadata:tool_mailaudit_access:requestip'] = 'IP address from which the tool was accessed where available.';
$string['privacy:metadata:tool_mailaudit_access:useragent'] = 'Browser user agent used to access the tool where available.';
$string['privacy:metadata:tool_mailaudit_access:userid'] = 'Moodle user id that accessed the tool.';
$string['privacy:metadata:tool_mailaudit_mail'] = 'Copies of outbound Moodle email retained for sent-mail audit.';
$string['privacy:metadata:tool_mailaudit_mail:attachmentnames'] = 'Names of files attached to the email where available.';
$string['privacy:metadata:tool_mailaudit_mail:bodybytes'] = 'Approximate stored or captured body size in bytes.';
$string['privacy:metadata:tool_mailaudit_mail:bodyhtml'] = 'HTML email body.';
$string['privacy:metadata:tool_mailaudit_mail:bodytext'] = 'Plain text email body.';
$string['privacy:metadata:tool_mailaudit_mail:courseid'] = 'Course id associated with the outbound email where available.';
$string['privacy:metadata:tool_mailaudit_mail:fromemail'] = 'Sender email address used for the email.';
$string['privacy:metadata:tool_mailaudit_mail:fromfirstname'] = 'Snapshot of the sender first name where available.';
$string['privacy:metadata:tool_mailaudit_mail:fromlastname'] = 'Snapshot of the sender last name where available.';
$string['privacy:metadata:tool_mailaudit_mail:fromname'] = 'Snapshot of the sender display name where available.';
$string['privacy:metadata:tool_mailaudit_mail:fromuserid'] = 'Moodle user id of the sender where available.';
$string['privacy:metadata:tool_mailaudit_mail:fromusername'] = 'Snapshot of the sender username where available.';
$string['privacy:metadata:tool_mailaudit_mail:metadata'] = 'Additional non-content audit metadata about the captured email.';
$string['privacy:metadata:tool_mailaudit_mail:moodlemessageid'] = 'Moodle notification or message id associated with the email where available.';
$string['privacy:metadata:tool_mailaudit_mail:moodlemessagetable'] = 'Moodle source table for the associated notification or message.';
$string['privacy:metadata:tool_mailaudit_mail:replyto'] = 'Reply-to address and name recorded for the email where available.';
$string['privacy:metadata:tool_mailaudit_mail:requestip'] = 'IP address of the request that triggered the email where available.';
$string['privacy:metadata:tool_mailaudit_mail:requestuserid'] = 'Moodle user id of the user whose request triggered the email where available.';
$string['privacy:metadata:tool_mailaudit_mail:stackjson'] = 'Optional captured call stack (disabled by default) describing where the email originated.';
$string['privacy:metadata:tool_mailaudit_mail:subject'] = 'Email subject.';
$string['privacy:metadata:tool_mailaudit_mail:timecreated'] = 'When the outbound email was captured.';
$string['privacy:metadata:tool_mailaudit_mail:toemail'] = 'Recipient email address.';
$string['privacy:metadata:tool_mailaudit_mail:tofirstname'] = 'Snapshot of the recipient first name where available.';
$string['privacy:metadata:tool_mailaudit_mail:tolastname'] = 'Snapshot of the recipient last name where available.';
$string['privacy:metadata:tool_mailaudit_mail:toname'] = 'Snapshot of the recipient display name where available.';
$string['privacy:metadata:tool_mailaudit_mail:touserid'] = 'Moodle user id of the recipient where available.';
$string['privacy:metadata:tool_mailaudit_mail:tousername'] = 'Snapshot of the recipient username where available.';
$string['privacy:metadata:tool_mailaudit_mail:useragent'] = 'Browser user agent of the request that triggered the email where available.';
$string['privacynotice'] = 'Privacy notice';
$string['privacynotice_desc'] = 'This tool stores copies of outbound Moodle mail, including recipients and (when enabled below) message bodies. Body storage is disabled by default to minimise retained personal data. Course teachers granted the view capabilities can read non-sensitive course mail; review the capability assignments and retention settings before enabling body storage on a production site.';
$string['purge_selected'] = 'Purge selected';
$string['purgedcount'] = 'Permanently purged {$a} deleted sent email audit record(s).';
$string['purgematching'] = 'Purge deleted matching records';
$string['recipient'] = 'Recipient';
$string['recipientcontains'] = 'Recipient contains';
$string['resetfilters'] = 'Reset filters';
$string['retentiondays'] = 'Retain sent email for days';
$string['retentiondays_desc'] = 'Legacy retention setting kept for compatibility. Automatic purge now removes already-deleted records by default; use the purge settings below for current behaviour.';
$string['selectallmatching'] = 'Select all {$a} matching record(s)';
$string['selectallpage'] = 'Select all on this page';
$string['selectedcount'] = '{$a} selected';
$string['selectmail'] = 'Select sent email record {$a}';
$string['selectnonepage'] = 'Select none on this page';
$string['sender'] = 'Sender';
$string['sendercontains'] = 'Sender contains';
$string['sensitivekinds'] = 'Admin-only sensitive kinds';
$string['sensitivekinds_desc'] = 'These kinds are visible only to users with the site-level view capability, regardless of course or own-mail permissions.';
$string['sentby'] = 'Sent by';
$string['senttime'] = 'Sent time';
$string['sentto'] = 'Sent to';
$string['showallslow'] = 'Show all (slow)';
$string['showallwarning'] = 'Showing all {$a} matching records may take a long time and can make the browser slow. Use tighter filters when possible.';
$string['softdelete_selected'] = 'Delete selected';
$string['softdeletematching'] = 'Delete matching records';
$string['stat_month'] = 'This month';
$string['stat_peakhour'] = 'Peak hour';
$string['stat_peakhour_value'] = '{$a->hour}:00 ({$a->count})';
$string['stat_tablesize'] = 'Table size';
$string['stat_today'] = 'Today';
$string['stat_topcourse'] = 'Top course';
$string['stat_toprecipient'] = 'Top recipient';
$string['stat_topsender'] = 'Top sender';
$string['stat_total'] = 'Total';
$string['statcardfilter'] = 'Click to filter the list by this';
$string['status'] = 'Status';
$string['status_failed'] = 'Failed';
$string['status_queued'] = 'Queued';
$string['status_sent'] = 'Sent';
$string['storebodyhtml'] = 'Store HTML body';
$string['storebodyhtml_desc'] = 'When enabled, the HTML body is retained for audit. Disabling reduces stored personal data for new captures.';
$string['storebodytext'] = 'Store plain text body';
$string['storebodytext_desc'] = 'When enabled, the plain text body is retained for audit. Disabling reduces stored personal data for new captures.';
$string['storestacktrace'] = 'Store capture stack trace';
$string['storestacktrace_desc'] = 'Stack traces are useful during debugging but increase database size. Keep disabled once the plugin is stable.';
$string['subject'] = 'Subject';
$string['subjectcontains'] = 'Subject contains';
$string['taskpurgeoldmail'] = 'Purge old sent email audit records';
$string['textbody'] = 'Text body';
$string['unknown'] = 'Unknown';
