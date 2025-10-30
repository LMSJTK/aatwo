<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Email Manager - handles scheduled email sending with phishing examples
 */
class email_manager {

    /**
     * Standard email schedule days (based on neuroscience principles)
     */
    const SCHEDULE_DAYS = [1, 3, 7, 14, 21, 28];

    /**
     * Create email template for a program
     *
     * @param int $programid Program ID
     * @param int $dayoffset Day offset from start
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string $phishingexample Phishing example content
     * @param int $courseid Optional linked course
     * @param int $topicid Optional topic
     * @return int Email template ID
     */
    public static function create_email_template($programid, $dayoffset, $subject, $body,
                                                  $phishingexample = '', $courseid = null,
                                                  $topicid = null) {
        global $DB;

        $record = new \stdClass();
        $record->programid = $programid;
        $record->dayoffset = $dayoffset;
        $record->subject = $subject;
        $record->body = $body;
        $record->phishingexample = $phishingexample;
        $record->courseid = $courseid;
        $record->topicid = $topicid;
        $record->active = 1;
        $record->timecreated = time();

        return $DB->insert_record('local_masterytrack_emails', $record);
    }

    /**
     * Generate standard email templates for a program using LLM
     *
     * @param int $programid Program ID
     * @param array $topics Array of topics to cover
     * @return array Array of created email IDs
     */
    public static function generate_email_schedule($programid, $topics = []) {
        $emailids = [];

        foreach (self::SCHEDULE_DAYS as $day) {
            $topic = !empty($topics) ? $topics[array_rand($topics)] : 'General Phishing';

            // Generate email content (this would use LLM integration)
            $content = self::generate_email_content($day, $topic);

            $emailid = self::create_email_template(
                $programid,
                $day,
                $content['subject'],
                $content['body'],
                $content['phishing_example'],
                null,
                null
            );

            $emailids[] = $emailid;
        }

        return $emailids;
    }

    /**
     * Generate email content for a specific day and topic
     * This is a placeholder - would be replaced with actual LLM integration
     *
     * @param int $day Day number
     * @param string $topic Topic name
     * @return array Content array with subject, body, and phishing example
     */
    private static function generate_email_content($day, $topic) {
        // This would call the LLM integration
        // For now, return template content
        return [
            'subject' => "Day {$day} Cybersecurity Training: {$topic}",
            'body' => "Welcome to Day {$day} of your cybersecurity mastery journey!\n\n" .
                     "Today we're focusing on: {$topic}\n\n" .
                     "Below you'll find a phishing example to help you recognize threats. " .
                     "Can you spot the red flags?\n\n" .
                     "Click here to access your courses: {courselinks}\n\n" .
                     "Stay vigilant!\n" .
                     "Your Security Team",
            'phishing_example' => "Example phishing email for {$topic}:\n\n" .
                                 "From: security@company-alert.com\n" .
                                 "Subject: Urgent: Verify Your Account\n\n" .
                                 "Dear User,\n" .
                                 "We've detected suspicious activity on your account...\n" .
                                 "[Red flags: urgent language, suspicious sender, generic greeting]"
        ];
    }

    /**
     * Send scheduled emails to users
     *
     * @param int $programid Optional program ID filter
     * @return array Statistics about sent emails
     */
    public static function send_scheduled_emails($programid = null) {
        global $DB;

        $stats = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        // Get all active programs
        $conditions = ['active' => 1];
        if ($programid) {
            $conditions['id'] = $programid;
        }
        $programs = $DB->get_records('local_masterytrack_programs', $conditions);

        foreach ($programs as $program) {
            $result = self::process_program_emails($program);
            $stats['sent'] += $result['sent'];
            $stats['failed'] += $result['failed'];
            $stats['skipped'] += $result['skipped'];
        }

        return $stats;
    }

    /**
     * Process email sending for a specific program
     *
     * @param object $program Program record
     * @return array Statistics
     */
    private static function process_program_emails($program) {
        global $DB;

        $stats = [
            'sent' => 0,
            'failed' => 0,
            'skipped' => 0
        ];

        // Get all users in program
        $users = $DB->get_records('local_masterytrack_progress', ['programid' => $program->id]);

        foreach ($users as $userprogress) {
            $user = \core_user::get_user($userprogress->userid);

            // Calculate days since program start
            $dayselapsed = floor((time() - $program->startdate) / 86400);

            // Get emails that should be sent
            $emails = $DB->get_records('local_masterytrack_emails', [
                'programid' => $program->id,
                'active' => 1
            ]);

            foreach ($emails as $email) {
                // Check if it's time to send this email
                if ($dayselapsed >= $email->dayoffset) {
                    // Check if already sent
                    $log = $DB->get_record('local_masterytrack_email_log', [
                        'emailid' => $email->id,
                        'userid' => $user->id,
                        'programid' => $program->id
                    ]);

                    if ($log && $log->sent) {
                        $stats['skipped']++;
                        continue;
                    }

                    // Prepare email content
                    $emaildata = self::prepare_email_for_user($email, $user, $program, $userprogress);

                    // Send email using Moodle's email_to_user function
                    $from = \core_user::get_support_user();
                    $success = email_to_user(
                        $user,
                        $from,
                        $emaildata['subject'],
                        html_to_text($emaildata['body']),
                        $emaildata['body']
                    );

                    // Log the send attempt
                    if (!$log) {
                        $log = new \stdClass();
                        $log->emailid = $email->id;
                        $log->userid = $user->id;
                        $log->programid = $program->id;
                        $log->sent = 0;
                        $log->timesent = null;
                        $log->error = null;
                        $log->id = $DB->insert_record('local_masterytrack_email_log', $log);
                    }

                    if ($success) {
                        $log->sent = 1;
                        $log->timesent = time();
                        $stats['sent']++;
                    } else {
                        $log->error = 'Failed to send email';
                        $stats['failed']++;
                    }

                    $DB->update_record('local_masterytrack_email_log', $log);
                }
            }
        }

        return $stats;
    }

    /**
     * Prepare email content with personalized course links
     *
     * @param object $email Email template
     * @param object $user User object
     * @param object $program Program record
     * @param object $userprogress User progress record
     * @return array Prepared email data
     */
    private static function prepare_email_for_user($email, $user, $program, $userprogress) {
        global $CFG, $DB;

        $subject = $email->subject;
        $body = $email->body;

        // Replace placeholders
        $subject = str_replace('{firstname}', $user->firstname, $subject);
        $subject = str_replace('{lastname}', $user->lastname, $subject);

        $body = str_replace('{firstname}', $user->firstname, $body);
        $body = str_replace('{lastname}', $user->lastname, $body);
        $body = str_replace('{points}', $userprogress->totalpoints, $body);
        $body = str_replace('{goal}', $program->masterygoal, $body);

        // Get assigned courses for this user's path
        $courses = $DB->get_records('local_masterytrack_courses', [
            'programid' => $program->id,
            'pathtype' => $userprogress->currentpathtype
        ], 'sequenceorder ASC', '*', 0, 3); // Get next 3 courses

        $courselinks = '';
        foreach ($courses as $coursemapping) {
            $course = get_course($coursemapping->courseid);
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            $courselinks .= "- {$course->fullname}: {$courseurl->out()}\n";
        }

        $body = str_replace('{courselinks}', $courselinks, $body);

        // Add phishing example if present
        if (!empty($email->phishingexample)) {
            $body .= "\n\n---\nPHISHING EXAMPLE:\n" . $email->phishingexample;
        }

        return [
            'subject' => $subject,
            'body' => nl2br($body)
        ];
    }

    /**
     * Get email schedule for a program
     *
     * @param int $programid Program ID
     * @return array Array of email templates
     */
    public static function get_program_emails($programid) {
        global $DB;
        return $DB->get_records('local_masterytrack_emails', ['programid' => $programid], 'dayoffset ASC');
    }

    /**
     * Update email template
     *
     * @param int $emailid Email ID
     * @param object $data Updated data
     * @return bool Success
     */
    public static function update_email_template($emailid, $data) {
        global $DB;

        $record = $DB->get_record('local_masterytrack_emails', ['id' => $emailid], '*', MUST_EXIST);

        $record->subject = $data->subject ?? $record->subject;
        $record->body = $data->body ?? $record->body;
        $record->phishingexample = $data->phishingexample ?? $record->phishingexample;
        $record->courseid = $data->courseid ?? $record->courseid;
        $record->active = $data->active ?? $record->active;

        return $DB->update_record('local_masterytrack_emails', $record);
    }

    /**
     * Delete email template
     *
     * @param int $emailid Email ID
     * @return bool Success
     */
    public static function delete_email_template($emailid) {
        global $DB;
        return $DB->delete_records('local_masterytrack_emails', ['id' => $emailid]);
    }
}
