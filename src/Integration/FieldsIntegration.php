<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Integration;

use GlpiPlugin\Projecttaskdashboard\Config;
use GlpiPlugin\Projecttaskdashboard\Search\SearchOptionResolver;
use Plugin;
use ProjectTask;

final class FieldsIntegration
{
    public function __construct(private readonly SearchOptionResolver $resolver = new SearchOptionResolver())
    {
    }

    public function isAvailable(): bool
    {
        return Plugin::isPluginActive('fields') && class_exists('PluginFieldsField');
    }

    public function resolveModuleOption(): ?int
    {
        return $this->resolve(Config::FIELDS_MODULE_HINT_ID, ['module']);
    }

    public function resolvePriorityOption(): ?int
    {
        return $this->resolve(Config::FIELDS_PRIORITY_HINT_ID, ['priorité', 'priorite']);
    }

    private function resolve(int $hintId, array $labels): ?int
    {
        if (!$this->isAvailable()) {
            return null;
        }

        try {
            $options = $this->resolver->options(ProjectTask::class);
        } catch (\Throwable) {
            return null;
        }

        foreach ($options as $id => $option) {
            if (!is_array($option)) {
                continue;
            }
            if ((int) ($option['pfields_fields_id'] ?? 0) === $hintId) {
                return (int) $id;
            }
        }

        foreach ($options as $id => $option) {
            if (!is_array($option)) {
                continue;
            }
            $name = $this->normalize((string) ($option['name'] ?? ''));
            foreach ($labels as $label) {
                $needle = $this->normalize($label);
                if ($name === $needle || str_ends_with($name, ' - ' . $needle)) {
                    return (int) $id;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = strtr($value, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c']);
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }
}
