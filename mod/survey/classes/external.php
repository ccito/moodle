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
 * Book module external API
 *
 * @package    mod_survey
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * Book module external functions
 *
 * @package    mod_survey
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_survey_external extends external_api {
    /**
     * Describes the parameters for get_survey_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_surveys_by_courses_parameters() {
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
     * Returns a list of survey in a provided list of courses,
     * if no list is provided all survey that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of survey details
     * @since Moodle 3.0
     */
    public static function get_surveys_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_surveys_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the survey to return.
        $arrsurvey = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the survey from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/survey:participate', $context)) {
                        // Check if this course was already loaded (by enrol_get_my_courses).
                        if (!isset($courses[$cid])) {
                            $courses[$cid] = get_course($cid);
                        }
                        $arraycourses[$cid] = $courses[$cid];
                    } else {
                        $warnings[] = array(
                            'item' => 'course',
                            'itemid' => $cid,
                            'warningcode' => '2',
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/survey:participate')
                        );
                    }
                } catch (Exception $e) {
                    $warnings[] = array(
                        'item' => 'course',
                        'itemid' => $cid,
                        'warningcode' => '1',
                        'message' => 'No access rights in course context '.$e->getMessage()
                    );
                }
            }
            // Get the survey in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $surveys = get_all_instances_in_courses("survey", $arraycourses);
            foreach ($surveys as $survey) {
                $surveycontext = context_module::instance($survey->coursemodule);
                // Entry to return.
                $surveydetails = array();
                // First, we return information that any user can see in the web interface.
                $surveydetails['id'] = $survey->id;
                $surveydetails['coursemodule']      = $survey->coursemodule;
                $surveydetails['course']            = $survey->course;
                $surveydetails['name']              = $survey->name;
                // Format intro.
                list($surveydetails['intro'], $surveydetails['introformat']) =
                    external_format_text($survey->intro, $survey->introformat,
                                            $surveycontext->id, 'mod_survey', 'intro', null);
                $surveydetails['template']      = $survey->template;
                $surveydetails['days']          = $survey->days;
                $surveydetails['timecreated']   = $survey->timecreated;
                $surveydetails['timemodified']  = $survey->timemodified;
                $surveydetails['questions']     = $survey->questions;
                if (has_capability('moodle/course:manageactivities', $surveycontext)) {
                    $surveydetails['section']       = $survey->section;
                    $surveydetails['visible']       = $survey->visible;
                    $surveydetails['groupmode']     = $survey->groupmode;
                    $surveydetails['groupingid']    = $survey->groupingid;
                }
                $arrsurvey[] = $surveydetails;
            }
        }
        $result = array();
        $result['surveys'] = $arrsurvey;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_survey_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_surveys_by_courses_returns() {
        return new external_single_structure(
            array(
                'surveys' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Survey id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Survey name'),
                            'intro' => new external_value(PARAM_RAW, 'The Survey intro'),
                            'introformat' => new external_format_value('intro'),
                            'template' => new external_value(PARAM_INT, 'template'),
                            'days' => new external_value(PARAM_INT, 'days'),
                            'timecreated' => new external_value(PARAM_RAW, 'time of creation'),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification'),
                            'questions' => new external_value(PARAM_RAW, 'questions'),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Surveys'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
