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
 * Tests for 'customfield_textregex'
 *
 * @package   customfield_textregex
 * @category  test
 * @author    Bence Molnar <molbence@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024 onwards Bence Molnar
 */

namespace customfield_textregex;

use advanced_testcase;
use core_customfield_generator;
use core_customfield_test_instance_form;
use core_customfield\category_controller;
use core_customfield\data_controller;
use core_customfield\field_controller;
use core_customfield\field_config_form;
use stdClass;
use coding_exception;
use core\exception\moodle_exception;

/**
 * Functional test for 'customfield_textregex'
 *
 * @package    customfield_textregex
 * @author    Bence Molnar <molbence@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2024 onwards Bence Molnar
 */
final class plugin_test extends advanced_testcase {

    /** @var stdClass[]  */
    private array $courses = [];

    /** @var category_controller */
    private category_controller $cfcat;

    /** @var field_controller[] */
    private array $cfields;

    /** @var data_controller[] */
    private array $cfdata;

    /**
     * Tests set up.
     *
     * @throws coding_exception
     */
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->cfcat = $this->get_generator()->create_category();

        $this->cfields[1] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield1', 'type' => 'textregex',
                'configdata' => ['displaysize' => 50, 'regex' => '/^[a-z]+$/'], 'description' => null]);
        $this->cfields[2] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield2', 'type' => 'textregex',
                'configdata' => ['required' => 1, 'displaysize' => 50, 'regex' => '/^[a-z]+$/']]);
        $this->cfields[3] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield3', 'type' => 'textregex',
                'configdata' => ['defaultvalue' => 'defvalue', 'displaysize' => 50, 'regex' => '/^[a-z]+$/']]);
        $this->cfields[4] = $this->get_generator()->create_field(
            ['categoryid' => $this->cfcat->get('id'), 'shortname' => 'myfield4', 'type' => 'text',
                'configdata' => ['link' => 'https://twitter.com/$$', 'displaysize' => 50, 'regex' => '/^[a-z]+$/']]);

        $this->courses[1] = $this->getDataGenerator()->create_course();
        $this->courses[2] = $this->getDataGenerator()->create_course();
        $this->courses[3] = $this->getDataGenerator()->create_course();

        $this->cfdata[1] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[1]->id,
            'valuea');
        $this->cfdata[2] = $this->get_generator()->add_instance_data($this->cfields[1], $this->courses[2]->id,
            'valueb');

        $this->setUser($this->getDataGenerator()->create_user());
    }

    /**
     * Get generator
     *
     * @return core_customfield_generator
     */
    protected function get_generator(): core_customfield_generator {
        return $this->getDataGenerator()->get_plugin_generator('core_customfield');
    }

    /**
     * Test for initialising field and data controllers
     *
     * @covers \core_customfield\field_controller::create
     * @throws coding_exception|moodle_exception
     */
    public function test_initialise(): void {
        $f = field_controller::create($this->cfields[1]->get('id'));
        $this->assertTrue($f instanceof field_controller);

        $f = field_controller::create(0, (object)['type' => 'textregex'], $this->cfcat);
        $this->assertTrue($f instanceof field_controller);

        $d = data_controller::create($this->cfdata[1]->get('id'));
        $this->assertTrue($d instanceof data_controller);

        $d = data_controller::create(0, null, $this->cfields[1]);
        $this->assertTrue($d instanceof data_controller);
    }

    /**
     * Test for configuration form functions
     *
     * Create a configuration form and submit it with the same values as in the field
     * @coversNothing
     */
    public function test_config_form(): void {
        $this->setAdminUser();
        $submitdata = (array)$this->cfields[1]->to_record();
        $submitdata['configdata'] = $this->cfields[1]->get('configdata');

        $submitdata = field_config_form::mock_ajax_submit($submitdata);
        $form = new field_config_form(null, null, 'post', '', null, true,
            $submitdata, true);
        $form->set_data_for_dynamic_submission();
        $this->assertTrue($form->is_validated());
        $form->process_dynamic_submission();
    }

    /**
     * Test for instance form functions
     *
     * @coversNothing
     * @throws coding_exception
     */
    public function test_instance_form(): void {
        global $CFG;
        require_once($CFG->dirroot . '/customfield/tests/fixtures/test_instance_form.php');
        $this->setAdminUser();
        $handler = $this->cfcat->get_handler();

        // First try to submit without required field.
        $submitdata = (array)$this->courses[1];
        core_customfield_test_instance_form::mock_submit($submitdata);
        $form = new core_customfield_test_instance_form('POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]);
        $this->assertFalse($form->is_validated());

        // Now with required but invalid field.
        $submitdata['customfield_myfield2'] = '123456';
        core_customfield_test_instance_form::mock_submit($submitdata);
        $form = new core_customfield_test_instance_form('POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]);
        $this->assertFalse($form->is_validated());

        // Now with required field.
        $submitdata['customfield_myfield2'] = 'sometext';
        core_customfield_test_instance_form::mock_submit($submitdata);
        $form = new core_customfield_test_instance_form('POST',
            ['handler' => $handler, 'instance' => $this->courses[1]]);
        $this->assertTrue($form->is_validated());

        $data = $form->get_data();
        $this->assertNotEmpty($data->customfield_myfield1);
        $this->assertNotEmpty($data->customfield_myfield2);
        $handler->instance_form_save($data);
    }

    /**
     * Test for data_controller::get_value and export_value
     * @coversNothing
     * @throws coding_exception|moodle_exception
     */
    public function test_get_export_value(): void {
        $this->assertEquals('valuea', $this->cfdata[1]->get_value());
        $this->assertEquals('valuea', $this->cfdata[1]->export_value());

        // Field without data but with a default value.
        $d = data_controller::create(0, null, $this->cfields[3]);
        $this->assertEquals('defvalue', $d->get_value());
        $this->assertEquals('defvalue', $d->export_value());

        // Field with a link.
        $d = $this->get_generator()->add_instance_data($this->cfields[4], $this->courses[1]->id, 'mynickname');
        $this->assertEquals('mynickname', $d->get_value());
        $this->assertEquals('<a href="https://twitter.com/mynickname">mynickname</a>', $d->export_value());
    }

    /**
     * Deleting fields and data
     * @coversNothing
     */
    public function test_delete(): void {
        $this->cfcat->get_handler()->delete_all();
    }
}
