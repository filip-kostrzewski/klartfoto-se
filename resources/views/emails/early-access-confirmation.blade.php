<x-mail::message>
# {{ __('marketing.email.heading') }}

{{ __('marketing.email.body', ['brand' => config('marketing.brand')]) }}

{{ __('marketing.email.status') }}

{{ __('marketing.email.privacy') }}

{{ __('marketing.email.closing') }}<br>
{{ config('marketing.brand') }}
</x-mail::message>
