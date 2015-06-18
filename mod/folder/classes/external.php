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
 * Folder module external API
 *
 * @package    mod_folder
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
/**
 * Folder module external functions
 *
 * @package    mod_folder
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_folder_external extends external_api {
    /**
     * Describes the parameters for get_folders_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_folders_by_courses_parameters() {
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
     * Returns a list of folders in a provided list of courses,
     * if no list is provided all folders that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of folders details
     * @since Moodle 3.0
     */
    public static function get_folders_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_folders_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the folders to return.
        $arrfolders = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the folders from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/folder:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/folder:view')
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
            // Get the folders in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $folders = get_all_instances_in_courses("folder", $arraycourses);
            foreach ($folders as $folder) {
                $foldercontext = context_module::instance($folder->coursemodule);
                // Entry to return.
                $folderdetails = array();
                // First, we return information that any user can see in the web interface.
                $folderdetails['id'] = $folder->id;
                $folderdetails['coursemodule']      = $folder->coursemodule;
                $folderdetails['course']            = $folder->course;
                $folderdetails['name']              = $folder->name;
                // Format intro.
                list($folderdetails['intro'], $folderdetails['introformat']) =
                    external_format_text($folder->intro, $folder->introformat,
                                            $foldercontext->id, 'mod_folder', 'intro', null);
                $folderdetails['display']       = $folder->display;
                $folderdetails['showexpanded']  = $folder->showexpanded;
                if (has_capability('moodle/course:manageactivities', $foldercontext)) {
                    $folderdetails['revision']      = $folder->revision;
                    $folderdetails['timemodified']  = $folder->timemodified;
                    $folderdetails['section']       = $folder->section;
                    $folderdetails['visible']       = $folder->visible;
                    $folderdetails['groupmode']     = $folder->groupmode;
                    $folderdetails['groupingid']    = $folder->groupingid;
                }
                $arrfolders[] = $folderdetails;
            }
        }
        $result = array();
        $result['folders'] = $arrfolders;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_folders_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_folders_by_courses_returns() {
        return new external_single_structure(
            array(
                'folders' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Folder id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Folder name'),
                            'intro' => new external_value(PARAM_RAW, 'The Folder intro'),
                            'introformat' => new external_format_value('intro'),
                            'display' => new external_value(PARAM_INT, 'display folder contents'),
                            'showexpanded' => new external_value(PARAM_INT, 'show subfolders expanded'),
                            'revision' => new external_value(PARAM_INT, 'revision', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Folders'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
