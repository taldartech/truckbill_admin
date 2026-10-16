@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => 'Hello,',
        'title' => 'Verify your email',
        'highlight' => 'email',
        'text' => 'Use the verification code below to confirm your email address during onboarding.',
    ])

    @include('emails.partials.code', [
        'label' => 'Your verification code',
        'code' => $otp,
    ])

    @include('emails.partials.note', [
        'text' => "This code expires in {$ttlMinutes} minutes.",
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'If you did not request this code, you can safely ignore this email.',
    ])
@endsection
