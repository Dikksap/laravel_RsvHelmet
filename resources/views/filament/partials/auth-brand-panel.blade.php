{{-- Pane branding untuk halaman auth (login, reset kata sandi). Hanya tampil pada layar lebar. --}}
<aside class="rsv-auth-brand" aria-hidden="true">
    <div class="rsv-auth-brand-inner">
        <div class="rsv-auth-lockup">
            <span class="rsv-auth-mark">RSV</span>
            <span class="rsv-auth-brand-name">RSV Helmet</span>
        </div>

        <div class="rsv-auth-brand-copy">
            <h2 class="rsv-auth-brand-title">Sistem Inventori &amp; Produksi</h2>

            <p class="rsv-auth-brand-description">
                Satu tempat untuk mengelola stok gudang, bill of materials, dan permintaan barang
                antar divisi.
            </p>

            <ul class="rsv-auth-brand-features">
                <li>Stok real-time di setiap gudang</li>
                <li>Bill of materials dan batch produksi</li>
                <li>Permintaan barang dengan alur persetujuan</li>
            </ul>
        </div>

        <p class="rsv-auth-brand-footnote">
            &copy; {{ now()->year }} RSV Helmet &middot; Panel Admin
        </p>
    </div>
</aside>
