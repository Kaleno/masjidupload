<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Laporan</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Laporan SPP</h1>
        </div>
    </x-slot>

    <div class="ui-page !space-y-4">
        @include('laporan.partials.month-range-form', [
            'range' => $range,
            'action' => route('laporan.spp.index'),
            'downloadRoute' => 'laporan.spp.download',
        ])

        <section x-data="searchList" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="ui-section-title">{{ $range->label() }}</h2>
                <p class="text-xs text-slate-500">Isi sel = tanggal bayar · <span class="font-semibold text-amber-700">Belum</span> = belum dibayar · - = belum wajib</p>
            </div>

            @if ($report['rows'] === [])
                <x-empty>Belum ada santri aktif.</x-empty>
            @else
                <x-list-search />

                <div class="ui-table-wrap">
                    <div class="ui-scroll">
                        <table class="ui-table text-sm">
                            <thead>
                                <tr>
                                    <th class="sticky left-0 z-10 bg-cream-50 text-left">Nama Santri</th>
                                    @foreach ($report['months'] as $month)
                                        <th class="whitespace-nowrap text-center">{{ $month['label'] }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['rows'] as $row)
                                    <tr data-search="{{ str(($row['santri']->user->name ?? '').' '.$row['santri']->nis)->lower() }}">
                                        <td class="sticky left-0 z-10 bg-white font-medium text-teal-950 whitespace-nowrap">{{ $row['santri']->user->name ?? '—' }}</td>
                                        @foreach ($row['cells'] as $cell)
                                            <td @class([
                                                'whitespace-nowrap text-center',
                                                'text-teal-800' => $cell['state'] === \App\Services\SppReport::Paid,
                                                'bg-amber-50 font-semibold text-amber-700' => $cell['state'] === \App\Services\SppReport::Unpaid,
                                                'text-slate-400' => $cell['state'] === \App\Services\SppReport::NotObligated,
                                            ])>{{ $cell['text'] }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
