<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ReportGenerated
{
    use Dispatchable, SerializesModels;

    public $reportType;
    public $societyId;
    public $generatedBy;
    public $reportData;

    public function __construct(string $reportType, int $societyId, int $generatedBy, array $reportData = [])
    {
        $this->reportType = $reportType;
        $this->societyId = $societyId;
        $this->generatedBy = $generatedBy;
        $this->reportData = $reportData;
    }
}
