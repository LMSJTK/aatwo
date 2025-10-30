<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Program Manager - handles creation and management of mastery track programs
 */
class program_manager {

    /**
     * Create a new mastery track program
     *
     * @param object $data Program data
     * @return int Program ID
     */
    public static function create_program($data) {
        global $DB, $USER;

        $record = new \stdClass();
        $record->name = $data->name;
        $record->description = $data->description ?? '';
        $record->cohortid = $data->cohortid;
        $record->startdate = $data->startdate;
        $record->enddate = $data->enddate ?? null;
        $record->masterygoal = $data->masterygoal ?? 27;
        $record->active = $data->active ?? 1;
        $record->timecreated = time();
        $record->timemodified = time();
        $record->usermodified = $USER->id;

        $programid = $DB->insert_record('local_masterytrack_programs', $record);

        // Initialize progress records for all cohort members
        self::enroll_cohort_members($programid, $data->cohortid);

        return $programid;
    }

    /**
     * Update an existing program
     *
     * @param int $programid Program ID
     * @param object $data Program data
     * @return bool Success
     */
    public static function update_program($programid, $data) {
        global $DB, $USER;

        $record = $DB->get_record('local_masterytrack_programs', ['id' => $programid], '*', MUST_EXIST);

        $record->name = $data->name ?? $record->name;
        $record->description = $data->description ?? $record->description;
        $record->cohortid = $data->cohortid ?? $record->cohortid;
        $record->startdate = $data->startdate ?? $record->startdate;
        $record->enddate = $data->enddate ?? $record->enddate;
        $record->masterygoal = $data->masterygoal ?? $record->masterygoal;
        $record->active = $data->active ?? $record->active;
        $record->timemodified = time();
        $record->usermodified = $USER->id;

        return $DB->update_record('local_masterytrack_programs', $record);
    }

    /**
     * Delete a program
     *
     * @param int $programid Program ID
     * @return bool Success
     */
    public static function delete_program($programid) {
        global $DB;

        $transaction = $DB->start_delegated_transaction();

        try {
            // Delete related records
            $DB->delete_records('local_masterytrack_courses', ['programid' => $programid]);
            $DB->delete_records('local_masterytrack_progress', ['programid' => $programid]);
            $DB->delete_records('local_masterytrack_emails', ['programid' => $programid]);
            $DB->delete_records('local_masterytrack_programs', ['id' => $programid]);

            $transaction->allow_commit();
            return true;
        } catch (\Exception $e) {
            $transaction->rollback($e);
            return false;
        }
    }

    /**
     * Get program by ID
     *
     * @param int $programid Program ID
     * @return object Program record
     */
    public static function get_program($programid) {
        global $DB;
        return $DB->get_record('local_masterytrack_programs', ['id' => $programid], '*', MUST_EXIST);
    }

    /**
     * Get all programs
     *
     * @param bool $activeonly Only return active programs
     * @return array Array of program records
     */
    public static function get_all_programs($activeonly = false) {
        global $DB;

        $conditions = $activeonly ? ['active' => 1] : [];
        return $DB->get_records('local_masterytrack_programs', $conditions, 'name ASC');
    }

    /**
     * Enroll cohort members in a program
     *
     * @param int $programid Program ID
     * @param int $cohortid Cohort ID
     * @return int Number of users enrolled
     */
    public static function enroll_cohort_members($programid, $cohortid) {
        global $DB;

        $members = $DB->get_records('cohort_members', ['cohortid' => $cohortid]);
        $count = 0;

        foreach ($members as $member) {
            // Check if already enrolled
            if (!$DB->record_exists('local_masterytrack_progress', [
                'programid' => $programid,
                'userid' => $member->userid
            ])) {
                $progress = new \stdClass();
                $progress->programid = $programid;
                $progress->userid = $member->userid;
                $progress->totalpoints = 0;
                $progress->masteryachieved = 0;
                $progress->currentpathtype = 'standard';
                $progress->timecreated = time();
                $progress->timemodified = time();

                $DB->insert_record('local_masterytrack_progress', $progress);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Add a course to a program
     *
     * @param int $programid Program ID
     * @param int $courseid Course ID
     * @param int $doklevel DOK level (1-4)
     * @param int $points Points value
     * @param string $pathtype Path type (standard/remedial/challenge)
     * @param int $topicid Topic ID
     * @param int $tacticid Tactic ID
     * @param int $sequenceorder Sequence order
     * @param int $prerequisitescore Prerequisite score
     * @return int Course mapping ID
     */
    public static function add_course_to_program($programid, $courseid, $doklevel, $points,
                                                  $pathtype = 'standard', $topicid = null,
                                                  $tacticid = null, $sequenceorder = 0,
                                                  $prerequisitescore = null) {
        global $DB;

        $record = new \stdClass();
        $record->programid = $programid;
        $record->courseid = $courseid;
        $record->doklevel = $doklevel;
        $record->points = $points;
        $record->pathtype = $pathtype;
        $record->topicid = $topicid;
        $record->tacticid = $tacticid;
        $record->sequenceorder = $sequenceorder;
        $record->prerequisitescore = $prerequisitescore;
        $record->timecreated = time();

        return $DB->insert_record('local_masterytrack_courses', $record);
    }

    /**
     * Get courses for a program
     *
     * @param int $programid Program ID
     * @param string $pathtype Optional path type filter
     * @return array Array of course records
     */
    public static function get_program_courses($programid, $pathtype = null) {
        global $DB;

        $conditions = ['programid' => $programid];
        if ($pathtype !== null) {
            $conditions['pathtype'] = $pathtype;
        }

        return $DB->get_records('local_masterytrack_courses', $conditions, 'sequenceorder ASC');
    }
}
