@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">Email Template Mappings</h1>
        <p class="text-gray-600 mt-2">Configure which email templates are sent for specific system events</p>
    </div>

    @if ($errors->any())
        <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
            <ul class="list-disc list-inside text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (session('success'))
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6">
        @foreach ($mappings as $eventValue => $mapping)
            <div class="bg-white rounded-lg shadow-md p-6 border-l-4 {{ $mapping['configured'] ? 'border-green-500' : 'border-gray-300' }}">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">{{ $mapping['label'] }}</h3>
                        <p class="text-sm text-gray-500 mt-1">Event: <code class="bg-gray-100 px-2 py-1 rounded">{{ $eventValue }}</code></p>
                    </div>
                    <span class="inline-block px-3 py-1 rounded-full text-sm font-medium {{ $mapping['configured'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                        {{ $mapping['configured'] ? '✓ Configured' : 'Not Configured' }}
                    </span>
                </div>

                @if ($mapping['template'])
                    <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <p class="text-sm text-gray-700">
                            <strong>Current Template:</strong> {{ $mapping['template']->name }}
                        </p>
                        <p class="text-sm text-gray-600 mt-2">
                            <strong>Subject:</strong> {{ $mapping['template']->subject }}
                        </p>
                    </div>
                @endif

                <div class="flex gap-2">
                    <button 
                        type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"
                        onclick="openTemplateSelector('{{ $eventValue }}', '{{ $mapping['label'] }}')"
                    >
                        {{ $mapping['configured'] ? 'Change Template' : 'Select Template' }}
                    </button>

                    @if ($mapping['configured'])
                        <button 
                            type="button"
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                            onclick="testTemplate('{{ $eventValue }}')"
                        >
                            Send Test Email
                        </button>

                        <button 
                            type="button"
                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition"
                            onclick="removeMapping('{{ $eventValue }}')"
                        >
                            Remove Mapping
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

<!-- Template Selector Modal -->
<div id="templateModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-2xl w-full mx-4">
        <h2 class="text-2xl font-bold mb-4" id="modalTitle">Select Template</h2>

        <div id="templateList" class="space-y-3 max-h-96 overflow-y-auto mb-6">
            <!-- Templates will be loaded here -->
        </div>

        <div class="flex justify-end gap-2">
            <button 
                type="button"
                class="px-4 py-2 bg-gray-300 text-gray-900 rounded-lg hover:bg-gray-400 transition"
                onclick="closeTemplateSelector()"
            >
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Test Email Modal -->
<div id="testEmailModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4">
        <h2 class="text-2xl font-bold mb-4">Send Test Email</h2>

        <form id="testEmailForm" onsubmit="submitTestEmail(event)">
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Recipient Email
                </label>
                <input 
                    type="email"
                    id="testEmailInput"
                    name="recipient_email"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                    value="{{ auth()->user()->email }}"
                    required
                >
            </div>

            <div class="flex justify-end gap-2">
                <button 
                    type="button"
                    class="px-4 py-2 bg-gray-300 text-gray-900 rounded-lg hover:bg-gray-400 transition"
                    onclick="closeTestEmailModal()"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"
                >
                    Send Test Email
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentEvent = null;

function openTemplateSelector(eventValue, eventLabel) {
    currentEvent = eventValue;
    document.getElementById('modalTitle').textContent = `Select Template for: ${eventLabel}`;
    
    // Fetch available templates
    fetch(`/admin/email-template-mappings/templates/${eventValue}`)
        .then(response => response.json())
        .then(data => {
            const templateList = document.getElementById('templateList');
            templateList.innerHTML = '';

            if (data.templates.length === 0) {
                templateList.innerHTML = '<p class="text-gray-500">No templates available</p>';
            } else {
                data.templates.forEach(template => {
                    const div = document.createElement('div');
                    div.className = 'p-3 border border-gray-200 rounded-lg hover:bg-blue-50 cursor-pointer transition';
                    div.innerHTML = `
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <p class="font-medium text-gray-900">${template.name}</p>
                                <p class="text-sm text-gray-600 mt-1">Subject: ${template.subject}</p>
                            </div>
                            <button 
                                type="button"
                                class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700"
                                onclick="selectTemplate(${template.id}, '${template.name}')"
                            >
                                Select
                            </button>
                        </div>
                    `;
                    templateList.appendChild(div);
                });
            }

            document.getElementById('templateModal').classList.remove('hidden');
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Failed to load templates');
        });
}

function selectTemplate(templateId, templateName) {
    if (!currentEvent) return;

    fetch(`/admin/email-template-mappings/${currentEvent}`, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ template_id: templateId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Template mapping updated successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update template mapping');
    });
}

function closeTemplateSelector() {
    document.getElementById('templateModal').classList.add('hidden');
    currentEvent = null;
}

function testTemplate(eventValue) {
    currentEvent = eventValue;
    document.getElementById('testEmailModal').classList.remove('hidden');
}

function closeTestEmailModal() {
    document.getElementById('testEmailModal').classList.add('hidden');
    currentEvent = null;
}

function submitTestEmail(event) {
    event.preventDefault();
    
    if (!currentEvent) return;

    const email = document.getElementById('testEmailInput').value;

    fetch(`/admin/email-template-mappings/${currentEvent}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
        body: JSON.stringify({ recipient_email: email })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeTestEmailModal();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to send test email');
    });
}

function removeMapping(eventValue) {
    if (!confirm('Are you sure you want to remove this template mapping?')) return;

    fetch(`/admin/email-template-mappings/${eventValue}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Template mapping removed successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to remove template mapping');
    });
}
</script>
@endsection
