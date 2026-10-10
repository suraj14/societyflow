{{--
    Profile-picture field shared by every edit form that changes a person's
    photo (self-service profile, admin user/owner/tenant edit).

    Expects:
      $field       input name posted to the server (default "avatar")
      $currentUrl  browser URL of the stored picture, or null
      $personName  used for the initials badge when there is no picture
      $label       optional heading

    Nothing selected = the current picture is kept. "Remove" is a separate,
    explicit checkbox (name remove_{field}); choosing a new file wins over it.
    The browser-side size/type check is a courtesy only - the server rules in
    App\Support\ProfileImage::rules() are the real validation.
--}}
@php
    $field       = $field ?? 'avatar';
    $currentUrl  = $currentUrl ?? null;
    $personName  = $personName ?? 'User';
    $label       = $label ?? 'Profile Photo';
    $initials    = collect(preg_split('/\s+/', trim($personName)))->filter()->take(2)
                    ->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('') ?: 'U';
    $maxKb       = \App\Support\ProfileImage::MAX_KB;
@endphp
<div class="mb-6" data-image-field="{{ $field }}" data-max-kb="{{ $maxKb }}">
    <label for="{{ $field }}-input" class="block text-sm font-medium text-gray-700 mb-2">{{ $label }}</label>
    <div class="flex items-center gap-4">
        <div class="w-20 h-20 rounded-full overflow-hidden bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center flex-shrink-0">
            <img id="{{ $field }}-preview" src="{{ $currentUrl }}" alt="Current profile photo"
                 class="w-full h-full object-cover {{ $currentUrl ? '' : 'hidden' }}">
            <span id="{{ $field }}-initials" class="text-white text-2xl font-semibold {{ $currentUrl ? 'hidden' : '' }}">{{ $initials }}</span>
        </div>
        <div class="min-w-0">
            <input type="file" id="{{ $field }}-input" name="{{ $field }}"
                   accept="image/jpeg,image/png,image/gif"
                   class="block w-full text-sm text-gray-700 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
            <p class="text-xs text-gray-500 mt-1">
                JPG, PNG or GIF, up to {{ $maxKb / 1024 }} MB.
                {{ $currentUrl ? 'Leave empty to keep the current photo.' : '' }}
            </p>
            @if($currentUrl)
                <label class="inline-flex items-center mt-2 text-sm text-gray-700">
                    <input type="checkbox" name="remove_{{ $field }}" value="1" id="{{ $field }}-remove"
                           class="mr-2 rounded border-gray-300">
                    Remove current photo
                </label>
            @endif
            <p id="{{ $field }}-client-error" class="text-red-500 text-sm mt-1 hidden" role="alert"></p>
            @error($field)
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
<script>
(function () {
    var root = document.querySelector('[data-image-field="{{ $field }}"]');
    if (!root) { return; }
    var input = root.querySelector('input[type=file]');
    var preview = document.getElementById('{{ $field }}-preview');
    var initials = document.getElementById('{{ $field }}-initials');
    var remove = document.getElementById('{{ $field }}-remove');
    var error = document.getElementById('{{ $field }}-client-error');
    var maxBytes = parseInt(root.getAttribute('data-max-kb'), 10) * 1024;
    var original = preview ? preview.getAttribute('src') : '';

    function showError(message) {
        error.textContent = message;
        error.classList.toggle('hidden', !message);
    }
    function showPreview(src) {
        if (src) { preview.src = src; preview.classList.remove('hidden'); initials.classList.add('hidden'); }
        else { preview.classList.add('hidden'); initials.classList.remove('hidden'); }
    }

    input.addEventListener('change', function () {
        showError('');
        var file = input.files && input.files[0];
        if (!file) { showPreview(original && !(remove && remove.checked) ? original : ''); return; }
        if (['image/jpeg', 'image/png', 'image/gif'].indexOf(file.type) === -1) {
            input.value = '';
            showError('Please choose a JPG, PNG or GIF image.');
            return;
        }
        if (file.size > maxBytes) {
            input.value = '';
            showError('That image is larger than ' + (maxBytes / 1048576) + ' MB. Please choose a smaller one.');
            return;
        }
        if (remove) { remove.checked = false; }
        var reader = new FileReader();
        reader.onload = function (e) { showPreview(e.target.result); };
        reader.readAsDataURL(file);
    });

    if (remove) {
        remove.addEventListener('change', function () {
            if (remove.checked) { input.value = ''; showError(''); showPreview(''); }
            else { showPreview(original); }
        });
    }
})();
</script>
