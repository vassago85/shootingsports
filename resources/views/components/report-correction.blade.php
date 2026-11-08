@props([
    'type',
    'subjectId',
])

<details class="report-correction" @if ($errors->any()) open @endif>
    <summary>Report a correction</summary>
    <form method="post" action="{{ route('corrections.store') }}" style="margin-top:14px;display:grid;gap:10px;max-width:520px">
        @csrf
        <input type="hidden" name="type" value="{{ $type }}">
        <input type="hidden" name="subject_id" value="{{ $subjectId }}">
        <input type="text" name="company_website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

        @if (session('status'))
            <p role="status">{{ session('status') }}</p>
        @endif

        <label for="correction-reason">What is wrong</label>
        <select id="correction-reason" name="reason" required>
            <option value="">Choose one</option>
            <option value="wrong_date" @selected(old('reason') === 'wrong_date')>Wrong date</option>
            <option value="wrong_province" @selected(old('reason') === 'wrong_province')>Wrong province</option>
            <option value="wrong_venue" @selected(old('reason') === 'wrong_venue')>Wrong venue</option>
            <option value="other" @selected(old('reason') === 'other')>Other</option>
        </select>
        @error('reason')<span>{{ $message }}</span>@enderror

        <label for="correction-details">Details</label>
        <textarea id="correction-details" name="details" rows="4" required minlength="10" maxlength="2000">{{ old('details') }}</textarea>
        @error('details')<span>{{ $message }}</span>@enderror

        <label for="correction-name">Your name</label>
        <input id="correction-name" name="submitter_name" type="text" value="{{ old('submitter_name') }}" required maxlength="120" autocomplete="name">
        @error('submitter_name')<span>{{ $message }}</span>@enderror

        <label for="correction-email">Email</label>
        <input id="correction-email" name="submitter_email" type="email" value="{{ old('submitter_email') }}" required maxlength="255" autocomplete="email">
        @error('submitter_email')<span>{{ $message }}</span>@enderror

        @if ($turnstileSiteKey = \App\Support\Turnstile::siteKey())
            <div class="cf-turnstile" data-sitekey="{{ $turnstileSiteKey }}"></div>
            <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
        @endif
        @error('cf-turnstile-response')<span>{{ $message }}</span>@enderror

        <button type="submit" class="btn">Send correction</button>
    </form>
</details>
