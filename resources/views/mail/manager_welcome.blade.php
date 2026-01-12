<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ $businessName }}</title>
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
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .content {
            padding: 30px 20px;
        }
        .credentials-box {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .credentials-box h3 {
            margin-top: 0;
            color: #667eea;
        }
        .credential-item {
            margin: 10px 0;
            padding: 10px;
            background: white;
            border-radius: 4px;
        }
        .credential-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
        }
        .credential-value {
            color: #333;
            font-size: 16px;
            margin-top: 5px;
            word-break: break-all;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 20px 0;
            font-weight: bold;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Welcome to {{ $businessName }}!</h1>
        </div>
        
        <div class="content">
            <h2>Hello {{ $userName }},</h2>
            
            <p>Welcome! Your account as a <strong>{{ $role }}</strong> has been successfully created.</p>
            
            <div class="credentials-box">
                <h3>🔐 Your Login Credentials</h3>
                <div class="credential-item">
                    <div class="credential-label">Email Address</div>
                    <div class="credential-value">{{ $email }}</div>
                </div>
                <div class="credential-item">
                    <div class="credential-label">Temporary Password</div>
                    <div class="credential-value">{{ $password }}</div>
                </div>
                <div class="credential-item">
                    <div class="credential-label">Role</div>
                    <div class="credential-value">{{ $role }}</div>
                </div>
            </div>

            <div class="warning">
                <strong>⚠️ Security Notice:</strong> Please change your password after logging in for the first time. Keep your credentials secure and do not share them with anyone.
            </div>

            <div style="text-align: center;">
                <a href="{{ $loginUrl }}" class="btn">Login to Your Account</a>
            </div>

            <p style="margin-top: 30px;">
                If you have any questions or need assistance, please don't hesitate to contact your administrator.
            </p>

            <p style="margin-top: 20px;">
                Best regards,<br>
                <strong>{{ $businessName }} Team</strong>
            </p>
        </div>

        <div class="footer">
            <p>This is an automated email. Please do not reply to this message.</p>
            <p>&copy; {{ date('Y') }} {{ $businessName }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
