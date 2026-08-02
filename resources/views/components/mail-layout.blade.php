<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>{{ $title ?? 'Notification' }} - {{ $appName ?? config('app.name', 'FeedGenius') }}</title>
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
                                    @if(isset($isAlert) && $isAlert)
                                        <td style="height: 4px; background: linear-gradient(90deg, #ef4444 0%, #dc2626 50%, #f87171 100%); background-color: #ef4444;"></td>
                                    @else
                                        <td style="height: 4px; background: linear-gradient(90deg, #10b981 0%, #34d399 50%, #6ee7b7 100%); background-color: #10b981;"></td>
                                    @endif
                                </tr>
                                <!-- Card Body -->
                                <tr>
                                    <td class="card-padding" align="center" style="padding: 44px 36px; text-align: center;">
                                        <!-- Logo or Icon Badge -->
                                        @if(!empty($logoUrl))
                                            <div style="width: 72px; height: 72px; border-radius: 16px; background-color: #0f172a; border: 2px solid rgba(16, 185, 129, 0.35); margin: 0 auto 24px auto; overflow: hidden; text-align: center;">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                    <tr>
                                                        <td align="center" valign="middle" style="height: 72px;">
                                                            <img src="{{ $logoUrl }}" alt="{{ $appName ?? 'FeedGenius' }}" style="max-width: 56px; max-height: 56px; width: auto; height: auto; object-fit: contain; display: block; margin: 0 auto; border: 0;">
                                                        </td>
                                                    </tr>
                                                </table>
                                            </div>
                                        @else
                                            @if(isset($isAlert) && $isAlert)
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
                                                                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: block; margin: 0 auto;">
                                                                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                                                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                                                    <circle cx="12" cy="16" r="1.5"></circle>
                                                                </svg>
                                                            </td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            @endif
                                        @endif

                                        {{ $slot }}

                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    @if(!empty($securityNotice))
                    <!-- Security Notice (Secondary Light Emerald Tint) -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin: 0; font-size: 14px; color: #6ee7b7; line-height: 1.5; max-width: 520px; font-weight: 500;">
                                {{ $securityNotice }}
                            </p>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 32px;">
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                                &copy; {{ date('Y') }} {{ $appName ?? config('app.name', 'FeedGenius') }} Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
