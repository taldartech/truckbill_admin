@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => "Hi {$name},",
        'title' => 'Join '.config('mail-brand.name').' as a Sales Partner',
        'highlight' => 'Sales Partner',
        'text' => 'You have been invited to join '.config('mail-brand.name').' as a Sales Partner. Complete your onboarding to get started.',
        'buttonUrl' => $url,
        'buttonLabel' => 'Complete Onboarding',
        'image' => 'hero-onboarding.png',
        'imageAlt' => 'Onboarding checklist',
    ])

    @include('emails.partials.steps', [
        'title' => 'What you’ll need to do',
        'items' => [
            ['icon' => 'id', 'text' => 'Enter your profile and KYC details'],
            ['icon' => 'key', 'text' => 'Create your login password'],
        ],
    ])

    @include('emails.partials.note', [
        'text' => "This link expires in {$expiryDays} ".str('day')->plural($expiryDays).'.',
    ])
@endsection
