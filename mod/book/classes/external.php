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
 * @package    mod_book
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
 * @package    mod_book
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_book_external extends external_api {
    /**
     * Describes the parameters for get_books_by_courses.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_books_by_courses_parameters() {
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
     * Returns a list of books in a provided list of courses,
     * if no list is provided all books that the user can view will be returned.
     *
     * @param array $courseids the course ids
     * @return array of books details
     * @since Moodle 3.0
     */
    public static function get_books_by_courses($courseids = array()) {
        global $CFG;
        $params = self::validate_parameters(self::get_books_by_courses_parameters(), array('courseids' => $courseids));
        $warnings = array();
        if (!empty($params['courseids'])) {
            $courses = array();
            $courseids = $params['courseids'];
        } else {
            $courses = enrol_get_my_courses();
            $courseids = array_keys($courses);
        }
        // Array to store the books to return.
        $arrbooks = array();
        // Ensure there are courseids to loop through.
        if (!empty($courseids)) {
            // Array of the courses we are going to retrieve the books from.
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
            // Get the books in this course, this function checks users visibility permissions.
            // We can avoid then additional validate_context calls.
            $books = get_all_instances_in_courses("book", $arraycourses);
            foreach ($books as $book) {
                $bookcontext = context_module::instance($book->coursemodule);
                // Entry to return.
                $bookdetails = array();
                // First, we return information that any user can see in the web interface.
                $bookdetails['id'] = $book->id;
                $bookdetails['coursemodule']      = $book->coursemodule;
                $bookdetails['course']            = $book->course;
                $bookdetails['name']              = $book->name;
                // Format intro.
                list($bookdetails['intro'], $bookdetails['introformat']) =
                    external_format_text($book->intro, $book->introformat,
                                            $bookcontext->id, 'mod_book', 'intro', null);
                $bookdetails['numbering']         = $book->numbering;
                $bookdetails['navstyle']          = $book->navstyle;
                $bookdetails['customtitles']      = $book->customtitles;
                if (has_capability('moodle/course:manageactivities', $bookcontext)) {
                    $bookdetails['revision']      = $book->revision;
                    $bookdetails['timecreated']   = $book->timecreated;
                    $bookdetails['timemodified']  = $book->timemodified;
                    $bookdetails['section']       = $book->section;
                    $bookdetails['visible']       = $book->visible;
                    $bookdetails['groupmode']     = $book->groupmode;
                    $bookdetails['groupingid']    = $book->groupingid;
                }
                $arrbooks[] = $bookdetails;
            }
        }
        $result = array();
        $result['books'] = $arrbooks;
        $result['warnings'] = $warnings;
        return $result;
    }
    /**
     * Describes the get_books_by_courses return value.
     *
     * @return external_single_structure
     * @since Moodle 3.0
     */
    public static function get_books_by_courses_returns() {
        return new external_single_structure(
            array(
                'books' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'Book id'),
                            'coursemodule' => new external_value(PARAM_INT, 'Course module id'),
                            'course' => new external_value(PARAM_TEXT, 'Course id'),
                            'name' => new external_value(PARAM_TEXT, 'Book name'),
                            'intro' => new external_value(PARAM_RAW, 'The Book intro'),
                            'introformat' => new external_format_value('intro'),
                            'numbering' => new external_value(PARAM_INT, 'numbering'),
                            'navstyle' => new external_value(PARAM_INT, 'navstyle'),
                            'customtitles' => new external_value(PARAM_INT, 'customtitles'),
                            'revision' => new external_value(PARAM_INT, 'revision', VALUE_OPTIONAL),
                            'timecreated' => new external_value(PARAM_RAW, 'time of creation', VALUE_OPTIONAL),
                            'timemodified' => new external_value(PARAM_RAW, 'time of last modification', VALUE_OPTIONAL),
                            'section' => new external_value(PARAM_INT, 'course section id', VALUE_OPTIONAL),
                            'visible' => new external_value(PARAM_BOOL, 'visible', VALUE_OPTIONAL),
                            'groupmode' => new external_value(PARAM_INT, 'group mode', VALUE_OPTIONAL),
                            'groupingid' => new external_value(PARAM_INT, 'group id', VALUE_OPTIONAL),
                        ), 'Books'
                    )
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
