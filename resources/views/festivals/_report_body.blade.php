@php $m = fn ($n) => '₹' . number_format((float) $n, 2); @endphp
        <h1>{{ $festival->name }} — Festival fund settlement</h1>
        <p>{{ $festival->festival_date->format('d M Y') }}@if($festival->venue) · {{ $festival->venue }}@endif · Status: {{ $festival->settlement_status === 'settled' ? 'Settled ' . optional($festival->settled_at)->format('d M Y') : 'Open (not yet settled)' }}</p>
        <p><em>All contributions were voluntary. Figures count only money recorded as received and expenses recorded as paid.</em></p>

        <h2>Fund position</h2>
        <table>
            <tr><td>Opening balance @if($festival->opening_balance_note)<small>({{ $festival->opening_balance_note }})</small>@endif</td><td>{{ $m($summary['opening_balance']) }}</td></tr>
            <tr><td>Voluntary contributions received</td><td>{{ $m($summary['contributions_gross']) }}</td></tr>
            <tr><td>Less refunds and adjustments</td><td>{{ $m($summary['refunds']) }}</td></tr>
            <tr><td>Net contributions</td><td>{{ $m($summary['contributions_net']) }}</td></tr>
            <tr><td>Expenses paid</td><td>{{ $m($summary['expenses_paid']) }}</td></tr>
            <tr><td><strong>Cash in the fund (opening + net contributions − expenses paid)</strong></td><td><strong>{{ $m($summary['cash_available']) }}</strong> {{ $summary['cash_available'] > 0 ? '(surplus)' : ($summary['cash_available'] < 0 ? '(shortfall)' : '') }}</td></tr>
            <tr><td>Expenses approved but not yet paid (unpaid vendor commitments)</td><td>{{ $m($summary['expenses_approved_unpaid']) }}</td></tr>
            <tr><td>After those commitments</td><td>{{ $m($summary['after_commitments']) }}</td></tr>
            <tr><td>Expenses awaiting approval</td><td>{{ $m($summary['expenses_submitted']) }}</td></tr>
            <tr><td>Pledged, not received (not money)</td><td>{{ $m($summary['pledged']) }} ({{ $summary['pledged_count'] }})</td></tr>
        </table>
        @if($festival->settlement_decision)<p><strong>Decision on the balance:</strong> {{ $festival->settlement_decision }}</p>@endif
        <p><small>Budget: estimated {{ $m($summary['estimated_budget']) }}@if($summary['approved_budget'] !== null), approved {{ $m($summary['approved_budget']) }}@endif. A budget is a plan; the cash position above is what was actually recorded.</small></p>

        <h2>Budget against actual</h2>
        <table><tr><th>Item</th><th>Estimate</th><th>Approved, unpaid</th><th>Paid</th></tr>
            @foreach($summary['items'] as $i)<tr><td>{{ $i['category'] }}{{ $i['overspent'] ? ' (over budget)' : '' }}</td><td>{{ $m($i['estimated']) }}</td><td>{{ $m($i['committed']) }}</td><td>{{ $m($i['paid']) }}</td></tr>@endforeach</table>

        <h2>Contributions received</h2>
        <table><tr><th>Receipt</th><th>Date</th><th>Contributor</th><th>Method / ref</th><th>Amount</th><th>Refunded</th></tr>
            @forelse($contributions as $c)<tr><td>{{ $c->receipt_number }}</td><td>{{ optional($c->received_on)->format('d M Y') }}</td><td>{{ $c->is_anonymous ? 'Anonymous' : ($c->contributor_name ?: '—') }}</td><td>{{ $c->payment_method }} {{ $c->payment_reference }}</td><td>{{ $m($c->amount) }}</td><td>{{ $m($c->refunded_amount) }}</td></tr>@empty<tr><td colspan="6">None.</td></tr>@endforelse</table>

        <h2>Expenses</h2>
        <table><tr><th>Date</th><th>Vendor / purpose</th><th>Amount</th><th>Status</th><th>Paid</th></tr>
            @forelse($expenses as $e)<tr><td>{{ $e->expense_date->format('d M Y') }}</td><td>{{ $e->vendor }} — {{ $e->purpose }}</td><td>{{ $m($e->amount) }}</td><td>{{ ucfirst($e->status) }}</td><td>{{ $e->paid_on ? $e->paid_on->format('d M Y') . ' ' . $e->payment_method . ' ' . $e->payment_reference : '—' }}</td></tr>@empty<tr><td colspan="5">None.</td></tr>@endforelse</table>

        <h2>Audit trail</h2>
        <table><tr><th>When</th><th>Action</th><th>By (user id)</th></tr>
            @foreach($audit as $a)<tr><td>{{ $a->created_at }}</td><td>{{ $a->action }}</td><td>{{ $a->user_id ?? 'system' }}</td></tr>@endforeach</table>
