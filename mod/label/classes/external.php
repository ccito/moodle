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
 * @package    mod_label
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
 * @package    mod_label
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_label_external extends external_api {
    /**
     * Describes the parameters for get_labels_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_labels_by_courses_parameters() {
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
     * Returns a list of labels in a provided list of courses,
     * if no list is provided all labels that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of labels details
     * @since Moodle 3.0
     */
    public static function get_labels_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_labels_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the labels to return.
        $arrlabels = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the labels from.
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
            // Get the labels in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $labels = get_all_instances_in_courses("label", $arraycourses);
            foreach ($labels as $label) {
                $labelcontext = context_module::instance($label->coursemodule);
                // Entry to return.
                $labeldetails = array();
                // First, we return information that any user can see in the web interface.
                $labeldetails['id'] = $label->id;
                $labeldetails['coursemodule']      = $label->coursemodule;
                $labeldetails['course']            = $label->course;
                $labeldetails['name']              = $label->name;
                // Format intro.
                list($labeldetails['intro'], $labeldetails['introformat']) =
                    external_format_text($label->intro, $label->introformat,
                                            $labelcontext->id, 'mod_label', 'intro', null);
                if (has_capability('moodle/course:manageactivities', $labelcontext)) {
                    $labeldetails['timemodified']  = $label->timemodified;
                    $labeldetails['section']       = $label->section;
                    $labeldetails['visible']       = $label->visible;
                    $labeldetails['groupmode']     = $label->groupmode;
                    $labeldetails['groupingid']    = $label->groupingid;
                }
                $arrlabels[] = $labeldetails;
            }
        }
        $result = array();
        $result['labels'] = $arrlabels;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_labels_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_labels_by_courses_returns() {
        return new external_single_structure(
            array(
                'labels' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Book id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Book name'),
                            'intro' => new external_value(PARAM_RAW, 'The Book intro'),
                            'introformat' => new external_format_value('intro'),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Labels'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
