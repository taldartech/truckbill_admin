@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hi {$name},",
        'title' => 'Welcome to '.config('mail-brand.name').'!',
        'highlight' => config('mail-brand.name').'!',
        'text' => 'Your account is almost ready. Complete your setup to get started with '.config('mail-brand.name').'.',
        'buttonUrl' => $url,
        'buttonLabel' => 'Complete Onboarding',
        'image' => 'hero-onboarding.png',
        'imageAlt' => 'Onboarding checklist',
    ])

    @include('emails.partials.steps', [
        'title' => 'What you’ll need to do',
        'items' => [
            ['icon' => 'user', 'text' => 'Create your login credentials'],
            ['icon' => 'file', 'text' => 'Enter your basic details'],
            ['icon' => 'file', 'text' => 'Accept the Terms & Conditions'],
            ['icon' => 'mail', 'text' => 'Verify your email with OTP'],
        ],
    ])

    @include('emails.partials.note', [
        'text' => "This link expires in {$expiryDays} ".str('day')->plural($expiryDays).'.',
    ])
@endsection
