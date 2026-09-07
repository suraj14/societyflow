<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Maintenance Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #667eea;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #667eea;
            font-size: 28px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .info-section {
            margin-bottom: 20px;
            background: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #667eea;
        }
        .info-value {
            color: #333;
        }
        .summary {
            margin-top: 30px;
            padding: 20px;
            background: #f0f4ff;
            border-left: 4px solid #667eea;
            border-radius: 5px;
        }
        .summary h3 {
            margin-top: 0;
            color: #667eea;
        }
        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .summary-item .label {
            font-weight: bold;
        }
        .summary-item .value {
            text-align: right;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #999;
            border-top: 1px solid #ddd;
            padding-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Maintenance Report</h1>
        <p>{{ $society->name ?? 'Society' }}</p>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span class="info-label">Report Type:</span>
            <span class="info-value">{{ ucfirst($reportType) }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Period:</span>
            <span class="info-value">{{ $periodLabel }}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Generated On:</span>
            <span class="info-value">{{ now()->format('d M Y, H:i A') }}</span>
        </div>
    </div>

    <div class="summary">
        <h3>Summary</h3>
        <div class="summary-item">
            <span class="label">Total Maintenance Collected:</span>
            <span class="value">{{ config('app.currency_symbol', '₹') }}{{ number_format($data['total_maintenance'] ?? 0, 2) }}</span>
        </div>
        <div class="summary-item">
            <span class="label">Number of Transactions:</span>
            <span class="value">{{ $data['count'] ?? 0 }}</span>
        </div>
    </div>

    <div class="footer">
        <p>This is an automatically generated report. For more details, please contact the society administration.</p>
        <p>© {{ now()->year }} {{ $society->name ?? 'Society' }}. All rights reserved.</p>
    </div>
</body>
</html>
