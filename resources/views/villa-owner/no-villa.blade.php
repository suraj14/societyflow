@extends('layouts.app')

@section('title', 'No Villa Assigned')
@section('page-title', 'Welcome')

@section('content')
<div class="p-6">
    <div class="max-w-lg mx-auto text-center py-12">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <i class="fas fa-home text-gray-400 text-4xl"></i>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">No Villa Assigned</h2>
        <p class="text-gray-600 mb-6">
            It looks like you don't have a villa assigned to your account yet. 
            Please contact your society administrator to get your villa linked.
        </p>
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-left">
            <h3 class="font-semibold text-blue-800 mb-2">Need Help?</h3>
            <p class="text-sm text-blue-700">
                Contact your society office or administrator with your villa details to get access to all features.
            </p>
        </div>
    </div>
</div>
@endsection
