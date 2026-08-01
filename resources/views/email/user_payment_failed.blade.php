<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Payment Failed Alert - {{ $appName ?? config('app.name', 'FeedGenius') }}</title>
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
                                <!-- Top Accent Bar (Danger Red Gradient) -->
                                <tr>
                                    <td style="height: 4px; background: linear-gradient(90deg, #ef4444 0%, #dc2626 50%, #f87171 100%); background-color: #ef4444;"></td>
                                </tr>
                                <!-- Card Body -->
                                <tr>
                                    <td class="card-padding" align="center" style="padding: 44px 36px; text-align: center;">
                                        <!-- Alert Icon Badge -->
                                        <div style="width: 64px; height: 64px; border-radius: 50%; background-color: rgba(239, 68, 68, 0.12); border: 2px solid rgba(239, 68, 68, 0.3); margin: 0 auto 24px auto;">
                                            <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                <tr>
                                                    <td align="center" valign="middle" style="height: 64px;">
                                                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;">
                                                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                                            <line x1="1" y1="10" x2="23" y2="10"></line>
                                                            <line x1="7" y1="15" x2="7.01" y2="15"></line>
                                                            <line x1="11" y1="15" x2="13" y2="15"></line>
                                                        </svg>
                                                    </td>
                                                </tr>
                                            </table>
                                        </div>

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
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Assistance Notice -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin: 0; font-size: 14px; color: #94a3b8; line-height: 1.5; max-width: 520px;">
                                Need help? Please contact our support team to update your payment information or resolve billing issues.
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