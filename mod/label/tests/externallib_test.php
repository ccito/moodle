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
 * External label functions unit tests
 *
 * @package    mod_label
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External label functions unit tests
 *
 * @package    mod_label
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_label_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_labels_by_courses
     */
    public function test_get_labels_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $course1 = self::getDataGenerator()->create_course();
        $labeloptions1 = array(
                              'course' => $course1->id,
                              'intro' => 'First Label'
                             );
        $label1 = self::getDataGenerator()->create_module('label', $labeloptions1);
        $course2 = self::getDataGenerator()->create_course();
        $labeloptions2 = array(
                              'course' => $course2->id,
                              'intro' => 'Second Label'
                             );
        $label2 = self::getDataGenerator()->create_module('label', $labeloptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $labels = mod_label_external::get_labels_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $labels = external_api::clean_returnvalue(mod_label_external::get_labels_by_courses_returns(), $labels);
        $this->assertCount(1, $labels['labels']);
        $this->assertEquals('First Label', $labels['labels'][0]['intro']);
        // As Student you cannot see some label properties like 'showunanswered'.
        $this->assertFalse(isset($labels['labels'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $labels = mod_label_external::get_labels_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $labels = external_api::clean_returnvalue(mod_label_external::get_labels_by_courses_returns(), $labels);
        $this->assertCount(0, $labels['labels']);
        $this->assertEquals(1, $labels['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this label.
        $labels = mod_label_external::get_labels_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $labels = external_api::clean_returnvalue(mod_label_external::get_labels_by_courses_returns(), $labels);
        $this->assertCount(1, $labels['labels']);
        $this->assertEquals('Second Label', $labels['labels'][0]['intro']);
        // As an Admin you can see some label properties like 'section'.
        $this->assertEquals(0, $labels['labels'][0]['section']);
    }
}
