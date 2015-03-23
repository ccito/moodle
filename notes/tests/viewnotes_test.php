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
 * External completion functions unit tests
 *
 * @package    core_completion
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
/**
 * External completion functions unit tests
 *
 * @package    core_completion
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_notes_viewnotes_testcase extends externallib_advanced_testcase {
    /**
     * Test update_activity_completion_status
     */
    public function test_get_activities_completion_status() {
        global $DB, $CFG;
        $this->resetAfterTest(true);
        $CFG->enablecompletion = true;
        
        // take role definitions.
        $studentrole = $DB->get_record('role', array('shortname' => 'student'));
        $teacherrole = $DB->get_record('role', array('shortname' => 'teacher'));
        
        // create students and teachers.        
        $student1 = $this->getDataGenerator()->create_user();
        $student2 = $this->getDataGenerator()->create_user();
        $teacher1 = $this->getDataGenerator()->create_user();
        $teacher2 = $this->getDataGenerator()->create_user();
        $courseA = $this->getDataGenerator()->create_course();
        $courseB = $this->getDataGenerator()->create_course();
        
        // Enroll students and teachers to COURSE-A.
        $this->getDataGenerator()->enrol_user($student1->id, $courseA->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $courseA->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($teacher1->id, $courseA->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher2->id, $courseA->id, $teacherrole->id);
         // Enroll students and teachers to COURSE-B (teacher1 is not enrolled in CourseB)
        $this->getDataGenerator()->enrol_user($student1->id, $courseB->id, $studentrole->id);
        $this->getDataGenerator()->enrol_user($student2->id, $courseB->id, $studentrole->id);
        //$this->getDataGenerator()->enrol_user($teacher1->id, $courseB->id, $teacherrole->id);
        $this->getDataGenerator()->enrol_user($teacher2->id, $courseB->id, $teacherrole->id);       
        
        // Generate notes.
        $gen = $this->getDataGenerator()->get_plugin_generator('core_notes');
        
        // notaA1: on student1 (CursoA) by Teacher1
        $params = array('courseid' => $courseA->id, 'userid' => $student1->id, 'publishstate' => NOTES_STATE_PUBLIC, 'userdmodified' => $teacher1->id);
        $noteA1 = $gen->create_instance($params);
        //notaA2: on student2 (CursoA) by Teacher1
        $params = array('courseid' => $courseA->id, 'userid' => $student1->id, 'publishstate' => NOTES_STATE_PUBLIC, 'userdmodified' => $teacher1->id);
        $noteA2 = $gen->create_instance($params);
        //notaS1: on student1 SITE-LEVEL by teacher1
        $params = array('courseid' => $courseA->id, 'userid' => $student1->id, 'publishstate' => NOTES_STATE_SITE, 'userdmodified' => $teacher1->id);
        $noteS1 = $gen->create_instance($params);
        //notaP1: on student1 PERSONAL by teacher1
        $params = array('courseid' => $courseA->id, 'userid' => $student1->id, 'publishstate' => NOTES_STATE_PUBLIC, 'userdmodified' => $teacher1->id);
        $noteP1 = $gen->create_instance($params);
        //notaB1: on student1 (CursoB) by teacher1
        $params = array('courseid' => $courseB->id, 'userid' => $student1->id, 'publishstate' => NOTES_STATE_PUBLIC, 'userdmodified' => $teacher1->id);
        $noteB1 = $gen->create_instance($params);
        

        $this->setUser($teacher1);
        $result = core_notes_external::get_course_notes($courseA->id, $student1->id);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);           
        $this->assertArrayHasKey($noteS1->id, $result['sitenotes']);
        $this->assertArrayHasKey($noteA1->id, $result['coursenotes']);
        $this->assertArrayHasKey($noteP1->id, $result['personalnotes']);
        
        $result = core_notes_external::get_course_notes($courseB->id, $student1->id);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);
        $this->assertEmpty($result['sitenotes']);
        $this->assertEmpty($result['coursenotes']);
        $this->assertEmpty($result['personalnotes']);
        
        $result = core_notes_external::get_course_notes(0, $student1->id);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);           
        $this->assertEmpty($result['sitenotes']);
        $this->assertArrayHasKey($noteA1->id, $result['coursenotes']);
        $this->assertArrayHasKey($noteB1->id, $result['coursenotes']);
        
        $this->setAdminUser();
        $result = core_notes_external::get_course_notes(0, $student1->id);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);
        $this->assertArrayHasKey($noteS1->id, $result['sitenotes']);
        
        $this->setUser($teacher1);
        $result = core_notes_external::get_course_notes(0, 0);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);
        $this->assertEmpty($result['sitenotes']);
        $this->assertEmpty($result['coursenotes']);
        $this->assertEmpty($result['personalnotes']);
        
        $this->setUser($teacher2);
        $result = core_notes_external::get_course_notes($courseA->id, $student1->id);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);           
        $this->assertArrayHasKey($noteS1->id, $result['sitenotes']);
        $this->assertArrayHasKey($noteA1->id, $result['coursenotes']);
        
        $this->setUser($teacher2);
        $result = core_notes_external::get_course_notes($courseA->id, 0);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);           
        $this->assertArrayHasKey($noteS1->id, $result['sitenotes']);
        $this->assertArrayHasKey($noteA1->id, $result['coursenotes']);
        $this->assertArrayHasKey($noteA2->id, $result['coursenotes']);
        
        $this->setUser($teacher1);
        $result = core_notes_external::get_course_notes($courseA->id, 0);
        $result = external_api::clean_returnvalue(core_notes_external::get_course_notes_returns(), $result);           
        $this->assertArrayHasKey($noteP1->id, $result['personalnotes']);

    }
}