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
 * @package    mod_quiz
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
 * @package    mod_quiz
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_quiz_external extends external_api {
    /**
     * Describes the parameters for get_quizzes_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_quizzes_by_courses_parameters() {
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
     * Returns a list of quizzes in a provided list of courses,
     * if no list is provided all quizzes that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of quizzes details
     * @since Moodle 3.0
     */
    public static function get_quizzes_by_courses($courseids = array()) {
        global $CFG, $USER, $DB;
        $params = self::validate_parameters(self::get_quizzes_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the quizzes to return.
        $arrquizzes = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the quizzes from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/quiz:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/quiz:view')
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
            // Get the quizzes in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $quizzes = get_all_instances_in_courses("quiz", $arraycourses);
            foreach ($quizzes as $quiz) {
                $quizcontext = context_module::instance($quiz->coursemodule);
                // Entry to return.
                $quizdetails = array();
                // First, we return information that any user can see in the web interface.
                $quizdetails['id'] = $quiz->id;
                $quizdetails['coursemodule']      = $quiz->coursemodule;
                $quizdetails['course']            = $quiz->course;
                $quizdetails['name']              = $quiz->name;
                // Format intro.
                list($quizdetails['intro'], $quizdetails['introformat']) =
                    external_format_text($quiz->intro, $quiz->introformat,
                                            $quizcontext->id, 'mod_quiz', 'intro', null);
                if (has_capability('moodle/course:manageactivities', $quizcontext)) {
                    $quizdetails['timeopen']                    = $quiz->timeopen;
                    $quizdetails['timeclose']                   = $quiz->timeclose;
                    $quizdetails['timelimit']                   = $quiz->timelimit;
                    $quizdetails['overduehandling']             = $quiz->overduehandling;
                    $quizdetails['graceperiod']                 = $quiz->graceperiod;
                    $quizdetails['preferredbehaviour']          = $quiz->preferredbehaviour;
                    $quizdetails['canredoquestions']            = $quiz->canredoquestions;
                    $quizdetails['attempts']                    = $quiz->attempts;
                    $quizdetails['attemptonlast']               = $quiz->attemptonlast;
                    $quizdetails['grademethod']                 = $quiz->grademethod;
                    $quizdetails['decimalpoints']               = $quiz->decimalpoints;
                    $quizdetails['questiondecimalpoints']       = $quiz->questiondecimalpoints;
                    $quizdetails['reviewattempt']               = $quiz->reviewattempt;
                    $quizdetails['reviewcorrectness']           = $quiz->reviewcorrectness;
                    $quizdetails['reviewmarks']                 = $quiz->reviewmarks;
                    $quizdetails['reviewspecificfeedback']      = $quiz->reviewspecificfeedback;
                    $quizdetails['reviewgeneralfeedback']       = $quiz->reviewgeneralfeedback;
                    $quizdetails['reviewrightanswer']           = $quiz->reviewrightanswer;
                    $quizdetails['reviewoverallfeedback']       = $quiz->reviewoverallfeedback;
                    $quizdetails['questionsperpage']            = $quiz->questionsperpage;
                    $quizdetails['navmethod']                   = $quiz->navmethod;
                    $quizdetails['shuffleanswers']              = $quiz->shuffleanswers;
                    $quizdetails['sumgrades']                   = $quiz->sumgrades;
                    $quizdetails['grade']                       = $quiz->grade;
                    $quizdetails['timecreated']                 = $quiz->timecreated;
                    $quizdetails['timemodified']                = $quiz->timemodified;
                    $quizdetails['password']                    = $quiz->password;
                    $quizdetails['subnet']                      = $quiz->subnet;
                    $quizdetails['browsersecurity']             = $quiz->browsersecurity;
                    $quizdetails['delay1']                      = $quiz->delay1;
                    $quizdetails['delay2']                      = $quiz->delay2;
                    $quizdetails['showuserpicture']             = $quiz->showuserpicture;
                    $quizdetails['showblocks']                  = $quiz->showblocks;
                    $quizdetails['completionattemptsexhausted'] = $quiz->completionattemptsexhausted;
                    $quizdetails['completionpass']              = $quiz->completionpass;
                    $quizdetails['section']                     = $quiz->section;
                    $quizdetails['visible']                     = $quiz->visible;
                    $quizdetails['groupmode']                   = $quiz->groupmode;
                    $quizdetails['groupingid']                  = $quiz->groupingid;
                }

                $arrquizzes[] = $quizdetails;
            }
        }
        $result = array();
        $result['quizzes'] = $arrquizzes;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_quizzes_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_quizzes_by_courses_returns() {
        return new external_single_structure(
            array(
                'quizzes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Resource id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Resource name'),
                            'intro' => new external_value(PARAM_RAW, 'The Resource intro'),
                            'introformat' => new external_format_value('intro'),
                            'timeopen' => new external_value(PARAM_RAW, 'time activity open', VALUE_OPTIONAL),
                            'timeclose' => new external_value(PARAM_RAW, 'time activity close', VALUE_OPTIONAL),
                            'timelimit' => new external_value(PARAM_RAW, 'time duration limit', VALUE_OPTIONAL),
                            'overduehandling' => new external_value(PARAM_ALPHA, 'what happens if the Student fails to submit quiz',
                                                                                                                    VALUE_OPTIONAL),
                            'graceperiod' => new external_value(PARAM_RAW, 'Submission grace period', VALUE_OPTIONAL),
                            'preferredbehaviour' => new external_value(PARAM_ALPHA, 'preferred behaviour', VALUE_OPTIONAL),
                            'canredoquestions' => new external_value(PARAM_BOOL, 'can redo questions', VALUE_OPTIONAL),
                            'attempts' => new external_value(PARAM_INT, 'attempts', VALUE_OPTIONAL),
                            'attemptonlast' => new external_value(PARAM_INT, 'attempt on last', VALUE_OPTIONAL),
                            'grademethod' => new external_value(PARAM_INT, 'grade method', VALUE_OPTIONAL),
                            'decimalpoints' => new external_value(PARAM_INT, 'decimal points', VALUE_OPTIONAL),
                            'questiondecimalpoints' => new external_value(PARAM_INT, 'question decimal points', VALUE_OPTIONAL),
                            'reviewattempt' => new external_value(PARAM_RAW, 'review attempt', VALUE_OPTIONAL),
                            'reviewcorrectness' => new external_value(PARAM_RAW, 'review correctness', VALUE_OPTIONAL),
                            'reviewmarks' => new external_value(PARAM_RAW, 'review marks', VALUE_OPTIONAL),
                            'reviewspecificfeedback' => new external_value(PARAM_RAW, 'review specific feedback', VALUE_OPTIONAL),
                            'reviewgeneralfeedback' => new external_value(PARAM_RAW, 'review general feedback', VALUE_OPTIONAL),
                            'reviewrightanswer' => new external_value(PARAM_RAW, 'review right answer', VALUE_OPTIONAL),
                            'reviewoverallfeedback' => new external_value(PARAM_RAW, 'review overall feedback', VALUE_OPTIONAL),
                            'questionsperpage' => new external_value(PARAM_INT, 'questions per page', VALUE_OPTIONAL),
                            'navmethod' => new external_value(PARAM_ALPHA, 'navigation method', VALUE_OPTIONAL),
                            'shuffleanswers' => new external_value(PARAM_INT, 'shuffle answers', VALUE_OPTIONAL),
                            'sumgrades' => new external_value(PARAM_FLOAT, 'sum grades', VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_FLOAT, 'grade', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_RAW, 'time created', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time modified', VALUE_OPTIONAL),
                            'password' => new external_value(PARAM_RAW, 'password', VALUE_OPTIONAL),
                            'subnet' => new external_value(PARAM_RAW, 'subnet', VALUE_OPTIONAL),
                            'browsersecurity' => new external_value(PARAM_RAW, 'browser security', VALUE_OPTIONAL),
                            'delay1' => new external_value(PARAM_INT, 'delay1', VALUE_OPTIONAL),
                            'delay2' => new external_value(PARAM_INT, 'delay2', VALUE_OPTIONAL),
                            'showuserpicture' => new external_value(PARAM_INT, 'show user picture', VALUE_OPTIONAL),
                            'showblocks' => new external_value(PARAM_INT, 'show blocks', VALUE_OPTIONAL),
                            'completionattemptsexhausted' => new external_value(PARAM_INT, 'completion attempts exhausted',
                                                                                                           VALUE_OPTIONAL),
                            'completionpass' => new external_value(PARAM_INT, 'completion pass', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Quizzes'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
