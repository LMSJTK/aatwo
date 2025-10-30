# Mastery Track Program for Moodle

A comprehensive Moodle plugin for implementing phishing awareness training and mastery-based learning programs with adaptive course paths, automated email scheduling, and detailed reporting.

## Overview

This plugin implements a neuroscience-informed, data-driven approach to cybersecurity training based on:

- **Bloom's Taxonomy**: Progressive learning from remembering to creating
- **Webb's Depth of Knowledge (DOK)**: Cognitive rigor-based point system (1-4 points)
- **Node Science**: Networked learning through diverse content and peer collaboration
- **Rule of 27**: 27-point mastery threshold with adaptive pathways
- **Neuroscience Principles**: Spaced repetition at strategic intervals (Days 1, 3, 7, 14, 21, 28)

## Features

### 1. Mastery Track Programs
- Create cohort-based learning programs
- Set mastery goals (default: 27 points)
- Assign courses with DOK levels and point values
- Track progress across topics and tactics

### 2. Adaptive Learning Paths
Three dynamic paths based on performance:
- **Standard Path** (70-94% score): Balanced progression
- **Remedial Path** (<70% score): Foundational reinforcement
- **Challenge Path** (95-100% score): Advanced content

### 3. Automated Email System
- Schedule emails at strategic intervals (Days 1, 3, 7, 14, 21, 28)
- Include phishing examples with red flag analysis
- Personalized course links based on learner's path
- LLM-generated content with customizable templates

### 4. LLM Integration
Three powerful AI-assisted features:
- **Course Tagging**: Automatically tag courses with topics, tactics, DOK levels
- **Path Generation**: AI-suggested learning paths for review
- **Email Content**: Generate engaging training emails with phishing examples

### 5. Comprehensive Reporting
- **Individual Reports**: Personal progress, topic/tactic breakdown, assigned courses
- **Group Trends**: Statistics, leaderboards, progress distribution visualizations
- **Real-time Tracking**: Points, mastery achievement, path assignments

## Installation

1. Copy the `local_masterytrack` folder to your Moodle's `local/` directory
2. Visit Site Administration > Notifications to install
3. Configure LLM settings (optional) at Site Administration > Plugins > Local plugins > Mastery Track Program

## Configuration

### LLM Settings
Navigate to: **Site Administration > Plugins > Local plugins > Mastery Track Program**

- **LLM Provider**: Select your provider (OpenAI, Anthropic, or Custom)
- **LLM Endpoint**: Full API endpoint URL
  - OpenAI: `https://api.openai.com/v1/chat/completions`
  - Anthropic: `https://api.anthropic.com/v1/messages`
  - Custom: Your own endpoint
- **LLM Model**: Model name to use
  - OpenAI: `gpt-4`, `gpt-3.5-turbo`, `gpt-4-turbo`
  - Anthropic: `claude-3-opus-20240229`, `claude-3-sonnet-20240229`, `claude-3-haiku-20240307`
  - Custom: Your model identifier
- **LLM API Key**: Authentication key for the LLM service

### Scheduled Tasks
The plugin includes three scheduled tasks:
1. **Send Scheduled Emails**: Runs daily at 8:00 AM
2. **Update User Progress**: Runs every 30 minutes
3. **Assign Adaptive Courses**: Runs every 4 hours

Configure at: **Site Administration > Server > Scheduled tasks**

## Usage

### Creating a Program

1. Navigate to **Site Administration > Plugins > Mastery Track Program**
2. Click "Create Program"
3. Configure:
   - Name and description
   - Select cohort
   - Set start date
   - Define mastery goal (default: 27)

### Adding Courses to Program

```php
use local_masterytrack\local\program_manager;

// Add a course with DOK level and points
program_manager::add_course_to_program(
    $programid,
    $courseid,
    $doklevel = 2,        // DOK 1-4
    $points = 2,          // Points to award
    $pathtype = 'standard', // standard/remedial/challenge
    $topicid = null,      // Optional topic ID
    $tacticid = null,     // Optional tactic ID
    $sequenceorder = 0,   // Order in sequence
    $prerequisitescore = null // Required score from previous course
);
```

### Using LLM Features

#### Tag Courses with Topics and Tactics
```php
use local_masterytrack\local\llm_manager;

$llm = new llm_manager();
$courseids = [1, 2, 3, 4]; // Array of course IDs
$results = $llm->tag_courses($courseids);
```

#### Generate Learning Paths
```php
$paths = $llm->generate_learning_paths($programid);
// Returns: ['standard' => [...], 'remedial' => [...], 'challenge' => [...]]
```

#### Generate Email Content
```php
$content = $llm->generate_email_content(
    $dayoffset = 7,
    $topic = 'Credential Phishing',
    $courses = []
);
// Returns: ['subject' => '...', 'body' => '...', 'phishing_example' => '...']
```

### Email Templates

Create emails with placeholders:
- `{firstname}` - User's first name
- `{lastname}` - User's last name
- `{points}` - Current points earned
- `{goal}` - Mastery goal
- `{courselinks}` - Auto-generated course links

Example:
```
Subject: Day 7 Cybersecurity Check-in, {firstname}!

Hi {firstname},

You've earned {points} out of {goal} points so far - great progress!

Today's focus: Credential Phishing

Below is a phishing example to test your skills:
[Phishing example content here]

Your assigned courses:
{courselinks}

Keep up the excellent work!
```

### Viewing Reports

#### Individual Report
```
URL: /local/masterytrack/individual_report.php?userid=123&programid=1
```

Shows:
- Overall progress percentage
- Points earned vs. goal
- Current adaptive path
- Topic and tactic breakdown
- Completed and assigned courses

#### Group Trends
```
URL: /local/masterytrack/group_trends.php?programid=1
```

Shows:
- Total users and mastery rate
- Average, min, max points
- Progress distribution chart
- Path distribution
- Topic performance
- Leaderboard

## Database Schema

### Core Tables
- `local_masterytrack_programs` - Program definitions
- `local_masterytrack_topics` - Cybersecurity topics
- `local_masterytrack_tactics` - Attack tactics
- `local_masterytrack_courses` - Course-to-program mappings
- `local_masterytrack_progress` - User progress tracking
- `local_masterytrack_user_points` - Detailed point records
- `local_masterytrack_emails` - Email templates and schedule
- `local_masterytrack_email_log` - Email sending log
- `local_masterytrack_course_tags` - LLM-generated tags

## Topics and Tactics

### Topics Covered
- Brand Impersonation
- Compliance
- Emotions
- Financial Transactions
- General Phishing
- Generic Cloud
- Mobile
- News and Events
- Office Communications
- Passwords
- Reporting
- Safe Web Browsing
- Shipment & Deliveries
- Small/Medium Businesses
- Social Media
- Spear Phishing
- Data Breach
- Malware
- MFA
- Personal Security
- Physical Security
- Ransomware
- SEG (Secure Email Gateway)
- Shared File

### Tactics
- Attachment Phish
- BEC/CEO Fraud
- Credential Phish
- QR Codes
- URL Phish

## DOK Levels and Points

| DOK Level | Description | Example Activity | Points |
|-----------|-------------|------------------|---------|
| DOK 1 | Awareness/Recall | Video, infographic, newsletter | 1 |
| DOK 2 | Understanding | CBT with quiz, podcast | 2 |
| DOK 3 | Application | Simulation, case study, SEG miss | 3 |
| DOK 4 | Creation | Game, build scenario | 4 |

## API Examples

### Programmatic Access

```php
// Create a program
use local_masterytrack\local\program_manager;

$programdata = (object)[
    'name' => 'Q1 2025 Phishing Training',
    'description' => 'Quarterly cybersecurity awareness program',
    'cohortid' => 5,
    'startdate' => time(),
    'masterygoal' => 27
];

$programid = program_manager::create_program($programdata);

// Track progress
use local_masterytrack\local\progress_tracker;

// Get user progress
$progress = progress_tracker::get_user_progress($userid, $programid);
echo "Points: {$progress->progress->totalpoints}/{$progress->program->masterygoal}";
echo "Progress: {$progress->percentcomplete}%";

// Get group statistics
$stats = progress_tracker::get_group_statistics($programid);
echo "Mastery Rate: " . round(($stats->overall->mastered / $stats->overall->totalusers) * 100, 2) . "%";

// Send emails manually
use local_masterytrack\local\email_manager;

$stats = email_manager::send_scheduled_emails($programid);
echo "Sent: {$stats['sent']}, Failed: {$stats['failed']}";
```

## Workflow Example

Based on the Credential Phishing learning path:

| Day | Activity | Type | DOK | Points | Total |
|-----|----------|------|-----|---------|-------|
| 1 | The War of the Worlds - Stolen Credentials | Newsletter | 0 | 1 | 1 |
| 2 | Quick Tip Card via Slack | Job Aid | 0 | 1 | 2 |
| 4 | Cybersecurity Awareness CBT | CBT | 1-2 | 2 | 4 |
| 5 | Credit Distribution | SEG Miss | 3 | 3 | 7 |
| 7 | Formula Phish | HTML Education | 1 | 1 | 8 |
| 14 | CYP Credential Phishing | Choose Your Phish | 3 | 3 | 11 |
| 16 | Credential Phishing Video | Video | 0 | 1 | 12 |
| 17 | Urgent Payment | SEG Miss | 3 | 3 | 15 |
| 20 | Quick Tip Card | Job Aid | 0 | 1 | 16 |
| 21 | Sherlock Game | Game | 4 | 4 | 20 |
| 25 | Dropbox Credential Phishing | Video | 0 | 1 | 21 |
| 27 | Hooked on Phish | Infographic | 0 | 1 | 22 |
| 30 | MS Login | SEG Miss | 3 | 3 | 25 |
| 34 | Credential Phishing Podcast | Podcast | 0 | 1 | 26 |
| 37 | Cyber Safe Lesson | Video | 0 | 1 | **27** |

Total time: ~39 minutes over 37 days = Mastery Achieved!

## Neuroscience Foundation

### Spaced Repetition Schedule
- **Day 1**: Initial exposure
- **Day 3**: First reinforcement (24-48 hours)
- **Day 7**: Short-term memory consolidation
- **Day 14**: Two-week retention check
- **Day 21**: Three-week mastery reinforcement
- **Day 28**: Long-term memory encoding

### Learning Principles
- **Encoding**: Multi-modal input (visual, auditory, kinesthetic)
- **Consolidation**: Repetition and sleep cycles
- **Retrieval Practice**: Quizzes and active recall
- **Spacing Effect**: Distributed practice over time

## Security Considerations

- Email templates should be reviewed before sending to avoid actual phishing
- LLM-generated content requires human approval
- API keys should be stored securely
- User data is protected by Moodle's privacy systems
- Capability checks enforce access control

## Support and Contribution

For issues, questions, or contributions:
- Report bugs via your Moodle support channels
- Review documentation for configuration options
- Customize templates in `/templates/` directory
- Extend classes in `/classes/local/` for custom functionality

## License

This plugin is licensed under the GNU GPL v3 or later.

## Credits

Based on the Phishing Awareness Training & Mastery Framework incorporating:
- Bloom's Taxonomy
- Webb's Depth of Knowledge
- Node Science
- Neuroscience principles of learning and memory

## Version History

- **1.0.0** (2025-10-30): Initial release
  - Mastery track programs
  - Adaptive learning paths
  - Email automation
  - LLM integration
  - Comprehensive reporting
