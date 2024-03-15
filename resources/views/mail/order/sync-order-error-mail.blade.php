<x-mail::message>
    # Introduction

    #### You have an error while trying to sync orders.
    #### Message: {!! $message !!}
    #### Status code: {{ $status_code }}

    #### Here is the trace log.

    {!! $log_trace !!}

    Thanks,<br>
    {{ config('app.name') }}
</x-mail::message>
