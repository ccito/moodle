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
 * External choice functions unit tests
 *
 * @package    mod_choice
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External choice functions unit tests
 *
 * @package    mod_choice
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_choice_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Test get_choices_by_courses
     */
    public function test_get_choices_by_courses() {

        global $DB, $USER;

        $this->resetAfterTest(true);

        $course1 = self::getDataGenerator()->create_course();
        $choiceoptions1 = array((
                                'course' => $course1->id,
                                'name' => 'First Choice Activity'
                               );
        $choice1 = self::getDataGenerator()->create_module('choice', $choiceoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $choiceoptions2 = array((
                                'course' => $course2->id,
                                'name' => 'Second Choice Activity'
                               );
        $choice2 = self::getDataGenerator()->create_module('choice', $choiceoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);

        $choices = mod_choice_external::get_choices_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $choices = external_api::clean_returnvalue(mod_choice_external::get_choices_by_courses_returns(), $choices);
        $this->assertCount(1, $choices['choices']);
        $this->assertEquals('First Choice Activity', $choices['choices'][0]['name']);
        // As Student you cannot see some choice properties like 'showunanswered'.
        $this->assertFalse(isset($choices['choices'][0]['showunanswered']));

        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $choices = mod_choice_external::get_choices_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $choices = external_api::clean_returnvalue(mod_choice_external::get_choices_by_courses_returns(), $choices);
        $this->assertCount(0, $choices['choices']);
        $this->assertEquals(1, $choices['warnings'][0]['warningcode']);

        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this choice activity.
        $choices = mod_choice_external::get_choices_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $choices = external_api::clean_returnvalue(mod_choice_external::get_choices_by_courses_returns(), $choices);
        $this->assertCount(1, $choices['choices']);
        $this->assertEquals('Second Choice Activity', $choices['choices'][0]['name']);
        // As an Admin you can see some choice properties like 'showunanswered'.
        $this->assertEquals(1, $choices['choices'][0]['showunanswered']);
    }
}
