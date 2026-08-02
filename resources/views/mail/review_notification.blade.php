@php
    $isAlert = (isset($rating) && $rating <= 3) || (stripos($title, 'Low Rating') !== false) || (stripos($title, 'Alert') !== false);
    $ratingVal = isset($rating) ? floatval($rating) : null;
    $fullStars = $ratingVal ? min(5, max(0, floor($ratingVal))) : 0;
    $emptyStars = 5 - $fullStars;
@endphp

<x-mail-layout 
    title="{{ $title }}" 
    :app-name="$appName ?? config('app.name', 'FeedGenius')" 
    :is-alert="$isAlert"
>
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
</x-mail-layout>
