{{--
    One-shot submission token. Include right after @csrf in any form that
    creates a record. PreventDuplicateFormSubmission answers a replay of the
    same token (double click, browser re-POST, retried request) with a
    notice instead of a second record. A new token is minted on every page
    render, so reloading the form and submitting again is a new submission.
--}}
<input type="hidden" name="_idempotency" value="{{ \Illuminate\Support\Str::uuid() }}">
