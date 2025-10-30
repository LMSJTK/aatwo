<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;
use local_masterytrack\local\progress_tracker;

/**
 * Individual report renderable
 */
class individual_report implements renderable, templatable {

    /** @var int User ID */
    protected $userid;

    /** @var int Program ID */
    protected $programid;

    /**
     * Constructor
     *
     * @param int $userid User ID
     * @param int $programid Program ID
     */
    public function __construct($userid, $programid) {
        $this->userid = $userid;
        $this->programid = $programid;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB, $USER;

        $data = new stdClass();

        // Get user
        $user = \core_user::get_user($this->userid);
        $data->username = fullname($user);

        // Get progress data
        $progressdata = progress_tracker::get_user_progress($this->userid, $this->programid);

        if (!$progressdata) {
            $data->error = 'No progress data found';
            return $data;
        }

        $data->programname = $progressdata->program->name;
        $data->totalpoints = $progressdata->progress->totalpoints;
        $data->masterygoal = $progressdata->program->masterygoal;
        $data->percentcomplete = $progressdata->percentcomplete;
        $data->currentpath = $progressdata->progress->currentpathtype;
        $data->masteryachieved = $progressdata->progress->masteryachieved;

        if ($progressdata->progress->masterydate) {
            $data->masterydate = userdate($progressdata->progress->masterydate);
        }

        // Topic breakdown
        $data->topics = [];
        foreach ($progressdata->topicbreakdown as $topic) {
            $data->topics[] = [
                'name' => $topic->name,
                'points' => $topic->points
            ];
        }

        // Tactic breakdown
        $data->tactics = [];
        foreach ($progressdata->tacticbreakdown as $tactic) {
            $data->tactics[] = [
                'name' => $tactic->name,
                'points' => $tactic->points
            ];
        }

        // Get completed courses
        $sql = "SELECT c.id, c.fullname, up.pointsearned, up.grade, up.timecompleted
                FROM {local_masterytrack_user_points} up
                JOIN {course} c ON up.courseid = c.id
                WHERE up.progressid = :progressid
                AND up.completed = 1
                ORDER BY up.timecompleted DESC";

        $completedcourses = $DB->get_records_sql($sql, ['progressid' => $progressdata->progress->id]);

        $data->completedcourses = [];
        foreach ($completedcourses as $course) {
            $data->completedcourses[] = [
                'name' => $course->fullname,
                'points' => $course->pointsearned,
                'grade' => round($course->grade, 2),
                'date' => userdate($course->timecompleted)
            ];
        }

        // Get assigned courses for current path
        $sql = "SELECT c.id, c.fullname, mc.points, mc.doklevel
                FROM {local_masterytrack_courses} mc
                JOIN {course} c ON mc.courseid = c.id
                WHERE mc.programid = :programid
                AND mc.pathtype = :pathtype
                ORDER BY mc.sequenceorder ASC";

        $assignedcourses = $DB->get_records_sql($sql, [
            'programid' => $this->programid,
            'pathtype' => $progressdata->progress->currentpathtype
        ]);

        $data->assignedcourses = [];
        foreach ($assignedcourses as $course) {
            // Check if completed
            $completed = $DB->record_exists('local_masterytrack_user_points', [
                'progressid' => $progressdata->progress->id,
                'courseid' => $course->id,
                'completed' => 1
            ]);

            $data->assignedcourses[] = [
                'name' => $course->fullname,
                'points' => $course->points,
                'doklevel' => $course->doklevel,
                'completed' => $completed,
                'url' => (new \moodle_url('/course/view.php', ['id' => $course->id]))->out()
            ];
        }

        return $data;
    }
}
