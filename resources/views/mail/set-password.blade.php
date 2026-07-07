@component('mail::message')
# {{ __('Set your password') }}

{{ __('Hello :name,', ['name' => $name]) }}

{{ __('An account has been created for you on :app.', ['app' => $appName]) }}

{{ __('Click the button below to set your password. This link expires on :date.', ['date' => $expiresAt->format('j F Y')]) }}

@component('mail::button', ['url' => $setPasswordUrl])
{{ __('Set your password') }}
@endcomponent

{{ __('If you were not expecting this email, you can safely ignore it.') }}

{{ __('Thanks,') }}<br>
{{ $orgName }}
@endcomponent
