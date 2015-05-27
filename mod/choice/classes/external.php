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
 * Choice module external API
 *
 * @package    mod_choice
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */

defined('MOODLE_INTERNAL') || die;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/mod/choice/lib.php");

/**
 * Choice module external functions
 *
 * @package    mod_choice
 * @category   external
 * @copyright  2015 Costantino Cito <ccito@cvaconsulting.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 3.0
 */
class mod_choice_external extends external_api {

    /**
     * Describes the parameters for get_choice_options.
     *
     * @return external_external_function_parameters
     * @since Moodle 3.0
     */
    public static function get_choice_options_parameters() {
        return new external_function_parameters (array('choiceinstanceid' => new external_value(PARAM_INT, 'choice id')));
    }

    /**
     * Returns options for a specific choice 
     *
     * @param int $choiceinstanceid the choice instance id
     * @return array of options details
     * @since Moodle 3.0
     */
    public static function get_choice_options($choiceinstanceid) {
        global $USER;
        $warnings = array();
        $params = self::validate_parameters(self::get_choice_options_parameters(), array('choiceinstanceid' => $choiceinstanceid));

        if (! $cm = get_coursemodule_from_id('choice', $params['choiceinstanceid'])) {
             throw new invalid_response_exception(get_string("invalidcoursemodule"));
        }

        $course = get_course($cm->course);

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/choice:choose', $context);

        if (!$choice = choice_get_choice($cm->instance)) {
             throw new invalid_response_exception(get_string("invalidcoursemodule"));
        }
        $groupmode = groups_get_activity_groupmode($cm);
        $onlyactive = $choice->includeinactive ? false : true;
        $allresponses = choice_get_response_data($choice, $cm, $groupmode, $onlyactive);

        $timenow = time();
        $choiceopen = true;
        $showpreview = false;
        if ($choice->visible == 0) {
            $warnings[4] = "Activity is hidden";
            $choiceopen = false;
        } else if ($choice->timeclose != 0) {
            if ($choice->timeopen > $timenow) {
                $choiceopen = false;
                $warnings[1] = get_string("notopenyet", "choice", userdate($choice->timeopen));
                if ($choice->showpreview) {
                    $warnings[2] = get_string('previewonly', 'choice', userdate($choice->timeopen));
                    $showpreview = true;
                }
            }
            if ($timenow > $choice->timeclose) {
                $choiceopen = false;
                $warnings[3] = get_string("expired", "choice", userdate($choice->timeclose));
            }
        }
        $optionsarray = array();

        if ($choiceopen or $showpreview) {

            $options = choice_prepare_options($choice, $USER, $cm, $allresponses);

            foreach ($options['options'] as $option) {
                $optionarr = array();
                $optionarr['id']            = $option->attributes->value;
                $optionarr['text']          = $option->text;
                $optionarr['maxanswers']    = $option->maxanswers;
                $optionarr['displaylayout'] = $option->displaylayout;
                $optionarr['countanswers']  = $option->countanswers;
                foreach (array('checked', 'disabled') as $field) {
                    if (property_exists($option->attributes, $field) and $option->attributes->$field == 1) {
                        $optionarr[$field] = 1;
                    } else {
                        $optionarr[$field] = 0;
                    }
                }
                // When showpreview is active, we show options as disabled.
                if ($showpreview or ($optionarr['checked'] == 1 and !$choice->allowupdate)) {
                    $optionarr['disabled'] = 1;
                }
                $optionsarray[] = $optionarr;
            }
        }
        foreach ($warnings as $key => $message) {
                    $warnings[$key] = array(
                        'item' => 'choice',
                        'itemid' => $cm->id,
                        'warningcode' => $key,
                        'message' => $message
                    );
        }
        return array(
                     'options' => $optionsarray,
                     'warnings' => $warnings
        );
    }

    /**
     * Describes the get_choice_results return value.
     *
     * @return external_multiple_structure
     * @since Moodle 3.0
     */
    public static function get_choice_options_returns() {
        return new external_single_structure(
            array(
                'options' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'id' => new external_value(PARAM_INT, 'option id'),
                            'text' => new external_value(PARAM_TEXT, 'text of the choice'),
                            'maxanswers' => new external_value(PARAM_INT, 'maximum number of answers'),
                            'displaylayout' => new external_value(PARAM_BOOL, 'true for orizontal, otherwise vertical'),
                            'countanswers' => new external_value(PARAM_INT, 'number of answers'),
                            'checked' => new external_value(PARAM_BOOL, 'we already answered'),
                            'disabled' => new external_value(PARAM_BOOL, 'option disabled'),
                            )
                    ), 'Options'
                ),
                'warnings' => new external_warnings(),
            )
        );
    }
}
