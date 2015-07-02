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
 * @package    mod_lesson
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
 * @package    mod_lesson
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_lesson_external extends external_api {
    /**
     * Describes the parameters for get_lessons_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_lessons_by_courses_parameters() {
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
     * Returns a list of lessons in a provided list of courses,
     * if no list is provided all lessons that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of lessons details
     * @since Moodle 3.0
     */
    public static function get_lessons_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_lessons_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the lessons to return.
        $arrlessons = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the lessons from.
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
            // Get the lessons in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $lessons = get_all_instances_in_courses("lesson", $arraycourses);
            foreach ($lessons as $lesson) {
                $lessoncontext = context_module::instance($lesson->coursemodule);
                // Entry to return.
                $lessondetails = array();
                // First, we return information that any user can see in the web interface.
                $lessondetails['id'] = $lesson->id;
                $lessondetails['coursemodule']      = $lesson->coursemodule;
                $lessondetails['course']            = $lesson->course;
                $lessondetails['name']              = $lesson->name;
                // Format intro.
                list($lessondetails['intro'], $lessondetails['introformat']) =
                    external_format_text($lesson->intro, $lesson->introformat,
                                            $lessoncontext->id, 'mod_lesson', 'intro', null);
                if (has_capability('mod/lesson:manage', $lessoncontext)) {
                    $lessondetails['practice']             = $lesson->practice;
                    $lessondetails['modattempts']          = $lesson->modattempts;
                    $lessondetails['usepassword']          = $lesson->usepassword;
                    $lessondetails['password']             = $lesson->password;
                    $lessondetails['dependency']           = $lesson->dependency;
                    $lessondetails['conditions']           = $lesson->conditions;
                    $lessondetails['grade']                = $lesson->grade;
                    $lessondetails['custom']               = $lesson->custom;
                    $lessondetails['ongoing']              = $lesson->ongoing;
                    $lessondetails['usemaxgrade']          = $lesson->usemaxgrade;
                    $lessondetails['maxanswers']           = $lesson->maxanswers;
                    $lessondetails['maxattempts']          = $lesson->maxattempts;
                    $lessondetails['review']               = $lesson->review;
                    $lessondetails['nextpagedefault']      = $lesson->nextpagedefault;
                    $lessondetails['feedback']             = $lesson->feedback;
                    $lessondetails['minquestions']         = $lesson->minquestions;
                    $lessondetails['maxpages']             = $lesson->maxpages;
                    $lessondetails['timelimit']            = $lesson->timelimit;
                    $lessondetails['retake']               = $lesson->retake;
                    $lessondetails['activitylink']         = $lesson->activitylink;
                    $lessondetails['mediafile']            = $lesson->mediafile;
                    $lessondetails['mediaheight']          = $lesson->mediaheight;
                    $lessondetails['mediawidth']           = $lesson->mediawidth;
                    $lessondetails['mediaclose']           = $lesson->mediaclose;
                    $lessondetails['slideshow']            = $lesson->slideshow;
                    $lessondetails['width']                = $lesson->width;
                    $lessondetails['height']               = $lesson->height;
                    $lessondetails['bgcolor']              = $lesson->bgcolor;
                    $lessondetails['displayleft']          = $lesson->displayleft;
                    $lessondetails['displayleftif']        = $lesson->displayleftif;
                    $lessondetails['progressbar']          = $lesson->progressbar;
                    $lessondetails['highscores']           = $lesson->highscores;
                    $lessondetails['maxhighscores']        = $lesson->maxhighscores;
                    $lessondetails['available']            = $lesson->available;
                    $lessondetails['deadline']             = $lesson->deadline;
                    $lessondetails['timemodified']         = $lesson->timemodified;
                    $lessondetails['completionendreached'] = $lesson->completionendreached;
                    $lessondetails['completiontimespent']  = $lesson->completiontimespent;
                    $lessondetails['section']              = $lesson->section;
                    $lessondetails['visible']              = $lesson->visible;
                    $lessondetails['groupmode']            = $lesson->groupmode;
                    $lessondetails['groupingid']           = $lesson->groupingid;
                }

                $arrlessons[] = $lessondetails;
            }
        }
        $result = array();
        $result['lessons'] = $arrlessons;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_lessons_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_lessons_by_courses_returns() {
        return new external_single_structure(
            array(
                'lessons' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id'                   => new external_value(PARAM_INT, 'Lesson id'),
                            'coursemodule'         => new external_value(PARAM_INT, 'Course module id'),
                            'course'               => new external_value(PARAM_TEXT, 'Course id'),
                            'name'                 => new external_value(PARAM_TEXT, 'Lesson name'),
                            'intro'                => new external_value(PARAM_RAW, 'The Lesson intro'),
                            'introformat'          => new external_format_value('intro'),
                            'practice'             => new external_value(PARAM_BOOL, 'practice lesson', VALUE_OPTIONAL),
                            'modattempts'          => new external_value(PARAM_BOOL, 'allow student review', VALUE_OPTIONAL),
                            'usepassword'          => new external_value(PARAM_BOOL, 'use password', VALUE_OPTIONAL),
                            'password'             => new external_value(PARAM_RAW, 'password', VALUE_OPTIONAL),
                            'dependency'           => new external_value(PARAM_INT, 'dependency', VALUE_OPTIONAL),
                            'conditions'           => new external_value(PARAM_RAW, 'conditions', VALUE_OPTIONAL),
                            'grade'                => new external_value(PARAM_INT, 'grade', VALUE_OPTIONAL),
                            'custom'               => new external_value(PARAM_BOOL, 'custom scoring', VALUE_OPTIONAL),
                            'ongoing'              => new external_value(PARAM_BOOL, 'ongoing', VALUE_OPTIONAL),
                            'usemaxgrade'          => new external_value(PARAM_INT, 'use max grade', VALUE_OPTIONAL),
                            'maxanswers'           => new external_value(PARAM_INT, 'max number of answers', VALUE_OPTIONAL),
                            'maxattempts'          => new external_value(PARAM_INT, 'max number of attempts', VALUE_OPTIONAL),
                            'review'               => new external_value(PARAM_BOOL, 'provide option to try a question again',
                                                                                                              VALUE_OPTIONAL),
                            'nextpagedefault'      => new external_value(PARAM_INT, 'action after correct answer', VALUE_OPTIONAL),
                            'feedback'             => new external_value(PARAM_BOOL, 'use default feedback', VALUE_OPTIONAL),
                            'minquestions'         => new external_value(PARAM_INT, 'min questions', VALUE_OPTIONAL),
                            'maxpages'             => new external_value(PARAM_INT, 'max pages', VALUE_OPTIONAL),
                            'timelimit'            => new external_value(PARAM_RAW, 'time limit duration', VALUE_OPTIONAL),
                            'retake'               => new external_value(PARAM_BOOL, 'allow more than one attempt', VALUE_OPTIONAL),
                            'activitylink'         => new external_value(PARAM_INT, 'activity link id', VALUE_OPTIONAL),
                            'mediafile'            => new external_value(PARAM_URL, 'media file', VALUE_OPTIONAL),
                            'mediaheight'          => new external_value(PARAM_INT, 'media height', VALUE_OPTIONAL),
                            'mediawidth'           => new external_value(PARAM_INT, 'media width', VALUE_OPTIONAL),
                            'mediaclose'           => new external_value(PARAM_INT, 'show close button', VALUE_OPTIONAL),
                            'slideshow'            => new external_value(PARAM_BOOL, 'slideshow', VALUE_OPTIONAL),
                            'width'                => new external_value(PARAM_INT, 'width', VALUE_OPTIONAL),
                            'height'               => new external_value(PARAM_INT, 'height', VALUE_OPTIONAL),
                            'bgcolor'              => new external_value(PARAM_RAW_TRIMMED, 'background color', VALUE_OPTIONAL),
                            'displayleft'          => new external_value(PARAM_BOOL, 'display left menu', VALUE_OPTIONAL),
                            'displayleftif'        => new external_value(PARAM_INT, 'min grade to display menu', VALUE_OPTIONAL),
                            'progressbar'          => new external_value(PARAM_BOOL, 'progress bar', VALUE_OPTIONAL),
                            'highscores'           => new external_value(PARAM_INT, 'high scores', VALUE_OPTIONAL),
                            'maxhighscores'        => new external_value(PARAM_INT, 'max high scores', VALUE_OPTIONAL),
                            'available'            => new external_value(PARAM_RAW, 'available', VALUE_OPTIONAL),
                            'deadline'             => new external_value(PARAM_RAW, 'deadline', VALUE_OPTIONAL),
                            'timemodified'         => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'completionendreached' => new external_value(PARAM_RAW, 'completion end reached', VALUE_OPTIONAL),
                            'completiontimespent'  => new external_value(PARAM_RAW, 'completion time spent', VALUE_OPTIONAL),
                            'section'              => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible'              => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode'            => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid'           => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Lessons'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
