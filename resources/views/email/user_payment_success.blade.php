<x-mail-layout 
    title="Payment Successful!" 
    :app-name="$appName ?? config('app.name', 'FeedGenius')" 
    security-notice="Thank you for choosing {{ $appName ?? 'FeedGenius' }}. If you have any questions regarding your invoice or account, please contact our support team."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Payment Successful!
    </h2>

    <!-- Subtitle / Greeting -->
    <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        Hi <strong style="color: #f1f5f9;">{{ $userName }}</strong>, thank you for your payment! Your transaction for <strong style="color: #f1f5f9;">{{ $appName ?? 'FeedGenius' }}</strong> has been completed successfully.
    </p>

    <!-- Payment Details Summary Card -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; margin-bottom: 32px; overflow: hidden;">
        <tr>
            <td style="padding: 20px; text-align: left;">
                <table border="0" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8; width: 40%;">Account Email:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 600; text-align: right;">{{ $userEmail }}</td>
                    </tr>
                    @if(!empty($planName))
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Subscription Plan:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 600; text-align: right;">{{ $planName }}</td>
                    </tr>
                    @endif
                    @if(!empty($amount))
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Amount Paid:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #34d399; font-weight: 700; text-align: right;">{{ $currency ?? 'USD' }} {{ is_numeric($amount) ? number_format($amount, 2) : $amount }}</td>
                    </tr>
                    @endif
                    @if(!empty($transactionId))
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Transaction ID:</td>
                        <td style="padding-bottom: 10px; font-size: 12px; color: #cbd5e1; font-family: monospace; text-align: right;">{{ $transactionId }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Payment Date:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 500; text-align: right;">{{ $paymentDate ?? date('F j, Y') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #94a3b8;">Status:</td>
                        <td style="font-size: 13px; color: #34d399; font-weight: 600; text-align: right;">Paid (Successful)</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ $dashboardUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Go to Dashboard
                </a>
            </td>
        </tr>
    </table>

    <!-- Fallback Link Section -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="border-top: 1px solid #334155; padding-top: 24px;">
        <tr>
            <td align="left">
                <p style="margin: 0 0 10px 0; font-size: 13px; color: #94a3b8; line-height: 1.5;">
                    If the button above doesn't work, copy and paste the following link into your browser:
                </p>
                <div style="background-color: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 14px; word-break: break-all; font-size: 13px; line-height: 1.5;">
                    <a href="{{ $dashboardUrl }}" target="_blank" style="color: #34d399; text-decoration: underline; font-weight: 500;">{{ $dashboardUrl }}</a>
                </div>
            </td>
        </tr>
    </table>
</x-mail-layout>
