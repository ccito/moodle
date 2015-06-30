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
 * LTI module external API
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * LTI module external functions
 *
 * @package    mod_lti
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_lti_external extends external_api {
    /**
     * Describes the parameters for get_ltis_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses_parameters() {
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
     * Returns a list of ltis in a provided list of courses,
     * if no list is provided all ltis that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of ltis details
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_ltis_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the ltis to return.
        $arrltis = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the ltis from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/lti:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/lti:view')
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
            // Get the ltis in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $ltis = get_all_instances_in_courses("lti", $arraycourses);
            foreach ($ltis as $lti) {
                $lticontext = context_module::instance($lti->coursemodule);
                // Entry to return.
                $ltidetails = array();
                // First, we return information that any user can see in the web interface.
                $ltidetails['id'] = $lti->id;
                $ltidetails['coursemodule']      = $lti->coursemodule;
                $ltidetails['course']            = $lti->course;
                $ltidetails['name']              = $lti->name;
                // Format intro.
                list($ltidetails['intro'], $ltidetails['introformat']) =
                    external_format_text($lti->intro, $lti->introformat,
                                            $lticontext->id, 'mod_lti', 'intro', null);
                if (has_capability('mod/lti:manage', $lticontext)) {
                    $ltidetails['timecreated']   = $lti->timecreated;
                    $ltidetails['timemodified']  = $lti->timemodified;
                    $ltidetails['typeid']        = $lti->typeid;
                    $ltidetails['toolurl']       = $lti->toolurl;
                    $ltidetails['securetoolurl'] = $lti->securetoolurl;
                    $ltidetails['instructorchoicesendname']      = $lti->instructorchoicesendname;
                    $ltidetails['instructorchoicesendemailaddr'] = $lti->instructorchoicesendemailaddr;
                    $ltidetails['instructorchoiceallowroster']   = $lti->instructorchoiceallowroster;
                    $ltidetails['instructorchoiceallowsetting']  = $lti->instructorchoiceallowsetting;
                    $ltidetails['instructorcustomparameters']    = $lti->instructorcustomparameters;
                    $ltidetails['instructorchoiceacceptgrades']  = $lti->instructorchoiceacceptgrades;
                    $ltidetails['grade']           = $lti->grade;
                    $ltidetails['launchcontainer'] = $lti->launchcontainer;
                    $ltidetails['resourcekey']     = $lti->resourcekey;
                    $ltidetails['password']        = $lti->password;
                    $ltidetails['debuglaunch']     = $lti->debuglaunch;
                    $ltidetails['showtitlelaunch'] = $lti->showtitlelaunch;
                    $ltidetails['showdescriptionlaunch'] = $lti->showdescriptionlaunch;
                    $ltidetails['servicesalt'] = $lti->servicesalt;

                    $ltidetails['icon']        = $lti->icon;
                    $ltidetails['secureicon']  = $lti->secureicon;

                    $ltidetails['section']     = $lti->section;
                    $ltidetails['visible']     = $lti->visible;
                    $ltidetails['groupmode']   = $lti->groupmode;
                    $ltidetails['groupingid']  = $lti->groupingid;
                }

                $arrltis[] = $ltidetails;
            }
        }
        $result = array();
        $result['ltis'] = $arrltis;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_ltis_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_ltis_by_courses_returns() {
        return new external_single_structure(
            array(
                'ltis' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'LTI id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'LTI name'),
                            'intro' => new external_value(PARAM_RAW, 'the LTI intro'),
                            'introformat' => new external_format_value('intro'),
                            'timecreated' => new external_value(PARAM_RAW, 'time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'typeid' => new external_value(PARAM_INT, 'type id', VALUE_OPTIONAL),
                            'toolurl' => new external_value(PARAM_URL, 'tool url', VALUE_OPTIONAL),
                            'securetoolurl' => new external_value(PARAM_URL, 'secure tool url', VALUE_OPTIONAL),
                            'instructorchoicesendname' => new external_value(PARAM_TEXT, 'instructor choice send name',
                                                                                                       VALUE_OPTIONAL),
                            'instructorchoicesendemailaddr' => new external_value(PARAM_BOOL, 'instructor choice send mail address',
                                                                                                                    VALUE_OPTIONAL),
                            'instructorchoiceallowroster' => new external_value(PARAM_BOOL, 'instructor choice allow roster',
                                                                                                             VALUE_OPTIONAL),
                            'instructorchoiceallowsetting' => new external_value(PARAM_BOOL, 'instructor choice allow setting',
                                                                                                               VALUE_OPTIONAL),
                            'instructorcustomparameters' => new external_value(PARAM_RAW, 'instructor custom parameters',
                                                                                                         VALUE_OPTIONAL),
                            'instructorchoiceacceptgrades' => new external_value(PARAM_BOOL, 'instructor choice accept grades',
                                                                                                               VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_INT, 'grade', VALUE_OPTIONAL),
                            'launchcontainer' => new external_value(PARAM_INT, 'launch container', VALUE_OPTIONAL),
                            'resourcekey' => new external_value(PARAM_RAW, 'resource key', VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'password', VALUE_OPTIONAL),
                            'debuglaunch' => new external_value(PARAM_INT, 'debug launch', VALUE_OPTIONAL),
                            'showtitlelaunch' => new external_value(PARAM_INT, 'show title launch', VALUE_OPTIONAL),
                            'showdescriptionlaunch' => new external_value(PARAM_INT, 'show description launch', VALUE_OPTIONAL),
                            'servicesalt' => new external_value(PARAM_RAW, 'service salt', VALUE_OPTIONAL),
                            'icon' => new external_value(PARAM_URL, 'icon', VALUE_OPTIONAL),
                            'secureicon' => new external_value(PARAM_URL, 'secure icon', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Learning Tools Interoperability activities'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
