<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\MailingList;
use App\Models\Segment;
use Illuminate\Database\Eloquent\Builder;

class AudienceService
{
    public function query(int $org, string $type, int $id, bool $eligible = false): Builder
    {
        $q = $eligible ? app(EligibilityService::class)->query($org) : Contact::where('organisation_id', $org);
        if ($type === 'list') {
            MailingList::where('organisation_id', $org)->findOrFail($id);

            return $q->whereHas('lists', fn ($l) => $l->where('mailing_lists.id', $id)->where('organisation_id', $org));
        }
        $s = Segment::where('organisation_id', $org)->findOrFail($id);
        foreach ($s->rules as $rule) {
            $value = $rule['value'];
            switch ($rule['field']) {
                case 'list_id':$q->whereHas('lists', fn ($l) => $l->where('mailing_lists.id', (int) $value)->where('organisation_id', $org));
                    break;
                case 'created_after':$q->whereDate('created_at', '>=', $value);
                    break;
                case 'created_before':$q->whereDate('created_at', '<=', $value);
                    break;
                case 'company_name':case 'source':case 'status':$q->where($rule['field'], $value);
                    break;
                case 'attribute':$key = $rule['key'];
                    $q->where('attributes->'.$key, $value);
                    break;
            }
        }

        return $q;
    }

    public function validateRules(array $rules, int $org): array
    {
        validator(['rules' => $rules], ['rules' => 'required|array|min:1|max:8', 'rules.*.field' => 'required|in:status,source,company_name,list_id,created_after,created_before,attribute', 'rules.*.value' => 'required|string|max:255', 'rules.*.key' => 'required_if:rules.*.field,attribute|nullable|regex:/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/'])->validate();
        foreach ($rules as $r) {
            if ($r['field'] === 'attribute') {
                validator($r, ['key' => 'required|regex:/^[a-zA-Z][a-zA-Z0-9_]{0,39}$/'])->validate();
            }
            if ($r['field'] === 'list_id') {
                MailingList::where('organisation_id', $org)->findOrFail((int) $r['value']);
            }if (in_array($r['field'], ['created_after', 'created_before'])) {
                validator($r, ['value' => 'date_format:Y-m-d'])->validate();
            }
        }

        return array_map(fn ($r) => array_intersect_key($r, array_flip(['field', 'value', 'key'])), $rules);
    }
}
