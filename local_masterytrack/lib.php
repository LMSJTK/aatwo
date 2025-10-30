<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Add navigation items
 *
 * @param global_navigation $navigation
 */
function local_masterytrack_extend_navigation(global_navigation $navigation) {
    global $PAGE, $USER;

    if (!get_config('local_masterytrack', 'enabled')) {
        return;
    }

    $context = context_system::instance();

    // Add mastery track node
    $node = $navigation->add(
        get_string('masterytrack', 'local_masterytrack'),
        null,
        navigation_node::TYPE_CUSTOM,
        null,
        'masterytrack'
    );

    // View own progress
    if (isloggedin() && !isguestuser()) {
        $node->add(
            get_string('progress', 'local_masterytrack'),
            new moodle_url('/local/masterytrack/individual_report.php', ['userid' => $USER->id]),
            navigation_node::TYPE_CUSTOM
        );
    }

    // Reports
    if (has_capability('local/masterytrack:viewreports', $context)) {
        $node->add(
            get_string('reports', 'local_masterytrack'),
            new moodle_url('/local/masterytrack/group_trends.php'),
            navigation_node::TYPE_CUSTOM
        );
    }

    // Manage programs
    if (has_capability('local/masterytrack:manageprograms', $context)) {
        $node->add(
            get_string('programs', 'local_masterytrack'),
            new moodle_url('/local/masterytrack/manage_programs.php'),
            navigation_node::TYPE_CUSTOM
        );
    }
}

/**
 * Hook into course completion event
 *
 * @return array
 */
function local_masterytrack_get_completion_handlers() {
    return [
        'course_completed' => 'local_masterytrack_course_completed',
    ];
}

/**
 * Handle course completion
 *
 * @param stdClass $data
 */
function local_masterytrack_course_completed($data) {
    global $DB;

    $userid = $data->userid;
    $courseid = $data->courseid;

    // Find all programs that include this course
    $sql = "SELECT mc.programid, mc.courseid, gg.finalgrade
            FROM {local_masterytrack_courses} mc
            JOIN {grade_grades} gg ON gg.userid = :userid
            JOIN {grade_items} gi ON gg.itemid = gi.id AND gi.courseid = :courseid
            WHERE mc.courseid = :courseid2
            AND gi.itemtype = 'course'";

    $records = $DB->get_records_sql($sql, [
        'userid' => $userid,
        'courseid' => $courseid,
        'courseid2' => $courseid
    ]);

    foreach ($records as $record) {
        // Update progress
        \local_masterytrack\local\progress_tracker::update_course_completion(
            $userid,
            $record->programid,
            $courseid,
            $record->finalgrade ?? 0
        );
    }
}

/**
 * Serve plugin files
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function local_masterytrack_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_SYSTEM) {
        return false;
    }

    require_login();

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/local_masterytrack/{$filearea}/{$relativepath}";
    $file = $fs->get_file_by_hash(sha1($fullpath));

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
