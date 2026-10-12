<footer class="site">
    <div class="wrap">
        <div class="row" style="gap:14px">
            <x-logo :size="26" />
            <span>Vendor events across Connecticut</span>
        </div>
        <div class="row" style="gap:18px">
            <a href="{{ \App\Models\Organizer::directoryUrl('/') }}">Find events</a>
            <a href="{{ \App\Models\Organizer::directoryUrl('/vendors') }}">Vendors</a>
            <a href="{{ \App\Models\Organizer::directoryUrl('/start') }}">Host an event</a>
        </div>
    </div>
</footer>
