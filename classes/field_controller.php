<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Field controller for 'customfield_textregex'.
 *
 * @package   customfield_textregex
 * @author    Bence Molnar <molbence@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024 onwards Bence Molnar
 */

namespace customfield_textregex;

use coding_exception;
use core\exception\moodle_exception;
use moodle_url;
use MoodleQuickForm;

/**
 * Class field
 *
 * @package   customfield_textregex
 * @author    Bence Molnar <molbence@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024 onwards Bence Molnar
 */
class field_controller extends \core_customfield\field_controller {
    /**
     * Plugin type text
     */
    const TYPE = 'textregex';

    /**
     * Add fields for editing a text field.
     *
     * @param MoodleQuickForm $mform
     * @throws coding_exception|moodle_exception
     */
    public function config_form_definition(MoodleQuickForm $mform): void {

        $mform->addElement('header', 'header_specificsettings', get_string('specificsettings', 'customfield_textregex'));
        $mform->setExpanded('header_specificsettings');

        $mform->addElement('text', 'configdata[regex]', get_string('regex', 'customfield_textregex'),
            ['size' => 150]);
        $mform->setType('configdata[regex]', PARAM_TEXT);
        $mform->addRule('configdata[regex]', null, 'required', null, 'client');
        $stricturl = new moodle_url('/admin/search.php', ['query' => 'strictformsrequired']);
        $mform->addHelpButton('configdata[regex]', 'regex', 'customfield_textregex', null, null, $stricturl);

        $mform->addElement('text', 'configdata[defaultvalue]', get_string('defaultvalue', 'core_customfield'),
            ['size' => 50]);
        $mform->setType('configdata[defaultvalue]', PARAM_TEXT);

        $mform->addElement('text', 'configdata[displaysize]', get_string('displaysize', 'customfield_textregex'), ['size' => 6]);
        $mform->setType('configdata[displaysize]', PARAM_INT);
        if (!$this->get_configdata_property('displaysize')) {
            $mform->setDefault('configdata[displaysize]', 50);
        }
        $mform->addRule('configdata[displaysize]', null, 'numeric', null, 'client');

        $mform->addElement('text', 'configdata[link]', get_string('islink', 'customfield_textregex'), ['size' => 50]);
        $mform->setType('configdata[link]', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('configdata[link]', 'islink', 'customfield_textregex');

        $linkstargetoptions = [
            ''       => get_string('none', 'customfield_textregex'),
            '_blank' => get_string('newwindow', 'customfield_textregex'),
            '_self'  => get_string('sameframe', 'customfield_textregex'),
            '_top'   => get_string('samewindow', 'customfield_textregex'),
        ];
        $mform->addElement('select', 'configdata[linktarget]', get_string('linktarget', 'customfield_textregex'),
            $linkstargetoptions);

        $mform->disabledIf('configdata[linktarget]', 'configdata[link]', 'eq', '');
    }

    /**
     * Validate the data on the field configuration form
     *
     * @param array $data from the add/edit profile field form
     * @param array $files
     * @return array associative array of error messages
     * @throws coding_exception
     */
    public function config_form_validation(array $data, $files = []): array {
        $errors = parent::config_form_validation($data, $files);

        if (preg_match($data['configdata']['regex'], '') === false) {
            $errors['configdata[regex]'] = get_string('errorconfigregex', 'customfield_textregex');
        } else if (
            !empty($data['configdata']['defaultvalue']) &&
            !preg_match($data['configdata']['regex'], $data['configdata']['defaultvalue'])
        ) {
            $errors['configdata[defaultvalue]'] = get_string('errorconfigdefault', 'customfield_textregex');
        }

        $displaysize = (int)$data['configdata']['displaysize'];
        if ($displaysize < 1 || $displaysize > 200) {
            $errors['configdata[displaysize]'] = get_string('errorconfigdisplaysize', 'customfield_textregex');
        }

        if (isset($data['configdata']['link'])) {
            $link = $data['configdata']['link'];
            if (strlen($link)) {
                if (strpos($link, '$$') === false) {
                    $errors['configdata[link]'] = get_string('errorconfiglinkplaceholder', 'customfield_textregex');
                } else {
                    $testurl = str_replace('$$', 'XYZ', $link);
                    $cleaned = clean_param($testurl, PARAM_URL);
                    if (empty($cleaned) || !preg_match('/^https?:\/\//i', $cleaned)) {
                        $errors['configdata[link]'] = get_string('errorconfiglinksyntax', 'customfield_textregex');
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Does this custom field type support being used as part of the block_myoverview
     * custom field grouping?
     * @return bool
     */
    public function supports_course_grouping(): bool {
        return true;
    }

    /**
     * If this field supports course grouping, then this function needs overriding to
     * return the formatted values for this.
     *
     * @param array $values the used values that need formatting
     * @return array
     * @throws coding_exception
     */
    public function course_grouping_format_values($values): array {
        $ret = [];
        foreach ($values as $value) {
            $ret[$value] = format_string($value);
        }
        $ret[BLOCK_MYOVERVIEW_CUSTOMFIELD_EMPTY] = get_string('nocustomvalue', 'block_myoverview',
            $this->get_formatted_name());
        return $ret;
    }
}
