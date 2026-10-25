@extends('layouts.erp')
@section('title','Workspace Smart Forms')
@section('content')
<section>
    <p class="text-xs font-extrabold uppercase tracking-widest text-amber-800">PRIVATE · WORKSPACE FORM STUDIO</p>
    <h2 class="mt-2 text-2xl font-extrabold">Smart Forms &amp; Responses</h2>
    <p class="mt-2 text-sm text-slate-600">Forms are versioned and restricted to this Partner workspace. Managers and Students can submit a current form and view their own response history. Only the Partner can publish new versions or export submissions.</p>
</section>

@if($actor->role === 'partner')
<section class="rounded-2xl border border-amber-200 bg-white p-5 shadow-sm">
    <h3 class="font-extrabold">Publish a Form Version</h3>
    <p class="mt-1 text-xs text-slate-600">Use an existing Form Code to publish an updated version. Old responses and field definitions are retained unchanged. Allowed field types: text, number, date and select.</p>
    <form method="POST" action="{{ route('erp.forms.save',['firmSlug'=>$firmSlug]) }}" id="form-studio" class="mt-4 space-y-4">
        @csrf
        <div class="grid gap-3 md:grid-cols-2">
            <label class="text-sm font-semibold">Form Code (same code = new version)
                <input class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm" name="form_key" required pattern="[a-z][a-z0-9_]{2,59}" maxlength="60" placeholder="client_visit_report" value="{{ old('form_key') }}">
            </label>
            <label class="text-sm font-semibold">Display Title
                <input class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm" name="title" required maxlength="160" minlength="4" placeholder="Client Visit Report" value="{{ old('title') }}">
            </label>
        </div>
        <label class="block text-sm font-semibold">Instructions (optional)
            <textarea name="description" maxlength="1000" rows="2" class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">{{ old('description') }}</textarea>
        </label>
        <div id="studio-fields" class="space-y-2"></div>
        <input type="hidden" name="fields" id="studio-schema" required>
        <div class="flex flex-wrap gap-2">
            <button type="button" id="studio-add" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-bold">+ Add Field</button>
            <button type="submit" class="rounded-lg bg-[#FCDF02] px-5 py-2 text-sm font-extrabold">Publish Immutable Version</button>
        </div>
        <p id="studio-error" aria-live="polite" class="text-xs font-semibold text-rose-800"></p>
    </form>
</section>
@endif

<section class="space-y-4">
    <h3 class="font-extrabold">Published Forms</h3>
    @forelse($templates as $template)
        @php($fields = json_decode($template->fields_json, true))
        <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div><h4 class="font-extrabold">{{ $template->title }} <span class="text-xs text-slate-400">v{{ $template->version }}</span></h4>
                <p class="mt-1 text-xs text-slate-600">{{ $template->description }}</p>
                <p class="mt-1 text-xs text-slate-500">Code: {{ $template->form_key }}</p></div>
                @if($actor->role === 'partner')
                <a class="rounded-lg border border-slate-300 px-3 py-2 text-xs font-bold" href="{{ route('erp.forms.export',['firmSlug'=>$firmSlug,'id'=>$template->id]) }}">Export v{{ $template->version }} CSV</a>
                @endif
            </div>
            <form method="POST" action="{{ route('erp.forms.submit',['firmSlug'=>$firmSlug,'id'=>$template->id]) }}" class="mt-4 grid gap-3 md:grid-cols-2">
                @csrf
                @foreach($fields as $field)
                    <label class="block text-sm font-semibold">
                        {{ $field['label'] }} @if($field['required']) <span class="text-rose-700" aria-label="Required">*</span> @endif
                        @if($field['type'] === 'select')
                        <select name="values[{{ $field['key'] }}]" @if($field['required']) required @endif class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">
                            <option value="">Choose an option</option>
                            @foreach($field['options'] as $option)<option value="{{ $option }}">{{ $option }}</option>@endforeach
                        </select>
                        @elseif($field['type'] === 'text')
                        <textarea name="values[{{ $field['key'] }}]" maxlength="2000" rows="2" @if($field['required']) required @endif class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm"></textarea>
                        @else
                        <input type="{{ $field['type'] === 'date' ? 'date' : 'number' }}" name="values[{{ $field['key'] }}]" @if($field['required']) required @endif @if($field['type'] === 'number') step="0.01" @endif class="mt-1 w-full rounded-lg border border-slate-300 p-3 text-sm">
                        @endif
                    </label>
                @endforeach
                <div class="md:col-span-2"><button type="submit" class="rounded-lg bg-slate-900 px-5 py-3 text-sm font-extrabold text-white">Submit My Response</button></div>
            </form>
        </article>
    @empty
        <p class="rounded-xl border border-slate-200 p-6 text-sm text-slate-600">No forms published in this workspace.</p>
    @endforelse
</section>

<section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
    <h3 class="font-extrabold">{{ $actor->role === 'partner' ? 'Recent Workspace Responses' : 'My Recent Responses' }}</h3>
    <div class="mt-4 space-y-3">
        @forelse($submissions as $submission)
            <details class="rounded-lg border border-slate-200 p-3">
                <summary class="cursor-pointer text-sm font-bold">{{ $submission->title }} · v{{ $submission->version }} · {{ $submission->member_name }} · #{{ $submission->id }}</summary>
                <p class="mt-2 text-xs text-slate-500">Submitted: {{ $submission->submitted_at }} UTC</p>
                <dl class="mt-2 grid gap-2 md:grid-cols-2">
                    @foreach(json_decode($submission->values_json,true) as $key => $value)
                        <div><dt class="text-xs font-bold text-slate-500">{{ $key }}</dt><dd class="break-words text-sm">{{ $value ?? '—' }}</dd></div>
                    @endforeach
                </dl>
            </details>
        @empty
            <p class="text-sm text-slate-500">No responses recorded yet.</p>
        @endforelse
    </div>
</section>
@if($actor->role === 'partner')
<script>
(() => {
  const studio = document.getElementById('form-studio');
  const list = document.getElementById('studio-fields');
  const error = document.getElementById('studio-error');
  const add = () => {
    if (list.children.length >= 12) { error.textContent = 'Maximum 12 fields per form.'; return; }
    const row = document.createElement('div');
    row.className = 'grid gap-2 rounded-xl border border-slate-200 bg-slate-50 p-3 md:grid-cols-5';
    row.innerHTML = '<input aria-label="Field key" class="rounded-lg border p-2 text-sm" placeholder="Field key: visit_date" required pattern="[a-z][a-z0-9_]{1,39}" maxlength="40">'
      + '<input aria-label="Field label" class="rounded-lg border p-2 text-sm" placeholder="Label" required maxlength="80">'
      + '<select aria-label="Field type" class="rounded-lg border p-2 text-sm"><option value="text">Text</option><option value="number">Number</option><option value="date">Date</option><option value="select">Select</option></select>'
      + '<input aria-label="Select choices" class="rounded-lg border p-2 text-sm" placeholder="Select choices: Yes,No" maxlength="820">'
      + '<label class="flex items-center gap-2 text-xs font-bold"><input type="checkbox"> Required <button type="button" class="ml-auto rounded border px-2 py-1 text-rose-700">Remove</button></label>';
    row.querySelector('button').addEventListener('click', () => { row.remove(); });
    list.appendChild(row);
    error.textContent = '';
  };
  document.getElementById('studio-add').addEventListener('click', add);
  add();
  studio.addEventListener('submit', event => {
    const fields = [...list.children].map(row => {
      const [key,label,type,opts] = row.querySelectorAll('input:not([type=checkbox]),select');
      const required = row.querySelector('input[type=checkbox]').checked;
      return {key:key.value.trim(), label:label.value.trim(), type:type.value,
        required, options: type.value === 'select' ? opts.value.split(',').map(s => s.trim()).filter(Boolean) : []};
    });
    if (!fields.length || fields.some(f => !f.key || !f.label || f.type === 'select' && f.options.length < 2)) {
      event.preventDefault(); error.textContent = 'Add a label and key to each field, plus 2+ choices for Select.'; return;
    }
    document.getElementById('studio-schema').value = JSON.stringify(fields);
  });
})();
</script>
@endif
@endsection
