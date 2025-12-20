<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Google Business Connected Successfully</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: scaleIn 0.5s ease-out;
        }

        .success-icon svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 3;
        }

        @keyframes scaleIn {
            from {
                transform: scale(0);
            }

            to {
                transform: scale(1);
            }
        }

        h1 {
            color: #1f2937;
            font-size: 28px;
            margin-bottom: 12px;
        }

        p {
            color: #6b7280;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .account-info {
            background: #f3f4f6;
            border-radius: 12px;
            padding: 24px;
            margin: 24px 0;
            text-align: left;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #6b7280;
            font-weight: 500;
        }

        .info-value {
            color: #1f2937;
            font-weight: 600;
        }

        .button {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 16px;
        }

        .button:hover {
            background: #5568d3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .next-steps {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 16px;
            margin-top: 24px;
            text-align: left;
            border-radius: 8px;
        }

        .next-steps h3 {
            color: #1e40af;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .next-steps ul {
            list-style: none;
            padding-left: 0;
        }

        .next-steps li {
            color: #1e40af;
            padding: 6px 0;
            padding-left: 24px;
            position: relative;
        }

        .next-steps li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #10b981;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="success-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h1>🎉 Successfully Connected!</h1>
        <p>Your Google Business Profile has been connected to your account.</p>

        <div class="account-info">
            <div class="info-row">
                <span class="info-label">Account Name:</span>
                <span class="info-value">{{ $account->account_name }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Account ID:</span>
                <span class="info-value">{{ $account->account_id }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Account Type:</span>
                <span class="info-value">{{ $account->type }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Locations Found:</span>
                <span class="info-value">{{ $locationsCount }}</span>
            </div>
        </div>

        <div class="next-steps">
            <h3>✨ What's Next?</h3>
            <ul>
                <li>View your business locations</li>
                <li>Sync reviews from Google</li>
                <li>Reply to customer reviews</li>
                <li>Monitor your ratings</li>
            </ul>
        </div>

        <a href="/api/google/business/accounts" class="button">View My Accounts</a>

        <p style="margin-top: 24px; font-size: 14px; color: #9ca3af;">
            You can close this window and return to your application.
        </p>
    </div>
</body>

</html>
