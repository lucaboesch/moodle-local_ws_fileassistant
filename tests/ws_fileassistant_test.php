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
 * Unit tests for /local/ws_fileassistant/externallib.php.
 *
 * @package     local_ws_fileassistant
 * @copyright   2020 Nina Herrmann <nina.herrmann@uni-muenster.de> Luca Bösch <luca.boesch@bfh.ch>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->dirroot . '/local/ws_fileassistant/externallib.php');

/**
 * Class local_fileassistant_testcase
 */
class local_fileassistant_testcase  extends advanced_testcase {

    /**
     * Test that files can be pushed to a course.
     */
    public function test_push_file_to_course() {
        global $DB, $USER;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fs = get_file_storage();
        $context = context_user::instance($USER->id);

        // Add two user private files.
        $files = [];
        $file = (object) [
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => file_get_unused_draft_itemid(),
            'author' => fullname($USER),
            'filepath'  => '/',
            'filename' => 'basepic.jpg',
            'content' => 'Test file 0',
        ];
        $files[] = $file;

        $file = (object) [
            'component' => 'user',
            'filearea' => 'private',
            'itemid' => file_get_unused_draft_itemid(),
            'author' => fullname($USER),
            'filepath' => '/assignment/',
            'filename' => 'infolder.jpg',
            'content' => 'Test file 1',
        ];
        $files[] = $file;

        foreach ($files as $file) {
            $userfilerecord = new stdClass;
            $userfilerecord->contextid = $context->id;
            $userfilerecord->component = $file->component;
            $userfilerecord->filearea = $file->filearea;
            $userfilerecord->itemid = 0;
            $userfilerecord->filepath = $file->filepath;
            $userfilerecord->filename = $file->filename;
            $userfilerecord->source = 'test';
            $userfile = $fs->create_file_from_string($userfilerecord, $file->content);
        }

        // Test the create_file_resource function.
        $course = self::getDataGenerator()->create_course();
        $courseid   = $course->id;

        // Add the 'basepic.jpg' file to the course.
        try {
            $wsaddfilecourse = local_ws_fileassistant_external::create_file_resource(
                'basepic.jpg', '/', $courseid, 1, 'picture1.jpg'
            );
        } catch (moodle_exception $exception) {
            // This should never happen.
            $this->fail('An exception was caught ('.get_class($exception).').');
        }

        // Verify the course_module has one resource.
        $resources = get_coursemodules_in_course('resource', $courseid);
        $this->assertEquals(1, count($resources));

        // Verify the resource displayname.
        $cm = array_pop($resources);
        $this->assertEquals('picture1.jpg', $cm->name);

        // Add the 'infolder.jpg' file to the course.
        try {
            $wsaddfilecourse = local_ws_fileassistant_external::create_file_resource(
                'infolder.jpg', '/assignment/', $courseid, 1, 'picture2.jpg'
            );
        } catch (moodle_exception $exception) {
            // This should never happen.
            $this->fail('An exception was caught ('.get_class($exception).').');
        }

        // Verify the course_module has two resources.
        $resources = get_coursemodules_in_course('resource', $courseid);
        $this->assertEquals(2, count($resources));

        // Verify the resource displayname.
        $keys = array_keys($resources);
        rsort($keys);
        $key = $keys[0];
        $cm = $resources[$key];
        $this->assertEquals('picture2.jpg', $cm->name);
    }
}
