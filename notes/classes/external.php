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
 * External notes API
 *
 * @package    core_notes
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/notes/lib.php");

/**
 * External notes API functions
 *
 * @package    core_notes
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_notes_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function get_course_notes_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'userid'   => new external_value(PARAM_INT, 'User ID', VALUE_OPTIONAL),
            )
        );
    }

    /**
     * Get notes
     *
     * @param int $courseid ID of the Course
     * @param int $userid ID of the User
     * @param int $steate
     * @param int $author
     * @return array of notes
     * @since Moodle 2.9
     */
    public static function create_note_list($courseid=0, $userid=0, $state = '', $author = 0) {
        $results = array();
        $notes = note_list($courseid, $userid, $state, $author);
        foreach ($notes as $key => $note) {
            $note = (array)$note;
            list($note['content'], $note['format']) = external_format_text($note['content'],
                                                                           $note['format'],
                                                                           $courseid,
                                                                           '',
                                                                           '',
                                                                           0);
            $results[$key] = $note;
        }
        return $results;
    }

    /**
     * Get Course notes
     *
     * @param int $courseid ID of the Course
     * @param int $userid ID of the User
     * @return array of site, course and personal notes and warnings
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function get_course_notes($courseid, $userid=0) {
        global $CFG, $USER;

        if (empty($CFG->enablenotes)) {
            throw new moodle_exception('notesdisabled', 'notes');
        }
        $warnings = array();
        $arrayparams = array(
            'courseid' => $courseid,
            'userid'   => $userid,
        );
        $params = self::validate_parameters(self::get_course_notes_parameters(), $arrayparams);

        if (empty($params['courseid'])) {
            $params['courseid'] = SITEID;
        }
        $user = null;
        if (!empty($params['userid'])) {
            $user = core_user::get_user($params['userid'], 'id', MUST_EXIST);
        }

        $course = get_course($params['courseid']);

        if ($course->id == SITEID) {
            $coursecontext = context_system::instance();
        } else {
            $coursecontext = context_course::instance($course->id);
        }
        self::validate_context($coursecontext);

        $sitenotes = array();
        $coursenotes = array();
        $personalnotes = array();

        if ($course->id != SITEID) {

            if (has_capability('moodle/notes:view', $coursecontext)) {
                $sitenotes = self::create_note_list($course->id, $params['userid'], NOTES_STATE_SITE);
                $coursenotes += self::create_note_list($course->id, $params['userid'], NOTES_STATE_PUBLIC);
                $personalnotes = self::create_note_list($course->id, $params['userid'], NOTES_STATE_DRAFT, $USER->id);
            }
        } else {
            if (has_capability('moodle/notes:view', $coursecontext)) {
                $sitenotes = self::create_note_list(0, $params['userid'], NOTES_STATE_SITE);
            }
            // It returns notes only for a specific user!
            if (!empty($user)) {
                foreach (enrol_get_users_courses($user->id, true) as $c) {
                    // All notes at course level, only if we have capability on every course.
                    if (has_capability('moodle/notes:view', context_course::instance($c->id))) {
                        $coursenotes += self::create_note_list($c->id, $params['userid'], NOTES_STATE_PUBLIC);
                    }
                }
            }
        }

        $results = array(
            'sitenotes'     => $sitenotes,
            'coursenotes'   => $coursenotes,
            'personalnotes' => $personalnotes,
            'warnings'      => $warnings
        );
        return $results;

    }

    /**
     * Returns array of note structure
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_note_structure() {
        return array(
                     'id'           => new external_value(PARAM_INT, 'ID of this Note'),
                     'courseid'     => new external_value(PARAM_INT, 'ID of the course'),
                     'userid'       => new external_value(PARAM_INT, 'User ID'),
                     'content'      => new external_value(PARAM_RAW, 'The content text formated'),
                     'format'       => new external_format_value('content'),
                     'created'      => new external_value(PARAM_INT, 'Time created (timestamp)'),
                     'lastmodified' => new external_value(PARAM_INT, 'Time of last modification (timestamp)'),
                     'usermodified' => new external_value(PARAM_INT, 'User ID of the creator of this Note'),
                     'publishstate' => new external_value(PARAM_ALPHA, "State of the note (i.e. draft, public, site, '' "),
        );
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_course_notes_returns() {
        return new external_single_structure(
            array(
                  'sitenotes' => new external_multiple_structure(
                      new external_single_structure(
                          self::get_note_structure() , ''
                      ), 'Site Notes', VALUE_OPTIONAL
                   ),
                   'coursenotes' => new external_multiple_structure(
                      new external_single_structure(
                          self::get_note_structure() , ''
                      ), 'Couse Notes', VALUE_OPTIONAL
                   ),
                   'personalnotes' => new external_multiple_structure(
                      new external_single_structure(
                          self::get_note_structure() , ''
                      ), 'Personal Notes', VALUE_OPTIONAL
                   ),
                 'warnings' => new external_warnings()
            ), 'Notes'
        );
    }
}
