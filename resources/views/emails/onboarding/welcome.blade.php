@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hi {$name},",
        'title' => 'Welcome to '.config('mail-brand.name').'!',
        'highlight' => config('mail-brand.name').'!',
        'text' => "Your account has been successfully set up and is now ready to use. You can log in to {$productLabel} using your credentials.",
        'buttonUrl' => $loginUrl,
        'buttonLabel' => 'Log In to '.config('mail-brand.name'),
        'image' => 'hero-onboarding.png',
        'imageAlt' => 'Account ready',
    ])

    @include('emails.partials.steps', [
        'title' => 'What you can do with '.config('mail-brand.name'),
        'items' => [
            ['icon' => 'receipt', 'text' => 'Manage billing and invoices seamlessly'],
            ['icon' => 'file', 'text' => 'Create and track LRs in one place'],
            ['icon' => 'shield', 'text' => 'Stay on top of GST and TDS compliance'],
        ],
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'Need a quick walkthrough to get started? Just reply to this email — we are here to support you.',
    ])
@endsection
