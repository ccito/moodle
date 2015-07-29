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
 * @package    mod_workshop
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
 * @package    mod_workshop
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_workshop_external extends external_api {
    /**
     * Describes the parameters for get_workshops_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_workshops_by_courses_parameters() {
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
     * Returns a list of workshops in a provided list of courses,
     * if no list is provided all workshops that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of workshops details
     * @since Moodle 3.0
     */
    public static function get_workshops_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_workshops_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the workshops to return.
        $arrworkshops = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the workshops from.
            $arraycourses = array();
            // Go through the courseids.
            foreach ($courseids as $cid) {
                // Check the user can function in this context.
                try {
                    $context = context_course::instance($cid);
                    self::validate_context($context);
                    if (has_capability('mod/workshop:view', $context)) {
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
                            'message' => get_string('missingrequiredcapability', 'webservice', 'mod/workshop:view')
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
            // Get the workshops in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $workshops = get_all_instances_in_courses("workshop", $arraycourses);
            foreach ($workshops as $workshop) {
                $workshopcontext = context_module::instance($workshop->coursemodule);
                // Entry to return.
                $workshopdetails = array();
                // First, we return information that any user can see in the web interface.
                $workshopdetails['id'] = $workshop->id;
                $workshopdetails['coursemodule']      = $workshop->coursemodule;
                $workshopdetails['course']            = $workshop->course;
                $workshopdetails['name']              = $workshop->name;
                // Format intro.
                list($workshopdetails['intro'], $workshopdetails['introformat']) =
                    external_format_text($workshop->intro, $workshop->introformat,
                                            $workshopcontext->id, 'mod_workshop', 'intro', null);

                if (has_capability('moodle/course:manageactivities', $workshopcontext)) {
                    list($workshopdetails['instructauthors'], $workshopdetails['instructauthorsformat']) =
                        external_format_text($workshop->instructauthors, $workshop->instructauthorsformat,
                                                $workshopcontext->id, 'mod_workshop', 'instructauthors', null);
                    list($workshopdetails['instructreviewers'], $workshopdetails['instructreviewersformat']) =
                        external_format_text($workshop->instructreviewers, $workshop->instructreviewersformat,
                                                $workshopcontext->id, 'mod_workshop', 'instructreviewers', null);
                    $workshopdetails['timemodified']          = $workshop->timemodified;
                    $workshopdetails['phase']                 = $workshop->phase;
                    $workshopdetails['useexamples']           = $workshop->useexamples;
                    $workshopdetails['usepeerassessment']     = $workshop->usepeerassessment;
                    $workshopdetails['useselfassessment']     = $workshop->useselfassessment;
                    $workshopdetails['grade']                 = $workshop->grade;
                    $workshopdetails['gradinggrade']          = $workshop->gradinggrade;
                    $workshopdetails['strategy']              = $workshop->strategy;
                    $workshopdetails['evaluation']            = $workshop->evaluation;
                    $workshopdetails['gradedecimals']         = $workshop->gradedecimals;
                    $workshopdetails['nattachments']          = $workshop->nattachments;
                    $workshopdetails['latesubmissions']       = $workshop->latesubmissions;
                    $workshopdetails['maxbytes']              = $workshop->maxbytes;
                    $workshopdetails['examplesmode']          = $workshop->examplesmode;
                    $workshopdetails['submissionstart']       = $workshop->submissionstart;
                    $workshopdetails['submissionend']         = $workshop->submissionend;
                    $workshopdetails['assessmentstart']       = $workshop->assessmentstart;
                    $workshopdetails['assessmentend']         = $workshop->assessmentend;
                    $workshopdetails['phaseswitchassessment'] = $workshop->phaseswitchassessment;
                    list($workshopdetails['conclusion'], $workshopdetails['conclusionformat']) =
                        external_format_text($workshop->conclusion, $workshop->conclusionformat,
                                                $workshopcontext->id, 'mod_workshop', 'conclusion', null);
                    $workshopdetails['overallfeedbackmode']     = $workshop->overallfeedbackmode;
                    $workshopdetails['overallfeedbackfiles']    = $workshop->overallfeedbackfiles;
                    $workshopdetails['overallfeedbackmaxbytes'] = $workshop->overallfeedbackmaxbytes;
                    $workshopdetails['section']       = $workshop->section;
                    $workshopdetails['visible']       = $workshop->visible;
                    $workshopdetails['groupmode']     = $workshop->groupmode;
                    $workshopdetails['groupingid']    = $workshop->groupingid;
                }
                $arrworkshops[] = $workshopdetails;
            }
        }
        $result = array();
        $result['workshops'] = $arrworkshops;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_workshops_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_workshops_by_courses_returns() {
        return new external_single_structure(
            array(
                'workshops' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Workshop id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Workshop name'),
                            'intro' => new external_value(PARAM_RAW, 'The Workshop intro'),
                            'introformat' => new external_format_value('intro'),
                            'instructauthors' => new external_value(PARAM_RAW, 'instruct authors', VALUE_OPTIONAL),
                            'instructauthorsformat' => new external_format_value('instructauthors', VALUE_OPTIONAL),
                            'instructreviewers' => new external_value(PARAM_RAW, 'instruct reviewers', VALUE_OPTIONAL),
                            'instructreviewersformat' => new external_format_value('instructreviewers', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'phase' => new external_value(PARAM_INT, 'phase', VALUE_OPTIONAL),
                            'useexamples' => new external_value(PARAM_BOOL, 'use examples', VALUE_OPTIONAL),
                            'usepeerassessment' => new external_value(PARAM_INT, 'use peer assessment', VALUE_OPTIONAL),
                            'useselfassessment' => new external_value(PARAM_BOOL, 'use self assessment', VALUE_OPTIONAL),
                            'grade' => new external_value(PARAM_FLOAT, 'grade', VALUE_OPTIONAL),
                            'gradinggrade' => new external_value(PARAM_FLOAT, 'grade in grade', VALUE_OPTIONAL),
                            'strategy' => new external_value(PARAM_TEXT, 'grading strategy', VALUE_OPTIONAL),
                            'evaluation' => new external_value(PARAM_TEXT, 'evaluation', VALUE_OPTIONAL),
                            'gradedecimals' => new external_value(PARAM_INT, 'grade decimals', VALUE_OPTIONAL),
                            'nattachments' => new external_value(PARAM_INT, 'number of attachments', VALUE_OPTIONAL),
                            'latesubmissions' => new external_value(PARAM_BOOL, 'late submissions', VALUE_OPTIONAL),
                            'maxbytes' => new external_value(PARAM_INT, 'max bytes', VALUE_OPTIONAL),
                            'examplesmode' => new external_value(PARAM_INT, 'examples mode', VALUE_OPTIONAL),
                            'submissionstart' => new external_value(PARAM_RAW, 'submission start time', VALUE_OPTIONAL),
                            'submissionend' => new external_value(PARAM_RAW, 'submission end time', VALUE_OPTIONAL),
                            'assessmentstart' => new external_value(PARAM_RAW, 'assessment start time', VALUE_OPTIONAL),
                            'assessmentend' => new external_value(PARAM_RAW, 'assessment end time', VALUE_OPTIONAL),
                            'phaseswitchassessment' => new external_value(PARAM_BOOL, 'phases witch assessment', VALUE_OPTIONAL),
                            'conclusion' => new external_value(PARAM_RAW, 'conclusion', VALUE_OPTIONAL),
                            'conclusionformat' => new external_format_value('conclusion', VALUE_OPTIONAL),
                            'overallfeedbackmode' => new external_value(PARAM_INT, 'overall feedback mode', VALUE_OPTIONAL),
                            'overallfeedbackfiles' => new external_value(PARAM_INT, 'overall feedback files', VALUE_OPTIONAL),
                            'overallfeedbackmaxbytes' => new external_value(PARAM_INT, 'overall feedback maxbytes', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Workshops'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
