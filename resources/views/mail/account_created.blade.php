<!DOCTYPE html>
<html>

<head>
    <title>Welcome to {{ config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }

        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #ddd;
        }

        .content {
            padding: 20px 0;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            background-color: #007bff;
            color: #fff;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }

        .footer {
            margin-top: 20px;
            font-size: 12px;
            text-align: center;
            color: #777;
        }
    </style>
</head>

<body>

    <div class="container">
        <div class="header">
            <h2>Welcome!</h2>
        </div>

        <div class="content">
            <p>👋 Thanks for signing up!</p>
            <p>Your account has been created, and we’ve sent a confirmation email to <strong>{{ $user_email }}</strong>.
            </p>

            <p>Just click the link below to get started:</p>

            <div style="text-align: center;">
                <a href="{{ $verification_url }}" class="btn" style="color: #fff;">Verify Email</a>
            </div>

            <p style="margin-top: 20px;">If you don’t see it, check your spam folder or resend the email.</p>

            <p>Still stuck? <a href="{{ url('/faq') }}">Our FAQs</a> are here to help.</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>

</body>

</html>
