<x-mail-layout 
    title="Registration Successful!" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="Thank you for choosing our system! If you have any questions, please contact our support team."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Welcome to {{ config('app.name', 'FeedGenius') }}!
    </h2>

    <!-- Subtitle / Greeting -->
    <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        Hello <strong style="color: #f1f5f9;">{{ $userName }}</strong>,<br><br>
        We are excited to inform you that your registration for the Review System was successful. Here are your registration details:
    </p>

    <!-- Details Summary Card -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-bottom: 32px; overflow: hidden;">
        <tr>
            <td style="padding: 20px; text-align: left;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td colspan="2" style="padding-bottom: 16px;">
                            <h3 style="margin: 0; font-size: 16px; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.05em;">👤 User Information</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8; width: 40%;">Name:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 600; text-align: right;">{{ $userName }}</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Email:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #34d399; font-weight: 600; text-align: right;">{{ $userEmail }}</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 24px; font-size: 13px; color: #94a3b8;">Registration Date:</td>
                        <td style="padding-bottom: 24px; font-size: 13px; color: #f1f5f9; font-weight: 500; text-align: right;">{{ \Carbon\Carbon::parse($registrationDate)->format('d/m/Y') }}</td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-bottom: 16px; border-top: 1px solid #334155; padding-top: 16px;">
                            <h3 style="margin: 0; font-size: 16px; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.05em;">🏢 Business Details</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Business Name:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 600; text-align: right;">{{ $businessName }}</td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 24px; font-size: 13px; color: #94a3b8;">Package Details:</td>
                        <td style="padding-bottom: 24px; font-size: 13px; color: #34d399; font-weight: 600; text-align: right;">{{ $subscriptionName }}</td>
                    </tr>

                    <tr>
                        <td colspan="2" style="padding-bottom: 16px; border-top: 1px solid #334155; padding-top: 16px;">
                            <h3 style="margin: 0; font-size: 16px; color: #f472b6; text-transform: uppercase; letter-spacing: 0.05em;">💳 Payment Details</h3>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Stripe Transaction ID:</td>
                        <td style="padding-bottom: 10px; font-size: 12px; color: #cbd5e1; font-family: monospace; text-align: right;">{{ $subscription->transaction_id ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #94a3b8;">Payment Amount:</td>
                        <td style="font-size: 13px; color: #f1f5f9; font-weight: 700; text-align: right;">£{{ number_format($subscription->amount ?? 0, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $loginUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Go to Dashboard
                </a>
            </td>
        </tr>
    </table>
</x-mail-layout>