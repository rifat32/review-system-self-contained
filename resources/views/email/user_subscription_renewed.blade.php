<!DOCTYPE html>
<html>

<head>
    <title>Subscription Renewed</title>
</head>

<body>
    <h1>Subscription Renewed Successfully</h1>
    <p>Hello {{ $userName }},</p>

    <p>Your subscription for <strong>{{ $businessName }}</strong> has been renewed successfully.</p>

    <h2>Renewal Details</h2>
    <p><strong>Plan:</strong> {{ $planName }}</p>
    <p><strong>Amount Paid:</strong> £{{ $amount }}</p>
    <p><strong>Current Period Ends:</strong> {{ $endDate }}</p>

    <p>Thank you for your continued support!</p>

    <p>Best regards,</p>
    <p>Review System Team</p>
</body>

</html>