@php
    $isAlert = (isset($rating) && $rating <= 3) || (stripos($title, 'Low Rating') !== false) || (stripos($title, 'Alert') !== false);
    $ratingVal = isset($rating) ? floatval($rating) : null;
    $fullStars = $ratingVal ? min(5, max(0, floor($ratingVal))) : 0;
    $emptyStars = 5 - $fullStars;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title }} - {{ $appName ?? config('app.name', 'FeedGenius') }}</title>
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
                                <!-- Top Accent Bar -->
                                <tr>
                                    @if($isAlert)
                                        <td style="height: 4px; background: linear-gradient(90deg, #ef4444 0%, #dc2626 50%, #f87171 100%); background-color: #ef4444;"></td>
                                    @else
                                        <td style="height: 4px; background: linear-gradient(90deg, #10b981 0%, #34d399 50%, #6ee7b7 100%); background-color: #10b981;"></td>
                                    @endif
                                </tr>
                                <!-- Card Body -->
                                <tr>
                                    <td class="card-padding" align="center" style="padding: 44px 36px; text-align: center;">
                                        <!-- Icon Badge -->
                                        @if($isAlert)
                                            <div style="width: 64px; height: 64px; border-radius: 50%; background-color: rgba(239, 68, 68, 0.12); border: 2px solid rgba(239, 68, 68, 0.3); margin: 0 auto 24px auto;">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                    <tr>
                                                        <td align="center" valign="middle" style="height: 64px;">
                                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;">
                                                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                                                            </svg>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @else
                                            <div style="width: 64px; height: 64px; border-radius: 50%; background-color: rgba(16, 185, 129, 0.12); border: 2px solid rgba(16, 185, 129, 0.3); margin: 0 auto 24px auto;">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                    <tr>
                                                        <td align="center" valign="middle" style="height: 64px;">
                                                            <svg width="32" height="32" viewBox="0 0 24 24" fill="currentColor" stroke="none" style="display: block; margin: 0 auto; color: #34d399;">
                                                                <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                                            </svg>
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @endif

                                        <!-- Title -->
                                        <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
                                            {{ $title }}
                                        </h2>

                                        <!-- Subtitle / Greeting -->
                                        <p style="margin: 0 0 28px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
                                            Hi <strong style="color: #f1f5f9;">{{ $userName }}</strong>, you have a new update regarding reviews for <strong style="color: #f1f5f9;">{{ $businessName }}</strong>.
                                        </p>

                                        <!-- Review Message Card -->
                                        <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #0f172a; border: 1px solid #334155; border-left: 4px solid {{ $isAlert ? '#ef4444' : '#10b981' }}; border-radius: 8px; margin-bottom: 32px; overflow: hidden;">
                                            <tr>
                                                <td style="padding: 20px; text-align: left;">
                                                    @if(!empty($ratingVal))
                                                        <div style="margin-bottom: 12px;">
                                                            <span style="font-size: 20px; color: #fbbf24; letter-spacing: 2px;">
                                                                @for($i = 0; $i < $fullStars; $i++)★@endfor @for($i = 0; $i < $emptyStars; $i++)☆@endfor
                                                            </span>
                                                            <span style="font-size: 14px; font-weight: 700; color: #f1f5f9; margin-left: 8px;">
                                                                {{ number_format($ratingVal, 1) }} / 5.0
                                                            </span>
                                                        </div>
                                                    @endif

                                                    <p style="margin: 0; font-size: 14px; line-height: 1.6; color: #cbd5e1; font-style: italic;">
                                                        "{{ $messageBody }}"
                                                    </p>
                                                </td>
                                            </tr>
                                        </table>

                                        <!-- Action Message -->
                                        <p style="margin: 0 0 24px 0; font-size: 14px; color: #94a3b8;">
                                            Please log in to your dashboard to view the full details and respond to this review.
                                        </p>

                                        <!-- Primary CTA Button -->
                                        <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
                                            <tr>
                                                @if($isAlert)
                                                    <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); background-color: #ef4444; box-shadow: 0 6px 20px rgba(239, 68, 68, 0.35);">
                                                        <a href="{{ $dashboardUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                                            View Dashboard
                                                        </a>
                                                    </td>
                                                @else
                                                    <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                                                        <a href="{{ $dashboardUrl }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                                            View Dashboard
                                                        </a>
                                                    </td>
                                                @endif
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
                                                        <a href="{{ $dashboardUrl }}" target="_blank" style="color: {{ $isAlert ? '#f87171' : '#34d399' }}; text-decoration: underline; font-weight: 500;">{{ $dashboardUrl }}</a>
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
                            <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.5; max-width: 520px;">
                                This is an automated notification from {{ $appName ?? 'FeedGenius' }}. Please do not reply to this email.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
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
