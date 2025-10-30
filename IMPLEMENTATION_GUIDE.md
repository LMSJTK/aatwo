# Implementation Guide: Mastery Track Program for Moodle

This guide walks you through implementing the mastery track program as described in the Education Tracking 2025 document.

## Phase 1: Installation and Setup

### 1.1 Install the Plugin

```bash
# Copy plugin to Moodle local directory
cp -r local_masterytrack /path/to/moodle/local/

# Visit admin notifications to install
# Navigate to: Site Administration > Notifications
```

### 1.2 Configure LLM Integration (Optional but Recommended)

Navigate to: **Site Administration > Plugins > Local plugins > Mastery Track Program**

Configure the following settings:

**OpenAI Configuration:**
- **LLM Provider**: OpenAI (GPT-4, GPT-3.5)
- **LLM Endpoint**: `https://api.openai.com/v1/chat/completions`
- **LLM Model**: `gpt-4`, `gpt-3.5-turbo`, or `gpt-4-turbo`
- **LLM API Key**: Your OpenAI API key

**Anthropic (Claude) Configuration:**
- **LLM Provider**: Anthropic (Claude)
- **LLM Endpoint**: `https://api.anthropic.com/v1/messages`
- **LLM Model**: `claude-3-opus-20240229`, `claude-3-sonnet-20240229`, or `claude-3-haiku-20240307`
- **LLM API Key**: Your Anthropic API key

**Custom/Local Configuration:**
- **LLM Provider**: Custom/Other
- **LLM Endpoint**: Your endpoint URL (e.g., `http://localhost:8080/v1/chat/completions`)
- **LLM Model**: Your model identifier
- **LLM API Key**: Your API key (if required)

### 1.3 Enable Scheduled Tasks

Navigate to: **Site Administration > Server > Scheduled tasks**

Verify these tasks are enabled:
- `local_masterytrack\task\send_scheduled_emails` - Daily at 8:00 AM
- `local_masterytrack\task\update_user_progress` - Every 30 minutes
- `local_masterytrack\task\assign_adaptive_courses` - Every 4 hours

## Phase 2: Export and Tag Courses

### 2.1 Export Course Data

```php
<?php
// Export course data for LLM tagging
// Run this as an admin script

require_once('config.php');

$courses = $DB->get_records('course', ['visible' => 1]);
$export = [];

foreach ($courses as $course) {
    if ($course->id == 1) continue; // Skip site course

    $modinfo = get_fast_modinfo($course);
    $activities = [];

    foreach ($modinfo->get_cms() as $cm) {
        if ($cm->uservisible) {
            $activities[] = [
                'type' => $cm->modname,
                'name' => $cm->name
            ];
        }
    }

    $export[] = [
        'id' => $course->id,
        'name' => $course->fullname,
        'shortname' => $course->shortname,
        'summary' => strip_tags($course->summary),
        'activities' => $activities
    ];
}

// Save to file for LLM processing
file_put_contents('course_export.json', json_encode($export, JSON_PRETTY_PRINT));
echo "Exported " . count($export) . " courses to course_export.json\n";
?>
```

### 2.2 Tag Courses with LLM

**Prompt 1: Course Tagging**

```
Analyze the following cybersecurity courses and tag each with:

1. Primary topic(s) from: Brand Impersonation, Compliance, Emotions,
   Financial Transactions, General Phishing, Generic Cloud, Mobile,
   News and Events, Office Communications, Passwords, Reporting,
   Safe Web Browsing, Shipment & Deliveries, Small/Medium Businesses,
   Social Media, Spear Phishing, Data Breach, Malware, MFA,
   Personal Security, Physical Security, Ransomware, SEG, Shared File

2. Tactic(s) from: Attachment Phish, BEC/CEO Fraud, Credential Phish,
   QR Codes, URL Phish

3. Webb's DOK level (1-4):
   - DOK 1: Recall/awareness (videos, infographics)
   - DOK 2: Understanding (CBTs with quizzes)
   - DOK 3: Application (simulations, case studies)
   - DOK 4: Creation (games, build scenarios)

4. Suggested point value (1-4) based on DOK

Courses:
[Paste course_export.json content]

Return in JSON format:
{
  "course_id": {
    "topics": ["topic1", "topic2"],
    "tactics": ["tactic1"],
    "dok_level": 2,
    "points": 2,
    "reasoning": "explanation"
  }
}
```

Alternatively, use the built-in LLM manager:

```php
<?php
require_once('config.php');

use local_masterytrack\local\llm_manager;

$llm = new llm_manager();

// Tag all courses
$courses = $DB->get_records('course', ['visible' => 1]);
$courseids = array_keys($courses);

$results = $llm->tag_courses($courseids);

// Review results
foreach ($results as $courseid => $tags) {
    echo "Course {$courseid}: ";
    echo "Topics: " . implode(', ', $tags['topics'] ?? []);
    echo " | DOK: " . ($tags['dok_level'] ?? 'N/A');
    echo " | Points: " . ($tags['points'] ?? 'N/A') . "\n";
}
?>
```

### 2.3 Approve Course Tags

Navigate to the tag approval interface (create this page or use direct database):

```php
<?php
// Review and approve tags
$tags = $DB->get_records('local_masterytrack_course_tags', ['approved' => 0]);

foreach ($tags as $tag) {
    $course = $DB->get_record('course', ['id' => $tag->courseid]);
    echo "{$course->fullname}\n";
    echo "  DOK: {$tag->doklevel}, Points: {$tag->suggestedpoints}\n";
    echo "  Confidence: " . ($tag->confidence * 100) . "%\n";

    // Approve if looks good
    if ($tag->confidence > 0.8) {
        $llm->approve_course_tags($tag->id);
        echo "  -> APPROVED\n";
    }
}
?>
```

## Phase 3: Generate Learning Paths

### 3.1 Use LLM to Generate Paths

**Prompt 2: Path Generation**

```
Based on these tagged courses, create three learning paths:

1. STANDARD PATH (70-94% performers):
   - Balanced progression through topics
   - Mix of DOK levels
   - Goal: Reach 27 points efficiently

2. REMEDIAL PATH (<70% performers):
   - Focus on fundamentals (DOK 1-2)
   - More repetition and practice
   - Slower progression with reinforcement

3. CHALLENGE PATH (95-100% performers):
   - Advanced content (DOK 3-4)
   - Faster progression
   - Complex scenarios and creation tasks

Tagged Courses:
[Include course tags from previous step]

Return JSON with three arrays of course IDs in recommended sequence:
{
  "standard": [2, 3, 4, 5, ...],
  "remedial": [6, 7, 8, 9, ...],
  "challenge": [10, 11, 12, 13, ...]
}
```

Or use the built-in generator:

```php
<?php
use local_masterytrack\local\llm_manager;

$llm = new llm_manager();
$paths = $llm->generate_learning_paths($programid);

echo "Generated paths:\n";
echo "Standard: " . count($paths['standard']) . " courses\n";
echo "Remedial: " . count($paths['remedial']) . " courses\n";
echo "Challenge: " . count($paths['challenge']) . " courses\n";
?>
```

### 3.2 Review and Adjust Paths

Have your content team review the suggested paths:

1. Check for logical progression
2. Verify prerequisites are met
3. Ensure topic coverage is complete
4. Adjust difficulty curves
5. Validate point totals reach ~27

## Phase 4: Create Email Content

### 4.1 Generate Email Templates

**Prompt 3: Email Generation**

```
Generate 6 engaging cybersecurity awareness emails for days 1, 3, 7, 14, 21, 28.

Requirements:
- Professional but friendly tone
- Educational without being preachy
- Include realistic phishing example with red flags
- Motivate continued learning
- Use placeholders: {firstname}, {points}, {goal}, {courselinks}

For each email, provide:
1. Subject line (catchy, not spammy)
2. Body content (2-3 short paragraphs)
3. Phishing example with highlighted red flags
4. Call to action

Topics to cover across the 6 emails:
- Credential Phishing
- Brand Impersonation
- BEC/CEO Fraud
- URL Phishing
- Attachment Threats
- General Review

Return JSON:
{
  "day_1": {
    "subject": "...",
    "body": "...",
    "phishing_example": "..."
  },
  ...
}
```

Or use the built-in generator:

```php
<?php
use local_masterytrack\local\email_manager;
use local_masterytrack\local\llm_manager;

$llm = new llm_manager();
$topics = ['Credential Phishing', 'Brand Impersonation', 'BEC/CEO Fraud',
           'URL Phish', 'Attachment Phish', 'General Review'];

foreach (email_manager::SCHEDULE_DAYS as $index => $day) {
    $topic = $topics[$index] ?? 'General Phishing';

    $content = $llm->generate_email_content($day, $topic);

    email_manager::create_email_template(
        $programid,
        $day,
        $content['subject'],
        $content['body'],
        $content['phishing_example']
    );

    echo "Created email for Day {$day}: {$topic}\n";
}
?>
```

### 4.2 Customize and Test Emails

1. Review each email template
2. Adjust tone for your organization
3. Verify placeholders work correctly
4. Send test emails to yourself
5. Check links and formatting

## Phase 5: Launch Program

### 5.1 Create Program

```php
<?php
use local_masterytrack\local\program_manager;

$programdata = (object)[
    'name' => 'Q1 2025 Phishing Awareness',
    'description' => 'Comprehensive phishing mastery track',
    'cohortid' => 5, // Your cohort ID
    'startdate' => strtotime('2025-01-01'),
    'masterygoal' => 27,
    'active' => 1
];

$programid = program_manager::create_program($programdata);
?>
```

### 5.2 Add Courses to Program

```php
<?php
// Add standard path courses
$standardcourses = [2, 3, 4, 5, 12, 15, 18, 22]; // Your course IDs

foreach ($standardcourses as $seq => $courseid) {
    program_manager::add_course_to_program(
        $programid,
        $courseid,
        $doklevel = 2, // From your tags
        $points = 2,   // From your tags
        'standard',
        $topicid,
        $tacticid,
        $seq + 1
    );
}

// Repeat for remedial and challenge paths
?>
```

### 5.3 Test with Small Group

1. Create test cohort with 5-10 users
2. Launch pilot program
3. Monitor first week emails
4. Check progress tracking
5. Verify adaptive path assignment
6. Review reports

## Phase 6: Reporting and Monitoring

### 6.1 Individual Reports

Users can view their own progress:
```
/local/masterytrack/individual_report.php?programid=1
```

Shows:
- Points earned / mastery goal
- Current adaptive path
- Topic/tactic breakdown
- Completed and assigned courses

### 6.2 Group Trends

Managers can view aggregate data:
```
/local/masterytrack/group_trends.php?programid=1
```

Shows:
- Total users and mastery rate
- Progress distribution chart
- Path distribution
- Topic performance
- Leaderboard

### 6.3 Key Metrics to Track

**Weekly:**
- Email open rates (via email logs)
- Course completion rates
- Average points earned
- Path distribution changes

**Monthly:**
- Overall mastery achievement rate
- Time to mastery (average days)
- Topic performance comparison
- User engagement trends

**Quarterly:**
- Correlation with phishing simulation results
- Reduction in successful phishing attacks
- User feedback and satisfaction
- ROI analysis

## Phase 7: Optimization

### 7.1 A/B Test Email Content

Create variants:
- Different subject lines
- Varied phishing examples
- Alternative CTAs
- Different send times

### 7.2 Adjust Point Values

Monitor completion times and adjust DOK/points:
- If courses take longer: increase points
- If too easy: increase DOK level
- Balance to keep 27-point goal achievable

### 7.3 Refine Adaptive Thresholds

Test different performance thresholds:
```php
// Current defaults
$remedial_threshold = 70;  // Below 70% -> remedial
$challenge_threshold = 95; // Above 95% -> challenge

// Adjust based on your data
```

### 7.4 Update Content Regularly

- Add new phishing trends
- Update examples for current events
- Refresh email templates quarterly
- Add new courses as developed

## Troubleshooting

### Emails Not Sending

1. Check scheduled task is running:
   ```bash
   php admin/cli/scheduled_task.php --execute='\local_masterytrack\task\send_scheduled_emails'
   ```

2. Verify email configuration:
   - Site Administration > Server > Email > Outgoing mail configuration

3. Check email logs:
   ```sql
   SELECT * FROM mdl_local_masterytrack_email_log
   WHERE sent = 0
   ORDER BY id DESC;
   ```

### Progress Not Updating

1. Run progress update manually:
   ```bash
   php admin/cli/scheduled_task.php --execute='\local_masterytrack\task\update_user_progress'
   ```

2. Verify course completions exist:
   ```sql
   SELECT cc.* FROM mdl_course_completions cc
   WHERE cc.userid = ? AND cc.timecompleted IS NOT NULL;
   ```

### LLM Integration Issues

1. Test API connection:
   ```bash
   curl -X POST https://your-llm-endpoint \
     -H "Authorization: Bearer YOUR_API_KEY" \
     -H "Content-Type: application/json" \
     -d '{"prompt": "test"}'
   ```

2. Check error logs:
   ```
   Site Administration > Server > Logs
   ```

3. Verify API key is set:
   ```php
   echo get_config('local_masterytrack', 'llm_apikey');
   ```

## Best Practices

### Do:
- Start with pilot cohort
- Review LLM-generated content before use
- Monitor metrics weekly
- Gather user feedback
- Iterate on email content
- Celebrate mastery achievements

### Don't:
- Launch to entire org without testing
- Use LLM content without review
- Ignore low engagement signals
- Set unrealistic mastery goals
- Neglect to update phishing examples
- Forget to correlate with real phishing metrics

## Support

For questions or issues:
1. Check plugin logs
2. Review Moodle documentation
3. Test with CLI scripts
4. Consult with your Moodle administrator
5. Review source code in `/classes/local/`

## Appendix: Example Credential Phishing Path

Based on Education Tracking 2025 document:

| Day | Activity | Type | DOK | Points | Total |
|-----|----------|------|-----|---------|-------|
| 1 | Awareness Newsletter | Newsletter | 0 | 1 | 1 |
| 2 | Quick Tip Card | Job Aid | 0 | 1 | 2 |
| 4 | Cybersecurity CBT | CBT | 2 | 2 | 4 |
| 5 | Credit Distribution | SEG Miss | 3 | 3 | 7 |
| 7 | Formula Phish | Education | 1 | 1 | 8 |
| 14 | Choose Your Phish | Simulation | 3 | 3 | 11 |
| 16 | Video | Video | 0 | 1 | 12 |
| 17 | Urgent Payment | SEG Miss | 3 | 3 | 15 |
| 20 | Quick Tip | Job Aid | 0 | 1 | 16 |
| 21 | Sherlock Game | Game | 4 | 4 | 20 |
| 25 | Video | Video | 0 | 1 | 21 |
| 27 | Infographic | Infographic | 0 | 1 | 22 |
| 30 | MS Login | SEG Miss | 3 | 3 | 25 |
| 34 | Podcast | Podcast | 0 | 1 | 26 |
| 37 | Cyber Safe Video | Video | 0 | 1 | **27** |

Total: 39 minutes across 37 days = Mastery!
