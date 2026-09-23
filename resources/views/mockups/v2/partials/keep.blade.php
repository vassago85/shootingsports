@if (request('device') === 'mobile')
    <input type="hidden" name="device" value="mobile">
@endif
@if (in_array(request('theme'), ['light', 'dark'], true))
    <input type="hidden" name="theme" value="{{ request('theme') }}">
@endif
