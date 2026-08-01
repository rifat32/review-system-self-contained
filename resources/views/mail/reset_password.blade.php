<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Reset Your Password - {{ $app_name ?? config('app.name', 'FeedGenius') }}</title>
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
                                        <!-- Logo or Icon Badge -->
                                        @if(!empty($logo_url))
                                            <div style="width: 72px; height: 72px; border-radius: 16px; background-color: #0f172a; border: 2px solid rgba(16, 185, 129, 0.35); margin: 0 auto 24px auto; overflow: hidden; text-align: center;">
                                                <table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%">
                                                    <tr>
                                                        <td align="center" valign="middle" style="height: 72px;">
                                                            <img src="{{ $logo_url }}" alt="{{ $app_name ?? 'FeedGenius' }}" style="max-width: 56px; max-height: 56px; width: auto; height: auto; object-fit: contain; display: block; margin: 0 auto; border: 0;">
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

                                        <!-- Title -->
                                        <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
                                            Reset Your Password
                                        </h2>

                                        <!-- Subtitle / Greeting -->
                                        <p style="margin: 0 0 32px 0; font-size: 15px; line-height: 1.625; color: #94a3b8; max-width: 480px;">
                                            Hi <strong style="color: #f1f5f9;">{{ $user_name ?? 'User' }}</strong>, we received a request to reset the password for your <strong style="color: #f1f5f9;">{{ $app_name ?? 'FeedGenius' }}</strong> account. If you made this request, please click the button below to set a new password.
                                        </p>

                                        <!-- Primary CTA Button (Emerald Green Gradient) -->
                                        <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
                                            <tr>
                                                <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                                                    <a href="{{ $reset_url }}" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                                                        Reset Password
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
                                                        <a href="{{ $reset_url }}" target="_blank" style="color: #34d399; text-decoration: underline; font-weight: 500;">{{ $reset_url }}</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Security Notice (Secondary Light Emerald Tint) -->
                    <tr>
                        <td align="center" style="padding-top: 24px;">
                            <p style="margin: 0; font-size: 14px; color: #6ee7b7; line-height: 1.5; max-width: 520px; font-weight: 500;">
                                If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding-top: 32px;">
                            <p style="margin: 0; font-size: 12px; color: #64748b; line-height: 1.5;">
                                &copy; {{ date('Y') }} {{ $app_name ?? 'FeedGenius' }} Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
