{{--
    Money in against money out, and what the account did between them.

    The flow half of the picture the position tiles start — this one moves with
    the time filter, because cash is a flow and a balance is not.
--}}
@php $flow = $this->flow(); @endphp

<div>
    <x-erp.card flush>
        <div class="erp-card-head">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="erp-card-title">Cash flow — {{ $flow['windowLabel'] }}</h2>
                    <p class="erp-card-hint">What actually moved through the account, not what was earned.</p>
                </div>
                <x-erp.range-picker :value="$this->widgetRange" class="shrink-0" />
            </div>
        </div>

        <div class="erp-figures">
            <x-erp.figure
                label="Money in"
                :value="$flow['in']"
                hint="received from customers, net of refunds"
            />

            <x-erp.figure
                label="Money out"
                :value="$flow['out']"
                hint="suppliers, freight and expenses paid"
            />

            <x-erp.figure
                label="Net movement"
                :value="$flow['net']"
                hint="what the account rose or fell by"
                :lead="true"
                :tone="$flow['netTone']"
            />
        </div>
    </x-erp.card>
</div>
