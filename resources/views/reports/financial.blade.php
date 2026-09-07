@extends('layouts.app')

@section('title', 'Financial Report')
@section('page-title', 'Financial Report')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Financial Report</h1>
        <p class="text-gray-600 mt-1">Financial Report Description</p>
    </div>

    <!-- Report Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form id="filterForm" method="GET" action="{{ route('reports.financial') }}" class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Report Type Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-3">Report Type</label>
                    <div class="space-y-3">
                        <div class="flex items-center">
                            <input type="radio" id="monthly" name="report_type" value="monthly" 
                                   {{ $reportType === 'monthly' ? 'checked' : '' }} 
                                   class="h-4 w-4 text-blue-600 cursor-pointer"
                                   onchange="toggleMonthDropdown()">
                            <label for="monthly" class="ml-3 cursor-pointer">
                                <span class="font-medium text-gray-900">Monthly</span>
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input type="radio" id="annually" name="report_type" value="annually" 
                                   {{ $reportType === 'annually' ? 'checked' : '' }} 
                                   class="h-4 w-4 text-blue-600 cursor-pointer"
                                   onchange="toggleMonthDropdown()">
                            <label for="annually" class="ml-3 cursor-pointer">
                                <span class="font-medium text-gray-900">Annually</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Month Dropdown (visible only when Monthly selected) -->
                <div id="monthContainer" class="{{ $reportType === 'monthly' ? '' : 'hidden' }}">
                    <label for="month" class="block text-sm font-medium text-gray-700 mb-2">Month</label>
                    <select name="month" id="month" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @php
                            $months = [
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                            ];
                        @endphp
                        @foreach($months as $num => $name)
                            <option value="{{ $num }}" {{ $month == $num ? 'selected' : '' }}>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- Year Dropdown (always visible) -->
                <div>
                    <label for="year" class="block text-sm font-medium text-gray-700 mb-2">Year</label>
                    <select name="year" id="year" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                        @php
                            $currentYear = now()->year;
                            for ($y = $currentYear - 5; $y <= $currentYear; $y++) {
                                echo "<option value=\"$y\" " . ($year == $y ? 'selected' : '') . ">$y</option>";
                            }
                        @endphp
                    </select>
                </div>
            </div>

            <!-- Filter and Download Buttons -->
            <div class="flex justify-between items-center gap-3">
                <button type="submit" name="action" value="filter" class="bg-gray-600 hover:bg-gray-700 text-white px-6 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-filter mr-2"></i>
                    Filter
                </button>
                
                <!-- Download PDF Button -->
                <button type="button" onclick="downloadPDF()" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Download PDF
                </button>
            </div>
        </form>

        <!-- Period Display -->
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong>Showing data for:</strong>
                @if($reportType === 'monthly')
                    @php
                        $monthName = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'][$month] ?? 'Unknown';
                    @endphp
                    {{ $monthName }} {{ $year }}
                @else
                    Year {{ $year }}
                @endif
            </p>
        </div>
    </div>

    <!-- Financial Report Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Apartment</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Maintenance Billed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Maintenance Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Utility Billed</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Utility Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Total Paid</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">Total Dues</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @php
                        $currencySymbol = \App\Models\SystemSetting::get('currency', 'symbol', '₹', auth()->user()->society_id);
                        $totals = [
                            'maintenance_billed' => 0,
                            'maintenance_paid' => 0,
                            'utility_billed' => 0,
                            'utility_paid' => 0,
                            'total_paid' => 0,
                            'total_dues' => 0,
                        ];
                    @endphp
                    @forelse($financialData as $row)
                        @php
                            $totals['maintenance_billed'] += $row['maintenance_billed'];
                            $totals['maintenance_paid'] += $row['maintenance_paid'];
                            $totals['utility_billed'] += $row['utility_billed'];
                            $totals['utility_paid'] += $row['utility_paid'];
                            $totals['total_paid'] += $row['total_paid'];
                            $totals['total_dues'] += $row['total_dues'];
                        @endphp
                        <tr class="hover:bg-gray-50 transition-colors">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                {{ $row['unit'] }} <span class="text-gray-500 text-xs">({{ $row['type'] }})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $currencySymbol }}{{ number_format($row['maintenance_billed'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $currencySymbol }}{{ number_format($row['maintenance_paid'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $currencySymbol }}{{ number_format($row['utility_billed'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                {{ $currencySymbol }}{{ number_format($row['utility_paid'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">
                                {{ $currencySymbol }}{{ number_format($row['total_paid'], 2) }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-red-600">
                                {{ $currencySymbol }}{{ number_format($row['total_dues'], 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                <i class="fas fa-inbox text-3xl mb-2 block opacity-50"></i>
                                No data available for the selected period
                            </td>
                        </tr>
                    @endforelse

                    <!-- Totals Row -->
                    @if(count($financialData) > 0)
                    <tr class="bg-gray-100 font-bold border-t-2 border-gray-300">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Total</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['maintenance_billed'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['maintenance_paid'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['utility_billed'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['utility_paid'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['total_paid'], 2) }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {{ $currencySymbol }}{{ number_format($totals['total_dues'], 2) }}
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleMonthDropdown() {
    const monthContainer = document.getElementById('monthContainer');
    const reportType = document.querySelector('input[name="report_type"]:checked').value;
    
    if (reportType === 'monthly') {
        monthContainer.classList.remove('hidden');
    } else {
        monthContainer.classList.add('hidden');
    }
}

function downloadPDF() {
    const reportType = document.querySelector('input[name="report_type"]:checked').value;
    const month = document.getElementById('month').value;
    const year = document.getElementById('year').value;
    
    // Create a hidden form for PDF download
    const downloadForm = document.createElement('form');
    downloadForm.method = 'POST';
    downloadForm.action = '{{ route("reports.download-financial") }}';
    
    // Add CSRF token
    const csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = '_token';
        tokenInput.value = csrfToken.content;
        downloadForm.appendChild(tokenInput);
    }
    
    // Add form fields
    const reportTypeInput = document.createElement('input');
    reportTypeInput.type = 'hidden';
    reportTypeInput.name = 'report_type';
    reportTypeInput.value = reportType;
    downloadForm.appendChild(reportTypeInput);
    
    const monthInput = document.createElement('input');
    monthInput.type = 'hidden';
    monthInput.name = 'month';
    monthInput.value = month;
    downloadForm.appendChild(monthInput);
    
    const yearInput = document.createElement('input');
    yearInput.type = 'hidden';
    yearInput.name = 'year';
    yearInput.value = year;
    downloadForm.appendChild(yearInput);
    
    document.body.appendChild(downloadForm);
    downloadForm.submit();
    document.body.removeChild(downloadForm);
}
</script>
@endsection
