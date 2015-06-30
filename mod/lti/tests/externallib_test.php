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
 * External lti functions unit tests
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External lti functions unit tests
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_lti_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_ltis_by_courses
     */
    public function test_get_ltis_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $ltioptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First LTI'
                             );
        $lti1 = self::getDataGenerator()->create_module('lti', $ltioptions1);
        $course2 = self::getDataGenerator()->create_course();
        $ltioptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second LTI'
                             );
        $lti2 = self::getDataGenerator()->create_module('lti', $ltioptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $ltis = mod_lti_external::get_ltis_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $ltis = external_api::clean_returnvalue(mod_lti_external::get_ltis_by_courses_returns(), $ltis);
        $this->assertCount(1, $ltis['ltis']);
        $this->assertEquals('First LTI', $ltis['ltis'][0]['name']);
        // As Student you cannot see some lti properties like 'showunanswered'.
        $this->assertFalse(isset($ltis['ltis'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $ltis = mod_lti_external::get_ltis_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $ltis = external_api::clean_returnvalue(mod_lti_external::get_ltis_by_courses_returns(), $ltis);
        $this->assertCount(0, $ltis['ltis']);
        $this->assertEquals(1, $ltis['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this lti.
        $ltis = mod_lti_external::get_ltis_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $ltis = external_api::clean_returnvalue(mod_lti_external::get_ltis_by_courses_returns(), $ltis);
        $this->assertCount(1, $ltis['ltis']);
        $this->assertEquals('Second LTI', $ltis['ltis'][0]['name']);
        // As an Admin you can see some lti properties like 'section'.
        $this->assertEquals(0, $ltis['ltis'][0]['section']);
    }
}
