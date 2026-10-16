@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'greeting' => filled($name) ? "Hi {$name}," : 'Hello,',
        'title' => 'Reset your password',
        'highlight' => 'password',
        'text' => 'We received a request to reset the password for your '.config('mail-brand.name').' account. Click the button below to choose a new password.',
        'buttonUrl' => $url,
        'buttonLabel' => 'Reset Password',
    ])

    @include('emails.partials.note', [
        'text' => "This link expires in {$expireMinutes} minutes.",
    ])

    @include('emails.partials.note', [
        'icon' => null,
        'text' => 'If you did not request a password reset, no further action is required.',
    ])

    @include('emails.partials.fallback-url', ['url' => $url])
@endsection
