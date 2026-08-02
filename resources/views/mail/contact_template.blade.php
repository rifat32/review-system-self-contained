<x-mail-layout 
    title="New Website Inquiry" 
    :app-name="config('app.name', 'FeedGenius')" 
>
    <!-- Title -->
    <h2 style="margin: 0 0 16px 0; font-size: 26px; font-weight: 700; color: #f1f5f9; letter-spacing: -0.5px;">
        New Website Inquiry
    </h2>

    <p style="margin: 0 0 16px 0; font-size: 15px; color: #94a3b8;">
        Hello {{ config('app.name', 'FeedGenius') }} Team,
    </p>
    <p style="margin: 0 0 24px 0; font-size: 15px; color: #94a3b8;">
        You have received a new message from your website contact form. Here are the details:
    </p>

    <!-- Info Table -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom: 24px; font-size: 15px; text-align: left;">
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #334155; font-weight: 600; color: #f1f5f9; width: 120px;">Name:</td>
            <td style="padding: 10px; border-bottom: 1px solid #334155; color: #94a3b8;">{{ $data['first_name'] }} {{ $data['last_name'] }}</td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #334155; font-weight: 600; color: #f1f5f9;">Email:</td>
            <td style="padding: 10px; border-bottom: 1px solid #334155; color: #94a3b8;"><a href="mailto:{{ $data['email'] }}" style="color: #34d399; text-decoration: none;">{{ $data['email'] }}</a></td>
        </tr>
        <tr>
            <td style="padding: 10px; border-bottom: 1px solid #334155; font-weight: 600; color: #f1f5f9;">Subject:</td>
            <td style="padding: 10px; border-bottom: 1px solid #334155; color: #94a3b8;">{{ $data['subject'] }}</td>
        </tr>
    </table>

    <p style="margin: 0 0 12px 0; font-size: 15px; font-weight: 600; color: #f1f5f9; text-align: left;">
        Message:
    </p>
    <div style="background-color: #0f172a; border-left: 4px solid #10b981; border-radius: 4px; padding: 16px; margin-bottom: 24px; font-size: 14px; line-height: 1.6; color: #94a3b8; text-align: left; white-space: pre-wrap;">
        {{ $data['message'] }}
    </div>

</x-mail-layout>
