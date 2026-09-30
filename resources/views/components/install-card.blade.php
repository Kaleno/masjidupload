<div x-data="installCard" x-show="visible" x-cloak class="ui-card flex items-start gap-4 p-4" data-install-card>
    <img src="{{ asset('icons/icon-192.png') }}" alt="" class="h-12 w-12 shrink-0 rounded-2xl">
    <div class="min-w-0 flex-1">
        <p class="font-semibold text-teal-950">Pasang aplikasi</p>
        <p class="mt-0.5 text-sm text-slate-600" x-show="! isIos">Buka lebih cepat dari layar utama, tampil penuh tanpa bilah browser.</p>
        <p class="mt-0.5 text-sm text-slate-600" x-show="isIos">
            Ketuk tombol <span class="font-semibold">Bagikan</span> (kotak dengan panah ke atas) di Safari, lalu pilih <span class="font-semibold">Tambah ke Layar Utama</span>.
        </p>
        <div class="mt-3 flex flex-wrap gap-2">
            <button type="button" class="btn-primary min-h-10" x-show="! isIos" @click="install()">Pasang</button>
            <button type="button" class="btn-ghost min-h-10" @click="dismiss()">Nanti saja</button>
        </div>
    </div>
</div>
