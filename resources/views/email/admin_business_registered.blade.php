<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Business Registration Notification</title>
    <style>
        body,
        table,
        td,
        a {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table,
        td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        table {
            border-collapse: collapse !important;
        }

        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f9fafb;
        }

        @media screen and (max-width: 600px) {
            .container {
                width: 100% !important;
                max-width: 100% !important;
            }

            .content-padding {
                padding: 20px !important;
            }
        }
    </style>
</head>

<body style="background-color: #f9fafb; margin: 0; padding: 0;">
    <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <table border="0" cellpadding="0" cellspacing="0" width="450" class="container" style="background-color: #ffffff; border-radius: 24px; overflow: hidden; border: 1px solid #e5e7eb; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);">
                    <!-- Header -->
                    <tr>
                        <td style="padding: 24px; border-bottom: 1px solid #f3f4f6;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td>
                                        <span style="color: #0e4f64; font-weight: bold; font-size: 20px; letter-spacing: -0.025em;">feed<span style="color: #22c55e;">genius</span></span>
                                    </td>
                                    <td align="right">
                                        <span style="font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600;">Internal Notification</span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <!-- Banner -->
                    <tr>
                        <td align="center" style="background-color: #0e4f64; padding: 32px 24px;">
                            <h1 style="color: #ffffff; margin: 0; font-size: 22px; font-weight: bold; line-height: 1.2;">New Business Registration</h1>
                            <p style="color: #99f6e4; font-size: 14px; margin: 8px 0 0 0;">A new business has just joined the platform.</p>
                        </td>
                    </tr>
                    <!-- Content -->
                    <tr>
                        <td class="content-padding" style="padding: 24px;">
                            <!-- Owner Info -->
                            <div style="margin-bottom: 24px;">
                                <h2 style="color: #111827; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px 0;">👤 Owner Information</h2>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f9fafb; border-radius: 16px; padding: 16px;">
                                    <tr>
                                        <td style="padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">Name</td>
                                        <td align="right" style="padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: #111827;">{{ $user->first_Name }} {{ $user->last_Name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">Email</td>
                                        <td align="right" style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: #22c55e;">{{ $user->email }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 8px; font-size: 13px; color: #6b7280;">Phone</td>
                                        <td align="right" style="padding-top: 8px; font-size: 13px; font-weight: 600; color: #111827;">{{ $user->phone ?? 'N/A' }}</td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Business Details -->
                            <div style="margin-bottom: 24px;">
                                <h2 style="color: #111827; font-size: 14px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 12px 0;">🏢 Business Details</h2>
                                <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f9fafb; border-radius: 16px; padding: 16px;">
                                    <tr>
                                        <td style="padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">Business Name</td>
                                        <td align="right" style="padding-bottom: 8px; border-bottom: 1px solid #e5e7eb; font-size: 13px; font-weight: bold; color: #111827;">{{ $business->Name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">Address</td>
                                        <td align="right" style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; font-weight: 500; color: #111827; line-height: 1.4;">{{ $business->Address }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; color: #6b7280;">Post Code</td>
                                        <td align="right" style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 13px; font-weight: 600; color: #111827;">{{ $business->PostCode ?? 'N/A' }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding-top: 8px; font-size: 13px; color: #6b7280;">Service Plan</td>
                                        <td align="right" style="padding-top: 8px;">
                                            <span style="background-color: rgba(34, 197, 94, 0.1); color: #22c55e; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: bold; text-transform: uppercase;">{{ $planName }}</span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Action -->
                            <div align="center" style="margin-top: 32px;">
                                <a href="{{ env('FRONT_END_URL') }}/admin/businesses" style="background-color: #22c55e; color: #ffffff; display: block; padding: 16px; border-radius: 12px; text-decoration: none; font-weight: bold; font-size: 16px; text-align: center; box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.2);">
                                    Review in Admin Dashboard
                                </a>
                                <p style="font-size: 11px; color: #9ca3af; margin-top: 24px; line-height: 1.5;">
                                    This is an automated notification from the {{ config('app.name') }} registration system. Please take action within 24 hours.
                                </p>
                            </div>
                        </td>
                    </tr>
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="background-color: #f9fafb; padding: 24px; border-top: 1px solid #f3f4f6;">
                            <p style="font-size: 10px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.1em; margin: 0;">
                                © {{ date('Y') }} {{ config('app.name') }} Inc. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>

</html>