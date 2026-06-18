@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hi {{ $name }},</p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        To get started, please complete your account setup by using the onboarding link below:
    </p>

    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;background-color:{{ config('mail-brand.colors.primary') }};color:#ffffff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">
            Complete Onboarding
        </a>
    </p>

    <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">
        This link will guide you through a quick setup where you can:
    </p>

    <ul style="margin:0 0 16px;padding-left:20px;font-size:16px;line-height:1.8;color:{{ config('mail-brand.colors.text') }};">
        <li>Create your login credentials</li>
        <li>Enter your basic details</li>
        <li>Accept the Terms &amp; Conditions</li>
    </ul>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        For security purposes, you will also need to verify your email using an OTP during the process.
    </p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        Once completed, your account will be activated and you will be ready to start using {{ config('mail-brand.name') }}.
    </p>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:{{ config('mail-brand.colors.muted') }};">
        This link expires in {{ $expiryDays }} {{ str('day')->plural($expiryDays) }}.
    </p>

    <p style="margin:0;font-size:16px;line-height:1.6;">
        If you face any issues or need assistance at any step, feel free to reply to this email or contact us at
        <a href="mailto:{{ config('mail-brand.support_email') }}" style="color:{{ config('mail-brand.colors.primary') }};text-decoration:none;">{{ config('mail-brand.support_email') }}</a>.
    </p>
@endsection
