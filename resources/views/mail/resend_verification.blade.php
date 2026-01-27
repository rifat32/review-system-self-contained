<!DOCTYPE html>
<html>

<head>
    <title>Verify Email Address - {{ config('app.name') }}</title>
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
            <h2>Verify Your Email Address</h2>
        </div>

        <div class="content">
            <p>Hello,</p>
            <p>We received a request to resend the verification email for your account associated with
                <strong>{{ $user_email }}</strong>.</p>

            <p>Please click the button below to verify your email address:</p>

            <div style="text-align: center;">
                <a href="{{ $verification_url }}" class="btn" style="color: #fff;">Verify Email Address</a>
            </div>

            <p style="margin-top: 20px;">If you did not make this request, you can safely ignore this email.</p>

            <p>Still need help? <a href="{{ url('/contact') }}">Contact Support</a>.</p>
        </div>

        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>

</body>

</html>
