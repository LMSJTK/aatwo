<?php
// This file is part of Moodle - http://moodle.org/

define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_masterytrack\local\program_manager;
use local_masterytrack\local\email_manager;
use local_masterytrack\local\llm_manager;

// Get CLI options
list($options, $unrecognized) = cli_get_params([
    'help' => false,
    'cohortid' => null,
    'programname' => 'Example Mastery Track Program',
], [
    'h' => 'help'
]);

if ($options['help'] || !$options['cohortid']) {
    echo "Example setup script for Mastery Track Program\n\n";
    echo "Usage:\n";
    echo "  php example_setup.php --cohortid=<id> [--programname=<name>]\n\n";
    echo "Options:\n";
    echo "  --cohortid        Required. Cohort ID to enroll\n";
    echo "  --programname     Optional. Name of program (default: 'Example Mastery Track Program')\n";
    echo "  -h, --help        Print this help\n\n";
    exit(0);
}

echo "Creating example mastery track program...\n\n";

// Step 1: Create program
echo "Step 1: Creating program...\n";
$programdata = (object)[
    'name' => $options['programname'],
    'description' => 'An example phishing awareness mastery track program with adaptive learning paths',
    'cohortid' => $options['cohortid'],
    'startdate' => time(),
    'masterygoal' => 27,
    'active' => 1
];

$programid = program_manager::create_program($programdata);
echo "Created program ID: {$programid}\n\n";

// Step 2: Get topics and tactics
echo "Step 2: Getting topics and tactics...\n";
$topics = $DB->get_records('local_masterytrack_topics', null, '', 'id, name');
$tactics = $DB->get_records('local_masterytrack_tactics', null, '', 'id, name');

$credentialphishing = null;
foreach ($topics as $topic) {
    if ($topic->name === 'Credential Phishing') {
        $credentialphishing = $topic->id;
        break;
    }
}

$credentialphishtactic = null;
foreach ($tactics as $tactic) {
    if ($tactic->name === 'Credential Phish') {
        $credentialphishtactic = $tactic->id;
        break;
    }
}

echo "Found " . count($topics) . " topics and " . count($tactics) . " tactics\n\n";

// Step 3: Add example courses (you'll need to replace these with actual course IDs)
echo "Step 3: Adding example courses...\n";
echo "Note: Replace these with your actual course IDs\n";

$examplecourses = [
    // Standard path
    ['courseid' => 2, 'doklevel' => 1, 'points' => 1, 'pathtype' => 'standard', 'sequence' => 1],
    ['courseid' => 3, 'doklevel' => 2, 'points' => 2, 'pathtype' => 'standard', 'sequence' => 2],
    ['courseid' => 4, 'doklevel' => 3, 'points' => 3, 'pathtype' => 'standard', 'sequence' => 3],
    ['courseid' => 5, 'doklevel' => 4, 'points' => 4, 'pathtype' => 'standard', 'sequence' => 4],

    // Remedial path (easier content)
    ['courseid' => 6, 'doklevel' => 1, 'points' => 1, 'pathtype' => 'remedial', 'sequence' => 1],
    ['courseid' => 7, 'doklevel' => 1, 'points' => 1, 'pathtype' => 'remedial', 'sequence' => 2],
    ['courseid' => 8, 'doklevel' => 2, 'points' => 2, 'pathtype' => 'remedial', 'sequence' => 3],

    // Challenge path (advanced content)
    ['courseid' => 9, 'doklevel' => 3, 'points' => 3, 'pathtype' => 'challenge', 'sequence' => 1],
    ['courseid' => 10, 'doklevel' => 4, 'points' => 4, 'pathtype' => 'challenge', 'sequence' => 2],
    ['courseid' => 11, 'doklevel' => 4, 'points' => 4, 'pathtype' => 'challenge', 'sequence' => 3],
];

foreach ($examplecourses as $course) {
    // Check if course exists
    if (!$DB->record_exists('course', ['id' => $course['courseid']])) {
        echo "  Skipping course ID {$course['courseid']} (not found)\n";
        continue;
    }

    program_manager::add_course_to_program(
        $programid,
        $course['courseid'],
        $course['doklevel'],
        $course['points'],
        $course['pathtype'],
        $credentialphishing,
        $credentialphishtactic,
        $course['sequence']
    );

    echo "  Added course ID {$course['courseid']} ({$course['pathtype']} path)\n";
}
echo "\n";

// Step 4: Generate email schedule
echo "Step 4: Generating email schedule...\n";
$emaildays = email_manager::SCHEDULE_DAYS;

foreach ($emaildays as $day) {
    $subject = "Day {$day}: Your Cybersecurity Journey";
    $body = "Hi {firstname},\n\n";
    $body .= "Welcome to Day {$day} of your mastery track journey!\n\n";
    $body .= "Current Progress: {points} / {goal} points\n";
    $body .= "Your Path: {currentpath}\n\n";
    $body .= "Today's courses:\n{courselinks}\n\n";
    $body .= "Keep up the great work!\n";

    $phishingexample = "=== PHISHING EXAMPLE ===\n";
    $phishingexample .= "From: security@companyalert.com\n";
    $phishingexample .= "Subject: URGENT: Verify Your Account Now\n\n";
    $phishingexample .= "Dear User,\n";
    $phishingexample .= "We've detected suspicious activity. Click here to verify: http://suspicious-link.com\n\n";
    $phishingexample .= "RED FLAGS:\n";
    $phishingexample .= "- Urgent language creating pressure\n";
    $phishingexample .= "- Suspicious sender domain\n";
    $phishingexample .= "- Generic greeting\n";
    $phishingexample .= "- Suspicious link URL\n";

    email_manager::create_email_template(
        $programid,
        $day,
        $subject,
        $body,
        $phishingexample,
        null,
        $credentialphishing
    );

    echo "  Created email for Day {$day}\n";
}
echo "\n";

// Step 5: Summary
echo "Setup complete!\n\n";
echo "Program Details:\n";
echo "  ID: {$programid}\n";
echo "  Name: {$options['programname']}\n";
echo "  Cohort: {$options['cohortid']}\n";
echo "  Mastery Goal: 27 points\n";
echo "  Email Schedule: " . implode(', ', $emaildays) . " days\n\n";

echo "Next steps:\n";
echo "  1. Review and update course assignments\n";
echo "  2. Customize email templates\n";
echo "  3. Configure LLM settings (optional)\n";
echo "  4. Enable scheduled tasks\n";
echo "  5. View reports at /local/masterytrack/group_trends.php?programid={$programid}\n\n";

exit(0);
