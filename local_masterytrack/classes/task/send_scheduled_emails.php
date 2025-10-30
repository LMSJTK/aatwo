<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\task;

defined('MOODLE_INTERNAL') || die();

use local_masterytrack\local\email_manager;

/**
 * Scheduled task to send emails
 */
class send_scheduled_emails extends \core\task\scheduled_task {

    /**
     * Get task name
     */
    public function get_name() {
        return get_string('sendemails', 'local_masterytrack');
    }

    /**
     * Execute task
     */
    public function execute() {
        mtrace('Starting scheduled email sending...');

        $stats = email_manager::send_scheduled_emails();

        mtrace("Sent: {$stats['sent']}, Failed: {$stats['failed']}, Skipped: {$stats['skipped']}");
        mtrace('Email sending complete.');
    }
}
