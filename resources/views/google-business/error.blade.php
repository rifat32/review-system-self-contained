<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connection Error</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
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

        .error-icon {
            width: 80px;
            height: 80px;
            background: #ef4444;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
            animation: shake 0.5s ease-out;
        }

        .error-icon svg {
            width: 48px;
            height: 48px;
            stroke: white;
            stroke-width: 3;
        }

        @keyframes shake {

            0%,
            100% {
                transform: translateX(0);
            }

            25% {
                transform: translateX(-10px);
            }

            75% {
                transform: translateX(10px);
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

        .error-details {
            background: #fef2f2;
            border-left: 4px solid #ef4444;
            padding: 16px;
            margin: 24px 0;
            text-align: left;
            border-radius: 8px;
        }

        .error-details h3 {
            color: #991b1b;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .error-details p {
            color: #991b1b;
            font-size: 14px;
            margin: 0;
        }

        .button {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 16px;
        }

        .button:hover {
            background: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
        }

        .help-section {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 8px;
            margin-top: 24px;
            text-align: left;
        }

        .help-section h3 {
            color: #1f2937;
            font-size: 16px;
            margin-bottom: 12px;
        }

        .help-section ul {
            list-style: none;
            padding-left: 0;
        }

        .help-section li {
            color: #4b5563;
            padding: 6px 0;
            padding-left: 24px;
            position: relative;
        }

        .help-section li:before {
            content: "→";
            position: absolute;
            left: 0;
            color: #6b7280;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="error-icon">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>

        <h1>❌ {{ $title }}</h1>
        <p>We encountered an error while connecting your Google Business account.</p>

        <div class="error-details">
            <h3>Error Details:</h3>
            <p>{{ $message }}</p>
        </div>

        <div class="help-section">
            <h3>💡 Troubleshooting Tips:</h3>
            <ul>
                <li>Make sure you're using the correct Google account</li>
                <li>Verify that you have Owner/Manager access to the Business Profile</li>
                <li>Check that the redirect URI is configured in Google Cloud Console</li>
                <li>Try clearing your browser cache and cookies</li>
                <li>Wait a few minutes and try again</li>
            </ul>
        </div>

        <a href="/api/google/business/redirect" class="button">Try Again</a>

        <p style="margin-top: 24px; font-size: 14px; color: #9ca3af;">
            If the problem persists, please contact support.
        </p>
    </div>
</body>

</html>
