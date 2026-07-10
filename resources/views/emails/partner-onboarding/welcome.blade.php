@extends('emails.layout')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">Hi {{ $name }},</p>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        Welcome to {{ config('mail-brand.name') }}! Your Sales Partner account is now active.
    </p>

    <p style="margin:0 0 24px;text-align:center;">
        <a href="{{ $loginUrl }}" style="display:inline-block;padding:14px 28px;background-color:{{ config('mail-brand.colors.primary') }};color:#ffffff;text-decoration:none;border-radius:10px;font-size:16px;font-weight:700;">
            Log in to CRM
        </a>
    </p>

    <p style="margin:0 0 12px;font-size:16px;line-height:1.6;">
        To complete your registration, please reply to this email with the following documents attached:
    </p>

    <ul style="margin:0 0 16px;padding-left:20px;font-size:16px;line-height:1.8;color:{{ config('mail-brand.colors.text') }};">
        <li>PAN card</li>
        <li>Aadhaar card</li>
        <li>Bank passbook (first page)</li>
        <li>Cancelled cheque</li>
    </ul>

    <p style="margin:0 0 16px;font-size:16px;line-height:1.6;">
        <strong>Reply to this email</strong> with the documents attached. Our team will verify them and confirm your account.
    </p>

    <p style="margin:0;font-size:14px;line-height:1.6;color:{{ config('mail-brand.colors.muted') }};">
        Replies will be received at {{ $replyTo }}.
    </p>
@endsection
