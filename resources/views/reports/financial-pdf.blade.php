<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Financial Report</title>
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
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        thead {
            background: #667eea;
            color: white;
        }
        th {
            padding: 12px;
            text-align: left;
            font-weight: bold;
            font-size: 12px;
            border: 1px solid #667eea;
        }
        td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            font-size: 12px;
        }
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        tbody tr:hover {
            background: #f0f4ff;
        }
        .total-row {
            background: #f0f4ff;
            font-weight: bold;
            border-top: 2px solid #667eea;
        }
        .total-row td {
            border: 1px solid #667eea;
        }
        .text-right {
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
        <h1>Financial Report</h1>
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

    <table>
        <thead>
            <tr>
                <th>Apartment</th>
                <th class="text-right">Maintenance Billed</th>
                <th class="text-right">Maintenance Paid</th>
                <th class="text-right">Utility Billed</th>
                <th class="text-right">Utility Paid</th>
                <th class="text-right">Total Paid</th>
                <th class="text-right">Total Dues</th>
            </tr>
        </thead>
        <tbody>
            @php
                $currencySymbol = \App\Models\SystemSetting::get('currency', 'symbol', '₹', $society->id ?? null);
                $totals = [
                    'maintenance_billed' => 0,
                    'maintenance_paid' => 0,
                    'utility_billed' => 0,
                    'utility_paid' => 0,
                    'total_paid' => 0,
                    'total_dues' => 0,
                ];
            @endphp
            @forelse($data as $row)
                @php
                    $totals['maintenance_billed'] += $row['maintenance_billed'];
                    $totals['maintenance_paid'] += $row['maintenance_paid'];
                    $totals['utility_billed'] += $row['utility_billed'];
                    $totals['utility_paid'] += $row['utility_paid'];
                    $totals['total_paid'] += $row['total_paid'];
                    $totals['total_dues'] += $row['total_dues'];
                @endphp
                <tr>
                    <td>{{ $row['unit'] }} ({{ $row['type'] }})</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['maintenance_billed'], 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['maintenance_paid'], 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['utility_billed'], 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['utility_paid'], 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['total_paid'], 2) }}</td>
                    <td class="text-right">{{ $currencySymbol }}{{ number_format($row['total_dues'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 20px;">No data available</td>
                </tr>
            @endforelse

            @if(count($data) > 0)
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['maintenance_billed'], 2) }}</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['maintenance_paid'], 2) }}</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['utility_billed'], 2) }}</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['utility_paid'], 2) }}</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['total_paid'], 2) }}</td>
                <td class="text-right">{{ $currencySymbol }}{{ number_format($totals['total_dues'], 2) }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        <p>This is an automatically generated report. For more details, please contact the society administration.</p>
        <p>© {{ now()->year }} {{ $society->name ?? 'Society' }}. All rights reserved.</p>
    </div>
</body>
</html>
