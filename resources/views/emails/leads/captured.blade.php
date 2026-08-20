@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">A new lead was captured from the website.</p>

    <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:100%;margin:0 0 24px;font-size:15px;line-height:1.7;color:{{ config('mail-brand.colors.text') }};">
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};width:140px;">Name</td>
            <td style="padding:6px 0;">{{ $lead->name }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Email</td>
            <td style="padding:6px 0;">{{ $lead->email }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Mobile</td>
            <td style="padding:6px 0;">{{ $lead->mobile ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Company</td>
            <td style="padding:6px 0;">{{ $lead->company_name ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">City</td>
            <td style="padding:6px 0;">{{ $lead->city ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">State</td>
            <td style="padding:6px 0;">{{ $lead->state ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Form channel</td>
            <td style="padding:6px 0;">{{ $lead->source }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Source</td>
            <td style="padding:6px 0;">{{ $trafficSource }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Medium</td>
            <td style="padding:6px 0;">{{ $trafficMedium }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Campaign</td>
            <td style="padding:6px 0;">{{ $lead->utm_campaign ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Landing URL</td>
            <td style="padding:6px 0;word-break:break-all;">{{ $lead->landing_url ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};">Referrer</td>
            <td style="padding:6px 0;word-break:break-all;">{{ $lead->referrer ?: '—' }}</td>
        </tr>
        @if ($lead->remarks)
            <tr>
                <td style="padding:6px 0;color:{{ config('mail-brand.colors.muted') }};vertical-align:top;">Remarks</td>
                <td style="padding:6px 0;white-space:pre-line;">{{ $lead->remarks }}</td>
            </tr>
        @endif
    </table>

    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ $leadUrl }}" style="display:inline-block;padding:14px 28px;background-color:{{ config('mail-brand.colors.primary') }};color:#ffffff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">
            View lead
        </a>
    </p>
@endsection
