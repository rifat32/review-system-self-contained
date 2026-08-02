<x-mail-layout 
    title="Payment Failed Alert" 
    :app-name="$appName ?? config('app.name', 'FeedGenius')" 
    security-notice="Need help? Please contact our support team to update your payment information or resolve billing issues."
    :is-alert="true"
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        Payment Failed Alert
    </h2>

    <!-- Subtitle / Greeting -->
    <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
        Hi <strong style="color: #f1f5f9;">{{ $userName }}</strong>, we were unable to process your recent payment for your <strong style="color: #f1f5f9;">{{ $appName ?? 'FeedGenius' }}</strong> account.
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
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Amount Attempted:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #ef4444; font-weight: 700; text-align: right;">{{ $currency ?? 'USD' }} {{ is_numeric($amount) ? number_format($amount, 2) : $amount }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #94a3b8;">Attempt Date:</td>
                        <td style="padding-bottom: 10px; font-size: 13px; color: #f1f5f9; font-weight: 500; text-align: right;">{{ date('F j, Y') }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 13px; color: #94a3b8; vertical-align: top;">Status / Reason:</td>
                        <td style="font-size: 13px; color: #f87171; font-weight: 500; text-align: right; vertical-align: top;">{{ $failureReason ?? 'Payment declined by issuer' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Primary CTA Button (Danger Red Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); background-color: #ef4444; box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);">
                <a href="{{ $retryUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Update Payment Method
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
                    <a href="{{ $retryUrl }}" target="_blank" style="color: #f87171; text-decoration: underline; font-weight: 500;">{{ $retryUrl }}</a>
                </div>
            </td>
        </tr>
    </table>
</x-mail-layout>