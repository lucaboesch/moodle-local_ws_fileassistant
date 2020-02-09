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
 * External Web Service Template
 *
 * @package     local_ws_fileassistant
 * @copyright   2020 Nina Herrmann <nina.herrmann@uni-muenster.de> Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once($CFG->dirroot. '/course/modlib.php');
require_once($CFG->dirroot . '/mod/resource/lib.php');
require_once($CFG->dirroot . '/mod/resource/locallib.php');

/**
 * Class local_ws_fileassistant_external
 */
class local_ws_fileassistant_external extends external_api {

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function create_file_resource_parameters() {
        return new external_function_parameters(
            array('filename' => new external_value(PARAM_TEXT, 'A path to a file in a user\'s \'private files\', ' .
                  'including the path', VALUE_OPTIONAL))
        );
    }

    /**
     * Assists with a file operation.
     *
     * @param string $filename file name including path
     * @return array $results The array of results.
     */
    public static function create_file_resource($filename) {
        global $USER, $DB;

        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(self::create_file_resource_parameters(),
            array('filename' => $filename));

        // For sure: filename courseid sectionnumber action.
        // Maybe: alias displayname display intro printintro popupwidth popupheight showsize showtype showdate.

        // Context validation.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should present.
        if (!has_capability('repository/user:view', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }

        $browser = get_file_browser();

        $component = "user";
        $filearea = "private";
        $itemid = 0;
        $filepath = "/";
        $filename = "test.pdf";

        $data = new stdClass();

        if ($fileinfo = $browser->get_file_info($context, $component, $filearea, $itemid, $filepath, $filename)) {
            $data->files = $fileinfo->itemid; // Itemid of the file.
        }

        file_put_contents('/Users/luca/Desktop/log2.txt', json_encode($context) . $component . $filearea . $itemid . $filepath . $filename);
        file_put_contents('/Users/luca/Desktop/log1.txt', json_encode($fileinfo));

        $data->name = 'foo.pdf'; // Displayed name.
        $data->showdescription = 0; // Whether to show the description.
        $data->files = $fileinfo->itemid; // Itemid of the file.
        $data->visible = 1; //
        $data->visibleoncoursepage = 1; //
        $data->course = '5'; //
        $data->coursemodule = 83;
        $data->section = 1;
        $mod = $DB->get_record('modules', ['name' => 'resource']);
        $data->module = $mod->id; // Id the module of name 'resource' has.
        $data->modulename = 'resource';
        $data->instance = '';
        $data->add = 'resource';
        $data->intro = '';
        $data->introformat = FORMAT_HTML;
        $data->completion = 0;

        // Set the display options to the site defaults.
        $config = get_config('resource');
        $data->display = $config->display;
        $data->popupheight = $config->popupheight;
        $data->popupwidth = $config->popupwidth;
        $data->printintro = $config->printintro;
        $data->showsize = (isset($config->showsize)) ? $config->showsize : 0;
        $data->showtype = (isset($config->showtype)) ? $config->showtype : 0;
        $data->showdate = (isset($config->showdate)) ? $config->showdate : 0;
        $data->filterfiles = $config->filterfiles;
        $data->timemodified = time();

        file_put_contents('/Users/luca/Desktop/log0.txt', json_encode($data));

        $course = get_course(5);

        // Add a file.
        $moduleinfo = add_moduleinfo($data, $course);

        return 'Added file ' . $params['filename'] . ' by user ' . $USER->firstname . ' now having resource id ' .
            $moduleinfo->id . '.';
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function create_file_resource_returns() {
        return new external_value(PARAM_TEXT, 'Filename + user first name');
    }
}