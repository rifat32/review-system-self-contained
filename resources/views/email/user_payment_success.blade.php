<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Successful - {{ $appName ?? config('app.name', 'FeedGenius') }}</title>
    <style type="text/css">
        body, table, td, a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }
        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #0f172a;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #f1f5f9;
        }
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
                padding: 12px !important;
            }
            .card-padding {
                padding: 28px 20px !important;
            }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #0f172a; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
    <!-- Main Outer Wrapper -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; table-layout: fixed;">
        <tr>
            <td align="center" style="padding: 40px 16px;">
                <!-- Container -->
                <table border="0" cellpadding="0" cellspacing="0" width="100%" class="email-container" style="max-width: 600px; margin: 0 auto;">
                    <!-- Main Card -->
                    <tr>
                        <td align="center">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
                                <!-- Top Accent Bar (Secondary Emerald Gradient) -->
                                <tr>
                                    <td style="height: 4px; background: linear-gradient(90deg, #10b981 0%, #34d399 50%, #6ee7b7 100%); background-color: #10b981;"></td>
                                </tr>
                                <!-- Card Body -->
                                <tr>
                                    <td class="card-padding" align="center" style="padding: 44px 36px; text-align: center;">
                                        <!-- Success Checkmark Badge -->
                                        <div style="width: 64px; height: 64px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.12); border: 2px solid rgba(16, 185, 129, 0.3); margin: 0 auto 24px auto;">
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                <tr>
                                                    <td align="center" valign="middle" style="height: 64px;">
                                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;">
                                                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                                        </svg>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

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
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Assistance Notice -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin: 0; font-size: 14px; color: #94a3b8; line-height: 1.5; max-width: 520px;">
                                Thank you for choosing {{ $appName ?? 'FeedGenius' }}. If you have any questions regarding your invoice or account, please contact our support team.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 32px;">
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                                &copy; {{ date('Y') }} {{ $appName ?? 'FeedGenius' }} Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
