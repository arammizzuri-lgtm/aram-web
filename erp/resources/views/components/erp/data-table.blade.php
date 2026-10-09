{{--
    The figures behind a chart, as a table.

    Exact where a chart is approximate, readable without colour, and the place
    to copy a number from. Every chart has one, behind its Table switch.

    <x-erp.data-table
        :columns="[['label' => 'Customer'], ['label' => 'Profit', 'numeric' => true]]"
        :rows="[['Kurdish Bride', '$5,250.12'], …]"
        :footer="['Total', '$17,792.52']"
        empty="Nothing earned in this window."
    />

    The first column names the row and reads in primary ink; figures are
    end-aligned and tabular so their decimals line up.
--}}
@props(['columns', 'rows', 'footer' => null, 'empty' => 'Nothing in this window.'])

<div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead>
            <tr class="border-b" style="border-color: var(--erp-border)">
                @foreach ($columns as $column)
                    <th scope="col" @class([
                        'erp-label px-3 py-2.5 whitespace-nowrap first:ps-5 last:pe-5',
                        'text-end' => $column['numeric'] ?? false,
                        'text-start' => ! ($column['numeric'] ?? false),
                    ])>{{ $column['label'] }}</th>
                @endforeach
            </tr>
        </thead>

        <tbody>
            @forelse ($rows as $row)
                <tr class="border-b" style="border-color: var(--erp-border)">
                    @foreach (array_values($row) as $index => $cell)
                        <td @class([
                                'px-3 py-2 whitespace-nowrap first:ps-5 last:pe-5',
                                'text-end erp-numeric' => $columns[$index]['numeric'] ?? false,
                                'font-medium' => $index === 0,
                            ])
                            style="color: {{ $index === 0 ? 'var(--erp-text-primary)' : 'var(--erp-text-secondary)' }}">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columns) }}">
                        <x-erp.empty>{{ $empty }}</x-erp.empty>
                    </td>
                </tr>
            @endforelse
        </tbody>

        @if ($footer && count($rows))
            <tfoot>
                <tr>
                    @foreach (array_values($footer) as $index => $cell)
                        <td @class([
                                'px-3 py-2.5 whitespace-nowrap font-semibold first:ps-5 last:pe-5',
                                'text-end erp-numeric' => $columns[$index]['numeric'] ?? false,
                            ])
                            style="color: var(--erp-text-primary)">{{ $cell }}</td>
                    @endforeach
                </tr>
            </tfoot>
        @endif
    </table>
</div>
