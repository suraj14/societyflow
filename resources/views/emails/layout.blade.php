<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Notification' }}</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .logo {
            max-width: 120px;
            height: auto;
            margin-bottom: 15px;
        }
        .society-name {
            font-size: 24px;
            font-weight: bold;
            margin: 0;
        }
        .email-body {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #2c3e50;
        }
        .content {
            font-size: 16px;
            line-height: 1.8;
            margin-bottom: 30px;
        }
        .button {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            transition: transform 0.2s;
        }
        .button:hover {
            transform: translateY(-2px);
        }
        .info-box {
            background-color: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .info-box h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }
        .email-footer {
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 30px 20px;
            text-align: center;
            font-size: 14px;
        }
        .footer-info {
            margin-bottom: 15px;
        }
        .footer-info strong {
            color: #3498db;
        }
        .automated-message {
            font-style: italic;
            color: #95a5a6;
            margin-top: 20px;
            font-size: 12px;
        }
        @media (max-width: 600px) {
            .email-body {
                padding: 20px 15px;
            }
            .society-name {
                font-size: 20px;
            }
            .greeting {
                font-size: 16px;
            }
            .content {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <!-- Header -->
        <div class="email-header">
            @if($society_logo ?? null)
                <img src="{{ $society_logo }}" alt="{{ $society_name ?? 'SocietyFlow' }} Logo" class="logo">
            @endif
            <h1 class="society-name">{{ $society_name ?? 'SocietyFlow' }}</h1>
        </div>

        <!-- Body -->
        <div class="email-body">
            @yield('content')
        </div>

        <!-- Footer -->
        <div class="email-footer">
            <div class="footer-info">
                <strong>{{ $society_name ?? 'SocietyFlow' }}</strong><br>
                @if($society_address ?? null)
                    {{ $society_address }}<br>
                @endif
                @if($society_phone ?? null)
                    Phone: {{ $society_phone }}<br>
                @endif
                @if($society_email ?? null)
                    Email: {{ $society_email }}
                @endif
            </div>
            
            <div class="automated-message">
                This is an automated email from {{ $society_name ?? 'SocietyFlow' }}. Please do not reply to this email.
            </div>
        </div>
    </div>
</body>
</html>