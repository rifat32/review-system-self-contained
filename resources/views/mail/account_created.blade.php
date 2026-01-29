<!DOCTYPE html>
<html>

<head>
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            margin: 0;
            padding: 0;
            color: #334155;
        }

        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            padding: 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }

        .header {
            background-color: #0A4B67;
            /* Secondary Color */
            color: #ffffff;
            text-align: center;
            padding: 40px 20px;
        }

        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 700;
        }

        .content {
            padding: 40px;
            line-height: 1.7;
        }

        .welcome-text {
            font-size: 18px;
            margin-bottom: 24px;
            color: #1e293b;
        }

        .email-display {
            background-color: #f1f5f9;
            padding: 12px 20px;
            border-radius: 8px;
            display: inline-block;
            margin-bottom: 24px;
            font-weight: 600;
            color: #0A4B67;
            border-left: 4px solid #32CD32;
        }

        .btn-container {
            text-align: center;
            margin: 32px 0;
        }

        .btn {
            display: inline-block;
            background-color: #32CD32;
            /* Primary Color */
            color: #ffffff !important;
            padding: 14px 32px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 16px;
            transition: opacity 0.2s;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .help-section {
            margin-top: 32px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #64748b;
        }

        .help-section a {
            color: #0A4B67;
            text-decoration: none;
            font-weight: 600;
        }

        .footer {
            padding: 24px;
            font-size: 12px;
            text-align: center;
            color: #94a3b8;
            background-color: #f8fafc;
        }

        .footer a {
            color: #94a3b8;
            text-decoration: underline;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h1>Welcome to {{ config('app.name') }}</h1>
        </div>

        <div class="content">
            <p class="welcome-text">👋 Hello!</p>
            <p>We're excited to have you join us. Your account has been successfully created for:</p>
            <div class="email-display">{{ $user_email }}</div>

            <p>To get started and unlock all the powerful features of {{ config('app.name') }}, please verify your email
                address by clicking the button below:</p>

            <div class="btn-container">
                <a href="{{ $verification_url }}" class="btn">Verify Email Address</a>
            </div>

            <p>If you did not create this account, no further action is required.</p>

            <div class="help-section">
                <p><strong>Still stuck?</strong> We're here to help! Check out <a
                        href="{{ env('FRONT_END_URL') }}/contact">Contact Support</a>.</p>
            </div>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.<br>
            If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your
            web browser:<br>
            <a href="{{ $verification_url }}">{{ $verification_url }}</a>
        </div>
    </div>

</body>

</html>