<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }
        .header.alert {
            background: linear-gradient(135deg, #e53935 0%, #e35d5b 100%);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .rating-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 0 4px 4px 0;
        }
        .rating-box.alert {
            border-left-color: #e53935;
        }
        .btn {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 12px 25px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin-top: 20px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 14px;
            color: #6c757d;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header {{ stripos($title, 'Low Rating') !== false ? 'alert' : '' }}">
            <h1>{{ $title }}</h1>
        </div>

        <div class="content">
            <p>Hello{{ $userName ? ' ' . $userName : '' }},</p>

            <p>You have a new update regarding reviews{{ $businessName ? ' for ' . $businessName : '' }}.</p>

            <div class="rating-box {{ stripos($title, 'Low Rating') !== false ? 'alert' : '' }}">
                <p style="margin: 0;">{{ $messageBody }}</p>
                @if($rating)
                <p style="margin-top: 10px; font-weight: bold;">
                    Rating: {{ $rating }} / 5.0
                </p>
                @endif
            </div>

            <p>Please log in to your dashboard to view the full details of this review.</p>

            <div style="text-align: center;">
                <a href="{{ env('FRONT_END_URL', url('/')) }}" class="btn">View Dashboard</a>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated notification from the Feed Genius Review System. Please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
