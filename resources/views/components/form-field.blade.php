@props(['label', 'name', 'type' => 'text', 'required' => false, 'helper' => null, 'placeholder' => null, 'value' => null, 'error' => null])

<div class="mb-4">
    @if($label)
        <label for="{{ $name }}" class="block text-sm font-medium text-gray-700 mb-1">
            {{ $label }}
            @if($required)
                <span class="text-red-500" aria-label="required">*</span>
            @endif
        </label>
    @endif
    
    @if($type === 'textarea')
        <textarea id="{{ $name }}" 
                  name="{{ $name }}"
                  placeholder="{{ $placeholder }}"
                  @class([
                      'w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors',
                      'border-red-500' => $error,
                      'border-gray-300' => !$error,
                  ])
                  {{ $required ? 'required' : '' }}
                  {{ $attributes }}>{{ $value }}</textarea>
    @elseif($type === 'select')
        <select id="{{ $name }}" 
                name="{{ $name }}"
                @class([
                    'w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors',
                    'border-red-500' => $error,
                    'border-gray-300' => !$error,
                ])
                {{ $required ? 'required' : '' }}
                {{ $attributes }}>
            {{ $slot }}
        </select>
    @else
        <input type="{{ $type }}" 
               id="{{ $name }}" 
               name="{{ $name }}"
               placeholder="{{ $placeholder }}"
               value="{{ $value }}"
               @class([
                   'w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors',
                   'border-red-500' => $error,
                   'border-gray-300' => !$error,
               ])
               {{ $required ? 'required' : '' }}
               {{ $attributes }} />
    @endif
    
    @if($helper && !$error)
        <small class="text-gray-500 mt-1 block">{{ $helper }}</small>
    @endif
    
    @if($error)
        <small class="text-red-500 mt-1 block flex items-center">
            <i class="fas fa-exclamation-circle mr-1"></i>
            {{ $error }}
        </small>
    @endif
</div>
