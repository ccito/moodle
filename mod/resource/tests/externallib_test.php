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
 * External resource functions unit tests
 *
 * @package    mod_resource
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External resource functions unit tests
 *
 * @package    mod_resource
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_resource_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_resources_by_courses
     */
    public function test_get_resources_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(false);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_resource');
        $resource1 = $generator->create_instance(array('course' => $course1->id));

        $course2 = self::getDataGenerator()->create_course();
        $resource2 = $generator->create_instance(array('course' => $course2->id));
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);

        $this->setUser($student1);
        $resources = mod_resource_external::get_resources_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $resources = external_api::clean_returnvalue(mod_resource_external::get_resources_by_courses_returns(), $resources);
        $this->assertCount(1, $resources['resources']);
        $this->assertEquals('File 1', $resources['resources'][0]['name']);
        // As Student you cannot see some resource properties like 'showunanswered'.
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $resources = mod_resource_external::get_resources_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $resources = external_api::clean_returnvalue(mod_resource_external::get_resources_by_courses_returns(), $resources);
        $this->assertCount(0, $resources['resources']);
        $this->assertEquals(1, $resources['warnings'][0]['warningcode']);
        $contextcourse1 = context_course::instance($course1->id);
        // Prohibit capability = mod:resource:view on Course1 for students.
        assign_capability('mod/resource:view', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();
        $resources = mod_resource_external::get_resources_by_courses(array($course1->id));
        $resources = external_api::clean_returnvalue(mod_resource_external::get_resources_by_courses_returns(), $resources);
        $this->assertEquals(2, $resources['warnings'][0]['warningcode']);
    }
}
