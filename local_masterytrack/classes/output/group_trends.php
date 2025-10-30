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
 * Group trends report renderable
 */
class group_trends implements renderable, templatable {

    /** @var int Program ID */
    protected $programid;

    /**
     * Constructor
     *
     * @param int $programid Program ID
     */
    public function __construct($programid) {
        $this->programid = $programid;
    }

    /**
     * Export for template
     *
     * @param renderer_base $output
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $data = new stdClass();

        // Get statistics
        $stats = progress_tracker::get_group_statistics($this->programid);

        $data->programname = $stats->program->name;

        // Overall statistics
        $data->totalusers = $stats->overall->totalusers;
        $data->mastered = $stats->overall->mastered;
        $data->avgpoints = round($stats->overall->avgpoints, 2);
        $data->minpoints = $stats->overall->minpoints;
        $data->maxpoints = $stats->overall->maxpoints;
        $data->masteryrate = $stats->overall->totalusers > 0
            ? round(($stats->overall->mastered / $stats->overall->totalusers) * 100, 2)
            : 0;

        // Topic statistics
        $data->topicstats = [];
        foreach ($stats->topicstats as $topic) {
            $data->topicstats[] = [
                'name' => $topic->name,
                'usercount' => $topic->usercount,
                'avgpoints' => round($topic->avgpoints, 2)
            ];
        }

        // Path distribution
        $data->pathdistribution = [];
        foreach ($stats->pathdistribution as $path) {
            $percentage = $stats->overall->totalusers > 0
                ? round(($path->count / $stats->overall->totalusers) * 100, 2)
                : 0;
            $data->pathdistribution[] = [
                'path' => $path->currentpathtype,
                'count' => $path->count,
                'percentage' => $percentage
            ];
        }

        // Get progress distribution for chart
        $sql = "SELECT
                    CASE
                        WHEN totalpoints = 0 THEN 'Not Started'
                        WHEN totalpoints < :masterygoal * 0.33 THEN 'Beginning'
                        WHEN totalpoints < :masterygoal2 * 0.66 THEN 'Progressing'
                        WHEN totalpoints < :masterygoal3 THEN 'Advanced'
                        ELSE 'Mastery'
                    END as stage,
                    COUNT(*) as count
                FROM {local_masterytrack_progress}
                WHERE programid = :programid
                GROUP BY stage";

        $distribution = $DB->get_records_sql($sql, [
            'programid' => $this->programid,
            'masterygoal' => $stats->program->masterygoal,
            'masterygoal2' => $stats->program->masterygoal,
            'masterygoal3' => $stats->program->masterygoal
        ]);

        $data->progressdistribution = [];
        foreach ($distribution as $stage) {
            $data->progressdistribution[] = [
                'stage' => $stage->stage,
                'count' => $stage->count
            ];
        }

        // Get leaderboard
        $sql = "SELECT p.id, p.userid, p.totalpoints, u.firstname, u.lastname
                FROM {local_masterytrack_progress} p
                JOIN {user} u ON p.userid = u.id
                WHERE p.programid = :programid
                ORDER BY p.totalpoints DESC
                LIMIT 10";

        $leaderboard = $DB->get_records_sql($sql, ['programid' => $this->programid]);

        $data->leaderboard = [];
        $rank = 1;
        foreach ($leaderboard as $entry) {
            $data->leaderboard[] = [
                'rank' => $rank++,
                'name' => fullname($entry),
                'points' => $entry->totalpoints
            ];
        }

        // Prepare chart data as JSON for JavaScript
        $data->chartdata = json_encode([
            'labels' => array_column($data->progressdistribution, 'stage'),
            'data' => array_column($data->progressdistribution, 'count')
        ]);

        return $data;
    }
}
