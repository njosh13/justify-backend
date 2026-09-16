<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Aro\Engine\Modifiers\ModifierOp;
use App\Domain\Aro\Models\AroInterpretation;
use App\Domain\Aro\Models\AroItem;
use App\Domain\Aro\Models\AroModifier;
use App\Domain\Aro\Models\AroVersion;
use Illuminate\Database\Seeder;
use Symfony\Component\Yaml\Yaml;

/**
 * Loads the ARO rule catalogue from database/seeders/aro/<version>/*.yaml.
 *
 * Convention: all money fields are written in WHOLE KES in the YAML and are
 * converted to cents here. Fields converted: item.included_amount, params
 * {per_unit, unit_size, half_unit_threshold, floor, unit_bands[].per_unit},
 * band.{lower,upper,fixed,floor}, and modifier.value when the op is
 * add/floor/cap (multiply and floor_ratio_of_base values stay decimal
 * strings).
 */
final class AroSeeder extends Seeder
{
    public function run(): void
    {
        $dir = database_path('seeders/aro/LN221-2023');

        $meta = Yaml::parseFile("{$dir}/version.yaml");
        $version = AroVersion::updateOrCreate(
            ['code' => $meta['code']],
            [
                'legal_notice' => $meta['legal_notice'],
                'effective_from' => $meta['effective_from'],
                'effective_to' => $meta['effective_to'] ?? null,
                'source_url' => $meta['source_url'] ?? null,
                'source_sha256' => $meta['source_sha256'] ?? null,
                'status' => $meta['status'] ?? 'draft',
            ],
        );

        foreach (glob("{$dir}/schedule*.yaml") ?: [] as $file) {
            $data = Yaml::parseFile($file);

            foreach ($data['items'] ?? [] as $row) {
                $item = AroItem::updateOrCreate(
                    ['aro_version_id' => $version->id, 'code' => $row['code']],
                    [
                        'schedule' => $row['schedule'],
                        'label' => $row['label'],
                        'rule_reference' => $row['rule_reference'],
                        'basis_type' => $row['basis_type'] ?? 'none',
                        'computation' => $row['computation'],
                        'scale_variant' => $row['scale_variant'] ?? 'none',
                        'applies_cost_basis' => $row['applies_cost_basis'] ?? 'non_contentious',
                        'is_instruction_fee' => $row['is_instruction_fee'] ?? false,
                        'unit_label' => $row['unit_label'] ?? null,
                        'units_included' => $row['units_included'] ?? null,
                        'included_amount_cents' => isset($row['included_amount']) ? $row['included_amount'] * 100 : null,
                        'params' => $this->convertParams($row['params'] ?? null),
                        'is_active' => $row['is_active'] ?? true,
                    ],
                );

                $item->bands()->delete();
                foreach ($row['bands'] ?? [] as $i => $band) {
                    $item->bands()->create([
                        'scale' => $band['scale'] ?? null,
                        'lower_cents' => ($band['lower'] ?? 0) * 100,
                        'upper_cents' => isset($band['upper']) ? $band['upper'] * 100 : null,
                        'fixed_cents' => isset($band['fixed']) ? $band['fixed'] * 100 : null,
                        'rate' => isset($band['rate']) ? (string) $band['rate'] : null,
                        'floor_cents' => isset($band['floor']) ? $band['floor'] * 100 : null,
                        'sort' => $band['sort'] ?? $i,
                    ]);
                }
            }

            foreach ($data['modifiers'] ?? [] as $row) {
                $op = ModifierOp::from($row['op']);
                $isAmount = in_array($op, [ModifierOp::Add, ModifierOp::Floor, ModifierOp::Cap], true);

                AroModifier::updateOrCreate(
                    ['aro_version_id' => $version->id, 'code' => $row['code']],
                    [
                        'label' => $row['label'],
                        'rule_reference' => $row['rule_reference'],
                        'op' => $row['op'],
                        'value' => $isAmount ? (string) (((int) $row['value']) * 100) : (string) $row['value'],
                        'applies_to_codes' => $row['applies_to'] ?? null,
                        'condition_key' => $row['condition_key'] ?? null,
                        'sort_order' => $row['sort_order'] ?? 0,
                    ],
                );
            }
        }

        $interpretations = Yaml::parseFile("{$dir}/interpretations.yaml");
        foreach ($interpretations['interpretations'] ?? [] as $row) {
            AroInterpretation::updateOrCreate(
                ['aro_version_id' => $version->id, 'code' => $row['code']],
                [
                    'rule_reference' => $row['rule_reference'],
                    'question' => $row['question'],
                    'decision' => $row['decision'],
                    'rationale' => $row['rationale'] ?? null,
                    'decided_at' => $row['decided_at'] ?? null,
                    'status' => $row['status'] ?? 'proposed',
                ],
            );
        }
    }

    /**
     * @param  array<string,mixed>|null  $params
     * @return array<string,mixed>|null
     */
    private function convertParams(?array $params): ?array
    {
        if ($params === null) {
            return null;
        }

        foreach (['per_unit', 'unit_size', 'half_unit_threshold', 'floor', 'cap'] as $key) {
            if (isset($params[$key])) {
                $params["{$key}_cents"] = $params[$key] * 100;
                unset($params[$key]);
            }
        }

        if (isset($params['unit_bands'])) {
            $params['unit_bands'] = array_map(function (array $band) {
                if (isset($band['per_unit'])) {
                    $band['per_unit_cents'] = $band['per_unit'] * 100;
                    unset($band['per_unit']);
                }

                return $band;
            }, $params['unit_bands']);
        }

        return $params;
    }
}
