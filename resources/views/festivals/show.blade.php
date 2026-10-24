@extends('layouts.app')

@section('title', $festival->name)
@section('page-title', $festival->name)

@section('content')
@php
    $field = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm';
    $btn = 'px-3 py-1.5 rounded-lg text-xs font-medium';
    $m = fn ($n) => '₹' . number_format((float) $n, 2);
    $open = $festival->isOpen();
    $badge = ['draft' => 'bg-yellow-100 text-yellow-800', 'submitted' => 'bg-blue-100 text-blue-800', 'approved' => 'bg-indigo-100 text-indigo-800', 'paid' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800', 'cancelled' => 'bg-gray-100 text-gray-600', 'pledged' => 'bg-yellow-100 text-yellow-800', 'received' => 'bg-green-100 text-green-800', 'refunded' => 'bg-gray-100 text-gray-700'];
@endphp
<div class="container mx-auto px-4 py-6 space-y-6">
    {{-- header --}}
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <a href="{{ route('festivals.index') }}" class="text-sm text-purple-700"><i class="fas fa-arrow-left mr-1"></i> All festivals</a>
            <h1 class="text-3xl font-bold text-gray-900 mt-1">{{ $festival->name }}</h1>
            <p class="text-gray-600">{{ $festival->festival_date->format('d M Y') }}@if($festival->end_date && !$festival->end_date->isSameDay($festival->festival_date)) – {{ $festival->end_date->format('d M Y') }}@endif
                @if($festival->venue) · {{ $festival->venue }}@endif @if($festival->expected_attendance) · about {{ number_format($festival->expected_attendance) }} people @endif
                · <span class="font-medium">{{ $festival->settlement_status === 'settled' ? 'Settled' : ucfirst($festival->status) }}</span></p>
            @if($festival->description)<p class="text-gray-700 mt-2 max-w-3xl">{{ $festival->description }}</p>@endif
        </div>
        <div class="flex flex-wrap gap-2">
            @if($canManage && $festival->status === 'draft')
                <form method="POST" action="{{ route('festivals.publish', $festival) }}" class="flex items-center gap-2">@csrf
                    <label class="text-xs text-gray-600 flex items-center gap-1"><input type="checkbox" name="announce" value="1"> Announce through Events</label>
                    <button class="{{ $btn }} bg-green-600 text-white">Publish</button>
                </form>
            @endif
            @if($canManage && !$festival->isSettled())<a href="{{ route('festivals.edit', $festival) }}" class="{{ $btn }} border border-gray-300 text-gray-700">Edit plan</a>@endif
            @if($canMoney)<a href="{{ route('festivals.report', $festival) }}" class="{{ $btn }} border border-gray-300 text-gray-700">Settlement report</a>@endif
            @if($canManage)
                <form method="POST" action="{{ route('festivals.duplicate', $festival) }}" class="flex items-center gap-1">@csrf
                    <input type="date" name="festival_date" required class="px-2 py-1 border border-gray-300 rounded text-xs" title="Date of next year's edition">
                    <button class="{{ $btn }} border border-gray-300 text-gray-700">Plan next edition</button>
                </form>
                @if(!$festival->isSettled() && in_array($festival->status, ['draft', 'published']))
                    <form method="POST" action="{{ route('festivals.cancel', $festival) }}" onsubmit="return confirm('Cancel this festival? Pledges are withdrawn.')">@csrf<button class="{{ $btn }} border border-red-300 text-red-700">Cancel festival</button></form>
                @endif
            @endif
        </div>
    </div>

    @if($errors->any())<div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">{{ $errors->first() }}</div>@endif

    {{-- fund summary --}}
    <div class="bg-purple-50 border border-purple-100 rounded-lg p-3 text-sm text-purple-900">
        Every contribution is <strong>voluntary</strong>. There is no fixed amount, no bill, no late fee, and nothing is added to anyone's maintenance. A goal is only a hope.
    </div>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4"><p class="text-gray-500 text-xs">Voluntary contributions received</p><p class="text-2xl font-bold">{{ $m($summary['contributions_net']) }}</p>
            <p class="text-xs text-gray-500">{{ $summary['contributors'] }} contributor(s)@if($summary['refunds'] > 0) · after {{ $m($summary['refunds']) }} refunded @endif</p></div>
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4"><p class="text-gray-500 text-xs">Voluntary goal</p>
            <p class="text-2xl font-bold">{{ $summary['target'] !== null ? $m($summary['target']) : '—' }}</p>
            @if($summary['target'] !== null)<p class="text-xs text-gray-500">{{ $m($summary['target_remaining']) }} still hoped for (not a due)</p>@endif</div>
        @if($canMoney || $canEnter)
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4"><p class="text-gray-500 text-xs">Expenses paid</p><p class="text-2xl font-bold">{{ $m($summary['expenses_paid']) }}</p>
                <p class="text-xs text-gray-500">Approved, not yet paid: {{ $m($summary['expenses_approved_unpaid']) }}</p></div>
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4"><p class="text-gray-500 text-xs">Cash in the festival fund</p><p class="text-2xl font-bold {{ $summary['cash_available'] < 0 ? 'text-red-700' : '' }}">{{ $m($summary['cash_available']) }}</p>
                <p class="text-xs text-gray-500">Opening + received − paid. After commitments: {{ $m($summary['after_commitments']) }}</p></div>
        @endif
    </div>
    @if($summary['overspent'] && ($canMoney || $canEnter))
        <div class="p-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">Spending plus approved commitments are above the budget of {{ $m($summary['budget_ceiling']) }}.</div>
    @endif

    {{-- resident: pledge --}}
    @if($festival->status === 'published' && $festival->isOpen())
    <div class="bg-white rounded-lg shadow-md p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Contribute, if you wish</h2>
        <p class="text-sm text-gray-600 mb-3">Give what you feel is right, or nothing at all — both are welcome. Your pledge is only an intention; it becomes a contribution when the office records the money received, and you then get a receipt.</p>
        <form method="POST" action="{{ route('festivals.pledge', $festival) }}" class="flex flex-wrap items-end gap-3">@csrf
            <input type="hidden" name="request_token" value="{{ \Illuminate\Support\Str::uuid() }}">
            <div><label class="block text-xs text-gray-600 mb-1">Amount (₹)</label><input type="number" name="amount" min="1" step="1" required class="px-3 py-2 border border-gray-300 rounded-lg text-sm w-40"></div>
            @if($festival->allow_anonymous)<label class="text-sm flex items-center gap-1 pb-2"><input type="checkbox" name="is_anonymous" value="1"> Keep my name off the records others see</label>@endif
            <button class="bg-purple-700 text-white px-4 py-2 rounded-lg text-sm">Pledge</button>
        </form>
    </div>
    @endif

    {{-- my contributions / all contributions --}}
    @if($contributions->isNotEmpty() || $canMoney)
    <div class="bg-white rounded-lg shadow-md p-5">
        <h2 class="font-semibold text-gray-900 mb-3">{{ $canMoney ? 'Contributions (confidential)' : 'My contributions' }}</h2>
        @if($canMoney && $open)
        <form method="POST" action="{{ route('festivals.contributions.record', $festival) }}" class="grid grid-cols-2 lg:grid-cols-8 gap-2 mb-4 p-3 bg-gray-50 rounded-lg items-end">@csrf
            <input type="hidden" name="request_token" value="{{ \Illuminate\Support\Str::uuid() }}">
            <div class="col-span-2"><label class="text-xs text-gray-600">Resident</label>
                <select name="user_id" class="{{ $field }}"><option value="">Not a listed resident</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="text-xs text-gray-600">Name (if not listed)</label><input name="contributor_name" class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Amount ₹</label><input type="number" step="0.01" min="1" name="amount" required class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Method</label><select name="payment_method" class="{{ $field }}">@foreach(\App\Models\FestivalContribution::METHODS as $x)<option value="{{ $x }}">{{ ucfirst(str_replace('_', ' ', $x)) }}</option>@endforeach</select></div>
            <div><label class="text-xs text-gray-600">Reference</label><input name="payment_reference" class="{{ $field }}" placeholder="not needed for cash"></div>
            <div><label class="text-xs text-gray-600">Received on</label><input type="date" name="received_on" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="{{ $field }}"></div>
            <div class="flex items-center gap-2">@if($festival->allow_anonymous)<label class="text-xs flex items-center gap-1"><input type="checkbox" name="is_anonymous" value="1"> Anonymous</label>@endif<button class="bg-green-600 text-white px-3 py-2 rounded-lg text-sm">Record received</button></div>
        </form>
        @endif
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2 pr-4">Contributor</th><th class="pr-4">Amount</th><th class="pr-4">Status</th><th class="pr-4">Receipt</th><th class="pr-4">Method / reference</th><th class="pr-4">Date</th><th></th></tr></thead>
            <tbody class="divide-y">
            @forelse($contributions as $c)
                <tr>
                    <td class="py-2 pr-4">{{ $c->is_anonymous && !$canMoney ? 'Anonymous' : ($c->contributor_name ?: ($c->user->name ?? '—')) }}@if($c->is_anonymous && $canMoney) <span class="text-xs text-gray-500">(anonymous to others)</span>@endif</td>
                    <td class="pr-4">{{ $m($c->amount) }}@if($c->refunded_amount > 0)<div class="text-xs text-gray-500">refunded {{ $m($c->refunded_amount) }}</div>@endif</td>
                    <td class="pr-4"><span class="px-2 py-0.5 rounded-full text-xs {{ $badge[$c->status] ?? '' }}">{{ ucfirst($c->status) }}</span></td>
                    <td class="pr-4">{{ $c->receipt_number ?: '—' }}</td>
                    <td class="pr-4">{{ $c->payment_method ? ucfirst(str_replace('_', ' ', $c->payment_method)) : '—' }} {{ $c->payment_reference }}</td>
                    <td class="pr-4">{{ optional($c->received_on)->format('d M Y') ?: '—' }}</td>
                    <td class="py-2 text-right whitespace-nowrap">
                        @if($c->status === 'pledged' && $open)
                            @if($canMoney)
                            <form method="POST" action="{{ route('festivals.contributions.receive', [$festival, $c->id]) }}" class="inline-flex flex-wrap gap-1 items-center">@csrf
                                <select name="payment_method" class="px-1 py-1 border rounded text-xs">@foreach(\App\Models\FestivalContribution::METHODS as $x)<option value="{{ $x }}">{{ ucfirst(str_replace('_', ' ', $x)) }}</option>@endforeach</select>
                                <input name="payment_reference" placeholder="ref" class="px-1 py-1 border rounded text-xs w-20"><input type="date" name="received_on" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="px-1 py-1 border rounded text-xs">
                                <button class="{{ $btn }} bg-green-600 text-white">Money received</button></form>
                            @endif
                            <form method="POST" action="{{ route('festivals.contributions.cancel', [$festival, $c->id]) }}" class="inline">@csrf<button class="{{ $btn }} border border-gray-300 text-gray-700">Withdraw</button></form>
                        @elseif(in_array($c->status, ['received']) && $canMoney && !$festival->isSettled())
                            <form method="POST" action="{{ route('festivals.contributions.refund', [$festival, $c->id]) }}" class="inline-flex gap-1 items-center">@csrf
                                <input type="number" step="0.01" min="1" name="amount" placeholder="₹" required class="px-1 py-1 border rounded text-xs w-20"><input name="reason" placeholder="reason" required class="px-1 py-1 border rounded text-xs w-28">
                                <button class="{{ $btn }} border border-gray-300 text-gray-700">Refund</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-4 text-gray-500">Nothing yet.</td></tr>
            @endforelse
            </tbody></table></div>
    </div>
    @endif

    {{-- budget --}}
    @if($canMoney || $canEnter || $canManage)
    <div class="bg-white rounded-lg shadow-md p-5">
        <h2 class="font-semibold text-gray-900 mb-3">Budget: estimated against actual</h2>
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2 pr-4">Item</th><th class="pr-4">Vendor</th><th class="pr-4">Estimate</th><th class="pr-4">Quotation</th><th class="pr-4">Limit</th><th class="pr-4">Approved, unpaid</th><th class="pr-4">Paid</th><th></th></tr></thead>
            <tbody class="divide-y">
            @forelse($summary['items'] as $i)
                <tr class="{{ $i['overspent'] ? 'bg-red-50' : '' }}"><td class="py-2 pr-4">{{ $i['category'] }}@if($i['description']) <span class="text-xs text-gray-500">{{ $i['description'] }}</span>@endif @if($i['overspent'])<span class="text-xs text-red-700">over budget</span>@endif</td>
                    <td class="pr-4">{{ $i['vendor'] ?: '—' }}</td><td class="pr-4">{{ $m($i['estimated']) }}</td><td class="pr-4">{{ $i['quotation'] !== null ? $m($i['quotation']) : '—' }}</td>
                    <td class="pr-4">{{ $i['approved_limit'] !== null ? $m($i['approved_limit']) : '—' }}</td><td class="pr-4">{{ $m($i['committed']) }}</td><td class="pr-4">{{ $m($i['paid']) }}</td>
                    <td>@if($canManage && !$festival->isSettled())<form method="POST" action="{{ route('festivals.budget.remove', [$festival, $i['id']]) }}">@csrf @method('DELETE')<button class="text-xs text-red-700">Remove</button></form>@endif</td></tr>
            @empty
                <tr><td colspan="8" class="py-4 text-gray-500">No budget lines yet.</td></tr>
            @endforelse
            </tbody></table></div>
        @if($canManage && !$festival->isSettled())
        <form method="POST" action="{{ route('festivals.budget.add', $festival) }}" class="grid grid-cols-2 lg:grid-cols-7 gap-2 mt-4 p-3 bg-gray-50 rounded-lg items-end">@csrf
            <div><label class="text-xs text-gray-600">Item</label><input name="category" required list="festival-cats" class="{{ $field }}" placeholder="Decoration">
                <datalist id="festival-cats">@foreach(['Decoration', 'Sound / DJ', 'Lighting', 'Prasad / Food', 'Tent', 'Security', 'Cleaning', 'Cultural program', 'Other'] as $c)<option value="{{ $c }}">@endforeach</datalist></div>
            <div><label class="text-xs text-gray-600">Note</label><input name="description" class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Estimate ₹</label><input type="number" step="0.01" min="0" name="estimated_amount" required class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Vendor</label><input name="vendor" class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Quotation ₹</label><input type="number" step="0.01" min="0" name="quotation_amount" class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Approved limit ₹</label><input type="number" step="0.01" min="0" name="approved_limit" class="{{ $field }}"></div>
            <div><button class="bg-purple-700 text-white px-3 py-2 rounded-lg text-sm">Add item</button></div>
        </form>
        @endif
    </div>
    @endif

    {{-- expenses --}}
    @if($canMoney || $canEnter)
    <div class="bg-white rounded-lg shadow-md p-5">
        <h2 class="font-semibold text-gray-900 mb-1">Expenses</h2>
        <p class="text-xs text-gray-500 mb-3">Draft → submitted → approved or rejected → paid. An approved expense is a commitment; the fund is only reduced when it is marked paid.</p>
        @if($open)
        <form method="POST" action="{{ route('festivals.expenses.store', $festival) }}" enctype="multipart/form-data" class="grid grid-cols-2 lg:grid-cols-7 gap-2 mb-4 p-3 bg-gray-50 rounded-lg items-end">@csrf
            <input type="hidden" name="request_token" value="{{ \Illuminate\Support\Str::uuid() }}">
            <div><label class="text-xs text-gray-600">Vendor / payee</label><input name="vendor" required class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Purpose</label><input name="purpose" required class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Amount ₹</label><input type="number" step="0.01" min="1" name="amount" required class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Invoice date</label><input type="date" name="expense_date" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="{{ $field }}"></div>
            <div><label class="text-xs text-gray-600">Budget line</label><select name="budget_item_id" class="{{ $field }}"><option value="">—</option>@foreach($festival->budgetItems as $i)<option value="{{ $i->id }}">{{ $i->category }}</option>@endforeach</select></div>
            <div><label class="text-xs text-gray-600">Invoice / receipt</label><input type="file" name="invoice" accept=".jpg,.jpeg,.png,.pdf" class="text-xs"></div>
            <div class="flex flex-col gap-1"><label class="text-xs flex items-center gap-1"><input type="checkbox" name="submit" value="1" checked> Submit for approval</label><button class="bg-purple-700 text-white px-3 py-2 rounded-lg text-sm">Save expense</button></div>
        </form>
        @endif
        <div class="overflow-x-auto"><table class="min-w-full text-sm">
            <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2 pr-4">Date</th><th class="pr-4">Vendor / purpose</th><th class="pr-4">Amount</th><th class="pr-4">Status</th><th class="pr-4">Trail</th><th></th></tr></thead>
            <tbody class="divide-y">
            @forelse($expenses as $e)
                <tr>
                    <td class="py-2 pr-4">{{ $e->expense_date->format('d M Y') }}</td>
                    <td class="pr-4">{{ $e->vendor }}<div class="text-xs text-gray-500">{{ $e->purpose }}@if($e->budgetItem) · {{ $e->budgetItem->category }}@endif @if($e->invoice_path) · <a class="text-purple-700" href="{{ route('festivals.expenses.invoice', [$festival, $e->id]) }}">invoice</a>@endif</div></td>
                    <td class="pr-4">{{ $m($e->amount) }}</td>
                    <td class="pr-4"><span class="px-2 py-0.5 rounded-full text-xs {{ $badge[$e->status] ?? '' }}">{{ ucfirst($e->status) }}</span>@if($e->rejection_reason)<div class="text-xs text-red-700">{{ $e->rejection_reason }}</div>@endif</td>
                    <td class="pr-4 text-xs text-gray-500">By {{ $e->creator->name ?? '—' }}@if($e->approver) · approved by {{ $e->approver->name }}@endif @if($e->paid_on) · paid {{ $e->paid_on->format('d M Y') }} {{ $e->payment_method }} {{ $e->payment_reference }}@endif</td>
                    <td class="py-2 text-right whitespace-nowrap">
                        @if($open)
                            @if($e->status === 'draft')<form method="POST" action="{{ route('festivals.expenses.action', [$festival, $e->id, 'submit']) }}" class="inline">@csrf<button class="{{ $btn }} bg-blue-600 text-white">Submit</button></form>@endif
                            @if($e->status === 'submitted' && ($canManage || $isCommittee))
                                <form method="POST" action="{{ route('festivals.expenses.action', [$festival, $e->id, 'approve']) }}" class="inline">@csrf<button class="{{ $btn }} bg-green-600 text-white">Approve</button></form>
                                <form method="POST" action="{{ route('festivals.expenses.action', [$festival, $e->id, 'reject']) }}" class="inline-flex gap-1">@csrf<input name="reason" required placeholder="reason" class="px-1 py-1 border rounded text-xs w-24"><button class="{{ $btn }} border border-red-300 text-red-700">Reject</button></form>
                            @endif
                            @if($e->status === 'approved' && $canMoney)
                                <form method="POST" action="{{ route('festivals.expenses.action', [$festival, $e->id, 'pay']) }}" class="inline-flex flex-wrap gap-1 items-center">@csrf
                                    <select name="payment_method" class="px-1 py-1 border rounded text-xs">@foreach(\App\Models\FestivalExpense::METHODS as $x)<option value="{{ $x }}">{{ ucfirst(str_replace('_', ' ', $x)) }}</option>@endforeach</select>
                                    <input name="payment_reference" placeholder="ref" class="px-1 py-1 border rounded text-xs w-20"><input type="date" name="paid_on" required value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="px-1 py-1 border rounded text-xs">
                                    <button class="{{ $btn }} bg-green-600 text-white">Mark paid</button></form>
                            @endif
                            @if(in_array($e->status, ['draft', 'submitted', 'approved']))<form method="POST" action="{{ route('festivals.expenses.action', [$festival, $e->id, 'cancel']) }}" class="inline">@csrf<button class="{{ $btn }} border border-gray-300 text-gray-700">Cancel</button></form>@endif
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-4 text-gray-500">No expenses yet.</td></tr>
            @endforelse
            </tbody></table></div>
    </div>
    @endif

    {{-- people & updates --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-lg shadow-md p-5">
            <h2 class="font-semibold text-gray-900 mb-3">Committee &amp; volunteers</h2>
            <ul class="text-sm divide-y">
                @forelse($festival->members as $mem)
                    <li class="py-2 flex justify-between"><span>{{ $mem->user->name ?? '—' }} <span class="text-xs text-gray-500">{{ ucfirst($mem->role) }}@if($mem->responsibility) · {{ $mem->responsibility }}@endif</span></span>
                        @if($canManage)<form method="POST" action="{{ route('festivals.members.remove', [$festival, $mem->id]) }}">@csrf @method('DELETE')<button class="text-xs text-red-700">Remove</button></form>@endif</li>
                @empty<li class="py-2 text-gray-500">Nobody assigned yet.</li>@endforelse
            </ul>
            @if($canManage && !$festival->isSettled())
            <form method="POST" action="{{ route('festivals.members.add', $festival) }}" class="flex flex-wrap gap-2 mt-3 items-end">@csrf
                <select name="user_id" required class="px-2 py-2 border border-gray-300 rounded-lg text-sm"><option value="">Select resident</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                <select name="role" class="px-2 py-2 border border-gray-300 rounded-lg text-sm"><option value="committee">Committee</option><option value="volunteer">Volunteer</option></select>
                <input name="responsibility" placeholder="Responsible for…" class="px-2 py-2 border border-gray-300 rounded-lg text-sm"><button class="bg-purple-700 text-white px-3 py-2 rounded-lg text-sm">Assign</button>
            </form>
            @endif
        </div>
        <div class="bg-white rounded-lg shadow-md p-5">
            <h2 class="font-semibold text-gray-900 mb-3">Arrangements &amp; updates</h2>
            @if($festival->arrangements)<p class="text-sm text-gray-700 whitespace-pre-line mb-3">{{ $festival->arrangements }}</p>@endif
            <ul class="text-sm divide-y">
                @forelse($updates as $u)<li class="py-2"><div class="font-medium">{{ $u->title }}@if($u->scheduled_for) <span class="text-xs text-gray-500">{{ $u->scheduled_for->format('d M, h:i A') }}</span>@endif</div>@if($u->body)<div class="text-gray-600">{{ $u->body }}</div>@endif</li>
                @empty<li class="py-2 text-gray-500">No updates posted.</li>@endforelse
            </ul>
            @if($canManage && !$festival->isSettled())
            <form method="POST" action="{{ route('festivals.updates.add', $festival) }}" class="grid grid-cols-1 gap-2 mt-3">@csrf
                <input name="title" required placeholder="Update title" class="{{ $field }}"><textarea name="body" rows="2" placeholder="Details" class="{{ $field }}"></textarea>
                <div class="flex gap-2"><input type="datetime-local" name="scheduled_for" class="{{ $field }}"><button class="bg-purple-700 text-white px-3 py-2 rounded-lg text-sm">Post</button></div>
            </form>
            @endif
        </div>
    </div>

    {{-- settlement --}}
    @if($canManage)
    <div class="bg-white rounded-lg shadow-md p-5">
        <h2 class="font-semibold text-gray-900 mb-2">Settlement</h2>
        @if($festival->isSettled())
            <p class="text-sm text-gray-700">Settled on {{ $festival->settled_at->format('d M Y') }}. @if($festival->settlement_decision)Decision on the balance: <em>{{ $festival->settlement_decision }}</em>@endif</p>
        @else
            <p class="text-sm text-gray-600 mb-3">Closing the festival makes its records read-only. Nothing is carried forward or refunded automatically: if money is left (or short), record the society's own decision here.</p>
            <form method="POST" action="{{ route('festivals.settle', $festival) }}" class="flex flex-wrap gap-2 items-end" onsubmit="return confirm('Settle and close this festival?')">@csrf
                <textarea name="decision" rows="2" placeholder="Decision on any surplus or shortfall (required if the balance is not zero)" class="{{ $field }} lg:w-2/3">{{ old('decision') }}</textarea>
                <button class="bg-gray-800 text-white px-4 py-2 rounded-lg text-sm">Settle &amp; close</button>
            </form>
        @endif
    </div>
    @endif
</div>
@endsection
