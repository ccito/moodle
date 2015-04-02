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
 * External notes functions unit tests
 *
 * @package    core_rating
 * @category   external
 * @copyright  015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/rating/classes/external.php');
require_once($CFG->dirroot . '/mod/forum/externallib.php');
require_once($CFG->dirroot . '/rating/lib.php');

class core_rating_externallib_testcase extends externallib_advanced_testcase {

    /**
     * Test create_notes
     */
    public function test_get_item_ratings() {

        global $DB, $USER;

        $this->resetAfterTest(true);

        $course = self::getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));

        $contextid = context_course::instance($course->id)->id;
        // Give view all ratings capability to teachers.
        $this->assignUserCapability('moodle/rating:viewall', $contextid, $teacherrole->id);

        $this->getDataGenerator()->enrol_user($student->id,  $course->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher1->id, $course->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher2->id, $course->id, $teacherrole->id);

        // Create the forum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course->id;
        // Set Aggregate type = Average of ratings.
        $record->assessed = RATING_AGGREGATE_AVERAGE;
        $forum = self::getDataGenerator()->create_module('forum', $record);

        // Add discussion to the forums.
        $record = new stdClass();
        $record->course = $course->id;
        $record->userid = $student->id;
        $record->forum = $forum->id;
        $discussion = self::getDataGenerator()->get_plugin_generator('mod_forum')->create_discussion($record);

        // Rete the discussion as teacher1.
        $rating1 = new stdClass();
        $rating1->contextid = $contextid;
        $rating1->component = 'mod_forum';
        $rating1->ratingarea = 'post';
        $rating1->itemid = $discussion->id;
        $rating1->rating = 90;
        $rating1->scaleid = 100;
        $rating1->userid = $teacher1->id;
        $rating1->timecreated = time();
        $rating1->timemodified = time();
        $rating1->id = $DB->insert_record('rating', (array) $rating1);

        // Rete the discussion as teacher2.
        $rating2 = new stdClass();
        $rating2->contextid = $contextid;
        $rating2->component = 'mod_forum';
        $rating2->ratingarea = 'post';
        $rating2->itemid = $discussion->id;
        $rating2->rating = 95;
        $rating2->scaleid = 100;
        $rating2->userid = $teacher1->id;
        $rating2->timecreated = time();
        $rating2->timemodified = time();
        $rating2->id = $DB->insert_record('rating', (array) $rating2);

        $ratings = core_rating_external::get_item_ratings_returns('module', $course->id, 'mod_forum', 'post', $discussion->id, 100);
        // We need to execute the return values cleaning process to simulate the web service server.
        $ratings = external_api::clean_returnvalue(core_rating_external::get_item_ratings_returns(), $ratings);
        $this->assertEquals(2, count($ratings['ratings']));

        $indexedratings = array();
        foreach ($ratings['ratings'] as $rating) {
            $indexedratings[$rating->id] = $rating;
        }
        $this->assertEquals($rating1->rating.' / '.$rating1->scaleid, count($indexedratings[$rating1]['rating']));
        $this->assertEquals($rating2->rating.' / '.$rating2->scaleid, count($indexedratings[$rating2]['rating']));

        $this->assertEquals(fullname($rating1), $indexedratings[$rating1]['fullname']);
        $this->assertEquals(fullname($rating2), $indexedratings[$rating2]['fullname']);

        $this->assertEquals($rating1->userid, $indexedratings[$rating1]['userid']);
        $this->assertEquals($rating2->userid, $indexedratings[$rating2]['userid']);

    }
}
