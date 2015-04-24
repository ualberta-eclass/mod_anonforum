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
 * The module forums external functions unit tests
 *
 * @package    mod_anonforum
 * @category   external
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

class mod_anonforum_external_testcase extends externallib_advanced_testcase {

    /**
     * Tests set up
     */
    protected function setUp() {
        global $CFG;

        require_once($CFG->dirroot . '/mod/anonforum/externallib.php');
    }

    /**
     * Test get forums
     */
    public function test_mod_anonforum_get_anonforums_by_courses() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Create a user.
        $user = self::getDataGenerator()->create_user();

        // Set to the user.
        self::setUser($user);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First forum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course1->id;
        $anonforum1 = self::getDataGenerator()->create_module('anonforum', $record);

        // Second forum.
        $record = new stdClass();
        $record->introformat = FORMAT_HTML;
        $record->course = $course2->id;
        $anonforum2 = self::getDataGenerator()->create_module('anonforum', $record);

        // Check the forum was correctly created.
        $this->assertEquals(2, $DB->count_records_select('anonforum', 'id = :anonforum1 OR id = :anonforum2',
                array('anonforum1' => $anonforum1->id, 'anonforum2' => $anonforum2->id)));

        // Enrol the user in two courses.
        // DataGenerator->enrol_user automatically sets a role for the user with the permission mod/form:viewdiscussion.
        $this->getDataGenerator()->enrol_user($user->id, $course1->id, null, 'manual');
        // Execute real Moodle enrolment as we'll call unenrol() method on the instance later.
        $enrol = enrol_get_plugin('manual');
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user->id);

        // Assign capabilities to view forums for forum 2.
        $cm2 = get_coursemodule_from_id('anonforum', $anonforum2->cmid, 0, false, MUST_EXIST);
        $context2 = context_module::instance($cm2->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $roleid2 = $this->assignUserCapability('mod/anonforum:viewdiscussion', $context2->id, $newrole);

        // Create what we expect to be returned when querying the two courses.
        $expectedanonforums = array();
        $expectedanonforums[$anonforum1->id] = (array) $anonforum1;
        $expectedanonforums[$anonforum2->id] = (array) $anonforum2;

        // Call the external function passing course ids.
        $anonforums = mod_anonforum_external::get_anonforums_by_courses(array($course1->id, $course2->id));
        external_api::clean_returnvalue(mod_anonforum_external::get_anonforums_by_courses_returns(), $anonforums);
        $this->assertEquals($expectedanonforums, $anonforums);

        // Call the external function without passing course id.
        $anonforums = mod_anonforum_external::get_anonforums_by_courses();
        external_api::clean_returnvalue(mod_anonforum_external::get_anonforums_by_courses_returns(), $anonforums);
        $this->assertEquals($expectedanonforums, $anonforums);

        // Unenrol user from second course and alter expected forums.
        $enrol->unenrol_user($instance2, $user->id);
        unset($expectedanonforums[$anonforum2->id]);

        // Call the external function without passing course id.
        $anonforums = mod_anonforum_external::get_anonforums_by_courses();
        external_api::clean_returnvalue(mod_anonforum_external::get_anonforums_by_courses_returns(), $anonforums);
        $this->assertEquals($expectedanonforums, $anonforums);

        // Call for the second course we unenrolled the user from, ensure exception thrown.
        try {
            mod_anonforum_external::get_anonforums_by_courses(array($course2->id));
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }

        // Call without required capability, ensure exception thrown.
        $this->unassignUserCapability('mod/anonforum:viewdiscussion', null, null, $course1->id);
        try {
            $anonforums = mod_anonforum_external::get_anonforums_by_courses(array($course1->id));
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }
    }

    /**
     * Test get forum discussions
     */
    public function test_mod_anonforum_get_anonforum_discussions() {
        global $USER, $CFG, $DB;

        $this->resetAfterTest(true);

        // Set the CFG variable to allow track forums.
        $CFG->anonforum_trackreadposts = true;

        // Create a user who can track forums.
        $record = new stdClass();
        $record->trackforums = true;
        $user1 = self::getDataGenerator()->create_user($record);
        // Create a bunch of other users to post.
        $user2 = self::getDataGenerator()->create_user();
        $user3 = self::getDataGenerator()->create_user();
        $user4 = self::getDataGenerator()->create_user();

        // Set the first created user to the test user.
        self::setUser($user1);

        // Create courses to add the modules.
        $course1 = self::getDataGenerator()->create_course();
        $course2 = self::getDataGenerator()->create_course();

        // First forum with tracking off.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->trackingtype = FORUM_TRACKING_OFF;
        $anonforum1 = self::getDataGenerator()->create_module('anonforum', $record);

        // Second forum of type 'qanda' with tracking enabled.
        $record = new stdClass();
        $record->course = $course2->id;
        $record->type = 'qanda';
        $record->trackingtype = FORUM_TRACKING_FORCED;
        $anonforum2 = self::getDataGenerator()->create_module('anonforum', $record);

        // Third forum where we will only have one discussion with no replies.
        $record = new stdClass();
        $record->course = $course2->id;
        $anonforum3 = self::getDataGenerator()->create_module('anonforum', $record);

        // Add discussions to the forums.
        $record = new stdClass();
        $record->course = $course1->id;
        $record->userid = $user1->id;
        $record->anonforum = $anonforum1->id;
        $discussion1 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user2->id;
        $record->anonforum = $anonforum2->id;
        $discussion2 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_discussion($record);

        $record = new stdClass();
        $record->course = $course2->id;
        $record->userid = $user2->id;
        $record->anonforum = $anonforum3->id;
        $discussion3 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_discussion($record);

        // Add three replies to the discussion 1 from different users.
        $record = new stdClass();
        $record->discussion = $discussion1->id;
        $record->parent = $discussion1->firstpost;
        $record->userid = $user2->id;
        $discussion1reply1 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_post($record);

        $record->parent = $discussion1reply1->id;
        $record->userid = $user3->id;
        $discussion1reply2 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_post($record);

        $record->userid = $user4->id;
        $discussion1reply3 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_post($record);

        // Add two replies to discussion 2 from different users.
        $record = new stdClass();
        $record->discussion = $discussion2->id;
        $record->parent = $discussion2->firstpost;
        $record->userid = $user1->id;
        $discussion2reply1 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_post($record);

        $record->parent = $discussion2reply1->id;
        $record->userid = $user3->id;
        $discussion2reply2 = self::getDataGenerator()->get_plugin_generator('mod_anonforum')->create_post($record);

        // Check the forums were correctly created.
        $this->assertEquals(3, $DB->count_records_select('anonforum', 'id = :anonforum1 OR id = :anonforum2 OR id = :anonforum3',
                array('anonforum1' => $anonforum1->id, 'anonforum2' => $anonforum2->id, 'anonforum3' => $anonforum3->id)));

        // Check the discussions were correctly created.
        $this->assertEquals(3, $DB->count_records_select('anonforum_discussions',
            'anonforum = :anonforum1 OR anonforum = :anonforum2 OR anonforum = :anonforum3',
            array('anonforum1' => $anonforum1->id, 'anonforum2' => $anonforum2->id, 'anonforum3' => $anonforum3->id)));

        // Check the posts were correctly created, don't forget each discussion created also creates a post.
        $this->assertEquals(7, $DB->count_records_select('anonforum_posts',
            'discussion = :discussion1 OR discussion = :discussion2',
                array('discussion1' => $discussion1->id, 'discussion2' => $discussion2->id)));

        // Enrol the user in the first course.
        $enrol = enrol_get_plugin('manual');
        // Following line enrol and assign default role id to the user.
        // So the user automatically gets mod/anonforum:viewdiscussion on all forums of the course.
        $this->getDataGenerator()->enrol_user($user1->id, $course1->id);

        // Now enrol into the second course.
        // We don't use the dataGenerator as we need to get the $instance2 to unenrol later.
        $enrolinstances = enrol_get_instances($course2->id, true);
        foreach ($enrolinstances as $courseenrolinstance) {
            if ($courseenrolinstance->enrol == "manual") {
                $instance2 = $courseenrolinstance;
                break;
            }
        }
        $enrol->enrol_user($instance2, $user1->id);

        // Assign capabilities to view discussions for forum 2.
        $cm = get_coursemodule_from_id('anonforum', $anonforum2->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $newrole = create_role('Role 2', 'role2', 'Role 2 description');
        $this->assignUserCapability('mod/anonforum:viewdiscussion', $context->id, $newrole);

        // Assign capabilities to view discussions for forum 3.
        $cm = get_coursemodule_from_id('anonforum', $anonforum3->cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        $this->assignUserCapability('mod/anonforum:viewdiscussion', $context->id, $newrole);

        // Create what we expect to be returned when querying the forums.
        $expecteddiscussions = array();
        $expecteddiscussions[$discussion1->id] = array(
                'id' => $discussion1->id,
                'course' => $discussion1->course,
                'anonforum' => $discussion1->anonforum,
                'name' => $discussion1->name,
                'firstpost' => $discussion1->firstpost,
                'userid' => $discussion1->userid,
                'groupid' => $discussion1->groupid,
                'assessed' => $discussion1->assessed,
                'timemodified' => $discussion1reply3->created,
                'usermodified' => $discussion1reply3->userid,
                'timestart' => $discussion1->timestart,
                'timeend' => $discussion1->timeend,
                'firstuserfullname' => fullname($user1),
                'firstuserimagealt' => $user1->imagealt,
                'firstuserpicture' => $user1->picture,
                'firstuseremail' => $user1->email,
                'subject' => $discussion1->name,
                'numreplies' => 3,
                'numunread' => '',
                'lastpost' => $discussion1reply3->id,
                'lastuserid' => $user4->id,
                'lastuserfullname' => fullname($user4),
                'lastuserimagealt' => $user4->imagealt,
                'lastuserpicture' => $user4->picture,
                'lastuseremail' => $user4->email
            );
        $expecteddiscussions[$discussion2->id] = array(
                'id' => $discussion2->id,
                'course' => $discussion2->course,
                'anonforum' => $discussion2->anonforum,
                'name' => $discussion2->name,
                'firstpost' => $discussion2->firstpost,
                'userid' => $discussion2->userid,
                'groupid' => $discussion2->groupid,
                'assessed' => $discussion2->assessed,
                'timemodified' => $discussion2reply2->created,
                'usermodified' => $discussion2reply2->userid,
                'timestart' => $discussion2->timestart,
                'timeend' => $discussion2->timeend,
                'firstuserfullname' => fullname($user2),
                'firstuserimagealt' => $user2->imagealt,
                'firstuserpicture' => $user2->picture,
                'firstuseremail' => $user2->email,
                'subject' => $discussion2->name,
                'numreplies' => 2,
                'numunread' => 3,
                'lastpost' => $discussion2reply2->id,
                'lastuserid' => $user3->id,
                'lastuserfullname' => fullname($user3),
                'lastuserimagealt' => $user3->imagealt,
                'lastuserpicture' => $user3->picture,
                'lastuseremail' => $user3->email
            );
            $expecteddiscussions[$discussion3->id] = array(
                'id' => $discussion3->id,
                'course' => $discussion3->course,
                'anonforum' => $discussion3->anonforum,
                'name' => $discussion3->name,
                'firstpost' => $discussion3->firstpost,
                'userid' => $discussion3->userid,
                'groupid' => $discussion3->groupid,
                'assessed' => $discussion3->assessed,
                'timemodified' => $discussion3->timemodified,
                'usermodified' => $discussion3->usermodified,
                'timestart' => $discussion3->timestart,
                'timeend' => $discussion3->timeend,
                'firstuserfullname' => fullname($user2),
                'firstuserimagealt' => $user2->imagealt,
                'firstuserpicture' => $user2->picture,
                'firstuseremail' => $user2->email,
                'subject' => $discussion3->name,
                'numreplies' => 0,
                'numunread' => 1,
                'lastpost' => $discussion3->firstpost,
                'lastuserid' => $user2->id,
                'lastuserfullname' => fullname($user2),
                'lastuserimagealt' => $user2->imagealt,
                'lastuserpicture' => $user2->picture,
                'lastuseremail' => $user2->email
            );

        // Call the external function passing forum ids.
        $discussions = mod_anonforum_external::get_anonforum_discussions(array($anonforum1->id, $anonforum2->id, $anonforum3->id));
        external_api::clean_returnvalue(mod_anonforum_external::get_anonforum_discussions_returns(), $discussions);
        $this->assertEquals($expecteddiscussions, $discussions);

        // Remove the users post from the qanda forum and ensure they can not return the discussion.
        $DB->delete_records('anonforum_posts', array('id' => $discussion2reply1->id));
        try {
            mod_anonforum_external::get_anonforum_discussions(array($anonforum2->id));
            $this->fail('Exception expected due to attempting to access qanda forum without posting.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Call without required view discussion capability.
        $this->unassignUserCapability('mod/anonforum:viewdiscussion', null, null, $course1->id);
        try {
            mod_anonforum_external::get_anonforum_discussions(array($anonforum1->id));
            $this->fail('Exception expected due to missing capability.');
        } catch (moodle_exception $e) {
            $this->assertEquals('nopermissions', $e->errorcode);
        }

        // Unenrol user from second course.
        $enrol->unenrol_user($instance2, $user1->id);

        // Call for the second course we unenrolled the user from, make sure exception thrown.
        try {
            mod_anonforum_external::get_anonforum_discussions(array($anonforum2->id));
            $this->fail('Exception expected due to being unenrolled from the course.');
        } catch (moodle_exception $e) {
            $this->assertEquals('requireloginerror', $e->errorcode);
        }
    }
}
