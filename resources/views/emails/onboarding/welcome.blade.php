@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hi {{ $name }},</p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        Welcome to {{ config('mail-brand.name') }}! Your account has been successfully set up and is now ready to use.
    </p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        You can log in to {{ $productLabel }} using your credentials:
    </p>

    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;background-color:{{ config('mail-brand.colors.primary') }};color:#ffffff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">
            Log In to {{ config('mail-brand.name') }}
        </a>
    </p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        With {{ config('mail-brand.name') }}, you can start managing your transport operations seamlessly — from billing and LR management to GST and TDS compliance.
    </p>

    <p style="margin:0;font-size:16px;line-height:1.6;">
        If you need any help getting started or would like a quick walkthrough, feel free to reach out at
        <a href="mailto:{{ config('mail-brand.support_email') }}" style="color:{{ config('mail-brand.colors.primary') }};text-decoration:none;">{{ config('mail-brand.support_email') }}</a>.
        We are here to support you.
    </p>
@endsection
