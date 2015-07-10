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
 * External survey functions unit tests
 *
 * @package    mod_survey
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External survey functions unit tests
 *
 * @package    mod_survey
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_survey_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_surveys_by_courses
     */
    public function test_get_surveys_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $surveyoptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First Survey'
                             );
        $survey1 = self::getDataGenerator()->create_module('survey', $surveyoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $surveyoptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second Survey'
                             );
        $survey2 = self::getDataGenerator()->create_module('survey', $surveyoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $surveys = mod_survey_external::get_surveys_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $surveys = external_api::clean_returnvalue(mod_survey_external::get_surveys_by_courses_returns(), $surveys);
        $this->assertCount(1, $surveys['surveys']);
        $this->assertEquals('First Survey', $surveys['surveys'][0]['name']);
        // As Student you cannot see some survey properties like 'showunanswered'.
        $this->assertFalse(isset($surveys['surveys'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $surveys = mod_survey_external::get_surveys_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $surveys = external_api::clean_returnvalue(mod_survey_external::get_surveys_by_courses_returns(), $surveys);
        $this->assertCount(0, $surveys['surveys']);
        $this->assertEquals(1, $surveys['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this survey.
        $surveys = mod_survey_external::get_surveys_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $surveys = external_api::clean_returnvalue(mod_survey_external::get_surveys_by_courses_returns(), $surveys);
        $this->assertCount(1, $surveys['surveys']);
        $this->assertEquals('Second Survey', $surveys['surveys'][0]['name']);
        // As an Admin you can see some survey properties like 'section'.
        $this->assertEquals(0, $surveys['surveys'][0]['section']);
        $contextcourse1 = context_course::instance($course1->id);
        // Prohibit capability = mod:survey:participate on Course1 for students.
        assign_capability('mod/survey:participate', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($student1);
        $surveys = mod_survey_external::get_surveys_by_courses(array($course1->id));
        $surveys = external_api::clean_returnvalue(mod_survey_external::get_surveys_by_courses_returns(), $surveys);
        $this->assertEquals(2, $surveys['warnings'][0]['warningcode']);
    }
}
