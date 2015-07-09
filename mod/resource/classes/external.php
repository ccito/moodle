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
 * Resource module external API
 *
 * @package    mod_resource
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * Resource module external functions
 *
 * @package    mod_resource
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_resource_external extends external_api {
    /**
     * Describes the parameters for get_resources_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_resources_by_courses_parameters() {
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
     * Returns a list of resources in a provided list of courses,
     * if no list is provided all resources that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of resources details
     * @since Moodle 3.0
     */
    public static function get_resources_by_courses($courseids = array()) {
        global $CFG, $USER, $DB;
        $params = self::validate_parameters(self::get_resources_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the resources to return.
        $arrresources = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the resources from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/resource:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/resource:view')
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
            // Get the resources in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $resources = get_all_instances_in_courses("resource", $arraycourses);
            foreach ($resources as $resource) {
                $resourcecontext = context_module::instance($resource->coursemodule);
                // Entry to return.
                $resourcedetails = array();
                // First, we return information that any user can see in the web interface.
                $resourcedetails['id'] = $resource->id;
                $resourcedetails['coursemodule']      = $resource->coursemodule;
                $resourcedetails['course']            = $resource->course;
                $resourcedetails['name']              = $resource->name;
                // Format intro.
                list($resourcedetails['intro'], $resourcedetails['introformat']) =
                    external_format_text($resource->intro, $resource->introformat,
                                            $resourcecontext->id, 'mod_resource', 'intro', null);
                $resourcedetails['tobemigrated']      = $resource->tobemigrated;
                $resourcedetails['legacyfiles']       = $resource->legacyfiles;
                $resourcedetails['legacyfileslast']   = $resource->legacyfileslast;
                $resourcedetails['display']           = $resource->display;
                $resourcedetails['displayoptions']    = $resource->displayoptions;
                $resourcedetails['filterfiles']       = $resource->filterfiles;
                $resourcedetails['revision']          = $resource->revision;
                $resourcedetails['timemodified']      = $resource->timemodified;
                $resourcedetails['section']           = $resource->section;
                $resourcedetails['visible']           = $resource->visible;
                $resourcedetails['groupmode']         = $resource->groupmode;
                $resourcedetails['groupingid']        = $resource->groupingid;

                $arrresources[] = $resourcedetails;
            }
        }
        $result = array();
        $result['resources'] = $arrresources;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_resources_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_resources_by_courses_returns() {
        return new external_single_structure(
            array(
                'resources' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Resource id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Resource name'),
                            'intro' => new external_value(PARAM_RAW, 'The Resource intro'),
                            'introformat' => new external_format_value('intro'),
                            'tobemigrated' => new external_value(PARAM_INT,
                                '0 means new resource file created in 2.0 or later, 1 means old plug-in type not migrated yet'),
                            'legacyfiles' => new external_value(PARAM_INT,
                                '0 means no legacy files, 1 means on-demand migration off, 2 means on-demand migration on'),
                            'legacyfileslast' => new external_value(PARAM_RAW, 'timestamp, last date of on-demand migration'),
                            'display' => new external_value(PARAM_INT,
                                'display id: Automatic/Embed/Force download/Open/In pop-up/In frame/New Window'),
                            'displayoptions' => new external_value(PARAM_RAW, 'arbitrary display options - serialized PHP array'),
                            'filterfiles' => new external_value(PARAM_INT, 'filterfiles id: None/All files/HMTL files only'),
                            'revision' => new external_value(PARAM_INT, 'revision counter, incremented when any file changed'),
                            'timemodified' => new external_value(PARAM_RAW, 'the timestamp when the module was modified'),
                            'section' => new external_value(PARAM_INT, 'course section id'),
                            'visible' => new external_value(PARAM_BOOL, 'visible'),
                            'groupmode' => new external_value(PARAM_INT, 'group mode'),
                            'groupingid' => new external_value(PARAM_INT, 'group id'),
                        ), 'Resources'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
