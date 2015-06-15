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
 * Feedback module external API
 *
 * @package    mod_feedback
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * Feedback module external functions
 *
 * @package    mod_feedback
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_feedback_external extends external_api {
    /**
     * Describes the parameters for get_feedbacks_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_feedbacks_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course id'),
                    'Array of course ids', VALUE_DEFAULT, array()
                ),
            )
        );
    }
    /**
     * Returns a list of feedbacks in a provided list of courses,
     * if no list is provided all feedbacks that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of feedbacks details
     * @since Moodle 3.0
     */
    public static function get_feedbacks_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_feedbacks_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the feedbacks to return.
        $arrfeedbacks = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the feedbacks from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    // Check if this course was already loaded (by enrol_get_my_courses).
                    if (!isset($courses[$cid])) {
                        $courses[$cid] = get_course($cid);
                    }
                    $arraycourses[$cid] = $courses[$cid];
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context '.$e->getMessage()
                    );
                }
            }
            // Get the feedbacks in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $feedbacks = get_all_instances_in_courses("feedback", $arraycourses);
            foreach ($feedbacks as $feedback) {
                $feedbackcontext = context_module::instance($feedback->coursemodule);
                // Entry to return.
                $feedbackdetails = array();
                // First, we return information that any user can see in the web interface.
                $feedbackdetails['id'] = $feedback->id;
                $feedbackdetails['coursemodule']      = $feedback->coursemodule;
                $feedbackdetails['course']            = $feedback->course;
                $feedbackdetails['name']              = $feedback->name;
                // Format intro.
                list($feedbackdetails['intro'], $feedbackdetails['introformat']) =
                    external_format_text($feedback->intro, $feedback->introformat,
                                            $feedbackcontext->id, 'mod_feedback', 'intro', null);
                $feedbackdetails['anonymous']              = $feedback->anonymous;
                $feedbackdetails['email_notification']     = $feedback->email_notification;
                $feedbackdetails['multiple_submit']        = $feedback->multiple_submit;
                $feedbackdetails['autonumbering']          = $feedback->autonumbering;
                $feedbackdetails['site_after_submit']      = $feedback->site_after_submit;
                // Format intro.
                list($feedbackdetails['page_after_submit'], $feedbackdetails['page_after_submitformat']) =
                    external_format_text($feedback->page_after_submit, $feedback->page_after_submitformat,
                                            $feedbackcontext->id, 'mod_feedback', 'page_after_submit', null);

                $feedbackdetails['publish_stats'] = $feedback->publish_stats;
                $feedbackdetails['timeopen']      = $feedback->timeopen;
                $feedbackdetails['timeclose']     = $feedback->timeclose;
                if (has_capability('moodle/course:manageactivities', $feedbackcontext)) {
                    $feedbackdetails['timemodified']  = $feedback->timemodified;
                    $feedbackdetails['completionsubmit'] = $feedback->completionsubmit;
                    $feedbackdetails['section']       = $feedback->section;
                    $feedbackdetails['visible']       = $feedback->visible;
                    $feedbackdetails['groupmode']     = $feedback->groupmode;
                    $feedbackdetails['groupingid']    = $feedback->groupingid;
                }
                $arrfeedbacks[] = $feedbackdetails;
            }
        }
        $result = array();
        $result['feedbacks'] = $arrfeedbacks;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_feedbacks_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_feedbacks_by_courses_returns() {
        return new external_single_structure(
            array(
                'feedbacks' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Feedback id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Feedback name'),
                            'intro' => new external_value(PARAM_RAW, 'The Feedback intro'),
                            'introformat' => new external_format_value('intro'),
                            'anonymous' => new external_value(PARAM_INT, 'show anonymous or user names'),
                            'email_notification' => new external_value(PARAM_INT, 'If enabled, teachers will receive notification'.
                                                                                                      ' of feedback submissions.'),
                            'multiple_submit' => new external_value(PARAM_INT, 'If enabled for anonymous surveys, users can submit'.
                                                                                         ' feedback an unlimited number of times.'),
                            'autonumbering' => new external_value(PARAM_INT, 'Enables or disables automated numbers on question'),
                            'site_after_submit' => new external_value(PARAM_URL, 'After submitting the feedback, a continue button'.
                                   'is displayed, which links to the course page. Alternatively, it may link to the next activity '.
                                                                                     'if the URL of the activity is entered here.'),
                            'page_after_submit' => new external_value(PARAM_RAW, 'Completion message'),
                            'page_after_submitformat' => new external_format_value('page_after_submit'),
                            'publish_stats' => new external_value(PARAM_INT, 'Show analysis page'),
                            'timeopen' => new external_value(PARAM_RAW, 'time of activity opening'),
                            'timeclose' => new external_value(PARAM_RAW, 'time of activity closure'),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'completionsubmit' => new external_value(PARAM_INT, 'If enabled, teachers will receive notification of'.
                                                                                          ' feedback submissions.', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Feedbacks'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
