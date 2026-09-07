<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\EmailLog;

echo "=== Email Log Test ===\n\n";

// Get latest notice_published email log
$log = EmailLog::where('trigger_action', 'notice_published')->latest()->first();

if ($log) {
    echo "✅ Email Log Found!\n\n";
    echo "Recipient Email: " . $log->recipient_email . "\n";
    echo "Recipient Name: " . $log->recipient_name . "\n";
    echo "Status: " . $log->status . "\n";
    echo "Trigger Action: " . $log->trigger_action . "\n";
    echo "\nVariables:\n";
    echo json_encode($log->variables, JSON_PRETTY_PRINT) . "\n";
} else {
    echo "❌ No email logs found for notice_published\n";
    echo "\nAll recent email logs:\n";
    $allLogs = EmailLog::latest()->limit(5)->get();
    foreach ($allLogs as $l) {
        echo "- " . $l->trigger_action . " (" . $l->status . ")\n";
    }
}
