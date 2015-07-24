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
 * Wiki module external API
 *
 * @package    mod_wiki
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * Wiki module external functions
 *
 * @package    mod_wiki
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_wiki_external extends external_api {
    /**
     * Describes the parameters for get_wikis_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_wikis_by_courses_parameters() {
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
     * Returns a list of wikis in a provided list of courses,
     * if no list is provided all wikis that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of wikis details
     * @since Moodle 3.0
     */
    public static function get_wikis_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_wikis_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the wikis to return.
        $arrwikis = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the wikis from.
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
            // Get the wikis in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $wikis = get_all_instances_in_courses("wiki", $arraycourses);
            foreach ($wikis as $wiki) {
                $wikicontext = context_module::instance($wiki->coursemodule);
                // Entry to return.
                $wikidetails = array();
                // First, we return information that any user can see in the web interface.
                $wikidetails['id'] = $wiki->id;
                $wikidetails['coursemodule']      = $wiki->coursemodule;
                $wikidetails['course']            = $wiki->course;
                $wikidetails['name']              = $wiki->name;
                // Format intro.
                list($wikidetails['intro'], $wikidetails['introformat']) =
                    external_format_text($wiki->intro, $wiki->introformat,
                                            $wikicontext->id, 'mod_wiki', 'intro', null);
                if (has_capability('mod/wiki:managewiki', $wikicontext)) {
                    $wikidetails['timecreated']    = $wiki->timecreated;
                    $wikidetails['timemodified']   = $wiki->timemodified;
                    $wikidetails['firstpagetitle'] = $wiki->firstpagetitle;
                    $wikidetails['wikimode']       = $wiki->wikimode;
                    $wikidetails['defaultformat']  = $wiki->defaultformat;
                    $wikidetails['forceformat']    = $wiki->forceformat;
                    $wikidetails['editbegin']      = $wiki->editbegin;
                    $wikidetails['editend']        = $wiki->editend;
                    $wikidetails['section']        = $wiki->section;
                    $wikidetails['visible']        = $wiki->visible;
                    $wikidetails['groupmode']      = $wiki->groupmode;
                    $wikidetails['groupingid']     = $wiki->groupingid;
                }

                $arrwikis[] = $wikidetails;
            }
        }
        $result = array();
        $result['wikis'] = $arrwikis;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_wikis_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_wikis_by_courses_returns() {
        return new external_single_structure(
            array(
                'wikis' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Wiki id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Wiki name'),
                            'intro' => new external_value(PARAM_RAW, 'The Wiki intro'),
                            'introformat' => new external_format_value('intro'),
                            'timecreated' => new external_value(PARAM_RAW, 'time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'firstpagetitle' => new external_value(PARAM_TEXT, 'first page title', VALUE_OPTIONAL),
                            'wikimode' => new external_value(PARAM_TEXT, 'wiki mode:collaborative,individual', VALUE_OPTIONAL),
                            'defaultformat' => new external_value(PARAM_TEXT, 'default format:HTML,Creole,Nwiki', VALUE_OPTIONAL),
                            'forceformat' => new external_value(PARAM_BOOL, 'force format', VALUE_OPTIONAL),
                            'editbegin' => new external_value(PARAM_INT, 'edit begin', VALUE_OPTIONAL),
                            'editend' => new external_value(PARAM_INT, 'edit end', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Wikis'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
