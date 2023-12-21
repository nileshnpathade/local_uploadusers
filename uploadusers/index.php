<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin version and other meta-data are defined here.
 *
 * @package     local_uploadusers
 * @copyright   2023 Nilesh Pathade nileshnpathade@gmail.com
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/local/uploadusers/uploadusers_form.php');
require_once($CFG->libdir.'/csvlib.class.php');
require_login();
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/uploadusers/index.php'));
$PAGE->set_pagelayout('standard');
$PAGE->set_title($SITE->fullname);
$mform = new local_uploadusers_form(array());

if ($mform->is_cancelled()) {
    redirect($CFG->wwwroot.'/local/uploadusers/index.php');
} else if ($data = $mform->get_data()) {
    $iid = csv_import_reader::get_new_iid('uploaduser');
    $cir = new csv_import_reader($iid, 'uploaduser');
    $content = $mform->get_file_content('userfile');
    $readcount = $cir->load_csv_content($content, null, null);
    $csvloaderror = $cir->get_error();
    $columns = $cir->get_columns();
    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        redirect('index.php', null, null, \core\notification::error(get_string('columnsemty', 'local_uploadusers')));
    }

    if (count($columns) === 2) {
        $cir->close();
        $cir->cleanup();
        redirect('index.php', null, null, \core\notification::error(get_string('invalidcsv', 'local_uploadusers')));
    }

    $processed = array();
    $columnarray = array('firstname', 'lastname', 'email');
    foreach ($columns as $key => $unused) {
        $field = $columns[$key];
        $field = trim($field);
        if (!in_array($field, $columnarray)) {
            redirect('index.php', null , null,
                \core\notification::error(get_string('invalidcsv', 'local_uploadusers')));
        }
    }

    $cir->init();
    while ($line = $cir->next()) {
        $tempuser = $DB->get_record('user', array('email' => $line[2]), '*', MUST_EXIST);
        // Email confirmation directly rather than using messaging so they will definitely get an email.
        $noreplyuser = core_user::get_noreply_user();
        if (!$mailresults = email_to_user($tempuser, $noreplyuser, get_string('emailtitle', 'local_uploadusers'),
            get_string('emailnotification', 'local_uploadusers'))) {
            redirect('index.php', get_string('emailsendsuccfully', 'local_uploadusers') , null,
                \core\output\notification::NOTIFY_SUCCESS);
        }
    }

    unset($content);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_uploadusers'));
echo $OUTPUT->box(get_string('uploadusertitle', 'local_uploadusers'));
echo html_writer::start_div('uploadusers');
$mform->display();
echo html_writer::end_div();
echo $OUTPUT->footer();
