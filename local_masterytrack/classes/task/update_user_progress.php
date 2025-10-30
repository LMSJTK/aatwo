<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\task;

defined('MOODLE_INTERNAL') || die();

use local_masterytrack\local\progress_tracker;

/**
 * Scheduled task to update user progress
 */
class update_user_progress extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('updateprogress', 'local_masterytrack');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        mtrace('Starting progress update...');

        // Get all active programs
        $programs = $DB->get_records('local_masterytrack_programs', ['active' => 1]);

        $updated = 0;
        foreach ($programs as $program) {
            // Get all courses in program
            $courses = $DB->get_records('local_masterytrack_courses', ['programid' => $program->id]);

            foreach ($courses as $coursemapping) {
                // Get completions for this course
                $sql = "SELECT cc.userid, gg.finalgrade
                        FROM {course_completions} cc
                        LEFT JOIN {grade_grades} gg ON gg.userid = cc.userid
                        LEFT JOIN {grade_items} gi ON gg.itemid = gi.id
                        WHERE cc.course = :courseid
                        AND cc.timecompleted IS NOT NULL
                        AND gi.itemtype = 'course'";

                $completions = $DB->get_records_sql($sql, ['courseid' => $coursemapping->courseid]);

                foreach ($completions as $completion) {
                    // Check if user is in this program
                    $progress = $DB->get_record('local_masterytrack_progress', [
                        'programid' => $program->id,
                        'userid' => $completion->userid
                    ]);

                    if ($progress) {
                        // Update progress
                        $grade = $completion->finalgrade ?? 0;
                        progress_tracker::update_course_completion(
                            $completion->userid,
                            $program->id,
                            $coursemapping->courseid,
                            $grade
                        );
                        $updated++;
                    }
                }
            }
        }

        mtrace("Updated progress for {$updated} course completions.");
        mtrace('Progress update complete.');
    }
}
