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
 * URL external API
 *
 * @package    mod_url
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

/**
 * URL external functions
 *
 * @package    mod_url
 * @category   external
 * @copyright  2015 Juan Leyva <juan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_url_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function view_url_parameters() {
        return new external_function_parameters(
            array(
                'urlid' => new external_value(PARAM_INT, 'url instance id')
            )
        );
    }

    /**
     * Simulate the url/view.php web interface page: trigger events, completion, etc...
     *
     * @param int $urlid the url instance id
     * @return array of warnings and status result
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function view_url($urlid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/url/lib.php");

        $params = self::validate_parameters(self::view_url_parameters(),
                                            array(
                                                'urlid' => $urlid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $url = $DB->get_record('url', array('id' => $params['urlid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($url, 'url');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/url:view', $context);

        // Call the url/lib API.
        url_view($url, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function view_url_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_urls_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_urls_by_courses_parameters() {
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
     * Returns a list of urls in a provided list of courses,
     * if no list is provided all urls that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of urls details
     * @since Moodle 3.0
     */
    public static function get_urls_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_urls_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the urls to return.
        $arrurls = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the urls from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/url:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/url:view')
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
            // Get the urls in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $urls = get_all_instances_in_courses("url", $arraycourses);
            foreach ($urls as $url) {
                $urlcontext = context_module::instance($url->coursemodule);
                // Entry to return.
                $urldetails = array();
                // First, we return information that any user can see in the web interface.
                $urldetails['id'] = $url->id;
                $urldetails['coursemodule']      = $url->coursemodule;
                $urldetails['course']            = $url->course;
                $urldetails['name']              = $url->name;
                // Format intro.
                list($urldetails['intro'], $urldetails['introformat']) =
                    external_format_text($url->intro, $url->introformat,
                                            $urlcontext->id, 'mod_url', 'intro', null);
                $urldetails['externalurl']    = $url->externalurl;
                $urldetails['display']        = $url->display;
                $urldetails['displayoptions'] = $url->displayoptions;
                $urldetails['parameters']     = $url->parameters;
                if (has_capability('moodle/course:manageactivities', $urlcontext)) {
                    $urldetails['section']        = $url->section;
                    $urldetails['visible']        = $url->visible;
                    $urldetails['timemodified']   = $url->timemodified;
                    $urldetails['groupmode']      = $url->groupmode;
                    $urldetails['groupingid']     = $url->groupingid;
                }

                $arrurls[] = $urldetails;
            }
        }
        $result = array();
        $result['urls'] = $arrurls;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_urls_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_urls_by_courses_returns() {
        return new external_single_structure(
            array(
                'urls' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'URL id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'URL name'),
                            'intro' => new external_value(PARAM_RAW, 'The URL intro'),
                            'introformat' => new external_format_value('intro'),
                            'externalurl' => new external_value(PARAM_URL, 'externalurl'),
                            'display' => new external_value(PARAM_INT, 'display'),
                            'displayoptions' => new external_value(PARAM_RAW, 'display options'),
                            'parameters' => new external_value(PARAM_RAW, 'parameters'),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'URLs'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }

}
