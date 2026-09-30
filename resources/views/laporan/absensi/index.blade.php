<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="ui-section-title">Laporan</p>
            <h1 class="font-display text-2xl font-semibold text-teal-950">Laporan absensi</h1>
        </div>
    </x-slot>

    @php
        $short = [
            'hadir' => ['H', 'text-teal-800'],
            'izin' => ['I', 'bg-sky-50 text-sky-800 font-semibold'],
            'sakit' => ['S', 'bg-violet-50 text-violet-800 font-semibold'],
            'alfa' => ['A', 'bg-amber-50 text-amber-800 font-semibold'],
            'libur' => ['L', 'bg-rose-100 text-rose-700 font-semibold'],
        ];
    @endphp

    <div class="ui-page !space-y-4" x-data="searchList">
        @include('laporan.partials.month-range-form', [
            'range' => $range,
            'action' => route('laporan.absensi.index'),
            'downloadRoute' => 'laporan.absensi.download',
        ])

        <p class="text-xs text-slate-500">
            H = Hadir · I = Izin · S = Sakit · A = Alfa · <span class="font-semibold text-rose-700">L = Libur</span> · kosong = belum ada absensi tersimpan. Sabtu &amp; Minggu tidak ditampilkan. Di Excel tiap bulan jadi satu sheet.
        </p>

        <x-list-search />

        @foreach ($months as $month)
            <section class="space-y-2">
                <h2 class="ui-section-title">{{ $month['label'] }}</h2>

                @if ($month['rows'] === [])
                    <x-empty>Belum ada santri aktif.</x-empty>
                @else
                    <div class="ui-table-wrap">
                        <div class="ui-scroll">
                            <table class="ui-table text-xs">
                                <thead>
                                    <tr>
                                        <th class="sticky left-0 z-10 bg-cream-50 text-left">Nama Santri</th>
                                        @foreach ($month['days'] as $day)
                                            <th class="px-1.5 text-center {{ $day['holiday'] ? 'text-rose-700' : '' }}" title="{{ $day['holiday'] }}">
                                                <span class="block">{{ $day['day'] }}</span>
                                                <span class="block font-normal text-slate-400">{{ $day['weekday'] }}</span>
                                            </th>
                                        @endforeach
                                        @foreach (['H', 'I', 'S', 'A'] as $label)
                                            <th class="px-1.5 text-center">{{ $label }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($month['rows'] as $row)
                                        <tr data-search="{{ str(($row['santri']->user->name ?? '').' '.$row['santri']->nis)->lower() }}">
                                            <td class="sticky left-0 z-10 bg-white font-medium text-teal-950 whitespace-nowrap">{{ $row['santri']->user->name ?? '—' }}</td>
                                            @foreach ($row['cells'] as $cell)
                                                <td class="px-1.5 text-center {{ $cell ? ($short[$cell][1] ?? '') : '' }}">{{ $cell ? ($short[$cell][0] ?? $cell) : '' }}</td>
                                            @endforeach
                                            @foreach ($row['totals'] as $total)
                                                <td class="px-1.5 text-center font-semibold text-slate-700">{{ $total }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </section>
        @endforeach
    </div>
</x-app-layout>
