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
 * @package    mod_page
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
 * @package    mod_page
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_page_external extends external_api {
    /**
     * Describes the parameters for get_pages_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_pages_by_courses_parameters() {
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
     * Returns a list of pages in a provided list of courses,
     * if no list is provided all pages that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of pages details
     * @since Moodle 3.0
     */
    public static function get_pages_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_pages_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the pages to return.
        $arrpages = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the pages from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/page:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/page:view')
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
            // Get the pages in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $pages = get_all_instances_in_courses("page", $arraycourses);
            foreach ($pages as $page) {
                $pagecontext = context_module::instance($page->coursemodule);
                // Entry to return.
                $pagedetails = array();
                // First, we return information that any user can see in the web interface.
                $pagedetails['id'] = $page->id;
                $pagedetails['coursemodule']      = $page->coursemodule;
                $pagedetails['course']            = $page->course;
                $pagedetails['name']              = $page->name;
                // Format intro.
                list($pagedetails['intro'], $pagedetails['introformat']) =
                    external_format_text($page->intro, $page->introformat,
                                            $pagecontext->id, 'mod_page', 'intro', null);
                list($pagedetails['content'], $pagedetails['contentformat']) =
                    external_format_text($page->content, $page->contentformat,
                                            $pagecontext->id, 'mod_page', 'content', null);
                $pagedetails['legacyfiles']       = $page->legacyfiles;
                $pagedetails['legacyfileslast']   = $page->legacyfileslast;
                $pagedetails['display']           = $page->display;
                $pagedetails['displayoptions']    = $page->displayoptions;
                $pagedetails['revision']          = $page->revision;
                $pagedetails['timemodified']      = $page->timemodified;
                $pagedetails['section']           = $page->section;
                $pagedetails['visible']           = $page->visible;
                $pagedetails['groupmode']         = $page->groupmode;
                $pagedetails['groupingid']        = $page->groupingid;
                $arrpages[] = $pagedetails;
            }
        }
        $result = array();
        $result['pages'] = $arrpages;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_pages_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_pages_by_courses_returns() {
        return new external_single_structure(
            array(
                'pages' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Page id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Page name'),
                            'intro' => new external_value(PARAM_RAW, 'The Page intro'),
                            'introformat' => new external_format_value('intro'),
                            'content' => new external_value(PARAM_RAW, 'The Page content'),
                            'contentformat' => new external_format_value('content'),
                            'legacyfiles' => new external_value(PARAM_INT, 'legacy files'),
                            'legacyfileslast' => new external_value(PARAM_INT, 'legacy files last'),
                            'display' => new external_value(PARAM_INT, 'display'),
                            'displayoptions' => new external_value(PARAM_RAW, 'display options'),
                            'revision' => new external_value(PARAM_INT, 'revision'),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification'),
                            'section' => new external_value(PARAM_INT, 'course section id'),
                            'visible' => new external_value(PARAM_BOOL, 'visible'),
                            'groupmode' => new external_value(PARAM_INT, 'group mode'),
                            'groupingid' => new external_value(PARAM_INT, 'group id'),
                        ), 'Page'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
