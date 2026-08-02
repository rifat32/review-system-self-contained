<!DOCTYPE html>
<html>
<head>
    <title>Subscription Expiring Alert</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333; line-height: 1.6;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 5px;">
        <h2 style="color: #d9534f; margin-bottom: 20px;">Action Required: Subscription Expiring Soon</h2>
        
        <p>Hello Admin,</p>
        
        <p>This is an automated alert to notify you that the subscription for the following business is expiring in exactly <strong>15 days</strong>.</p>
        
        <div style="background-color: #f9f9f9; padding: 15px; border-left: 4px solid #d9534f; margin-bottom: 20px;">
            <p style="margin: 0 0 10px 0;"><strong>Business Name:</strong> {{ $business->name ?? 'N/A' }}</p>
            <p style="margin: 0 0 10px 0;"><strong>Owner Name:</strong> {{ $owner ? ($owner->first_Name . ' ' . $owner->last_Name) : 'N/A' }}</p>
            <p style="margin: 0 0 10px 0;"><strong>Owner Email:</strong> <a href="mailto:{{ $owner->email ?? '' }}">{{ $owner->email ?? 'N/A' }}</a></p>
            <p style="margin: 0;"><strong>Expiration Date:</strong> {{ \Carbon\Carbon::parse($business->trial_end_date)->format('F j, Y') }}</p>
        </div>
        
        <p>Please reach out to the customer or monitor the account to ensure a smooth renewal process.</p>
        
        <p style="margin-top: 30px; font-size: 0.9em; color: #777;">
            <em>This is an automated message from the Feed Genius System.</em>
        </p>
    </div>
</body>
</html>
