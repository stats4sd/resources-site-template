@component('mail::message')
# {{ __('You have been invited') }}

{{ __('You have been invited to create an account on :app.', ['app' => $appName]) }}

{{ __('Click the button below to set up your account. This invitation expires on :date.', ['date' => $expiresAt->format('j F Y')]) }}

@component('mail::button', ['url' => $registerUrl])
{{ __('Accept invitation') }}
@endcomponent

{{ __('If you were not expecting this invitation, you can safely ignore this email.') }}

{{ __('Thanks,') }}<br>
{{ $orgName }}
@endcomponent
