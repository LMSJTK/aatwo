<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\task;

defined('MOODLE_INTERNAL') || die();

use local_masterytrack\local\progress_tracker;

/**
 * Scheduled task to assign adaptive courses based on performance
 */
class assign_adaptive_courses extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('assigncourses', 'local_masterytrack');
    }

    /**
     * Execute task
     */
    public function execute() {
        global $DB;

        mtrace('Starting adaptive course assignment...');

        // Get all user progress records
        $sql = "SELECT p.*, pr.id as programid
                FROM {local_masterytrack_progress} p
                JOIN {local_masterytrack_programs} pr ON p.programid = pr.id
                WHERE pr.active = 1
                AND p.masteryachieved = 0";

        $progressrecords = $DB->get_records_sql($sql);

        $assigned = 0;
        foreach ($progressrecords as $progress) {
            // Get user's recent grades to determine path
            $sql = "SELECT AVG(grade) as avggrade
                    FROM {local_masterytrack_user_points}
                    WHERE progressid = :progressid
                    AND completed = 1
                    AND timecompleted > :since";

            $since = time() - (7 * 86400); // Last 7 days
            $result = $DB->get_record_sql($sql, [
                'progressid' => $progress->id,
                'since' => $since
            ]);

            if ($result && $result->avggrade !== null) {
                // Determine appropriate path
                $newpath = null;
                if ($result->avggrade < 70) {
                    $newpath = 'remedial';
                } else if ($result->avggrade >= 95) {
                    $newpath = 'challenge';
                } else {
                    $newpath = 'standard';
                }

                // Update path if changed
                if ($newpath !== $progress->currentpathtype) {
                    $progress->currentpathtype = $newpath;
                    $progress->timemodified = time();
                    $DB->update_record('local_masterytrack_progress', $progress);

                    // Enroll in new path courses
                    progress_tracker::enroll_in_path_courses(
                        $progress->userid,
                        $progress->programid,
                        $newpath
                    );
                    $assigned++;

                    mtrace("Assigned user {$progress->userid} to {$newpath} path");
                }
            }
        }

        mtrace("Assigned {$assigned} users to adaptive paths.");
        mtrace('Adaptive course assignment complete.');
    }
}
