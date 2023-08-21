<?php

declare(strict_types=1);

namespace Modules\Media\Rules\Concerns;

use Modules\Media\Rules\GroupRules\MinItemsRule;
use Modules\Media\Rules\GroupRules\MinTotalSizeInKbRule;
use Modules\Media\Rules\ItemRules\AttributeRule;
use Modules\Media\Rules\UploadedMediaRules;

/** @var \Illuminate\Foundation\Http\FormRequest $this */
trait ValidatesMedia
{
    public function validateResolved()
    {
        $this->prepareForValidation();

        if (! $this->passesAuthorization()) {
            $this->failedAuthorization();
        }

        $validator = $this->getValidatorInstance();

        $rules = $validator->getRules();

        $this->rewrittenRules = $this->moveItemRulesToMediaItems($rules);

        $validator->setRules($this->rewrittenRules);

        if ($validator->fails()) {
            $this->failedValidation($validator);
        }

        $this->passedValidation();
    }

    public function getRewrittenRules(): array
    {
        return $this->rewrittenRules ?? [];
    }

    public function moveItemRulesToMediaItems(array $rules): array
    {
        [$itemRules, $remainingRules] = $this->filterItemRules($rules);

        return array_merge($remainingRules, $itemRules);
    }

    public function filterItemRules(array $allAttributeRules): array
    {
        $itemRules = [];
        $remainingRules = [];

        foreach ($allAttributeRules as $attribute => $attributeRules) {
            $remainingRules[$attribute] = [];

            if (is_string($attributeRules)) {
                $remainingRules[$attribute] = $allAttributeRules;

                continue;
            }

            foreach ($attributeRules as $rule) {
                if (is_string($rule)) {
                    $remainingRules[$attribute][] = $rule;
                } elseif ($rule instanceof UploadedMediaRules) {
                    foreach ($rule->groupRules as $groupRule) {
                        $remainingRules[$attribute][] = $groupRule;
                    }
                    foreach ($rule->itemRules as $itemRule) {
                        if ($itemRule instanceof AttributeRule) {
                            $ruleAttribute = $itemRule->attribute;

                            $itemRules["{$attribute}.*.{$ruleAttribute}"][] = $itemRule;
                        } else {
                            $itemRules["{$attribute}.*"][] = $itemRule;
                        }
                    }
                } else {
                    $remainingRules[$attribute][] = $rule;
                }

                $minimumRuleUsed = collect($remainingRules[$attribute])->contains(function ($rule) {
                    if (is_string($rule)) {
                        return false;
                    }

                    if ($rule instanceof MinItemsRule && $rule->getMinItemCount()) {
                        return true;
                    }

                    if ($rule instanceof MinTotalSizeInKbRule && $rule->getMinTotalSizeInKb()) {
                        return true;
                    }

                    return false;
                });

                if ($minimumRuleUsed) {
                    $remainingRules[$attribute][] = 'required';
                }
            }
        }

        return [$itemRules, $remainingRules];
    }

    protected function validateSingleMedia(): UploadedMediaRules
    {
        return (new UploadedMediaRules())->maxItems(1);
    }

    protected function validateMultipleMedia()
    {
        return new UploadedMediaRules();
    }
}
