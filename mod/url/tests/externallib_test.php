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
 * External mod_url functions unit tests
 *
 * @package    mod_url
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * External mod_url functions unit tests
 *
 * @package    mod_url
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_url_external_testcase extends externallib_advanced_testcase {

    /**
     * Test view_url
     */
    public function test_view_url() {
        global $DB;

        $this->resetAfterTest(true);

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $url = $this->getDataGenerator()->create_module('url', array('course' => $course->id));
        $context = context_module::instance($url->cmid);
        $cm = get_coursemodule_from_instance('url', $url->id);

        // Test invalid instance id.
        try {
            mod_url_external::view_url(0);
            $this->fail('Exception expected due to invalid mod_url instance id.');
        } catch (moodle_exception $e) {
            $this->assertEquals('invalidrecord', $e->errorcode);
        }

        // Test not-enrolled user.
        $user = self::getDataGenerator()->create_user();
        $this->setUser($user);
        try {
            mod_url_external::view_url($url->id);
            $this->fail('Exception expected due to not enrolled user.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Test user with full capabilities.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $course->id, $studentrole->id);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = mod_url_external::view_url($url->id);
        $result = external_api::clean_returnvalue(mod_url_external::view_url_returns(), $result);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = array_shift($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_url\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $moodleurl = new \moodle_url('/mod/url/view.php', array('id' => $cm->id));
        $this->assertEquals($moodleurl, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('mod/url:view', CAP_PROHIBIT, $studentrole->id, $context->id);
        accesslib_clear_all_caches_for_unit_testing();

        try {
            mod_url_external::view_url($url->id);
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

    }

    /**
     * Test get_urls_by_courses
     */
    public function test_get_urls_by_courses() {
        global $DB, $USER;
        $this->resetAfterTest(true);
        $course1 = self::getDataGenerator()->create_course();
        $urloptions1 = array(
                              'course' => $course1->id,
                              'name' => 'First URL'
                             );
        $url1 = self::getDataGenerator()->create_module('url', $urloptions1);
        $course2 = self::getDataGenerator()->create_course();
        $urloptions2 = array(
                              'course' => $course2->id,
                              'name' => 'Second URL'
                             );
        $url2 = self::getDataGenerator()->create_module('url', $urloptions2);
        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Student1 in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course1->id, $studentrole->id);
        $this->setUser($student1);
        $urls = mod_url_external::get_urls_by_courses(array());
        // We need to execute the return values cleaning process to simulate the web service server.
        $urls = external_api::clean_returnvalue(mod_url_external::get_urls_by_courses_returns(), $urls);
        $this->assertCount(1, $urls['urls']);
        $this->assertEquals('First URL', $urls['urls'][0]['name']);
        // As Student you cannot see some URL properties like 'section'.
        $this->assertFalse(isset($urls['urls'][0]['section']));
        // Student1 is not enrolled in this Course.
        // The webservice will give a warning!
        $urls = mod_url_external::get_urls_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $urls = external_api::clean_returnvalue(mod_url_external::get_urls_by_courses_returns(), $urls);
        $this->assertCount(0, $urls['urls']);
        $this->assertEquals(1, $urls['warnings'][0]['warningcode']);
        // Now as admin.
        $this->setAdminUser();
        // As Admin we can see this url.
        $urls = mod_url_external::get_urls_by_courses(array($course2->id));
        // We need to execute the return values cleaning process to simulate the web service server.
        $urls = external_api::clean_returnvalue(mod_url_external::get_urls_by_courses_returns(), $urls);
        $this->assertCount(1, $urls['urls']);
        $this->assertEquals('Second URL', $urls['urls'][0]['name']);
        // As an Admin you can see some urls properties like 'section'.
        $this->assertEquals(0, $urls['urls'][0]['section']);
        $contextcourse1 = context_course::instance($course1->id);
        // Prohibit capability = mod/url:view on Course1 for students.
        assign_capability('mod/url:view', CAP_PROHIBIT, $studentrole->id, $contextcourse1->id);
        accesslib_clear_all_caches_for_unit_testing();
        $this->setUser($student1);
        $urls = mod_url_external::get_urls_by_courses(array($course1->id));
        $urls = external_api::clean_returnvalue(mod_url_external::get_urls_by_courses_returns(), $urls);
        $this->assertEquals(2, $urls['warnings'][0]['warningcode']);
    }
}
