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
 * Collection of useful functions and constants
 *
 * @package    block_dukreminder
 * @copyright  gtn gmbh <office@gtn-solutions.com>
 * @author       Florian Jungwirth <fjungwirth@gtn-solutions.com>
 * @ideaandconcept Gerhard Schwed <gerhard.schwed@donau-uni.ac.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define('COMPLETION_STATUS_ALL', 0);
define('COMPLETION_STATUS_COMPLETED', 1);
define('COMPLETION_STATUS_NOTCOMPLETED', 2);

define('PLACEHOLDER_COURSENAME', '###coursename###');
define('PLACEHOLDER_USERNAME', '###username###');
define('PLACEHOLDER_USERMAIL', '###usermail###');
define('PLACEHOLDER_USERCOUNT', '###usercount###');
define('PLACEHOLDER_USERS', '###users###');

define('CRITERIA_COMPLETION', 250000);
define('CRITERIA_ENROLMENT', 250001);
define('CRITERIA_ALL', 250002);
define('CRITERIA_ACTIVITY_GRADE', 250003); // added by G. Schwed, 2016-01

// SHOULD BE CHANGED.
define('EMAIL_DUMMY', 2);

/**
 * Build navigation tabs
 * @param integer $courseid
 */
function block_dukreminder_build_navigation_tabs($courseid) {

    $rows[] = new tabobject('tab_course_reminders',
        new moodle_url('/blocks/dukreminder/course_reminders.php',
        array("courseid" => $courseid)),
        get_string('tab_course_reminders', 'block_dukreminder'));
    $rows[] = new tabobject('tab_new_reminder',
        new moodle_url('/blocks/dukreminder/new_reminder.php',
        array("courseid" => $courseid)),
        get_string('tab_new_reminder', 'block_dukreminder'));
    return $rows;
}

/**
 * Init Js and CSS
 * @return nothing
 */
function block_dukreminder_init_js_css() {

}
/**
 * This function gets all the pending reminder entries. An entry is pending
 * if dateabsolute is set and it is not sent yet (sent = 0)
 * OR
 * if daterelative is set
 *
 * @return array $entries
 */
function block_dukreminder_get_pending_reminders() {
    global $DB;
    $entries = $DB->get_records('block_dukreminder', array('sent' => 0));
    $now = time();

    // Get all potential reminders.
    $entries = $DB->get_records_select('block_dukreminder',
        "(sent = 0 AND dateabsolute > 0 AND dateabsolute < $now) OR (dateabsolute = 0 AND daterelative > 0)");

    // Check for non existing (deleted) courses and unset the related reminders
    // Hack: normally reminders should be deleted whe a course is deleted.
    foreach ($entries as $entry) {
        $course = $DB->get_record('course', array('id' => $entry->courseid));
        if ($course === FALSE) {
            mtrace("... course $entry->courseid does not exist (perhaps deleted) => skipped<br/>");
            unset($entries[$entry->id]);
        }
    }
    mtrace("... " . count($entries) . " pending reminder(s) found<br/>");
    return $entries;
}

/**
 * Replace placeholders
 * @param string $text
 * @param string $coursename
 * @param string $username
 * @param string $usermail
 * @param string $users
 * @param string $usercount
 * @return nothing
 */
function block_dukreminder_replace_placeholders($text, $coursename = '', $username = '',
            $usermail = '', $users = '', $usercount = '') {

    $text = str_replace(PLACEHOLDER_COURSENAME, $coursename, $text);
    $text = str_replace(PLACEHOLDER_USERMAIL, $usermail, $text);
    $text = str_replace(PLACEHOLDER_USERNAME, $username, $text);
    $text = str_replace(PLACEHOLDER_USERCOUNT, $usercount, $text);
    $text = str_replace(PLACEHOLDER_USERS, $users, $text);

    return $text;
}

/**
 * This function filters the users to recieve a reminder according to the
 * criterias recorded in the database.
 * The criterias are:
 *  - deadline: amount of sec after course enrolment
 *  - groups: user groups specified in the course
 *  - completion status: if users have already completed/not completed the course
 *  - passed grade of an activity
 *
 * @param stdClass $entry database entry of block_dukreminder table
 * @return array $users users to recieve a reminder
 */
function block_dukreminder_filter_users($entry) {
    global $CFG, $DB;
    $criteria_name = block_dukreminder_get_criteria($entry->criteria);
    $details = "";
    // If criteria is 'grade' => get some details.
    if ($entry->criteria > 1000000) {
        $gradeitemid = $entry->criteria - 1000000;
        $entry->criteria = CRITERIA_ACTIVITY_GRADE; // = 25000
        $grade_item = $DB->get_record('grade_items', array('id' => $gradeitemid));
        #print "grade_item: $grade_item->itemname; gradepass: $grade_item->gradepass<br>"; // Schwed debug
        $details = "; required grade: $grade_item->gradepass";
    }
    $type = ($entry->dateabsolute > 0) ? 'absolute' : 'relative';
    mtrace("<br>processing reminder $entry->id: $type / subject: $entry->title / criteria: $criteria_name / $details<br/>");

    // Get all potential users.
    #$users = get_role_users(5, context_course::instance($entry->courseid)); // original
    $users = get_role_users(5, context_course::instance($entry->courseid), '', 'u.id, u.auth, u.username, u.firstname, u.lastname, u.email, u.institution, u.department, u.idnumber, u.address, u.suspended'); // some fields added for AUA by G. Schwed

    // START Modification for AUA by G. Schwed
    // Remove all 'visitors' (persons with role-id = 10).
    $visitors = get_role_users(10, context_course::instance($entry->courseid));
    foreach ($visitors as $visitor) {
        mtrace("... unset visitor $visitor->id, $visitor->email<br/>");
        unset($users[$visitor->id]);
    }
    // End AUA special

    // Remove all suspended users, users with nologin and users with email still sent.
    foreach($users as $user) {
        if ($user->auth == "nologin") {
            mtrace("... unset nologin user $user->id, $user->email<br/>");
            unset($users[$user->id]);
        }
        if ($user->suspended == 1) {
            mtrace("... unset suspended user $user->id, $user->email<br/>");
            unset($users[$user->id]);
        }

        // Check if email was already sent to this user.
        if ($DB->record_exists('block_dukreminder_mailssent', array('reminderid' => $entry->id, 'userid' => $user->id))) {
            mtrace("... email already sent to user $user->id, $user->email => skipped<br/>");
            unset($users[$user->id]);
        }

        // Check for active enrolment in this course.
        if (!is_enrolled(context_course::instance($entry->courseid), $user, '', TRUE)) {
            mtrace("... user $user->id is suspended => skipped<br>");
            unset($users[$user->id]);
        }
    }

    // Filter users by groups.
    $groupids = explode(';', $entry->to_groups);
    if ($entry->to_groups) {
        foreach ($users as $user) {
            // If user is  part in 1 or more group -> unset.
            $ismember = false;
            foreach ($groupids as $groupid) {
                if (groups_is_member($groupid, $user->id)) {
                    $ismember = true;
                }
            }

            // Unset if user is not at least in one of the selected groups
            // or REVERSE to sent only to persons NOT in selected groups: if($ismember); G. Schwed
            if (!$ismember) { 
                mtrace("... user $user->id not in group => skipped<br/>");
                unset($users[$user->id]);
            }
        }
    }

    // Number of remaining users.
    $count = count($users);
    if ($count == 0) {
        mtrace("=> No users found for reminder $entry->id => nothing to do!<br/>");
        return $users;
    } else {
        mtrace("... $count potential users found; ");
    }


    // Process time and criterias
    // ---------------------------

    // Filter users by absolute date + ALL
    // Send email to all users at the specified day.
    if ($entry->dateabsolute > 0 && $entry->criteria == CRITERIA_ALL) {
        #mtrace("absolute + all<br>"); // debug
        return $users;
    }

    // Filter user by absolute date + course completion.
    // Send email if user has not yet completed the course.
    if ($entry->dateabsolute > 0 && $entry->criteria == CRITERIA_COMPLETION) {
        #mtrace("absolute + course not completed<br>"); // debug
        foreach ($users as $user) {
            $select = "course = $entry->courseid AND userid = $user->id";
            $timecompleted = $DB->get_field_select('course_completions', 'timecompleted', $select);
            // If user has completed -> unset.
            if (($timecompleted)) {
                $timecompleted = date("d.m.Y", $timecompleted); // convert timestamp to string
                mtrace("... user $user->id: course completed $timecompleted => skipped<br/>");
                unset($users[$user->id]);
            }
        }
        return $users;
    }

    // Filter users by absolute date + activity grade
    // Send email if user has not yet reached the minimum grade for activity.
    if ($entry->dateabsolute > 0 && $entry->criteria == CRITERIA_ACTIVITY_GRADE) {
        #mtrace("absolute + grade; courseid: $entry->courseid<br>"); // debug
        // Get grade item.
        $grade_item = $DB->get_record('grade_items', array('id' => $gradeitemid));

        // Check finalgrade for each user and skip if he has passed
        foreach ($users as $user) {
            $grade = $DB->get_record('grade_grades', array('itemid' => $grade_item->id, 'userid' => $user->id));
            $detail = "";
            if ($grade) {
                $detail .= " grade: $grade->rawgrade; finalgrade: $grade->finalgrade";
            } else { $detail .= "no grades";
            }
            // Unset if user has passed.
            if ($grade AND $grade->finalgrade >= $grade_item->gradepass) {
                $detail .= " => passed => skipped";
                unset($users[$user->id]);
            } else { $detail .= " => not passed!";
            }
            mtrace("... user $user->id: $detail<br>");
        }
        return $users;
    }


    // Filter user by absolute date + others (= activity completion).
    // Send email if user has not completed the activity.
    if ($entry->dateabsolute > 0 && $entry->criteria != CRITERIA_ALL) { // Criteria (activity) completion.
        #mtrace("absolute + 'not all = activity completion'<br>"); // debug
        $course = $DB->get_record('course', array('id' => $entry->courseid));
        $completion = new completion_info($course);

        // Unset all users if no valid criterias.
        // (Perhaps course criterias have been changed after creating the reminder.)
        if ($DB->get_record('course_completion_criteria', array('id' => $entry->criteria)) === FALSE) {
            mtrace("... ! Error: no or wrong completion criterias ! => reminder skipped<br>");
            unset($users);
            $users = array();
            return $users;
        } else {
            // Check for comleted activity
            $criteria = completion_criteria::factory((array)$DB->get_record('course_completion_criteria', array('id' => $entry->criteria)));
            foreach ($users as $user) {
                $usercompleted = $completion->get_user_completion($user->id, $criteria);
                if ($usercompleted->is_complete()) {
                    mtrace("... user $user->id: activity completed => skipped<br/>");
                    unset($users[$user->id]);
                }
            }
        }
        return $users;
    }


    //
    // Relative time
    // 
    // Filter users by relative time + course enrolment
    // Send email if user is enrolled long enough and has not completed the course.
    if ($entry->daterelative > 0 && $entry->criteria == CRITERIA_ENROLMENT) {
        #mtrace("relative  + enrolment<br>"); // debug
        $enabledenrolplugins = implode(',', $DB->get_fieldset_select('enrol', 'id', "courseid = $entry->courseid AND status = 0"));

        foreach ($users as $user) {
            // If user has completed the course -> unset.
            $timecompleted = $DB->get_field('course_completions',
                'timecompleted', array('userid' => $user->id, 'course' => $entry->courseid));
            if ($timecompleted > 0) {
                mtrace("... user $user->id; course completed => skipped<br/>");
                unset($users[$user->id]);
            }

            // Check user enrolment dates.
            $enrolments = array_values($DB->get_records_select('user_enrolments', "userid = $user->id AND enrolid IN ($enabledenrolplugins)"));
            $enrolid = $enrolments[0]->enrolid; // Take just first enrolment method. (Normally it should be 'manual'.)
            $enrolmenttime = $enrolments[0]->timestart;
            // Hack by G. Schwed, because some enrolment plugins still use 'timecreated' instead of 'timestart'.
            if ($enrolmenttime == 0) {
                $enrolmenttime = $enrolments[0]->timecreated;
                mtrace("... user $user->id: 'timestart' = 0 (enrolment $enrolid) => using 'timecreated'<br>");
            }

            // If user is not enrolled long enough -> unset.
            if ($enrolmenttime + $entry->daterelative > time()) {
                mtrace("... user $user->id not enrolled long enough => skipped<br/>");
                unset($users[$user->id]);
                continue;
            }

        }
        return $users;
    }

    // Filter users by relative time + course completion.
    // Send email if the course was completed the specified time ago.
    if ($entry->daterelative > 0 && $entry->criteria == CRITERIA_COMPLETION) {
        #mtrace("relative  + course completion<br>"); // debug
        // Check user completion dates.
        foreach ($users as $user) {
            $completiontime = $DB->get_field('course_completions',
                'timecompleted',
                array('userid' => $user->id, 'course' => $entry->courseid));
            // If user completion is not long enough ago -> unset.
            if ($completiontime == FALSE || ($completiontime + $entry->daterelative > time())) {
                mtrace("... user $user->id: course not completed or deadline not reached for => skipped<br/>");
                unset($users[$user->id]);
            }
        }
        return $users;
    }

    // Filter user by relative time + activity grade.
    // Send email if minimum grade was reached more than specified time ago.
    if ($entry->daterelative > 0 && $entry->criteria == CRITERIA_ACTIVITY_GRADE) {
        #mtrace("relative + grade<br>"); // debug
        // Get grade item.
        $grade_item = $DB->get_record('grade_items', array('id' => $gradeitemid));

        // Skip all users without grade.
        foreach ($users as $user) {
            $grade = $DB->get_record('grade_grades', array('itemid' => $grade_item->id, 'userid' => $user->id));
            // Unset if no grades available.
            if (!$grade or $grade->rawgrade == '') {
                mtrace("... user $user->id: no attempt => skipped<br>");
                unset($users[$user->id]);
            }
        }

        // If activity is QUIZ:
        if ($grade_item->itemmodule == 'quiz') {
            // Get details of quiz and calculate grading factor (max. grade / total of question points).
            $quiz = $DB->get_record('quiz', array('id' => $grade_item->iteminstance));
            $g = $quiz->grade / $quiz->sumgrades; //

            // Find the last passed attempt and check grade and time.
            foreach ($users as $user) {
                $details = "... user $user->id: ";
                // Get last passed quiz attempt for user.
                $sql = "SELECT * FROM {quiz_attempts} 
                        WHERE quiz = $grade_item->iteminstance 
                        AND userid = $user->id 
                        AND sumgrades * $g > $grade_item->gradepass
                        ORDER BY timefinish DESC LIMIT 1";
                $quiz_attempt = $DB->get_record_sql($sql);
                if ($quiz_attempt) {
                    $details .= "Attempt $quiz_attempt->attempt, Grade: " . $quiz_attempt->sumgrades * $g . "; time: ".userdate($quiz_attempt->timefinish);
                } else {
                    mtrace($details . "no succesful attempt => skipped<br>");
                    unset($users[$user->id]);
                    continue;
                }
                // Check time
                if ($quiz_attempt->timefinish + $entry->daterelative > time()) {
                    $details .= "; deadline not reached => skipped";
                    unset($users[$user->id]);
                }
                mtrace($details."<br>");
            }
            return $users;
        }

        // If activity is SCORM:
        if ($grade_item->itemmodule == 'scorm') {
            require_once $CFG->dirroot.'/mod/scorm/locallib.php';
            $scorm = $DB->get_record('scorm', array('id' => $grade_item->iteminstance));

            // Get all attempts of a user, find the latest passed and check grade and time
            foreach ($users as $user) {
                $details = "... user $user->id: ";
                $attempts = scorm_get_all_attempts($scorm->id, $user->id);
                // Get grades and times for all attempts.
                foreach ($attempts as $i) {
                    $grade = scorm_grade_user_attempt($scorm, $user->id, $i);
                    $time  = scorm_get_sco_runtime($scorm->id, '', $user->id, $i)->finish;
                    $grades[$i] = $grade;
                    if ($grade >= $grade_item->gradepass) {
                        $times[$i] = $time;
                    } else {
                        $times[$i] = 0;
                    }
                }

                // Skip user if no passed attempt available.
                if (max($times) == 0) {
                    mtrace($details . "$i attempt(s); not yet passed! => skipped<br>"); 
                    unset($users[$user->id]);
                    unset($times);
                    continue;
                }
                // Find latest passed attempt.
                if (max($times) > 0) {
                    $latest = max($times);
                    $la = array_search($latest, $times);
                    $details .= "$i attempt(s); latest passed: $la; grade = $grades[$la]; finished ".userdate($times[$la]);
                }
                // Check time.
                if ($times[$la] + $entry->daterelative > time()) {
                    $details .= "; deadline not reached => skipped";
                    unset($users[$user->id]);
                }
                unset($times);
                mtrace($details."<br>");
            }
            return $users;
        }
        mtrace("ERROR. Das sollte nicht vorkommen!");
    }


    // Filter users by relative time + activity completion.
    if ($entry->daterelative > 0 && $entry->criteria != CRITERIA_COMPLETION && $entry->criteria != CRITERIA_ENROLMENT) {
        #mtrace("relative + activity completion<br>"); // debug
        // Check for valid criterias
        // (because perhaps course criterias have been changed after creating the reminder)
        if ($DB->get_record('course_completion_criteria', array('id' => $entry->criteria)) === FALSE) {
            mtrace("... ! Error: no or wrong completion criterias ! => reminder skipped<br>");
            // unset all users to skip this reminder
            foreach ($users as $user) {
                unset($users[$user->id]);
            }
            return $users;
        } else {
            $criteria = completion_criteria::factory((array)$DB->get_record('course_completion_criteria', array('id' => $entry->criteria)));
        }

        $course = $DB->get_record('course', array('id' => $entry->courseid));
        $completion = new completion_info($course);
        // Check user completion dates.
        foreach ($users as $user) {
            $usercompleted = $completion->get_user_completion($user->id, $criteria);
            // If user criteria completion is not long enough ago -> unset.
            if (!isset($usercompleted->timecompleted) || ($usercompleted->timecompleted + $entry->daterelative > time())) {
                mtrace("... user $user->id: activity not completed or deadline not reached => skipped<br/>");
                unset($users[$user->id]);
            }
        }
        return $users;
    }
    mtrace("... error: no matching criterias<br>");
}


/**
 * Get manager
 * @param object $user
 * @return boolean
 *
 * Get superior (manager) for each user.
 * Manager emails are stored in custom profile field 'manager' for each user.
 */
function block_dukreminder_get_manager($users) {
    global $DB, $CFG;
    require_once $CFG->dirroot.'/user/profile/lib.php';
    // Load all custom profile fields, esp. for 'manager' and 'director'
    profile_load_custom_fields($users);
    if (isset($users->profile['manager']) && ($users->profile['manager'] <> '')) {
        $manager = $users->profile['manager'];
        // Suche userid des Vorgesetzten in mdl_user
        $select = "email LIKE '$manager'";
        $manager_id = $DB->get_field_select('user', 'id', $select);

        // Hole Details des Managers aus mdl_user
        $manager = $DB->get_record('user',array('id' => $manager_id));
        #mtrace("... User $users->id => Manager: $manager_id, $manager->email"); // Schwed, debug

        return $manager;
    }
}

/**
 * Get AUA manager
 * @param object $user
 * @return boolean
 *
 * Special funtion for AUA
 * AUA managers are stored as LDAP records in address field of every user.
 */
//
function block_dukreminder_get_manager_aua($user) {
    global $DB;
    if (strlen($user->address) > 0) { // Wenn Feld nicht leer
        $manager = addslashes(substr($user->address, 0, 50)) . "%";
        // Search for user id of manager in mdl_user.
        $select = "idnumber LIKE '$manager'";
        $managerid = $DB->get_field_select('user', 'id', $select);
    } else { // If no manager available take default user.
        #$managerid = 2; // Gerhard Schwed Admin, debug; special for AUA
        #$managerid = 16543; // = Team Compliance; special for AUA
    }
    // Get details from mdl_user.
    $manager = $DB->get_record('user', array('id' => $managerid));
    return $manager;
}


/**
 * Get director
 * @param object $user
 * @return boolean
 *
 * Get director for each user.
 * Director emails are stored in custom profile field 'director' for each user.
 */
function block_dukreminder_get_director($users) {
    global $DB;
    // Load all custom profile fields, esp. for 'manager' and 'director'
    profile_load_custom_fields($users);
    if (isset($users->profile['director']) && ($users->profile['director'] <> '')) {
        $director = $users->profile['director'];
        // Suche userid des Vorgesetzten in mdl_user
        $select = "email LIKE '$director'";
        $director_id = $DB->get_field_select('user', 'id', $select);

        // Hole Details des Directors aus mdl_user
        $director = $DB->get_record('user',array('id' => $director_id));
        #mtrace("... User $users->id => Director: $director_id, $director->email"); // Schwed, debug

        return $director;
    }
}



/**
 * Replace placeholders
 * @param string $course
 * @param array $users
 * @param boolean $textteacher
 * @return nothing
 */
function block_dukreminder_get_mail_text($course, $users, $textteacher = null) {

    $userlisting = '';
    foreach ($users as $user) {
        $userlisting .= "\n<br>" . fullname($user) . ", $user->email";
    }

    // If text_teacher is not set, use lang string (for old reminders).
    if (!$textteacher) {
        $textparams = new stdClass();
        $textparams->amount = count($users);
        $textparams->course = $course;

        $mailtext = get_string('email_teacher_notification', 'block_dukreminder', $textparams);
        $mailtext .= $userlisting;
    } else {
        // If text_teacher is set, use it and replace placeholders.
        $mailtext = block_dukreminder_replace_placeholders($textteacher, $course, '', '', $userlisting, count($users));
        #$mailtext = strip_tags($mailtext); // deactivated by G. Schwed
    }
    return $mailtext;
}

/**
 * Get course teachers
 * @param string $coursecontext
 * @return array
 */
function block_dukreminder_get_course_teachers($coursecontext) {
    return array_merge(get_role_users(4, $coursecontext),
        get_role_users(3, $coursecontext),
        get_role_users(2, $coursecontext),
        get_role_users(1, $coursecontext));
}

/**
 * Get criteria
 * @param string $criteriaid
 * @return string
 */
function block_dukreminder_get_criteria($criteriaid) {
    global $DB;

    if ($criteriaid == CRITERIA_COMPLETION) {
        return get_string('criteria_completion', 'block_dukreminder');
    };
    if ($criteriaid == CRITERIA_ENROLMENT) {
        return get_string('criteria_enrolment', 'block_dukreminder');
    };
    if ($criteriaid == CRITERIA_ALL) {
        return get_string('criteria_all', 'block_dukreminder');
    }
    if ($criteriaid > 1000000) { // 
        $grade_item_id = $criteriaid - 1000000;
        $gradeitem = $DB->get_record('grade_items', array('id' => $grade_item_id));
        #print_r($gradeitem); // Schwed debug
        #$itemname = $gradeitem->itemname; // Schwed debug
        #print "itemname = $itemname<br>"; // Schwed debug
        return get_string('criteria_activity_grade', 'block_dukreminder') . ': ' .  $gradeitem->itemname . " (" . $gradeitem->itemmodule . ")";
    }

    // Any other case must be an activity.
    // So let's get its name to display.
    $completioncriteriaentry = $DB->get_record('course_completion_criteria', array('id' => $criteriaid));
    #print_r($completioncriteriaentry); // debug
    $mod = new stdClass();
    if (isset($completioncriteriaentry->module)) {
        $mod = get_coursemodule_from_id($completioncriteriaentry->module, $completioncriteriaentry->moduleinstance);
    }
    return $mod->name;
}
