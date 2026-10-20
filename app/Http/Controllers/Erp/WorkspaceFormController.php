<?php

namespace App\Http\Controllers\Erp;

use App\Http\Controllers\Controller;
use App\Support\TenantOps;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use JsonException;

final class WorkspaceFormController extends Controller
{
    private function template(object $actor, int $id): object
    {
        $row = DB::table('workspace_form_templates')
            ->where('company_id', $actor->company_id)
            ->where('workspace_id', $actor->workspace_id)
            ->where('id', $id)->first();
        abort_unless($row, 404);
        return $row;
    }

    /** Only typed, JSON-serializable form definitions. No executable code or arbitrary SQL. */
    private function fields(string $raw): array
    {
        try {
            $fields = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['fields' => 'Enter a valid JSON list of field definitions.']);
        }
        if (! is_array($fields) || ! array_is_list($fields)
            || count($fields) < 1 || count($fields) > 12) {
            throw ValidationException::withMessages(['fields' => 'A form needs 1 to 12 ordered fields.']);
        }

        $seen = [];
        $clean = [];
        foreach ($fields as $field) {
            if (! is_array($field) || array_diff(array_keys($field), ['key', 'label', 'type', 'required', 'options'])) {
                throw ValidationException::withMessages(['fields' => 'Unrecognized field definition.']);
            }
            $check = Validator::make($field, [
                'key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{1,39}$/'],
                'label' => ['required', 'string', 'min:2', 'max:80'],
                'type' => ['required', Rule::in(['text', 'number', 'date', 'select'])],
                'required' => ['required', 'boolean'],
                'options' => ['sometimes', 'array', 'max:10'],
                'options.*' => ['required', 'string', 'min:1', 'max:80', 'distinct'],
            ]);
            if ($check->fails()) {
                throw ValidationException::withMessages(['fields' => $check->errors()->first()]);
            }
            if (isset($seen[$field['key']])) {
                throw ValidationException::withMessages(['fields' => 'Field keys must be unique.']);
            }
            $seen[$field['key']] = true;
            $opts = $field['options'] ?? [];
            if ($field['type'] === 'select' && (! array_is_list($opts) || count($opts) < 2)) {
                throw ValidationException::withMessages(['fields' => 'Select fields need 2 to 10 listed choices.']);
            }
            if ($field['type'] !== 'select' && $opts !== []) {
                throw ValidationException::withMessages(['fields' => 'Only select fields can contain choices.']);
            }
            $clean[] = [
                'key' => $field['key'],
                'label' => trim($field['label']),
                'type' => $field['type'],
                'required' => (bool) $field['required'],
                'options' => $opts,
            ];
        }
        return $clean;
    }

    public function index(Request $request, string $firmSlug)
    {
        [$company, $workspace, $actor] = TenantOps::context($request);

        $versions = DB::table('workspace_form_templates')
            ->where('company_id', $company->id)->where('workspace_id', $workspace->id)
            ->select('form_key')->selectRaw('MAX(version) as latest_version')
            ->groupBy('form_key');

        $templates = DB::table('workspace_form_templates as f')
            ->joinSub($versions, 'v', function ($join) {
                $join->on('f.form_key', '=', 'v.form_key')
                    ->on('f.version', '=', 'v.latest_version');
            })
            ->where('f.company_id', $company->id)->where('f.workspace_id', $workspace->id)
            ->orderBy('f.title')->limit(100)->select('f.*')->get();

        $submissions = DB::table('workspace_form_submissions as s')
            ->join('workspace_form_templates as t', function ($join) {
                $join->on('t.id', '=', 's.template_id')
                    ->on('t.company_id', '=', 's.company_id')
                    ->on('t.workspace_id', '=', 's.workspace_id');
            })
            ->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 's.membership_id')
                    ->on('m.company_id', '=', 's.company_id')
                    ->on('m.workspace_id', '=', 's.workspace_id');
            })
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('s.company_id', $company->id)->where('s.workspace_id', $workspace->id)
            ->when($actor->role !== 'partner', fn ($q) => $q->where('s.membership_id', $actor->id))
            ->orderByDesc('s.id')->limit(100)
            ->select('s.*', 't.title', 't.version', 'u.name as member_name')->get();

        return response()->view('erp.workspace-forms', compact(
            'company', 'workspace', 'actor', 'firmSlug', 'templates', 'submissions'
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function save(Request $request, string $firmSlug)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $data = $request->validate([
            'form_key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]{2,59}$/'],
            'title' => ['required', 'string', 'min:4', 'max:160'],
            'description' => ['nullable', 'string', 'max:1000'],
            'fields' => ['required', 'string', 'max:10000'],
        ]);
        $fields = $this->fields($data['fields']);
        DB::transaction(function () use ($actor, $data, $fields) {
            DB::table('workspaces')->where('company_id', $actor->company_id)
                ->where('id', $actor->workspace_id)->lockForUpdate()->firstOrFail();
            $version = (int) DB::table('workspace_form_templates')
                ->where('company_id', $actor->company_id)->where('workspace_id', $actor->workspace_id)
                ->where('form_key', $data['form_key'])->max('version') + 1;
            $id = DB::table('workspace_form_templates')->insertGetId([
                'company_id' => $actor->company_id,
                'workspace_id' => $actor->workspace_id,
                'created_by' => $actor->id,
                'form_key' => $data['form_key'],
                'version' => $version,
                'title' => trim($data['title']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'fields_json' => json_encode($fields, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'forms.template.version_created', 'workspace_form_template', $id, [
                'form_key' => $data['form_key'], 'version' => $version, 'field_count' => count($fields),
            ]);
        }, 3);
        return redirect()->route('erp.forms.index', ['firmSlug' => $firmSlug])
            ->with('success', 'Versioned form published to this Partner workspace.');
    }

    public function submit(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        $template = $this->template($actor, $id);
        $fields = json_decode($template->fields_json, true, 64, JSON_THROW_ON_ERROR);
        $values = $request->input('values', []);
        if (! is_array($values) || array_is_list($values) && $values !== []) {
            throw ValidationException::withMessages(['values' => 'Submit keyed form values.']);
        }
        $allowed = array_column($fields, 'key');
        if (array_diff(array_keys($values), $allowed)) {
            throw ValidationException::withMessages(['values' => 'Form contains an unknown field.']);
        }
        $rules = [];
        foreach ($fields as $f) {
            $rule = [$f['required'] ? 'required' : 'nullable'];
            if ($f['type'] === 'text') $rule = array_merge($rule, ['string', 'max:2000']);
            if ($f['type'] === 'number') $rule = array_merge($rule, ['numeric', 'between:-1000000000,1000000000', 'decimal:0,2']);
            if ($f['type'] === 'date') $rule[] = 'date_format:Y-m-d';
            if ($f['type'] === 'select') $rule[] = Rule::in($f['options']);
            $rules['values.'.$f['key']] = $rule;
        }
        $validated = Validator::make(['values' => $values], $rules)->validate();
        $stored = [];
        foreach ($allowed as $key) {
            $value = $validated['values'][$key] ?? null;
            $stored[$key] = $value === '' ? null : $value;
        }

        DB::transaction(function () use ($actor, $template, $stored) {
            DB::table('workspaces')->where('id', $actor->workspace_id)
                ->where('company_id', $actor->company_id)->lockForUpdate()->firstOrFail();
            // Old versions remain immutable for historic evidence, not newly submittable.
            $latest = DB::table('workspace_form_templates')
                ->where('company_id', $actor->company_id)
                ->where('workspace_id', $actor->workspace_id)
                ->where('form_key', $template->form_key)->max('version');
            if ((int) $latest !== (int) $template->version) {
                throw ValidationException::withMessages(['values' => 'This form has a newer version. Reload and resubmit.']);
            }
            $submissionId = DB::table('workspace_form_submissions')->insertGetId([
                'company_id' => $actor->company_id,
                'workspace_id' => $actor->workspace_id,
                'membership_id' => $actor->id,
                'template_id' => $template->id,
                'values_json' => json_encode($stored, JSON_THROW_ON_ERROR),
                'submitted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            TenantOps::audit($actor, 'forms.submitted', 'workspace_form_submission', $submissionId, [
                'template_id' => $template->id, 'version' => $template->version,
            ]);
            if ($actor->role !== 'partner') {
                $partners = DB::table('memberships')->where('company_id', $actor->company_id)
                    ->where('workspace_id', $actor->workspace_id)->where('role', 'partner')
                    ->where('active', true)->whereNull('platform_suspended_at')
                    ->limit(20)->pluck('id');
                foreach ($partners as $partnerId) {
                    TenantOps::notify($actor, (int) $partnerId, 'Workspace form submitted',
                        'A team member submitted form '.$template->title.'.');
                }
            }
        }, 3);
        return redirect()->route('erp.forms.index', ['firmSlug' => $firmSlug])
            ->with('success', 'Your submission was saved privately with the original form version.');
    }

    public function export(Request $request, string $firmSlug, int $id)
    {
        [, , $actor] = TenantOps::context($request);
        TenantOps::requireRole($actor, ['partner']);
        $template = $this->template($actor, $id);
        $fields = json_decode($template->fields_json, true, 64, JSON_THROW_ON_ERROR);
        TenantOps::audit($actor, 'forms.exported', 'workspace_form_template', $id, [
            'version' => $template->version,
        ]);
        $safe = static function ($value): string {
            $v = (string) ($value ?? '');
            return preg_match('/^[\s\x00-\x1F]*[=+\-@]/u', $v) ? "'".$v : $v;
        };
        $q = DB::table('workspace_form_submissions as s')
            ->join('memberships as m', function ($join) {
                $join->on('m.id', '=', 's.membership_id')
                    ->on('m.company_id', '=', 's.company_id')
                    ->on('m.workspace_id', '=', 's.workspace_id');
            })
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('s.company_id', $actor->company_id)
            ->where('s.workspace_id', $actor->workspace_id)
            ->where('s.template_id', $template->id)
            ->select('s.id', 's.submitted_at', 's.values_json', 'u.name')
            ->orderBy('s.id');
        return response()->streamDownload(function () use ($q, $fields, $safe) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_merge(['Submission ID', 'Submitted at (UTC)', 'Member'],
                array_column($fields, 'label')));
            foreach ($q->cursor() as $r) {
                $values = json_decode($r->values_json, true, 64, JSON_THROW_ON_ERROR);
                fputcsv($out, array_merge([$r->id, $r->submitted_at, $safe($r->name)],
                    array_map(fn ($f) => $safe($values[$f['key']] ?? null), $fields)));
            }
            fclose($out);
        }, 'workspace-form-'.$template->form_key.'-v'.$template->version.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
