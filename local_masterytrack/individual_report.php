<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_masterytrack\output\individual_report;

$userid = optional_param('userid', $USER->id, PARAM_INT);
$programid = required_param('programid', PARAM_INT);

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url('/local/masterytrack/individual_report.php', ['userid' => $userid, 'programid' => $programid]);
$PAGE->set_title(get_string('individualreport', 'local_masterytrack'));
$PAGE->set_heading(get_string('individualreport', 'local_masterytrack'));

// Check permissions
if ($userid != $USER->id) {
    require_capability('local/masterytrack:viewreports', $context);
}

echo $OUTPUT->header();

$report = new individual_report($userid, $programid);
$data = $report->export_for_template($OUTPUT);

echo $OUTPUT->render_from_template('local_masterytrack/individual_report', $data);

echo $OUTPUT->footer();
