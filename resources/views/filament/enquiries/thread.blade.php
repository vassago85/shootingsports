@if ($record instanceof \App\Models\Enquiry && $record->replies->isNotEmpty())
    <div class="fi-section" style="margin-top:1.5rem">
        <h3 style="font-weight:600;margin-bottom:.75rem">Thread</h3>
        @foreach ($record->replies as $reply)
            <p style="margin:0 0 .25rem;font-size:.85rem;opacity:.7">
                {{ $reply->from_enquirer ? $record->name : 'Shooting Sports' }} · {{ $reply->created_at?->timezone(config('app.timezone'))->format('j M Y, H:i') }}
            </p>
            <p style="white-space:pre-wrap;margin:0 0 1rem">{{ $reply->body }}</p>
        @endforeach
    </div>
@endif
