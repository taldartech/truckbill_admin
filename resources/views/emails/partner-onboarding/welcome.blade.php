@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hi {$name},",
        'title' => 'Welcome to '.config('mail-brand.name').'!',
        'highlight' => config('mail-brand.name').'!',
        'text' => 'Your Sales Partner account is now active. To complete your registration, reply to this email with the documents listed below.',
        'buttonUrl' => $loginUrl,
        'buttonLabel' => 'Log in to CRM',
        'image' => 'hero-onboarding.png',
        'imageAlt' => 'Account ready',
    ])

    @include('emails.partials.steps', [
        'title' => 'Documents to share',
        'items' => [
            ['icon' => 'id', 'text' => 'PAN card'],
            ['icon' => 'id', 'text' => 'Aadhaar card'],
            ['icon' => 'bank', 'text' => 'Bank passbook (first page)'],
            ['icon' => 'receipt', 'text' => 'Cancelled cheque'],
        ],
    ])

    <p style="margin:0 0 20px 0;font-size:16px;line-height:1.6;color:{{ config('mail-brand.colors.text') }};">
        <strong style="color:{{ config('mail-brand.colors.heading') }};">Reply to this email</strong> with the documents attached. Our team will verify them and confirm your account.
    </p>

    @include('emails.partials.note', [
        'icon' => null,
        'text' => "Replies will be received at {$replyTo}.",
    ])
@endsection
