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
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $fs = get_file_storage();

        // Add two files to core_privacy::tests::0.
        $files = [];
        $file = (object) [
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => file_get_unused_draft_itemid(),
            'author'    => fullname($USER),
            'filepath'  => '/',
            'filename' => 'basepic.jpg',
            'content' => 'Test file 0',
        ];
        $files[] = $file;

        $file = (object) [
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => file_get_unused_draft_itemid(),
            'author'    => fullname($USER),
            'filepath' => '/assignment/',
            'filename' => 'infolder.jpg',
            'content' => 'Test file 1',
        ];
        $files[] = $file;

        foreach ($files as $file) {
            $record = [
                'contextid' => $context->id,
                'component' => $file->component,
                'filearea'  => $file->filearea,
                'itemid'    => $file->itemid,
                'filepath'  => $file->path,
                'filename'  => $file->name,
            ];

            $file->namepath = '/' . $file->filearea . '/' . ($file->itemid ?: '') . $file->path . $file->name;
            $file->storedfile = $fs->create_file_from_string($record, $file->content);
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
        $this->assertEquals(1, $DB->count_records('resource'));

        // Add the 'infolder.jpg' file to the course.
        try {
            $wsaddfilecourse = local_ws_fileassistant_external::create_file_resource(
                'infolder.jpg', '/assignment/', $courseid, 1, 'picture2.jpg'
            );
        } catch (moodle_exception $exception) {
            // This should never happen.
            $this->fail('An exception was caught ('.get_class($exception).').');
        }
        $this->assertEquals(2, $DB->count_records('resource'));

        // Verify the course_module has two resources.
        $cmcount = $DB->count_records('course_modules', ['course' => $courseid]);
        $this->assertEquals(2, $cmcount);

    }
}
