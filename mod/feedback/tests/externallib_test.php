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
 * External feedback functions unit tests
 *
 * @package    mod_feedback
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External feedback functions unit tests
 *
 * @package    mod_feedback
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_feedback_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_feedbacks_by_courses
     */
    public function test_get_feedbacks_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $module = $DB->get_record('modules', array('name' => 'feedback'));
        $module->visible = 1;
        $DB->update_record('modules', $module);

        $course1 = self::getDataGenerator()->create_course();
        $feedbackoptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First Feedback'
                             );
        $feedback1 = self::getDataGenerator()->create_module('feedback', $feedbackoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $feedbackoptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second Feedback'
                             );
        $feedback2 = self::getDataGenerator()->create_module('feedback', $feedbackoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $feedbacks = mod_feedback_external::get_feedbacks_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $feedbacks = external_api::clean_returnvalue(mod_feedback_external::get_feedbacks_by_courses_returns(), $feedbacks);

        $this->assertCount(1, $feedbacks['feedbacks']);
        $this->assertEquals('First Feedback', $feedbacks['feedbacks'][0]['name']);
        // As Student you cannot see some feedback properties like 'showunanswered'.
        $this->assertFalse(isset($feedbacks['feedbacks'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $feedbacks = mod_feedback_external::get_feedbacks_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $feedbacks = external_api::clean_returnvalue(mod_feedback_external::get_feedbacks_by_courses_returns(), $feedbacks);
        $this->assertCount(0, $feedbacks['feedbacks']);
        $this->assertEquals(1, $feedbacks['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this feedback.
        $feedbacks = mod_feedback_external::get_feedbacks_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $feedbacks = external_api::clean_returnvalue(mod_feedback_external::get_feedbacks_by_courses_returns(), $feedbacks);
        $this->assertCount(1, $feedbacks['feedbacks']);
        $this->assertEquals('Second Feedback', $feedbacks['feedbacks'][0]['name']);
        // As an Admin you can see some feedback properties like 'section'.
        $this->assertEquals(0, $feedbacks['feedbacks'][0]['section']);
    }
}
