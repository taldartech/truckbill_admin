@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hello,</p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        Use the verification code below to confirm your email address during onboarding.
    </p>

    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="margin:0 0 24px;">
        <tr>
            <td align="center" style="padding:24px;background-color:#f8fafc;border:1px solid {{ config('mail-brand.colors.border') }};border-radius:12px;">
                <p style="margin:0 0 8px;font-size:13px;font-weight:600;letter-spacing:0.04em;text-transform:uppercase;color:{{ config('mail-brand.colors.muted') }};">
                    Your verification code
                </p>
                <p style="margin:0;font-size:36px;font-weight:700;letter-spacing:0.2em;color:{{ config('mail-brand.colors.primary_dark') }};">
                    {{ $otp }}
                </p>
            </td>
        </tr>
    </table>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        This code expires in {{ $ttlMinutes }} minutes.
    </p>

    <p style="margin:0;font-size:14px;line-height:1.6;color:{{ config('mail-brand.colors.muted') }};">
        If you did not request this code, you can safely ignore this email.
    </p>
@endsection
