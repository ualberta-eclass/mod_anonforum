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
 * Steps definitions related with the anonymous forum activity.
 *
 * @package    mod_anonforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

use Behat\Behat\Context\Step\Given as Given,
    Behat\Gherkin\Node\TableNode as TableNode;
/**
 * Anonymous forum-related steps definitions.
 *
 * @package    mod_anonforum
 * @category   test
 * @copyright  2013 David Monllaó
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_mod_anonforum extends behat_base {

    /**
     * Adds a discussion to the anonymous forum specified by it's name with the provided table data (usually Subject and Message). The step begins from the forum's course page.
     *
     * @When /^I add a new discussion to "([^"]*)" anonymous forum with:$/
     *
     * @param string $anonforumname
     * @param TableNode $table
     */
    public function i_add_a_anonforum_discussion_to_anonforum_with($anonforumname, TableNode $table) {

        // Escaping $anonforumname as it has been stripped automatically by the transformer.
        return array(
            new Given('I follow "' . $this->escape($anonforumname) . '"'),
            new Given('I press "' . get_string('addanewdiscussion', 'anonforum') . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttoanonforum', 'anonforum') . '"'),
            new Given('I wait to be redirected')
        );
    }

    /**
     * Adds a reply to the specified post of the specified anonymous forum. The step begins from the anonymous forum's page or from the anonymous forum's course page.
     *
     * @Given /^I reply "(?P<post_subject_string>(?:[^"]|\\")*)" post from "(?P<anonforum_name_string>(?:[^"]|\\")*)" anonymous forum with:$/
     * @param string $postname The subject of the post
     * @param string $anonforumname The anonymous forum name
     * @param TableNode $table
     */
    public function i_reply_post_from_anonforum_with($postsubject, $anonforumname, TableNode $table) {

        return array(
            new Given('I follow "' . $this->escape($anonforumname) . '"'),
            new Given('I follow "' . $this->escape($postsubject) . '"'),
            new Given('I should see "Reply"'),
            new Given('I follow "' . get_string('reply', 'anonforum') . '"'),
            new Given('I set the following fields to these values:', $table),
            new Given('I press "' . get_string('posttoanonforum', 'anonforum') . '"'),
            new Given('I wait to be redirected')
        );
    }
}
