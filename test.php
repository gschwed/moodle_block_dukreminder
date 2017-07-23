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
 * Test
 *
 * @package    block_dukreminder
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 * @author       Florian Jungwirth <fjungwirth@gtn-solutions.com>
 * @ideaandconcept Gerhard Schwed <gerhard.schwed@donau-uni.ac.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(__FILE__)."/inc.php");
global $CFG, $DB, $OUTPUT, $PAGE, $USER;
$courseid = required_param('courseid', PARAM_INT);

// Check for valid courseid, login and user capabilities
if (!$course = $DB->get_record('course', array('id' => $courseid))) {
    print_error('invalidcourse', 'block_simplehtml', $courseid);
}
require_login($course);

$context = context_course::instance($courseid);
require_capability('block/dukreminder:use', $context);

print "<b>Reminder Test for course $courseid</b><br>"; // debug


// Development
/*
require_once($CFG->libdir.'/completionlib.php');
$completioninfo = new completion_info($course);
$status = $completioninfo->has_activities();
print "Kurs Activity Status: $status<br>";
$completiondata = $completioninfo->get_activities();
print "modules with completion tracking:<br>";
print_r($completiondata);

foreach ($completiondata as $abc) {
    print "<br>----<br>";
    print_r($abc);
}
*/
// End development


// Main program
$entries = block_dukreminder_get_pending_reminders();

// Get only reminders of actual course => unset all others.
foreach ($entries as $entry) {
    if ($entry->courseid <> $courseid) unset($entries[$entry->id]);
}

print_r($entries);

// Process all found reminders.
foreach ($entries as $entry) {
    $mailssent = $entry->mailssent;
    $creator = $DB->get_record('user', array('id' => $entry->createdby));
    $course = $DB->get_record('course', array('id' => $entry->courseid));
    $coursecontext = context_course::instance($course->id);

    $users = block_dukreminder_filter_users($entry);

    // Exit if no users found for this reminder
    if (count($users) == 0) continue;


    // Go through users and send mails AND save the user managers.
    $managers = array();
    foreach ($users as $user) {

        // If email was already sent to this user skip and continue with next user.
        if ($DB->record_exists('block_dukreminder_mailssent', array('reminderid' => $entry->id, 'userid' => $user->id))) {
            mtrace("... email already sent to user $user->id, $user->email => skipped!!<br/>");
            continue;
        }

        // Send email.
        $user->mailformat = FORMAT_HTML;
        $mailtext = block_dukreminder_replace_placeholders($entry->text, $course->fullname, fullname($user), $user->email);
    #    email_to_user($user, $creator, $entry->subject, strip_tags($mailtext), $mailtext); // deactivated for testing purposes only
        mtrace("... a reminder mail was sent to student $user->id, $user->email for $entry->subject <br/>");
        $mailssent++;

        // Log reminderid, userid and timestamp into database.
    #    $DB->insert_record('block_dukreminder_mailssent', array('userid' => $user->id, 'reminderid' => $entry->id, 'timesent' => time())); // deactivated for testing purposes only

        // Update number of sent emails in DB
        #$entry->mailssent += 1;
        $entry->mailssent = $DB->get_field('block_dukreminder', 'mailssent', array('id' => $entry->id)) + 1;
        unset($entry->criteria); 
        print_r($entry); // Schwed debug
    #    $DB->update_record('block_dukreminder', $entry); // deactivated for testing purposes only

        // Check for user superior/manager and save information for later notifications.
        if ($entry->to_reportsuperior) {
            // $usermanager = block_dukreminder_get_manager_aua($user); // Schwed, special Version for AUA
            $usermanager = block_dukreminder_get_manager($user);
            if ($usermanager) {
                if (!isset($managers[$usermanager->id])) {
                    $managers[$usermanager->id] = $usermanager;
                }
                $managers[$usermanager->id]->users[] = $user;
            }
        }

        // Check for user director and save information for later notifications.
        if ($entry->to_reportdirector) {
            $userdirector = block_dukreminder_get_director($user);
            if ($userdirector) {
                if (!isset($directors[$userdirector->id])) {
                    $directors[$userdirector->id] = $userdirector;
                }
                $directors[$userdirector->id]->users[] = $user;
            }
        }
    }

    // Send reports.
    $mailtext = block_dukreminder_get_mail_text($course->fullname, $users, $entry->text_teacher);
    $subject = "Reminder report: " . $entry->subject; // added by G. Schwed
    // Report to course trainers
    if ($entry->to_reporttrainer && $mailssent > 0) {
        // Get course teachers and send mails, and additional mails.
        $teachers = block_dukreminder_get_course_teachers($coursecontext);
        foreach ($teachers as $teacher) {
        #    email_to_user($teacher, $creator, $subject, strip_tags($mailtext), $mailtext); // deactivated for testing purposes only
            echo("... a report mail was sent to teacher $teacher->id, $teacher->email<br/>");
        }
    }
    // Report to others.
    if ($entry->to_mail && $mailssent > 0) {
        $addresses = explode(';', $entry->to_mail);
        $dummyuser = $DB->get_record('user', array('id' => EMAIL_DUMMY));
        $dummyuser->mailformat = FORMAT_HTML;

        foreach ($addresses as $address) {
            $dummyuser->email = $address;
        #    email_to_user($dummyuser, $creator, $subject, strip_tags($mailtext), $mailtext);
            echo("... a report mail was sent to others: $address <br/>");
        }
    }

    // Report to Superiors/Managers.
    if ($entry->to_reportsuperior && $mailssent > 0) {
        foreach ($managers as $manager) {
            #print_r($manager);
            $mailtext = block_dukreminder_get_mail_text($course->fullname, $manager->users, $entry->text_teacher);
        #    email_to_user($manager, $creator, $subject, strip_tags($mailtext), $mailtext); // deactivated for testing purposes only
            echo("... a report mail was sent to manager $manager->id, $manager->email<br/>");
        }
    }

    // Report to Directors.
    if ($entry->to_reportdirector && $mailssent > 0) {
        foreach ($directors as $director) {
            #print_r($director);
            $mailtext = block_dukreminder_get_mail_text($course->fullname, $director->users, $entry->text_teacher);
        #    email_to_user($director, $creator, $subject, strip_tags($mailtext), $mailtext); // deactivated for testing purposes only
            echo("... a report mail was sent to director $director->id, $director->email<br/>");
        }
    }

    // Set sent status.
    $entry->sent = 1;
#    $DB->update_record('block_dukreminder', $entry); // deactivated for testing purposes only
}
