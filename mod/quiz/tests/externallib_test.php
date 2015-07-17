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
 * External quiz functions unit tests
 *
 * @package    mod_quiz
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External quiz functions unit tests
 *
 * @package    mod_quiz
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_quiz_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_quizzes_by_courses
     */
    public function test_get_quizzes_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $quizoptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First Quiz'
                             );
        $quiz1 = self::getDataGenerator()->create_module('quiz', $quizoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $quizoptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second Quiz'
                             );
        $quiz2 = self::getDataGenerator()->create_module('quiz', $quizoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $quizzes = mod_quiz_external::get_quizzes_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $quizzes = external_api::clean_returnvalue(mod_quiz_external::get_quizzes_by_courses_returns(), $quizzes);
        $this->assertCount(1, $quizzes['quizzes']);
        $this->assertEquals('First Quiz', $quizzes['quizzes'][0]['name']);
        // As Student you cannot see some quiz properties like 'showunanswered'.
        $this->assertFalse(isset($quizzes['quizzes'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $quizzes = mod_quiz_external::get_quizzes_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $quizzes = external_api::clean_returnvalue(mod_quiz_external::get_quizzes_by_courses_returns(), $quizzes);
        $this->assertCount(0, $quizzes['quizzes']);
        $this->assertEquals(1, $quizzes['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this quiz.
        $quizzes = mod_quiz_external::get_quizzes_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $quizzes = external_api::clean_returnvalue(mod_quiz_external::get_quizzes_by_courses_returns(), $quizzes);
        $this->assertCount(1, $quizzes['quizzes']);
        $this->assertEquals('Second Quiz', $quizzes['quizzes'][0]['name']);
        // Check warningcode=2 when miss capability.
        $this->assertEquals(0, $quizzes['quizzes'][0]['section']);
        // Verify thw WS gives warnincode 2.
        $this->setUser($student1);
        $contextcourse1 = context_course::instance($course1->id);
        // Prohibit capability = mod:quiz:view on Course1 for students.
        assign_capability('mod/quiz:view', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();
        $quizzes = mod_quiz_external::get_quizzes_by_courses(array($course1->id));
        $quizzes = external_api::clean_returnvalue(mod_quiz_external::get_quizzes_by_courses_returns(), $quizzes);
        $this->assertEquals(2, $quizzes['warnings'][0]['warningcode']);
    }
}
