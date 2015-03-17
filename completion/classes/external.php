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
 * External completion API
 *
 * @package    core_completion
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/completionlib.php");
/**
 * External completion API functions
 *
 * @package    core_completion
 * @category   external
 * @copyright  Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 2.9
 */
class core_completion_external extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */

    public static function get_course_completion_status_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'userid'   => new external_value(PARAM_INT, 'User ID'),
            )
        );
    }
    /**
     * Get Course completion status
     *
     * @param int $courseid ID of the Course
     * @param int $userid ID of the User
     * @return array of course completion status and warnings
     * @since Moodle 2.9
     */
    public static function get_course_completion_status($courseid, $userid) {
        global $CFG, $USER;

        require_once($CFG->libdir . '/completionlib.php');
        $warnings = array();
        $arrayparams = array(
            'courseid' => $courseid,
            'userid'   => $userid,
        );
        $params = self::validate_parameters(self::get_course_completion_status_parameters(), $arrayparams);
        $course = get_course($params['courseid']);
        $user = core_user::get_user($params['userid'], 'id', MUST_EXIST);
        $context = context_course::instance($course->id);
        self::validate_context($context);

        // Can current user see user's course completion status?
        // This check verifies if completion is enabled because $course is mandatory.
        if (!completion_can_view_data($user->id, $course)) {
            throw new moodle_exception('cannotviewreport');
        }

        $info = new completion_info($course);

        // Check this user is enroled.
        if (!$info->is_tracked_user($user->id)) {
            if ($USER->id == $user->id) {
                throw new moodle_exception('notenroled', 'completion');
            } else {
                throw new moodle_exception('usernotenroled', 'completion');
            }
        }

        $completions = $info->get_completions($user->id);
        if (empty($completions)) {
            throw new moodle_exception('err_nocriteria', 'completion');
        }

        // Has this user completed any criteria?
        $criteriacomplete = $info->count_course_user_data($user->id);

        // Load course completion.
        $completionparams = array(
            'userid' => $user->id,
            'course' => $course->id,
        );
        $ccompletion = new completion_completion($completionparams);

        // Is course complete?
        if ($info->is_course_complete($user->id)) {
            $coursecompletionstatus = 'complete';
        } else if (!$criteriacomplete && !$ccompletion->timestarted) {
            $coursecompletionstatus = 'notyetstarted';
        } else {
            $coursecompletionstatus = 'inprogress';
        }

        $rows = array();
        // Loop through course criteria.
        foreach ($completions as $completion) {
            $criteria = $completion->get_criteria();

            $row = array();
            $row['type'] = $criteria->criteriatype;
            $row['title'] = $criteria->get_title();
            $row['status'] = $completion->get_status();
            $row['complete'] = $completion->is_complete();
            $row['timecompleted'] = $completion->timecompleted;
            $row['details'] = $criteria->get_details($completion);
            $rows[] = $row;
        }
        $result = array(
                  'status'      => $coursecompletionstatus,
                  'aggregation' => $info->get_aggregation_method(),
                  'completions' => $rows
        );

        $results = array(
            'status' => $result,
            'warnings' => $warnings
        );
        return $results;

    }
    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function get_course_completion_status_returns() {
        return new external_single_structure(
            array(
                'status' => new external_single_structure(
                    array(
                        'status'        => new external_value(PARAM_ALPHA, 'notyetstarted,inprogress,complete'),
                        'aggregation'   => new external_value(PARAM_INT, 'Aggregation: COMPLETION_AGGREGATION_ALL/ANY'),
                        'completions'   => new external_multiple_structure(
                            new external_single_structure(
                            array(
                                 'type'          => new external_value(PARAM_INT,   'Completion criteria type'),
                                 'title'         => new external_value(PARAM_TEXT,  'Completion criteria Title'),
                                 'status'        => new external_value(PARAM_TEXT, 'Completion status (Yes/No)'),
                                 'complete'      => new external_value(PARAM_BOOL,   'Completion status (true/false)'),
                                 'timecompleted' => new external_value(PARAM_INT,   'Timestamp for criteria completetion'),
                                 'details' => new external_single_structure(
                                     array(
                                         'type' => new external_value(PARAM_TEXT, 'Type description'),
                                         'criteria' => new external_value(PARAM_RAW, 'Criteria description'),
                                         'requirement' => new external_value(PARAM_TEXT, 'Requirement description'),
                                         'status' => new external_value(PARAM_TEXT, 'Status description'),
                                         ), 'details'),
                                 ), 'Completions'
                            ), ''
                         )
                    ), 'Course status'
                ),
                'warnings' => new external_warnings()
            ), 'Course completion status'
        );
    }
}