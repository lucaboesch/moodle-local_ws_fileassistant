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

class local_fileassistant_testcase  extends advanced_testcase {
    /**
     * Helper function to create draft files
     *
     * @param  array  $filedata data for the file record (to not use defaults)
     * @return stored_file the stored file instance
     */
    public static function create_draft_file($filedata = array()) {
        global $USER;

        $fs = get_file_storage();

        $filerecord = array(
            'component' => 'user',
            'filearea'  => 'draft',
            'itemid'    => isset($filedata['itemid']) ? $filedata['itemid'] : file_get_unused_draft_itemid(),
            'author'    => isset($filedata['author']) ? $filedata['author'] : fullname($USER),
            'filepath'  => isset($filedata['filepath']) ? $filedata['filepath'] : '/',
            'filename'  => isset($filedata['filename']) ? $filedata['filename'] : 'file.txt',
        );

        if (isset($filedata['contextid'])) {
            $filerecord['contextid'] = $filedata['contextid'];
        } else {
            $usercontext = context_user::instance($USER->id);
            $filerecord['contextid'] = $usercontext->id;
        }
        $source = isset($filedata['source']) ? $filedata['source'] : serialize((object)array('source' => 'From string'));
        $content = isset($filedata['content']) ? $filedata['content'] : 'some content here';

        $file = $fs->create_file_from_string($filerecord, $content);
        $file->set_source($source);

        return $file;
    }

    /**
     * Test that files can be pushed to a course.
     */
    public function test_push_file_to_course() {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        $filerecord = ['filename' => 'basepic.jpg'];
        $file = self::create_draft_file($filerecord);

        $secondrecord = [
            'filename' => 'infolder.jpg',
            'filepath' => '/assignment/',
            'itemid' => $file->get_itemid()
        ];
        $file = self::create_draft_file($secondrecord);

        $thirdrecord = [
            'filename' => 'deeperfolder.jpg',
            'filepath' => '/assignment/pics/',
            'itemid' => $file->get_itemid()
        ];
        $file = self::create_draft_file($thirdrecord);

        $fourthrecord = [
            'filename' => 'differentimage.jpg',
            'filepath' => '/secondfolder/',
            'itemid' => $file->get_itemid()
        ];
        $file = self::create_draft_file($fourthrecord);

        // This record has the same name as the last record, but it's in a different folder.
        // Just checking this is also returned.
        $fifthrecord = [
            'filename' => 'differentimage.jpg',
            'filepath' => '/assignment/pics/',
            'itemid' => $file->get_itemid()
        ];
        $file = self::create_draft_file($fifthrecord);

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
