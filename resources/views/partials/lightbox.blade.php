@push('scripts')
<script>
(function () {
    document.querySelectorAll('[data-lightbox] a').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            var box = document.createElement('div');
            box.className = 'lightbox';
            box.innerHTML = '<img alt=""><button type="button" aria-label="Close">×</button>';
            box.querySelector('img').src = a.href;
            var close = function () { box.remove(); document.removeEventListener('keydown', onKey); };
            var onKey = function (ev) { if (ev.key === 'Escape') close(); };
            box.addEventListener('click', function (ev) { if (ev.target !== box.querySelector('img')) close(); });
            document.addEventListener('keydown', onKey);
            document.body.appendChild(box);
            box.querySelector('button').focus();
        });
    });
})();
</script>
@endpush
