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
            array('filename' => new external_value(PARAM_TEXT, 'A file in a user\'s \'private files\', ' .
                  'default in / when no filepath provided', VALUE_REQUIRED),
                  'filepath' => new external_value(PARAM_TEXT, 'A path to a file in a user\'s \'private files\'', VALUE_OPTIONAL),
                  'courseid' => new external_value(PARAM_INT, 'The course id the file is to be handeled in', VALUE_REQUIRED)
            )
        );
    }

    /**
     * Assists with a file operation.
     *
     * @param string $filename file name
     * @param string $filepath file path
     * @param int $courseid course id
     * @return array $results The array of results.
     */
    public static function create_file_resource($filename, $filepath, $courseid) {
        global $USER, $DB;

        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(self::create_file_resource_parameters(),
            array('filename' => $filename,
                'filepath' => $filepath,
                'courseid' => $courseid));

        // For sure: sectionnumber action.
        // Maybe: alias displayname display intro printintro popupwidth popupheight showsize showtype showdate.

        // Context validation.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should present.
        if (!has_capability('repository/user:view', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }
        $coursecontext = \context_course::instance($courseid);
        if (!has_capability('moodle/course:manageactivities', $coursecontext)) {
            throw new moodle_exception('cannotaddcoursemodule');
        }

        $component = "user";
        $filearea = "draft";
        if ($filepath == '' OR !isset($filepath)) {
            $filepath = "/";
        }

        $data = new stdClass();

        $itemid = $DB->get_field('files', 'itemid', [
            'component' => $component,
            'filearea' => $filearea,
            'filepath' => $filepath,
            'filename' => $filename,
            'userid' => $USER->id
        ]);

        $data->name = $filename; // Displayed name.
        $data->showdescription = 0; // Whether to show the description.
        $data->files = $itemid;
        $data->visible = 1; //
        $data->visibleoncoursepage = 1; //
        $data->course = $courseid;
//        $data->coursemodule = 83;
        $data->section = 1;
        $mod = $DB->get_record('modules', ['name' => 'resource']);
        $data->module = $mod->id; // Id the module of name 'resource' has.
        $data->modulename = 'resource';
        $data->instance = '';
        $data->add = 'resource';
        $data->intro = '';
        $data->introformat = FORMAT_HTML;
//        $data->completion = 0;

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

        $course = get_course($courseid);

        // Add a file.
        $moduleinfo = add_moduleinfo($data, $course);

        return 'Added file ' . $filepath . $params['filename'] . ' by user ' . $USER->firstname . ' to course id ' . $courseid . ' now having resource id ' .
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
