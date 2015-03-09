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
    public static function get_activities_completion_status_parameters() {
        return new external_function_parameters(
            array(
                'courseid' => new external_value(PARAM_INT, 'Course ID'),
                'userid'   => new external_value(PARAM_INT, 'User ID'),
            )
        );
    }
    /**
     * Get Activities completion status
     *
     * @param int $courseid ID of the Course
     * @param int $userid ID of the User
     * @return array of activities progress and warnings
     * @since Moodle 2.9
     */
    public static function get_activities_completion_status($courseid, $userid) {
        global $CFG, $USER;
        require_once($CFG->libdir . '/grouplib.php');
        $warnings = array();
        $arrayparams = array(
            'courseid' => $courseid,
            'userid'   => $userid,
        );

        $params = self::validate_parameters(self::get_activities_completion_status_parameters(), $arrayparams);

        $course = get_course($params['courseid']);
        $user = core_user::get_user($params['userid'], 'id', MUST_EXIST);

        $context = context_course::instance($course->id);
        self::validate_context($context);

        // Check that current user have permissions to see this user's activities.
        if ($user->id != $USER->id) {
            require_capability('report/progress:view', $context);
            $group     = groups_get_course_group($course, true);
            $groupmode = groups_get_course_groupmode($course);
            if ($group === 0 && $groupmode == SEPARATEGROUPS &&
                    !has_capability('moodle/site:accessallgroups', $context)) {

                $usergroups = groups_get_all_groups($course->id, $user->id);
                $currentusergroups = groups_get_all_groups($course->id, $USER->id);
                $samegroups = array_intersect_key($currentusergroups, $usergroups);
                if (empty($samegroups)) {
                    // We are not in the same group!
                    throw new moodle_exception('accessdenied', 'admin');
                }
            }
        }

        $completion = new completion_info($course);
        $activities = $completion->get_activities();
        $progresses = $completion->get_progress_all();
        $userprogress = $progresses[$user->id];

        $results = array();
        foreach ($activities as $activity) {

            // Check if current user has visibility on this activity.
            if (!$activity->uservisible) {
                continue;
            }

            // Get progress information and state.
            if (array_key_exists($activity->id, $userprogress->progress)) {
                $thisprogress  = $userprogress->progress[$activity->id];
                $state         = $thisprogress->completionstate;
                $timecompleted = $thisprogress->timemodified;
            } else {
                $state = COMPLETION_INCOMPLETE;
                $timecompleted = 0;
            }

            $results[] = array(
                       'cmid'          => $activity->id,
                       'modname'       => $activity->modname,
                       'instance'      => $activity->instance,
                       'state'         => $state,
                       'timecompleted' => $timecompleted,
                       'tracking'      => $activity->completion == COMPLETION_TRACKING_AUTOMATIC ? 'auto' : 'manual'
            );
        }

        $results = array(
            'statuses' => $results,
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
    public static function get_activities_completion_status_returns() {
        return new external_single_structure(
            array(
            	'statuses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'cmid'          => new external_value(PARAM_INT,    'Comment ID'),
                            'modname'       => new external_value(PARAM_PLUGIN, 'Activity module name'),
                            'instance'      => new external_value(PARAM_INT,    'Instance ID'),
                            'state'         => new external_value(PARAM_INT,    'Completion state value'),
                            'timecompleted' => new external_value(PARAM_INT,    'Timestamp for completed activity'),
                            'tracking'      => new external_value(PARAM_ALPHA,  'Tracking (auto/manual)'),
                        ), 'Activity'
                    ), 'List of activities status'
                ),
                'warnings' => new external_warnings()
            )
        );
    }
}
