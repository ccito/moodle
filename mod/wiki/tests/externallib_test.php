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
 * External wiki functions unit tests
 *
 * @package    mod_wiki
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External wiki functions unit tests
 *
 * @package    mod_wiki
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_wiki_externallib_testcase extends externallib_advanced_testcase {
    /**
     * Test get_wikis_by_courses
     */
    public function test_get_wikis_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        // As admin.
        $this->setAdminUser();
        $course1 = self::getDataGenerator()->create_course();
        $wikioptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First Wiki'
                             );
        $wiki1 = self::getDataGenerator()->create_module('wiki', $wikioptions1);
        $course2 = self::getDataGenerator()->create_course();
        $wikioptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second Wiki'
                             );
        $wiki2 = self::getDataGenerator()->create_module('wiki', $wikioptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $wikis = mod_wiki_external::get_wikis_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $wikis = external_api::clean_returnvalue(mod_wiki_external::get_wikis_by_courses_returns(), $wikis);
        $this->assertCount(1, $wikis['wikis']);
        $this->assertEquals('First Wiki', $wikis['wikis'][0]['name']);
        // As Student you cannot see some wiki properties like 'showunanswered'.
        $this->assertFalse(isset($wikis['wikis'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $wikis = mod_wiki_external::get_wikis_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $wikis = external_api::clean_returnvalue(mod_wiki_external::get_wikis_by_courses_returns(), $wikis);
        $this->assertCount(0, $wikis['wikis']);
        $this->assertEquals(1, $wikis['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this wiki.
        $wikis = mod_wiki_external::get_wikis_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $wikis = external_api::clean_returnvalue(mod_wiki_external::get_wikis_by_courses_returns(), $wikis);
        $this->assertCount(1, $wikis['wikis']);
        $this->assertEquals('Second Wiki', $wikis['wikis'][0]['name']);
        // As an Admin you can see some wiki properties like 'section'.
        $this->assertEquals(0, $wikis['wikis'][0]['section']);
    }
}
