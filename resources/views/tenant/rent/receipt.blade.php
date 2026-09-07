<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rent Receipt - {{ $rent->month }} {{ $rent->year }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .receipt-header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        
        .receipt-header p {
            margin: 10px 0 0 0;
            font-size: 16px;
            opacity: 0.9;
        }
        
        .receipt-body {
            padding: 40px;
        }
        
        .receipt-section {
            margin-bottom: 30px;
        }
        
        .receipt-section h3 {
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .receipt-table td {
            padding: 12px 0;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        .receipt-table td:first-child {
            font-weight: 600;
            color: #555;
            width: 40%;
        }
        
        .receipt-table td:last-child {
            text-align: right;
        }
        
        .amount-highlight {
            font-size: 24px;
            font-weight: bold;
            color: #16a34a;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-partial { background-color: #fef3c7; color: #92400e; }
        .status-pending { background-color: #fee2e2; color: #dc2626; }
        
        .property-info {
            background-color: #f8fafc;
            border-left: 4px solid #667eea;
            padding: 20px;
            border-radius: 0 8px 8px 0;
        }
        
        .property-info h4 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 16px;
        }
        
        .property-info p {
            margin: 5px 0;
            color: #666;
        }
        
        .payments-section {
            background-color: #f0f9ff;
            border: 1px solid #0ea5e9;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .payments-section h4 {
            margin: 0 0 15px 0;
            color: #0c4a6e;
            font-size: 16px;
        }
        
        .payment-item {
            background: white;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            border-left: 4px solid #0ea5e9;
        }
        
        .payment-item:last-child {
            margin-bottom: 0;
        }
        
        .payment-method-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .method-cash { background-color: #fef3c7; color: #92400e; }
        .method-online { background-color: #dbeafe; color: #1e40af; }
        .method-upi { background-color: #dcfce7; color: #166534; }
        .method-cheque { background-color: #f3e8ff; color: #7c3aed; }
        .method-card { background-color: #fce7f3; color: #be185d; }
        
        .receipt-footer {
            background-color: #f8fafc;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }
        
        .receipt-footer h3 {
            margin: 0 0 10px 0;
            color: #16a34a;
            font-size: 20px;
        }
        
        .receipt-footer p {
            margin: 5px 0;
            color: #666;
            font-size: 14px;
        }
        
        .print-controls {
            text-align: center;
            margin: 20px 0;
        }
        
        .print-btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            transition: background-color 0.3s;
        }
        
        .print-btn:hover {
            background: #5a67d8;
        }
        
        .close-btn {
            background: #6b7280;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            margin: 0 10px;
            transition: background-color 0.3s;
        }
        
        .close-btn:hover {
            background: #4b5563;
        }
        
        @media print {
            body {
                background-color: white;
                padding: 0;
            }
            
            .receipt-container {
                box-shadow: none;
                border-radius: 0;
            }
            
            .print-controls {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .receipt-body {
                padding: 20px;
            }
            
            .receipt-header {
                padding: 20px;
            }
            
            .receipt-header h1 {
                font-size: 24px;
            }
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="close-btn">
            <i class="fas fa-times"></i> Close
        </button>
    </div>

    <div class="receipt-container">
        <div class="receipt-header">
            <h1>RENT RECEIPT</h1>
            <p>{{ $rent->society->name ?? 'Society Management System' }}</p>
        </div>
        
        <div class="receipt-body">
            <!-- Rent Details Section -->
            <div class="receipt-section">
                <h3>Rent Information</h3>
                <table class="receipt-table">
                    <tr>
                        <td>Bill Number:</td>
                        <td><strong>{{ $rent->bill_number }}</strong></td>
                    </tr>
                    <tr>
                        <td>Rent Period:</td>
                        <td><strong>{{ $rent->month }} {{ $rent->year }}</strong></td>
                    </tr>
                    <tr>
                        <td>Total Rent Amount:</td>
                        <td class="amount-highlight">₹{{ number_format($rent->total_amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Amount Paid:</td>
                        <td><strong>₹{{ number_format($rent->paid_amount, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Balance Amount:</td>
                        <td><strong>₹{{ number_format($rent->balance_amount, 2) }}</strong></td>
                    </tr>
                    <tr>
                        <td>Due Date:</td>
                        <td><strong>{{ \Carbon\Carbon::parse($rent->due_date)->format('M d, Y') }}</strong></td>
                    </tr>
                    <tr>
                        <td>Payment Status:</td>
                        <td>
                            <span class="status-badge status-{{ $rent->status }}">
                                {{ ucfirst($rent->status) }}
                            </span>
                        </td>
                    </tr>
                    @if($rent->paid_date)
                    <tr>
                        <td>Paid Date:</td>
                        <td><strong>{{ \Carbon\Carbon::parse($rent->paid_date)->format('M d, Y') }}</strong></td>
                    </tr>
                    @endif
                </table>
            </div>
            
            <!-- Property Information Section -->
            @if($rent->flat)
            <div class="receipt-section">
                <h3>Property Details</h3>
                <div class="property-info">
                    <h4>
                        @if($rent->flat->property_type === 'villa')
                            Villa - {{ $rent->flat->villa_name ?? $rent->flat->flat_number }}
                        @else
                            Apartment - {{ $rent->flat->flat_number }}
                        @endif
                    </h4>
                    @if($rent->flat->building)
                        <p><strong>Building:</strong> {{ $rent->flat->building->name }}</p>
                        @if($rent->flat->floor)
                        <p><strong>Floor:</strong> {{ $rent->flat->floor }}</p>
                        @endif
                    @elseif($rent->flat->villaArea)
                        <p><strong>Villa Area:</strong> {{ $rent->flat->villaArea->name }}</p>
                    @endif
                    <p><strong>Society:</strong> {{ $rent->society->name ?? 'N/A' }}</p>
                    <p><strong>Tenant:</strong> {{ auth()->user()->name }}</p>
                </div>
            </div>
            @endif
            
            <!-- Payment History Section -->
            @if($rent->payments->count() > 0)
            <div class="receipt-section">
                <div class="payments-section">
                    <h4>Payment History</h4>
                    @foreach($rent->payments as $payment)
                    <div class="payment-item">
                        <table class="receipt-table" style="margin-bottom: 0;">
                            <tr>
                                <td style="width: 30%;">Payment ID:</td>
                                <td style="text-align: left;"><strong>{{ $payment->payment_id }}</strong></td>
                            </tr>
                            <tr>
                                <td>Amount:</td>
                                <td style="text-align: left;"><strong>₹{{ number_format($payment->amount, 2) }}</strong></td>
                            </tr>
                            <tr>
                                <td>Method:</td>
                                <td style="text-align: left;">
                                    <span class="payment-method-badge method-{{ $payment->payment_method }}">
                                        {{ ucfirst($payment->payment_method) }}
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td>Date:</td>
                                <td style="text-align: left;"><strong>{{ \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') }}</strong></td>
                            </tr>
                            <tr>
                                <td>Status:</td>
                                <td style="text-align: left;">
                                    <span class="status-badge status-{{ $payment->status }}">
                                        {{ ucfirst($payment->status) }}
                                    </span>
                                </td>
                            </tr>
                            @if($payment->notes)
                            <tr>
                                <td>Notes:</td>
                                <td style="text-align: left;">{{ $payment->notes }}</td>
                            </tr>
                            @endif
                        </table>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>
        
        <div class="receipt-footer">
            <h3>{{ $rent->status === 'paid' ? 'Thank you for your payment!' : 'Rent Receipt' }}</h3>
            <p>This is a computer-generated receipt.</p>
            <p>Generated on {{ now()->format('M d, Y h:i A') }}</p>
        </div>
    </div>

    <div class="print-controls">
        <button onclick="window.print()" class="print-btn">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button onclick="window.close()" class="close-btn">
            <i class="fas fa-times"></i> Close
        </button>
    </div>
</body>
</html>