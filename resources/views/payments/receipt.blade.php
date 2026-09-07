<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt #{{ $payment->id }}</title>
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
            background-color: #dcfce7;
            color: #166534;
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
        
        .notes-section {
            background-color: #fffbeb;
            border: 1px solid #fbbf24;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .notes-section h4 {
            margin: 0 0 10px 0;
            color: #92400e;
            font-size: 16px;
        }
        
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
            <h1>PAYMENT RECEIPT</h1>
            <p>{{ $payment->society->name ?? 'Society Management System' }}</p>
        </div>
        
        <div class="receipt-body">
            <!-- Payment Details Section -->
            <div class="receipt-section">
                <h3>Payment Information</h3>
                <table class="receipt-table">
                    <tr>
                        <td>Payment ID:</td>
                        <td><strong>{{ $payment->payment_id ?? $payment->id }}</strong></td>
                    </tr>
                    <tr>
                        <td>Amount Paid:</td>
                        <td class="amount-highlight">₹{{ number_format($payment->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <td>Bill Type:</td>
                        <td><strong>{{ $payment->bill_type ?? 'N/A' }}</strong></td>
                    </tr>
                    <tr>
                        <td>Payment Method:</td>
                        <td>
                            <span class="payment-method-badge method-{{ $payment->payment_method }}">
                                {{ ucfirst($payment->payment_method) }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Payment Date:</td>
                        <td><strong>{{ $payment->payment_date ? \Carbon\Carbon::parse($payment->payment_date)->format('M d, Y') : 'N/A' }}</strong></td>
                    </tr>
                    <tr>
                        <td>Due Date:</td>
                        <td><strong>{{ $payment->due_date ? \Carbon\Carbon::parse($payment->due_date)->format('M d, Y') : 'N/A' }}</strong></td>
                    </tr>
                    <tr>
                        <td>Status:</td>
                        <td>
                            <span class="status-badge">
                                {{ ucfirst($payment->status ?? 'success') }}
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td>Recorded On:</td>
                        <td>{{ $payment->created_at->format('M d, Y h:i A') }}</td>
                    </tr>
                </table>
            </div>
            
            <!-- Property Information Section -->
            @if($payment->flat)
            <div class="receipt-section">
                <h3>Property Details</h3>
                <div class="property-info">
                    <h4>
                        {{ $payment->flat->building ? 'Apartment' : 'Villa' }}
                        - {{ $payment->flat->flat_number ?? ($payment->flat->villa_name ?? 'N/A') }}
                    </h4>
                    @if($payment->flat->building)
                        <p><strong>Building:</strong> {{ $payment->flat->building->name }}</p>
                        @if($payment->flat->floor)
                        <p><strong>Floor:</strong> {{ $payment->flat->floor }}</p>
                        @endif
                    @elseif($payment->flat->villaArea)
                        <p><strong>Villa Area:</strong> {{ $payment->flat->villaArea->name }}</p>
                    @endif
                    <p><strong>Society:</strong> {{ $payment->society->name ?? 'N/A' }}</p>
                </div>
            </div>
            @endif
            
            <!-- Notes Section -->
            @if($payment->notes)
            <div class="receipt-section">
                <div class="notes-section">
                    <h4>Additional Notes</h4>
                    <p>{{ $payment->notes }}</p>
                </div>
            </div>
            @endif
        </div>
        
        <div class="receipt-footer">
            <h3>Thank you for your payment!</h3>
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