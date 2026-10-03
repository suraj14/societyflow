{{--
    Period selector shared by the Maintenance and Financial reports.

    The "Showing data for" line is driven by the *current* dropdown values
    (JS), not by what the server last rendered, so the label on screen is
    never out of step with what Filter / Download PDF will send. The
    server validates the same fields through ReportPeriod and never
    substitutes the current date for an invalid selection.

    Expects: $reportType, $month, $year, $filterRoute, $downloadRoute.
--}}
<div class="bg-white rounded-lg shadow-md p-6 mb-6">
    <form id="filterForm" method="GET" action="{{ $filterRoute }}" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-3">Report Type</label>
                <div class="space-y-3">
                    <div class="flex items-center">
                        <input type="radio" id="monthly" name="report_type" value="monthly"
                               {{ $reportType === 'monthly' ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 cursor-pointer" onchange="sfPeriodChanged()">
                        <label for="monthly" class="ml-3 cursor-pointer"><span class="font-medium text-gray-900">Monthly</span></label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="annually" name="report_type" value="annually"
                               {{ $reportType === 'annually' ? 'checked' : '' }}
                               class="h-4 w-4 text-blue-600 cursor-pointer" onchange="sfPeriodChanged()">
                        <label for="annually" class="ml-3 cursor-pointer"><span class="font-medium text-gray-900">Annual</span></label>
                    </div>
                </div>
            </div>

            <div id="monthContainer" class="{{ $reportType === 'monthly' ? '' : 'hidden' }}">
                <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                <select name="month" id="month" onchange="sfPeriodChanged()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $name)
                        <option value="{{ $i + 1 }}" {{ (int) $month === $i + 1 ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                <select name="year" id="year" onchange="sfPeriodChanged()" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                    @php
                        $currentYear = now()->year;
                        $firstYear = min($currentYear - 5, (int) $year);
                    @endphp
                    @for($y = $firstYear; $y <= $currentYear; $y++)
                        <option value="{{ $y }}" {{ (int) $year === $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center gap-3">
            <p class="text-sm text-blue-800 bg-blue-50 border border-blue-200 rounded-lg px-3 py-2">
                <strong>Showing data for:</strong> <span id="sf-period-label">{{ $period->label() }}</span>
                <span id="sf-period-pending" class="hidden text-blue-600">&middot; click Filter to apply</span>
            </p>
            <div class="flex gap-3">
                <button type="submit" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i> Filter
                </button>
                <button type="button" onclick="sfDownloadPdf()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-download mr-2"></i> Download PDF
                </button>
            </div>
        </div>
    </form>

    {{-- The PDF is generated from the same selection the page shows. --}}
    <form id="sf-pdf-form" method="POST" action="{{ $downloadRoute }}" class="hidden">
        @csrf
        <input type="hidden" name="report_type" value="">
        <input type="hidden" name="month" value="">
        <input type="hidden" name="year" value="">
    </form>
</div>

<script>
(function () {
    const MONTHS = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    const rendered = @json($period->label());

    function selection() {
        const type = document.querySelector('input[name="report_type"]:checked').value;
        return { type, month: document.getElementById('month').value, year: document.getElementById('year').value };
    }

    function label(sel) {
        return sel.type === 'monthly' ? MONTHS[parseInt(sel.month, 10) - 1] + ' ' + sel.year : 'Year ' + sel.year;
    }

    window.sfPeriodChanged = function () {
        const sel = selection();
        document.getElementById('monthContainer').classList.toggle('hidden', sel.type !== 'monthly');
        // A hidden month must not be submitted for an annual report.
        document.getElementById('month').disabled = sel.type !== 'monthly';
        const text = label(sel);
        document.getElementById('sf-period-label').textContent = text;
        document.getElementById('sf-period-pending').classList.toggle('hidden', text === rendered);
    };

    window.sfDownloadPdf = function () {
        const sel = selection();
        const form = document.getElementById('sf-pdf-form');
        form.querySelector('[name=report_type]').value = sel.type;
        form.querySelector('[name=month]').value = sel.type === 'monthly' ? sel.month : '';
        form.querySelector('[name=year]').value = sel.year;
        form.submit();
    };

    document.addEventListener('DOMContentLoaded', window.sfPeriodChanged);
})();
</script>
