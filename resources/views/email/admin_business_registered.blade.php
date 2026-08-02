<x-mail-layout 
    title="New Business Registration" 
    :app-name="config('app.name', 'FeedGenius')" 
    security-notice="This is an automated notification from the {{ config('app.name', 'FeedGenius') }} registration system. Please take action within 24 hours."
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        New Business Registration
    </h2>

    <p style="margin: 0 0 24px 0; font-size: 15px; color: #94a3b8; line-height: 1.625;">
        A new business has just joined the platform.
    </p>

    <!-- Owner Info Box -->
    <div style="background-color: #0f172a; border-left: 4px solid #3b82f6; border-radius: 8px; padding: 24px; margin-bottom: 24px;">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #60a5fa; text-transform: uppercase; letter-spacing: 0.05em;">👤 Owner Information</h3>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px;">Name</div>
            <div style="display: table-cell; color: #f1f5f9; font-size: 14px; text-align: right; font-weight: 600;">{{ $user->first_Name }} {{ $user->last_Name }}</div>
        </div>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px;">Email</div>
            <div style="display: table-cell; color: #34d399; font-size: 14px; text-align: right; font-weight: 600;">{{ $user->email }}</div>
        </div>

        <div style="padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px;">Phone</div>
            <div style="display: table-cell; color: #f1f5f9; font-size: 14px; text-align: right; font-weight: 600;">{{ $user->phone ?? 'N/A' }}</div>
        </div>
    </div>

    <!-- Business Details Box -->
    <div style="background-color: #0f172a; border-left: 4px solid #8b5cf6; border-radius: 8px; padding: 24px; margin-bottom: 32px;">
        <h3 style="margin: 0 0 16px 0; font-size: 16px; color: #a78bfa; text-transform: uppercase; letter-spacing: 0.05em;">🏢 Business Details</h3>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px; vertical-align: top;">Business Name</div>
            <div style="display: table-cell; color: #f1f5f9; font-size: 14px; text-align: right; font-weight: 600;">{{ $business->Name }}</div>
        </div>
        
        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px; vertical-align: top;">Address</div>
            <div style="display: table-cell; color: #f1f5f9; font-size: 14px; text-align: right; font-weight: 500; line-height: 1.4;">{{ $business->Address }}</div>
        </div>

        <div style="margin-bottom: 12px; padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px; vertical-align: top;">Post Code</div>
            <div style="display: table-cell; color: #f1f5f9; font-size: 14px; text-align: right; font-weight: 600;">{{ $business->PostCode ?? 'N/A' }}</div>
        </div>

        <div style="padding: 12px; background-color: #1e293b; border-radius: 6px; border: 1px solid #334155; display: table; width: 100%; box-sizing: border-box;">
            <div style="display: table-cell; font-weight: 600; color: #94a3b8; font-size: 13px; vertical-align: middle;">Service Plan</div>
            <div style="display: table-cell; text-align: right; vertical-align: middle;">
                <span style="background-color: rgba(16, 185, 129, 0.15); color: #34d399; padding: 4px 10px; border-radius: 9999px; font-size: 12px; font-weight: 700; text-transform: uppercase;">{{ $planName }}</span>
            </div>
        </div>
    </div>

    <!-- Primary CTA Button (Emerald Green Gradient) -->
    <table border="0" cellpadding="0" cellspacing="0" style="margin: 0 auto 32px auto;">
        <tr>
            <td align="center" style="border-radius: 10px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); background-color: #10b981; box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);">
                <a href="{{ env('FRONT_END_URL') }}/admin/businesses" target="_blank" style="display: inline-block; padding: 14px 36px; font-size: 16px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px; letter-spacing: 0.2px;">
                    Review in Admin Dashboard
                </a>
            </td>
        </tr>
    </table>

</x-mail-layout>