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
     * Test get_choice_results
     */
    public function test_get_choice_results() {

        global $DB, $USER;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $params = new stdClass();
        $params->course = $course->id;
        $params->option = array('fried rice', 'spring rolls', 'sweet and sour pork', 'satay beef', 'gyouza');
        $params->name = 'First Choice Activity';
        $params->showresults = CHOICE_SHOWRESULTS_AFTER_ANSWER;
        $params->publish = 1;
        $params->allowmultiple = 1;
        $params->showunanswered = 1;
        $choice = self::getDataGenerator()->create_module('choice', $params);

        $cm = get_coursemodule_from_id('choice', $choice->cmid);
        $choiceinstance = choice_get_choice($cm->instance);
        $options = array_keys($choiceinstance->option);
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));

        // Enroll Students in Course1.
        self::getDataGenerator()->enrol_user($student1->id,  $course->id, $studentrole->id);
        self::getDataGenerator()->enrol_user($student2->id,  $course->id, $studentrole->id);

        $this->setUser($student1);
        $myanswer = $options[2];
        choice_user_submit_response($myanswer, $choice, $student1->id, $course, $cm);
        $results = mod_choice_external::get_choice_results($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_results_returns(), $results);

        // Create an array with optionID as Key.
        $resultsarr = array();
        foreach ($results['options'] as $option) {
            $resultsarr[$option['id']] = $option['userresponses'];
        }
        // The stundent1 is the userid who choosed the myanswer(option 3).
        $this->assertEquals($resultsarr[$myanswer][0]['userid'], $student1->id);
        // The stundent2 is the userid who didn't answered yet.
        $this->assertEquals($resultsarr[0][0]['userid'], $student2->id);

        // As Stundent2 we cannot see results (until we answered).
        $this->setUser($student2);
        $results = mod_choice_external::get_choice_results($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_results_returns(), $results);
        // We do not retrieve any response!
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
        }

        $timenow = time();
        // We can see results only after activity close (even if we didn't answered).
        $choice->showresults = CHOICE_SHOWRESULTS_AFTER_CLOSE;
        // Set timeopen and timeclose in the past.
        $choice->timeopen = $timenow - (60 * 60 * 24 * 3);
        $choice->timeclose = $timenow + (60 * 60 * 24 * 2);
        $DB->update_record('choice', $choice);

        $results = mod_choice_external::get_choice_results($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_results_returns(), $results);
        // We do not retrieve any response (activity is still open).
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
        }

        // We close the activity (setting timeclose in the past).
        $choice->timeclose = $timenow - (60 * 60 * 24 * 2);
        $DB->update_record('choice', $choice);
        // Now as Stundent2 we will see results!
        $results = mod_choice_external::get_choice_results($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_results_returns(), $results);
        // Create an array with optionID as Key.
        $resultsarr = array();
        foreach ($results['options'] as $option) {
            $resultsarr[$option['id']] = $option['userresponses'];
        }
        // The stundent1 is the userid who choosed the myanswer(option 3).
        $this->assertEquals($resultsarr[$myanswer][0]['userid'], $student1->id);
        // The stundent2 is the userid who didn't answered yet.
        $this->assertEquals($resultsarr[0][0]['userid'], $student2->id);

        // Do not publish user names!
        $choice->publish = 0;
        $DB->update_record('choice', $choice);
        $results = mod_choice_external::get_choice_results($choice->cmid);
        // We need to execute the return values cleaning process to simulate the web service server.
        $results = external_api::clean_returnvalue(mod_choice_external::get_choice_results_returns(), $results);
        // Create an array with optionID as Key.
        $resultsarr = array();
        // Does not show any user response!
        foreach ($results['options'] as $option) {
            $this->assertCount(0, $option['userresponses']);
            $resultsarr[$option['id']] = $option;
        }
        // But we can see totals and percentages.
        $this->assertEquals(1, $resultsarr[$myanswer]['numberofuser']);
    }
}
