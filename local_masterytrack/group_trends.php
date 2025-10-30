<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

use local_masterytrack\output\group_trends;

$programid = required_param('programid', PARAM_INT);

require_login();

$context = context_system::instance();
require_capability('local/masterytrack:viewreports', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/masterytrack/group_trends.php', ['programid' => $programid]);
$PAGE->set_title(get_string('grouptrends', 'local_masterytrack'));
$PAGE->set_heading(get_string('grouptrends', 'local_masterytrack'));

echo $OUTPUT->header();

$report = new group_trends($programid);
$data = $report->export_for_template($OUTPUT);

echo $OUTPUT->render_from_template('local_masterytrack/group_trends', $data);

echo $OUTPUT->footer();
