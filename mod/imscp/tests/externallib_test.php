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
 * External imscp functions unit tests
 *
 * @package    mod_imscp
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External imscp functions unit tests
 *
 * @package    mod_imscp
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_imscp_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_imscps_by_courses
     */
    public function test_get_imscps_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $imscpoptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First IMScp'
                             );
        $imscp1 = self::getDataGenerator()->create_module('imscp', $imscpoptions1);
        $course2 = self::getDataGenerator()->create_course();
        $imscpoptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second IMScp'
                             );
        $imscp2 = self::getDataGenerator()->create_module('imscp', $imscpoptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $imscps = mod_imscp_external::get_imscps_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $imscps = external_api::clean_returnvalue(mod_imscp_external::get_imscps_by_courses_returns(), $imscps);
        $this->assertCount(1, $imscps['imscps']);
        $this->assertEquals('First IMScp', $imscps['imscps'][0]['name']);
        // As Student you cannot see some imscp properties like 'showunanswered'.
        $this->assertFalse(isset($imscps['imscps'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $imscps = mod_imscp_external::get_imscps_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $imscps = external_api::clean_returnvalue(mod_imscp_external::get_imscps_by_courses_returns(), $imscps);
        $this->assertCount(0, $imscps['imscps']);
        $this->assertEquals(1, $imscps['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this imscp.
        $imscps = mod_imscp_external::get_imscps_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $imscps = external_api::clean_returnvalue(mod_imscp_external::get_imscps_by_courses_returns(), $imscps);
        $this->assertCount(1, $imscps['imscps']);
        $this->assertEquals('Second IMScp', $imscps['imscps'][0]['name']);
        // As an Admin you can see some imscp properties like 'section'.
        $this->assertEquals(0, $imscps['imscps'][0]['section']);
        $this->setUser($student1);
        $contextcourse1 = context_course::instance($course1->id);
        // Prohibit capability = mod:imscp:view on Course1 for students.
        assign_capability('mod/imscp:view', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();
        $imscps = mod_imscp_external::get_imscps_by_courses(array($course1->id));
        $imscps = external_api::clean_returnvalue(mod_imscp_external::get_imscps_by_courses_returns(), $imscps);
        $this->assertEquals(2, $imscps['warnings'][0]['warningcode']);
    }
}
