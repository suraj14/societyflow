@extends('layouts.app')

@section('title', 'Maintenance Report')
@section('page-title', 'Maintenance Report')

@section('content')
<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">Maintenance Report</h1>
        <p class="text-gray-600 mt-1">Maintenance Report Description</p>
    </div>

    <!-- Report Form -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <form id="filterForm" method="POST" action="{{ route('reports.download-maintenance') }}" class="space-y-6">
            @csrf

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
                            <span class="text-gray-600 text-sm">Detailed monthly breakdown</span>
                        </label>
                    </div>
                    <div class="flex items-center">
                        <input type="radio" id="annually" name="report_type" value="annually" 
                               {{ $reportType === 'annually' ? 'checked' : '' }} 
                               class="h-4 w-4 text-blue-600 cursor-pointer"
                               onchange="toggleMonthDropdown()">
                        <label for="annually" class="ml-3 cursor-pointer">
                            <span class="font-medium text-gray-900">Annually</span>
                            <span class="text-gray-600 text-sm">Year-over-year analysis</span>
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

            <!-- Download Button -->
            <div class="flex justify-end">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition-colors inline-flex items-center">
                    <i class="fas fa-download mr-2"></i>
                    Download PDF
                </button>
            </div>
        </form>

        <!-- Period Display -->
        <div class="mt-4 p-3 bg-blue-50 border border-blue-200 rounded-lg">
            <p class="text-sm text-blue-800">
                <strong>Report Period:</strong>
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
</script>
@endsection
