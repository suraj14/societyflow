{{--
    Password strength + show/hide for every "new password" pair on the page, with no per-form markup.

    A pair is a password input that has a matching <name>_confirmation input in the same form (register, reset, profile,
    tenant, owner, user and society-admin forms). A login form has no confirmation field, so it is left alone.

    The rules come from App\Support\PasswordPolicy - the same array the server validates with - so the checklist is what
    the server will enforce. The meter is guidance only: the server is the authority.

    Optional: @include('partials.password-strength', ['policyEmail' => $email]) to show the policy of that account's
    society (used on the reset page, where the link holder owns the account).
--}}
@php
    $sfPolicyUser = auth()->user();
    $sfPolicyEmail = $policyEmail ?? null;
    $sfPolicy = \App\Support\PasswordPolicy::describe($sfPolicyEmail
        ? \App\Support\PasswordPolicy::forSociety(\App\Models\User::where('email', $sfPolicyEmail)->value('society_id'))
        : \App\Support\PasswordPolicy::forUser($sfPolicyUser));
@endphp
<script type="application/json" id="sf-password-policy">{!! json_encode($sfPolicy, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
<style>
    .sf-pw-wrap { position: relative; }
    .sf-pw-toggle { position: absolute; top: 0; right: 0; bottom: 0; padding: 0 .75rem; background: transparent; border: 0; cursor: pointer; color: #6b7280; font-size: .75rem; font-weight: 600; }
    .sf-pw-toggle:hover { color: #111827; }
    .sf-pw-meter { display: flex; gap: 4px; margin-top: .5rem; }
    .sf-pw-meter span { flex: 1; height: 5px; border-radius: 3px; background: #e5e7eb; }
    .sf-pw-label { margin-top: .25rem; font-size: .75rem; font-weight: 600; min-height: 1rem; }
    .sf-pw-list { list-style: none; margin: .35rem 0 0; padding: 0; font-size: .75rem; color: #6b7280; }
    .sf-pw-list li::before { content: "\25CB"; display: inline-block; width: 1.1rem; }
    .sf-pw-list li.sf-ok { color: #047857; }
    .sf-pw-list li.sf-ok::before { content: "\2713"; }
    .sf-pw-list li.sf-bad { color: #b91c1c; }
    .sf-pw-match { margin-top: .25rem; font-size: .75rem; font-weight: 600; min-height: 1rem; }
</style>
<script>
(function () {
    var holder = document.getElementById('sf-password-policy');
    if (!holder) { return; }
    var policy;
    try { policy = JSON.parse(holder.textContent); } catch (e) { return; }

    var COLORS = ['#e5e7eb', '#dc2626', '#d97706', '#65a30d', '#059669'];
    var LABELS = ['', 'Too weak', 'Fair', 'Good', 'Strong'];

    function has(re, s) { try { return new RegExp(re, 'u').test(s); } catch (e) { return false; } }
    function variety(pw) {
        return [has('\\p{Ll}', pw), has('\\p{Lu}', pw), has('\\d', pw), has('[^\\p{L}\\p{N}\\s]', pw)].filter(Boolean).length;
    }
    // exactly the server's checks (App\Support\PasswordPolicy)
    function met(pw) {
        return {
            min: pw.length >= policy.min && pw.length <= policy.max,
            uppercase: !policy.uppercase || has('\\p{Lu}', pw),
            number: !policy.number || has('\\d', pw),
            special: !policy.special || has('[^\\p{L}\\p{N}\\s]', pw),
            common: policy.common.indexOf(pw.toLowerCase()) === -1
        };
    }
    function level(pw) {
        if (!pw) { return 0; }
        var m = met(pw);
        if (!(m.min && m.uppercase && m.number && m.special && m.common)) { return 1; }
        var v = variety(pw);
        if (v === 4 && pw.length >= 14) { return 4; }
        if (v >= 3 && pw.length >= 10) { return 3; }
        return 2;
    }

    function enhance(input, confirm) {
        input.dataset.sfPw = '1';
        input.setAttribute('autocomplete', 'new-password');
        confirm.setAttribute('autocomplete', 'new-password');
        input.setAttribute('maxlength', String(policy.max));

        function wrap(el) {
            var box = document.createElement('div');
            box.className = 'sf-pw-wrap';
            el.parentNode.insertBefore(box, el);
            box.appendChild(el);
            el.style.paddingRight = '4rem';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sf-pw-toggle';
            btn.textContent = 'Show';
            btn.setAttribute('aria-pressed', 'false');
            btn.setAttribute('aria-label', 'Show password');
            btn.addEventListener('click', function () {
                var show = el.type === 'password';
                el.type = show ? 'text' : 'password';
                btn.textContent = show ? 'Hide' : 'Show';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
            box.appendChild(btn);
            return box;
        }

        var box = wrap(input);
        wrap(confirm);

        var meter = document.createElement('div');
        meter.className = 'sf-pw-meter';
        meter.setAttribute('aria-hidden', 'true');
        for (var i = 0; i < 4; i++) { meter.appendChild(document.createElement('span')); }
        var label = document.createElement('div');
        label.className = 'sf-pw-label';
        label.setAttribute('role', 'status');
        label.setAttribute('aria-live', 'polite');
        var list = document.createElement('ul');
        list.className = 'sf-pw-list';
        list.setAttribute('aria-label', 'Password requirements');
        var items = {};
        policy.requirements.forEach(function (r) {
            var li = document.createElement('li');
            li.textContent = r.label;
            list.appendChild(li);
            items[r.key] = li;
        });
        var common = document.createElement('li');
        common.textContent = 'Not a commonly used password';
        list.appendChild(common);
        box.parentNode.insertBefore(meter, box.nextSibling);
        meter.parentNode.insertBefore(label, meter.nextSibling);
        label.parentNode.insertBefore(list, label.nextSibling);

        var match = document.createElement('div');
        match.className = 'sf-pw-match';
        match.setAttribute('role', 'status');
        match.setAttribute('aria-live', 'polite');
        confirm.closest('.sf-pw-wrap').parentNode.insertBefore(match, confirm.closest('.sf-pw-wrap').nextSibling);

        function paint() {
            var pw = input.value, lv = level(pw), m = met(pw);
            Array.prototype.forEach.call(meter.children, function (seg, idx) { seg.style.background = idx < lv ? COLORS[lv] : COLORS[0]; });
            label.textContent = LABELS[lv];
            label.style.color = lv ? COLORS[lv] : '';
            Object.keys(items).forEach(function (k) {
                items[k].className = !pw ? '' : (m[k] ? 'sf-ok' : 'sf-bad');
            });
            common.className = !pw ? '' : (m.common ? 'sf-ok' : 'sf-bad');
            var c = confirm.value;
            if (!c) { match.textContent = ''; }
            else if (c === pw) { match.textContent = 'Passwords match'; match.style.color = '#047857'; }
            else { match.textContent = 'Passwords do not match'; match.style.color = '#b91c1c'; }
        }
        input.addEventListener('input', paint);
        confirm.addEventListener('input', paint);
        paint();
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.querySelectorAll('input[type="password"][name]').forEach(function (input) {
            var confirm = form.querySelector('input[name="' + input.name + '_confirmation"]');
            if (confirm && !input.dataset.sfPw) { enhance(input, confirm); }
        });
    });
})();
</script>
