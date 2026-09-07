@if($errors->any() || session('error'))
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded-lg" role="alert">
        <div class="flex items-center mb-2">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <p class="font-medium">Please fix the following errors:</p>
        </div>
        <ul class="list-disc list-inside ml-6">
            @if(session('error'))
                <li>{{ session('error') }}</li>
            @endif
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if(session('success'))
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded-lg" role="alert">
        <div class="flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <p class="font-medium">{{ session('success') }}</p>
        </div>
    </div>
@endif

@if(session('warning'))
    <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 mb-6 rounded-lg" role="alert">
        <div class="flex items-center">
            <i class="fas fa-exclamation-triangle mr-3"></i>
            <p class="font-medium">{{ session('warning') }}</p>
        </div>
    </div>
@endif

@if(session('info'))
    <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-6 rounded-lg" role="alert">
        <div class="flex items-center">
            <i class="fas fa-info-circle mr-3"></i>
            <p class="font-medium">{{ session('info') }}</p>
        </div>
    </div>
@endif