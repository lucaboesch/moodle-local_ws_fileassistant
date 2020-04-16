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
require_once($CFG->dirroot . '/files/externallib.php');
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
                  'filepath' => new external_value(PARAM_TEXT, 'A path to a file in a user\'s \'private files\'', VALUE_DEFAULT,
                      '/'),
                  'courseid' => new external_value(PARAM_INT, 'The course id the file is to be handeled in', VALUE_REQUIRED),
                  'sectionnumber' => new external_value(PARAM_INT, 'In which section the file is to be added', VALUE_REQUIRED),
                  'displayname' => new external_value(PARAM_TEXT, 'The name to display for the file', VALUE_DEFAULT, '')
            )
        );
    }

    /**
     * Assists with a file operation.
     *
     * @param string $filename file name
     * @param string $filepath file path
     * @param int $courseid course id
     * @param int $sectionnumber section number
     * @param string $displayname file display name
     * @return string A string describing the result.
     */
    public static function create_file_resource($filename, $filepath, $courseid, $sectionnumber, $displayname) {
        global $USER, $DB;

        // Parameter validation.
        // REQUIRED.
        $params = self::validate_parameters(self::create_file_resource_parameters(),
            array('filename' => $filename,
                'filepath' => $filepath,
                'courseid' => $courseid,
                'sectionnumber' => $sectionnumber,
                'displayname' => $displayname));

        // For sure: action.
        // Maybe: alias display intro printintro popupwidth popupheight showsize showtype showdate.

        // Context validation.
        $context = \context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should present.
        if (!has_capability('repository/user:view', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }
        if (!has_capability('moodle/user:manageownfiles', $context)) {
            throw new moodle_exception('cannotviewprofile');
        }
        $coursecontext = \context_course::instance($courseid);
        if (!has_capability('moodle/course:manageactivities', $coursecontext)) {
            throw new moodle_exception('cannotaddcoursemodule');
        }

        $component = "user";
        $filearea = "private";
        if ($filepath == '' OR !isset($filepath)) {
            $filepath = "/";
        }
        if ($displayname == '') {
            $futurefilename = $filename;
        } else {
            $futurefilename = $displayname;
        }

        $fs = get_file_storage();
        $privatefiles = $fs->get_area_files($context->id, $component, $filearea, 0, 'id', true);

        foreach ($privatefiles as $file) {
            if ($file->is_directory()) {
                continue;
            }
            if (($file->get_filepath() == $filepath) AND ($file->get_filename() == $filename)) {
                // Found the file, now make a temporary one to pass on.

                $tempfileinfo = new stdClass();
                $tempfileinfo->contextid = $context->id;
                $tempfileinfo->component = 'user';
                $tempfileinfo->filearea = 'draft';
                $tempfileinfo->filepath = '/';
                $tempfileinfo->filename = $futurefilename;
                $tempfileinfo->itemid = file_get_unused_draft_itemid();
                $tempfileinfo->source = $file->get_source();
                $tempfile = $fs->create_file_from_storedfile($tempfileinfo, $file->get_id());

                $data = new stdClass();

                $data->name = $futurefilename; // Displayed name.
                $data->showdescription = 0; // Whether to show the description.
                $data->files = $tempfile->get_itemid();
                $data->visible = 1;
                $data->visibleoncoursepage = 1;
                $data->introeditor = array('text' => '', 'format' => 1, 'itemid' => null);
                $data->course = $courseid;
                $data->section = $sectionnumber;
                $mod = $DB->get_record('modules', ['name' => 'resource']);
                $data->module = $mod->id; // Id the module of name 'resource' has.
                $data->modulename = 'resource';
                $data->instance = '';
                $data->add = 'resource';
                $data->intro = '';
                $data->introformat = FORMAT_HTML;

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

                // Remove the temporary file.
                if ($file = $fs->get_file($context->id, 'user', 'draft', $tempfile->get_itemid(), '/', $futurefilename)) {
                    $file->delete();
                }
                return get_string('successmessage', 'local_ws_fileassistant', array('folder' => $filepath,
                    'file' => $params['filename'], 'username' => fullname($USER), 'courseid' => $courseid,
                    'coursesection' => $sectionnumber, 'newname' => $futurefilename, 'resourceid' => $moduleinfo->id));
            }
        }
        throw new moodle_exception('filenotfound');
    }

    /**
     * Returns description of method result value
     * @return external_description
     */
    public static function create_file_resource_returns() {
        return new external_value(PARAM_TEXT, 'Filename + user first name');
    }
}
