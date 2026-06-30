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

namespace tool_mailaudit\form;

use tool_mailaudit\local\filters;

/**
 * Filter form for the sent-mail audit browser.
 *
 * @package    tool_mailaudit
 * @copyright  2026 VIT
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class filter_form extends \moodleform {
    /**
     * Define the form.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $customdata = $this->_customdata;

        $mform->addElement('date_selector', 'datefrom', get_string('datefrom', 'tool_mailaudit'), ['optional' => true]);
        $mform->setType('datefrom', PARAM_INT);
        $mform->addElement('date_selector', 'dateto', get_string('dateto', 'tool_mailaudit'), ['optional' => true]);
        $mform->setType('dateto', PARAM_INT);

        $kindoptions = $customdata['kindoptions'];
        unset($kindoptions['']);
        $mform->addElement(
            'autocomplete',
            'kinds',
            get_string('kind', 'tool_mailaudit'),
            $kindoptions,
            ['multiple' => true]
        );
        $mform->setType('kinds', PARAM_ALPHANUMEXT);

        $statusoptions = $customdata['statusoptions'];
        unset($statusoptions['']);
        $mform->addElement(
            'autocomplete',
            'statuses',
            get_string('status', 'tool_mailaudit'),
            $statusoptions,
            ['multiple' => true]
        );
        $mform->setType('statuses', PARAM_ALPHA);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $this->add_user_selector('fromuserids', get_string('sentby', 'tool_mailaudit'));
        $mform->addElement('advcheckbox', 'fromuseridsnot', get_string('mustnotmatch', 'tool_mailaudit'));
        $mform->setType('fromuseridsnot', PARAM_BOOL);

        $this->add_user_selector('touserids', get_string('sentto', 'tool_mailaudit'));
        $mform->addElement('advcheckbox', 'touseridsnot', get_string('mustnotmatch', 'tool_mailaudit'));
        $mform->setType('touseridsnot', PARAM_BOOL);

        $courseoptions = $customdata['courseoptions'] ?? [];
        if ($courseoptions) {
            $mform->addElement(
                'autocomplete',
                'courseids',
                get_string('course', 'tool_mailaudit'),
                $courseoptions,
                ['multiple' => true]
            );
            $mform->setType('courseids', PARAM_INT);
            $mform->addElement('advcheckbox', 'courseidsnot', get_string('mustnotmatch', 'tool_mailaudit'));
            $mform->setType('courseidsnot', PARAM_BOOL);
        }

        $this->add_text_with_not('sender', get_string('sendercontains', 'tool_mailaudit'));
        $this->add_text_with_not('recipient', get_string('recipientcontains', 'tool_mailaudit'));
        $this->add_text_with_not('subject', get_string('subjectcontains', 'tool_mailaudit'));
        $this->add_text_with_not('body', get_string('bodycontains', 'tool_mailaudit'));

        $mform->addElement('advcheckbox', 'includedeleted', get_string('includedeleted', 'tool_mailaudit'));
        $mform->setType('includedeleted', PARAM_BOOL);

        $mform->addElement('select', 'perpage', get_string('perpage', 'tool_mailaudit'), [
            25 => 25,
            50 => 50,
            100 => 100,
            200 => 200,
            filters::SHOW_ALL_PERPAGE => get_string('showallslow', 'tool_mailaudit'),
        ]);
        $mform->setType('perpage', PARAM_INT);

        $buttons = [
            $mform->createElement('submit', 'submitbutton', get_string('filter', 'tool_mailaudit')),
            $mform->createElement('cancel', 'resetbutton', get_string('resetfilters', 'tool_mailaudit')),
        ];
        $mform->addGroup($buttons, 'actions', '', [' '], false);
        $mform->disable_form_change_checker();
    }

    /**
     * Add a text field with a "must not match" checkbox.
     *
     * @param string $name Base field name.
     * @param string $label Field label.
     */
    private function add_text_with_not(string $name, string $label): void {
        $mform = $this->_form;
        $group = [
            $mform->createElement('text', $name, $label),
            $mform->createElement('advcheckbox', $name . 'not', '', get_string('mustnotmatch', 'tool_mailaudit')),
        ];
        $mform->addGroup($group, $name . 'group', $label, [' '], false);
        $mform->setType($name, PARAM_TEXT);
        $mform->setType($name . 'not', PARAM_BOOL);
    }

    /**
     * Add a Moodle user autocomplete selector.
     *
     * @param string $name Field name.
     * @param string $label Field label.
     */
    private function add_user_selector(string $name, string $label): void {
        $mform = $this->_form;
        $options = [
            'ajax' => 'core_user/form_user_selector',
            'multiple' => true,
            'valuehtmlcallback' => static function ($userid): string {
                $user = \core_user::get_user($userid, 'id, firstname, lastname, email, username');
                return $user ? fullname($user) . ' ' . s($user->email) : '';
            },
        ];
        $mform->addElement('autocomplete', $name, $label, [], $options);
        $mform->setType($name, PARAM_INT);
    }
}
