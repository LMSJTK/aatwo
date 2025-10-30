<?php
// This file is part of Moodle - http://moodle.org/

namespace local_masterytrack\local;

defined('MOODLE_INTERNAL') || die();

/**
 * LLM Manager - handles LLM integration for course tagging, path generation, and email content
 */
class llm_manager {

    /**
     * LLM API endpoint
     */
    private $apiendpoint;

    /**
     * LLM API key
     */
    private $apikey;

    /**
     * LLM provider
     */
    private $provider;

    /**
     * LLM model
     */
    private $model;

    /**
     * Constructor
     */
    public function __construct() {
        $this->apiendpoint = get_config('local_masterytrack', 'llm_endpoint');
        $this->apikey = get_config('local_masterytrack', 'llm_apikey');
        $this->provider = get_config('local_masterytrack', 'llm_provider') ?: 'openai';
        $this->model = get_config('local_masterytrack', 'llm_model') ?: 'gpt-4';
    }

    /**
     * Tag courses with topics and tactics using LLM
     *
     * @param array $courseids Array of course IDs to tag
     * @return array Results of tagging
     */
    public function tag_courses($courseids) {
        global $DB;

        $results = [];

        foreach ($courseids as $courseid) {
            $course = $DB->get_record('course', ['id' => $courseid]);
            if (!$course) {
                continue;
            }

            // Extract course content
            $coursedata = $this->extract_course_data($course);

            // Build prompt for LLM
            $prompt = $this->build_tagging_prompt($coursedata);

            // Call LLM API
            $response = $this->call_llm_api($prompt);

            if ($response['success']) {
                // Parse response and store tags
                $tags = $this->parse_tagging_response($response['content']);
                $this->store_course_tags($courseid, $tags);
                $results[$courseid] = $tags;
            } else {
                $results[$courseid] = ['error' => $response['error']];
            }
        }

        return $results;
    }

    /**
     * Extract course data for LLM analysis
     *
     * @param object $course Course record
     * @return array Course data
     */
    private function extract_course_data($course) {
        global $DB;

        $data = [
            'name' => $course->fullname,
            'shortname' => $course->shortname,
            'summary' => strip_tags($course->summary),
            'activities' => []
        ];

        // Get course modules
        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_cms();

        foreach ($cms as $cm) {
            if ($cm->uservisible) {
                $data['activities'][] = [
                    'type' => $cm->modname,
                    'name' => $cm->name
                ];
            }
        }

        return $data;
    }

    /**
     * Build prompt for course tagging
     *
     * @param array $coursedata Course data
     * @return string Prompt
     */
    private function build_tagging_prompt($coursedata) {
        $topics = [
            'Brand Impersonation', 'Compliance', 'Emotions', 'Financial Transactions',
            'General Phishing', 'Generic Cloud', 'Mobile', 'News and Events',
            'Office Communications', 'Passwords', 'Reporting', 'Safe Web Browsing',
            'Shipment & Deliveries', 'Small/Medium Businesses', 'Social Media',
            'Spear Phishing', 'Data Breach', 'Malware', 'MFA', 'Personal Security',
            'Physical Security', 'Ransomware', 'SEG', 'Shared File'
        ];

        $tactics = [
            'Attachment Phish', 'BEC/CEO Fraud', 'Credential Phish', 'QR Codes', 'URL Phish'
        ];

        $prompt = "Analyze the following cybersecurity course and identify:\n";
        $prompt .= "1. Primary topic(s) from this list: " . implode(', ', $topics) . "\n";
        $prompt .= "2. Tactic(s) covered from this list: " . implode(', ', $tactics) . "\n";
        $prompt .= "3. Webb's DOK level (1-4) based on cognitive complexity\n";
        $prompt .= "4. Suggested point value (1-4)\n\n";
        $prompt .= "Course Name: " . $coursedata['name'] . "\n";
        $prompt .= "Summary: " . $coursedata['summary'] . "\n";
        $prompt .= "Activities: " . count($coursedata['activities']) . " total\n\n";
        $prompt .= "Return response in JSON format:\n";
        $prompt .= "{\n";
        $prompt .= '  "topics": ["topic1", "topic2"],';
        $prompt .= '  "tactics": ["tactic1"],';
        $prompt .= '  "dok_level": 2,';
        $prompt .= '  "points": 2,';
        $prompt .= '  "confidence": 0.85,';
        $prompt .= '  "reasoning": "explanation"';
        $prompt .= "\n}";

        return $prompt;
    }

    /**
     * Generate learning paths based on course tags
     *
     * @param int $programid Program ID
     * @return array Suggested paths
     */
    public function generate_learning_paths($programid) {
        global $DB;

        // Get all tagged courses for the program
        $courses = $DB->get_records('local_masterytrack_courses', ['programid' => $programid]);

        $coursedata = [];
        foreach ($courses as $course) {
            $tags = $DB->get_records('local_masterytrack_course_tags', ['courseid' => $course->courseid]);
            $coursedata[] = [
                'course' => $course,
                'tags' => $tags
            ];
        }

        // Build prompt for path generation
        $prompt = $this->build_path_generation_prompt($coursedata);

        // Call LLM API
        $response = $this->call_llm_api($prompt);

        if ($response['success']) {
            return $this->parse_path_response($response['content']);
        }

        return ['error' => $response['error']];
    }

    /**
     * Build prompt for learning path generation
     *
     * @param array $coursedata Course and tag data
     * @return string Prompt
     */
    private function build_path_generation_prompt($coursedata) {
        $prompt = "Based on the following tagged cybersecurity courses, generate three learning paths:\n";
        $prompt .= "1. STANDARD PATH: For average performers (70-94% scores)\n";
        $prompt .= "2. REMEDIAL PATH: For low performers (<70% scores) - focus on fundamentals\n";
        $prompt .= "3. CHALLENGE PATH: For high performers (95-100% scores) - advanced content\n\n";
        $prompt .= "Courses:\n";

        foreach ($coursedata as $data) {
            $course = $data['course'];
            $tags = $data['tags'];
            $prompt .= "- Course ID: {$course->courseid}, DOK: {$course->doklevel}, Points: {$course->points}\n";
            foreach ($tags as $tag) {
                $prompt .= "  Topics: " . ($tag->topicid ?? 'N/A') . ", Tactics: " . ($tag->tacticid ?? 'N/A') . "\n";
            }
        }

        $prompt .= "\nReturn a JSON structure with three arrays (standard, remedial, challenge) containing course IDs in recommended sequence.";

        return $prompt;
    }

    /**
     * Generate email content for scheduled sends
     *
     * @param int $dayoffset Day offset
     * @param string $topic Topic name
     * @param array $courses Related courses
     * @return array Email content
     */
    public function generate_email_content($dayoffset, $topic, $courses = []) {
        $prompt = "Generate an engaging cybersecurity awareness email for Day {$dayoffset} of a training program.\n";
        $prompt .= "Topic: {$topic}\n";
        $prompt .= "Include:\n";
        $prompt .= "1. Catchy subject line\n";
        $prompt .= "2. Brief educational content (2-3 paragraphs)\n";
        $prompt .= "3. A realistic phishing example with red flags highlighted\n";
        $prompt .= "4. Call to action to complete courses\n";
        $prompt .= "5. Motivational closing\n\n";
        $prompt .= "Tone: Professional but friendly, educational without being preachy\n";
        $prompt .= "Use placeholders: {firstname}, {points}, {goal}, {courselinks}\n\n";
        $prompt .= "Return JSON:\n";
        $prompt .= "{\n";
        $prompt .= '  "subject": "...",';
        $prompt .= '  "body": "...",';
        $prompt .= '  "phishing_example": "..."';
        $prompt .= "\n}";

        $response = $this->call_llm_api($prompt);

        if ($response['success']) {
            return json_decode($response['content'], true);
        }

        return [
            'subject' => "Day {$dayoffset} Training: {$topic}",
            'body' => "Default email content",
            'phishing_example' => "Default phishing example"
        ];
    }

    /**
     * Call LLM API
     *
     * @param string $prompt Prompt text
     * @param array $options Additional options
     * @return array Response with success flag and content/error
     */
    private function call_llm_api($prompt, $options = []) {
        if (empty($this->apiendpoint) || empty($this->apikey)) {
            return [
                'success' => false,
                'error' => 'LLM API not configured'
            ];
        }

        // Format request based on provider
        $data = $this->format_request($prompt, $options);
        $headers = $this->get_headers();

        $ch = curl_init($this->apiendpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpcode == 200) {
            return [
                'success' => true,
                'content' => $this->parse_response($response)
            ];
        } else {
            return [
                'success' => false,
                'error' => "HTTP {$httpcode}: {$response}"
            ];
        }
    }

    /**
     * Format request based on provider
     *
     * @param string $prompt Prompt text
     * @param array $options Additional options
     * @return array Formatted request data
     */
    private function format_request($prompt, $options) {
        $maxTokens = $options['max_tokens'] ?? 1000;
        $temperature = $options['temperature'] ?? 0.7;

        switch ($this->provider) {
            case 'openai':
                return [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ];

            case 'anthropic':
                return [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ];

            case 'custom':
            default:
                // Generic format for custom providers
                return [
                    'model' => $this->model,
                    'prompt' => $prompt,
                    'max_tokens' => $maxTokens,
                    'temperature' => $temperature
                ];
        }
    }

    /**
     * Get headers for API request
     *
     * @return array Headers
     */
    private function get_headers() {
        $headers = ['Content-Type: application/json'];

        switch ($this->provider) {
            case 'openai':
                $headers[] = 'Authorization: Bearer ' . $this->apikey;
                break;

            case 'anthropic':
                $headers[] = 'x-api-key: ' . $this->apikey;
                $headers[] = 'anthropic-version: 2023-06-01';
                break;

            case 'custom':
            default:
                $headers[] = 'Authorization: Bearer ' . $this->apikey;
                break;
        }

        return $headers;
    }

    /**
     * Parse response from LLM
     *
     * @param string $response Raw response
     * @return string Parsed content
     */
    private function parse_response($response) {
        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response;
        }

        switch ($this->provider) {
            case 'openai':
                // OpenAI format: choices[0].message.content
                return $result['choices'][0]['message']['content'] ?? $response;

            case 'anthropic':
                // Anthropic format: content[0].text
                return $result['content'][0]['text'] ?? $response;

            case 'custom':
            default:
                // Try common formats
                return $result['response'] ?? $result['content'] ?? $result['text'] ?? $response;
        }
    }

    /**
     * Parse LLM tagging response
     *
     * @param string $content Response content
     * @return array Parsed tags
     */
    private function parse_tagging_response($content) {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse LLM response'];
        }

        return $data;
    }

    /**
     * Parse LLM path generation response
     *
     * @param string $content Response content
     * @return array Parsed paths
     */
    private function parse_path_response($content) {
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'Failed to parse LLM response'];
        }

        return $data;
    }

    /**
     * Store course tags
     *
     * @param int $courseid Course ID
     * @param array $tags Tag data
     * @return int Tag record ID
     */
    private function store_course_tags($courseid, $tags) {
        global $DB;

        // Check if tags already exist
        $existing = $DB->get_record('local_masterytrack_course_tags', ['courseid' => $courseid]);

        $record = new \stdClass();
        $record->courseid = $courseid;
        $record->doklevel = $tags['dok_level'] ?? null;
        $record->suggestedpoints = $tags['points'] ?? null;
        $record->confidence = $tags['confidence'] ?? null;
        $record->llmmetadata = json_encode($tags);
        $record->approved = 0; // Requires manual approval
        $record->timecreated = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_masterytrack_course_tags', $record);
            return $existing->id;
        } else {
            return $DB->insert_record('local_masterytrack_course_tags', $record);
        }
    }

    /**
     * Get course tags
     *
     * @param int $courseid Course ID
     * @return object|false Tag record
     */
    public function get_course_tags($courseid) {
        global $DB;
        return $DB->get_record('local_masterytrack_course_tags', ['courseid' => $courseid]);
    }

    /**
     * Approve course tags
     *
     * @param int $tagid Tag ID
     * @return bool Success
     */
    public function approve_course_tags($tagid) {
        global $DB;
        return $DB->set_field('local_masterytrack_course_tags', 'approved', 1, ['id' => $tagid]);
    }
}
