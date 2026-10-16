@extends('emails.layout')

@section('content')
    @include('emails.partials.intro', [
        'title' => 'New lead captured',
        'highlight' => 'lead',
        'text' => 'A new lead was captured from the website. Review the details below and follow up quickly.',
        'buttonUrl' => $leadUrl,
        'buttonLabel' => 'View lead',
    ])

    @include('emails.partials.details', [
        'title' => 'Lead details',
        'rows' => array_filter([
            ['label' => 'Name', 'value' => $lead->name],
            ['label' => 'Email', 'value' => $lead->email],
            ['label' => 'Mobile', 'value' => $lead->mobile],
            ['label' => 'Company', 'value' => $lead->company_name],
            ['label' => 'City', 'value' => $lead->city],
            ['label' => 'State', 'value' => $lead->state],
            ['label' => 'Form channel', 'value' => $lead->source],
            ['label' => 'Source', 'value' => $trafficSource],
            ['label' => 'Medium', 'value' => $trafficMedium],
            ['label' => 'Campaign', 'value' => $lead->utm_campaign],
            ['label' => 'Landing URL', 'value' => $lead->landing_url, 'break' => true],
            ['label' => 'Referrer', 'value' => $lead->referrer, 'break' => true],
            $lead->remarks ? ['label' => 'Remarks', 'value' => $lead->remarks, 'multiline' => true] : null,
        ]),
    ])
@endsection
