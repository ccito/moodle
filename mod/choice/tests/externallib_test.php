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
require_once($CFG->dirroot . '/mod/choice/classes/external.php');

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
     * Test get_choice_options
     */
    public function test_get_choice_options() {

        global $DB, $USER;

        // Warningcodes.
        $notopenyet = 1;
        $previewonly = 2;
        $expired = 3;

        $this->resetAfterTest(true);
        $timenow = time();
        $timeopen = $timenow + (60 * 60 * 24 * 2);
        $timeclose = $timenow + (60 * 60 * 24 * 7);
        $course = self::getDataGenerator()->create_course();
        $possibleoptions = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params = array();
        $params['course'] = $course->id;
        $params['option'] = $possibleoptions;
        $params['name'] = 'First Choice Activity';
        $params['showpreview'] = 0;

        $generator = $this->getDataGenerator()->get_plugin_generator('mod_choice');
        $choice = $generator->create_instance($params);

        $student1 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        // Enroll Students in Course.
        self::getDataGenerator()->enrol_user($student1->id,  $course->id, $studentrole->id);
        $this->setUser($student1);

        $results = mod_choice_external::get_choice_options($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);

        // Here we force timeopen/close in the future.
        $choice->timeopen = $timeopen;
        $choice->timeclose = $timeclose;
        $DB->update_record('choice', $choice);

        $results = mod_choice_external::get_choice_options($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_options_returns(), $results);
        // We should retrieve no options.
        $this->assertCount(0, $results['options']);
        $this->assertEquals($notopenyet, $results['warnings'][0]['warningcode']);

        // Here we see the options because of preview!
        $choice->showpreview = 1;
        $DB->update_record('choice', $choice);
        $results = mod_choice_external::get_choice_options($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);

        foreach ($results['options'] as $option) {
            // Each option is disabled as this is only the preview!
            $this->assertEquals(1, $option['disabled']);
        }
        $warnings = array();
        foreach ($results['warnings'] as $warning) {
            $warnings[$warning['warningcode']] = $warning['message'];
        }
        $this->assertTrue(isset($warnings[$previewonly]));
        $this->assertTrue(isset($warnings[$notopenyet]));

        // Simulate activity as opened!
        $choice->timeopen = $timenow - (60 * 60 * 24 * 3);
        $choice->timeclose = $timenow + (60 * 60 * 24 * 2);
        $DB->update_record('choice', $choice);
        $cm = get_coursemodule_from_id('choice', $choice->cmid);
        $choiceinstance = choice_get_choice($cm->instance);
        $optionsids = array_keys($choiceinstance->option);
        $myanswerid = $optionsids[2];
        choice_user_submit_response($myanswerid, $choice, $student1->id, $course, $cm);

        $results = mod_choice_external::get_choice_options($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_options_returns(), $results);
        // We should retrieve all options.
        $this->assertCount(count($possibleoptions), $results['options']);
        foreach ($results['options'] as $option) {
            // When we answered and we cannot update our choice.
            if ($option['id'] == $myanswerid and !$choice->allowupdate) {
                $this->assertEquals(1, $option['disabled']);
                $this->assertEquals(1, $option['checked']);
            } else {
                $this->assertEquals(0, $option['disabled']);
            }
        }

        // Set timeopen and timeclose as older than today!
        // We simulate what happens when the activity is closed.
        $choice->timeopen = $timenow - (60 * 60 * 24 * 3);
        $choice->timeclose = $timenow - (60 * 60 * 24 * 2);
        $DB->update_record('choice', $choice);
        $results = mod_choice_external::get_choice_options($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_options_returns(), $results);
        // We should retrieve no options.
        $this->assertCount(0, $results['options']);
        $this->assertEquals($expired, $results['warnings'][0]['warningcode']);

    }
}
