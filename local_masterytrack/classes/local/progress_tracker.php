<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Progress Tracker - handles user progress tracking and point calculations
 */
class progress_tracker {

    /**
     * Update user progress for a completed course
     *
     * @param int $userid User ID
     * @param int $programid Program ID
     * @param int $courseid Course ID
     * @param float $grade Grade achieved
     * @return bool Success
     */
    public static function update_course_completion($userid, $programid, $courseid, $grade) {
        global $DB;

        // Get progress record
        $progress = $DB->get_record('local_masterytrack_progress', [
            'programid' => $programid,
            'userid' => $userid
        ]);

        if (!$progress) {
            return false;
        }

        // Get course mapping to determine points
        $coursemapping = $DB->get_record('local_masterytrack_courses', [
            'programid' => $programid,
            'courseid' => $courseid
        ]);

        if (!$coursemapping) {
            return false;
        }

        // Check if already recorded
        $existing = $DB->get_record('local_masterytrack_user_points', [
            'progressid' => $progress->id,
            'courseid' => $courseid
        ]);

        $record = new \stdClass();
        $record->progressid = $progress->id;
        $record->courseid = $courseid;
        $record->topicid = $coursemapping->topicid;
        $record->tacticid = $coursemapping->tacticid;
        $record->pointsearned = $coursemapping->points;
        $record->grade = $grade;
        $record->completed = 1;
        $record->timecompleted = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_masterytrack_user_points', $record);
        } else {
            $DB->insert_record('local_masterytrack_user_points', $record);
        }

        // Recalculate total points
        self::recalculate_total_points($progress->id);

        // Check for adaptive path assignment
        self::check_adaptive_path($userid, $programid, $courseid, $grade);

        return true;
    }

    /**
     * Recalculate total points for a user's progress
     *
     * @param int $progressid Progress ID
     * @return int Total points
     */
    public static function recalculate_total_points($progressid) {
        global $DB;

        $sql = "SELECT SUM(pointsearned) as total
                FROM {local_masterytrack_user_points}
                WHERE progressid = :progressid AND completed = 1";

        $result = $DB->get_record_sql($sql, ['progressid' => $progressid]);
        $totalpoints = $result->total ?? 0;

        // Update progress record
        $progress = $DB->get_record('local_masterytrack_progress', ['id' => $progressid]);
        $progress->totalpoints = $totalpoints;
        $progress->timemodified = time();

        // Check if mastery achieved
        $program = $DB->get_record('local_masterytrack_programs', ['id' => $progress->programid]);
        if ($totalpoints >= $program->masterygoal && !$progress->masteryachieved) {
            $progress->masteryachieved = 1;
            $progress->masterydate = time();
        }

        $DB->update_record('local_masterytrack_progress', $progress);

        return $totalpoints;
    }

    /**
     * Check if adaptive path assignment is needed based on performance
     *
     * @param int $userid User ID
     * @param int $programid Program ID
     * @param int $courseid Completed course ID
     * @param float $grade Grade achieved
     * @return string|null New path type or null if no change
     */
    public static function check_adaptive_path($userid, $programid, $courseid, $grade) {
        global $DB;

        $progress = $DB->get_record('local_masterytrack_progress', [
            'programid' => $programid,
            'userid' => $userid
        ]);

        if (!$progress) {
            return null;
        }

        $newpath = null;

        // Adaptive logic based on grade
        if ($grade < 70) {
            // Low score - assign remedial path
            $newpath = 'remedial';
        } else if ($grade >= 95) {
            // High score - assign challenge path
            $newpath = 'challenge';
        } else {
            // Standard path
            $newpath = 'standard';
        }

        // Update path if changed
        if ($newpath !== $progress->currentpathtype) {
            $progress->currentpathtype = $newpath;
            $progress->timemodified = time();
            $DB->update_record('local_masterytrack_progress', $progress);

            // Enroll in appropriate courses
            self::enroll_in_path_courses($userid, $programid, $newpath);
        }

        return $newpath;
    }

    /**
     * Enroll user in courses for their current path
     *
     * @param int $userid User ID
     * @param int $programid Program ID
     * @param string $pathtype Path type
     * @return int Number of enrollments
     */
    public static function enroll_in_path_courses($userid, $programid, $pathtype) {
        global $DB;

        $courses = $DB->get_records('local_masterytrack_courses', [
            'programid' => $programid,
            'pathtype' => $pathtype
        ]);

        $count = 0;
        foreach ($courses as $course) {
            // Check if already enrolled
            $enrolled = $DB->record_exists('user_enrolments', [
                'userid' => $userid
            ]);

            if (!$enrolled) {
                // Enroll user in course
                enrol_try_internal_enrol($course->courseid, $userid, null, time());
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get user progress report
     *
     * @param int $userid User ID
     * @param int $programid Program ID
     * @return object Progress data
     */
    public static function get_user_progress($userid, $programid) {
        global $DB;

        $progress = $DB->get_record('local_masterytrack_progress', [
            'programid' => $programid,
            'userid' => $userid
        ]);

        if (!$progress) {
            return null;
        }

        $program = $DB->get_record('local_masterytrack_programs', ['id' => $programid]);

        // Get points breakdown by topic
        $sql = "SELECT t.id, t.name, SUM(up.pointsearned) as points
                FROM {local_masterytrack_user_points} up
                JOIN {local_masterytrack_topics} t ON up.topicid = t.id
                WHERE up.progressid = :progressid
                GROUP BY t.id, t.name";

        $topicbreakdown = $DB->get_records_sql($sql, ['progressid' => $progress->id]);

        // Get points breakdown by tactic
        $sql = "SELECT tc.id, tc.name, SUM(up.pointsearned) as points
                FROM {local_masterytrack_user_points} up
                JOIN {local_masterytrack_tactics} tc ON up.tacticid = tc.id
                WHERE up.progressid = :progressid
                GROUP BY tc.id, tc.name";

        $tacticbreakdown = $DB->get_records_sql($sql, ['progressid' => $progress->id]);

        return (object)[
            'progress' => $progress,
            'program' => $program,
            'topicbreakdown' => $topicbreakdown,
            'tacticbreakdown' => $tacticbreakdown,
            'percentcomplete' => round(($progress->totalpoints / $program->masterygoal) * 100, 2)
        ];
    }

    /**
     * Get group progress statistics
     *
     * @param int $programid Program ID
     * @return object Group statistics
     */
    public static function get_group_statistics($programid) {
        global $DB;

        $program = $DB->get_record('local_masterytrack_programs', ['id' => $programid]);

        // Overall statistics
        $sql = "SELECT
                    COUNT(*) as totalusers,
                    SUM(CASE WHEN masteryachieved = 1 THEN 1 ELSE 0 END) as mastered,
                    AVG(totalpoints) as avgpoints,
                    MIN(totalpoints) as minpoints,
                    MAX(totalpoints) as maxpoints
                FROM {local_masterytrack_progress}
                WHERE programid = :programid";

        $stats = $DB->get_record_sql($sql, ['programid' => $programid]);

        // Topic performance
        $sql = "SELECT t.id, t.name,
                    COUNT(DISTINCT up.progressid) as usercount,
                    AVG(up.pointsearned) as avgpoints
                FROM {local_masterytrack_user_points} up
                JOIN {local_masterytrack_progress} p ON up.progressid = p.id
                JOIN {local_masterytrack_topics} t ON up.topicid = t.id
                WHERE p.programid = :programid
                GROUP BY t.id, t.name";

        $topicstats = $DB->get_records_sql($sql, ['programid' => $programid]);

        // Path distribution
        $sql = "SELECT currentpathtype, COUNT(*) as count
                FROM {local_masterytrack_progress}
                WHERE programid = :programid
                GROUP BY currentpathtype";

        $pathdistribution = $DB->get_records_sql($sql, ['programid' => $programid]);

        return (object)[
            'program' => $program,
            'overall' => $stats,
            'topicstats' => $topicstats,
            'pathdistribution' => $pathdistribution
        ];
    }
}
