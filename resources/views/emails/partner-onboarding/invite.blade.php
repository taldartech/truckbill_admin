@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hi {{ $name }},</p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        You have been invited to join {{ config('mail-brand.name') }} as a Sales Partner. Please complete your onboarding using the link below:
    </p>

    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ $url }}" style="display:inline-block;padding:14px 28px;background-color:{{ config('mail-brand.colors.primary') }};color:#ffffff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">
            Complete Onboarding
        </a>
    </p>

    <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">
        During onboarding you will:
    </p>

    <ul style="margin:0 0 16px;padding-left:20px;font-size:16px;line-height:1.8;color:{{ config('mail-brand.colors.text') }};">
        <li>Enter your profile and KYC details</li>
        <li>Create your login password</li>
    </ul>

    <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:{{ config('mail-brand.colors.muted') }};">
        This link expires in {{ $expiryDays }} {{ str('day')->plural($expiryDays) }}.
    </p>

    <p style="margin:0;font-size:16px;line-height:1.6;">
        If you need help, contact us at
        <a href="mailto:{{ config('mail-brand.support_email') }}" style="color:{{ config('mail-brand.colors.primary') }};text-decoration:none;">{{ config('mail-brand.support_email') }}</a>.
    </p>
@endsection
