<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\EmailNotificationService;
use App\Models\Notice;
use App\Models\Society;

class TestEmailSending extends Command
{
    protected $signature = 'email:test-send {--notice-id=1}';
    protected $description = 'Test email sending for a notice';

    public function handle()
    {
        $noticeId = $this->option('notice-id');
        
        $notice = Notice::find($noticeId);
        if (!$notice) {
            $this->error("Notice with ID {$noticeId} not found");
            return 1;
        }

        $this->info("Testing email send for Notice: {$notice->title}");
        $this->info("Society ID: {$notice->society_id}");
        
        try {
            $result = EmailNotificationService::sendNoticeNotification($notice, 'published');
            
            if ($result) {
                $this->info('✅ Email notification sent successfully!');
                return 0;
            } else {
                $this->error('❌ Email notification failed to send');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
