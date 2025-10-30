<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Post installation hook
 */
function xmldb_local_masterytrack_install() {
    global $DB;

    // Insert default topics
    $topics = [
        ['name' => 'Brand Impersonation', 'category' => 'core', 'description' => 'Recognizing fake brand communications'],
        ['name' => 'Compliance', 'category' => 'core', 'description' => 'Regulatory and compliance-based phishing'],
        ['name' => 'Emotions', 'category' => 'core', 'description' => 'Emotional manipulation techniques'],
        ['name' => 'Financial Transactions', 'category' => 'core', 'description' => 'Payment and invoice scams'],
        ['name' => 'General Phishing', 'category' => 'core', 'description' => 'Basic phishing awareness'],
        ['name' => 'Generic Cloud', 'category' => 'core', 'description' => 'Cloud service impersonation'],
        ['name' => 'Mobile', 'category' => 'core', 'description' => 'Mobile device threats'],
        ['name' => 'News and Events', 'category' => 'core', 'description' => 'Current events exploitation'],
        ['name' => 'Office Communications', 'category' => 'core', 'description' => 'Internal communication spoofing'],
        ['name' => 'Passwords', 'category' => 'core', 'description' => 'Password security and credential theft'],
        ['name' => 'Reporting', 'category' => 'core', 'description' => 'How to report phishing'],
        ['name' => 'Safe Web Browsing', 'category' => 'core', 'description' => 'Web safety practices'],
        ['name' => 'Shipment & Deliveries', 'category' => 'core', 'description' => 'Package delivery scams'],
        ['name' => 'Small/Medium Businesses', 'category' => 'core', 'description' => 'SMB-targeted attacks'],
        ['name' => 'Social Media', 'category' => 'core', 'description' => 'Social platform threats'],
        ['name' => 'Spear Phishing', 'category' => 'core', 'description' => 'Targeted phishing attacks'],
        ['name' => 'Data Breach', 'category' => 'advanced', 'description' => 'Data breach awareness'],
        ['name' => 'Malware', 'category' => 'advanced', 'description' => 'Malicious software threats'],
        ['name' => 'MFA', 'category' => 'advanced', 'description' => 'Multi-factor authentication'],
        ['name' => 'Personal Security', 'category' => 'advanced', 'description' => 'Personal information protection'],
        ['name' => 'Physical Security', 'category' => 'advanced', 'description' => 'Physical access threats'],
        ['name' => 'Ransomware', 'category' => 'advanced', 'description' => 'Ransomware prevention'],
        ['name' => 'SEG', 'category' => 'advanced', 'description' => 'Secure Email Gateway bypass'],
        ['name' => 'Shared File', 'category' => 'advanced', 'description' => 'File sharing risks'],
    ];

    foreach ($topics as $topic) {
        $record = (object)$topic;
        $DB->insert_record('local_masterytrack_topics', $record);
    }

    // Insert default tactics
    $tactics = [
        ['name' => 'Attachment Phish', 'description' => 'Malicious attachments'],
        ['name' => 'BEC/CEO Fraud', 'description' => 'Business email compromise'],
        ['name' => 'Credential Phish', 'description' => 'Credential harvesting'],
        ['name' => 'QR Codes', 'description' => 'QR code-based attacks'],
        ['name' => 'URL Phish', 'description' => 'Malicious links'],
    ];

    foreach ($tactics as $tactic) {
        $record = (object)$tactic;
        $DB->insert_record('local_masterytrack_tactics', $record);
    }

    return true;
}
