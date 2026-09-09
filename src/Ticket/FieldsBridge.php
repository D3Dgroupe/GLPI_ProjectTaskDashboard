<?php

declare(strict_types=1);

namespace GlpiPlugin\Projecttaskdashboard\Ticket;

use ITILCategory;
use PluginFieldsContainer;
use PluginFieldsDropdown;
use PluginFieldsField;
use ProjectTask;
use RuntimeException;

final class FieldsBridge
{
    public const PRIORITY_FIELD_ID = 4;
    public const MODULE_FIELD_ID = 5;

    /**
     * @return array{label:string,itemtype:string,default_value:int,required:bool}
     */
    public function getPriorityDropdownDefinition(): array
    {
        [$priority] = $this->getConfiguration();

        return [
            'label' => trim((string) ($priority['label'] ?? 'Priorité')) ?: 'Priorité',
            'itemtype' => $this->getDropdownItemtype($priority),
            'default_value' => (int) ($priority['default_value'] ?? 0),
            'required' => (bool) ($priority['mandatory'] ?? false),
        ];
    }

    /**
     * Build the extra input consumed by the Fields plugin hooks on ProjectTask::add().
     *
     * @return array<string, int>
     */
    public function buildTaskInput(int $ticketCategoryId, int $priorityId): array
    {
        [$priority, $module, $container] = $this->getConfiguration();

        if ((bool) ($priority['mandatory'] ?? false) && $priorityId <= 0) {
            throw new RuntimeException('La priorité est obligatoire.');
        }
        if ((bool) ($module['mandatory'] ?? false) && $ticketCategoryId <= 0) {
            throw new RuntimeException('Le ticket doit avoir une catégorie pour alimenter le champ Module.');
        }

        $this->validateDropdownValue($priority, $priorityId, 'Priorité');

        return [
            'c_id' => (int) $container['id'],
            $this->getInputKey($priority) => $priorityId,
            $this->getInputKey($module) => $ticketCategoryId,
        ];
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>}
     */
    private function getConfiguration(): array
    {
        if (!class_exists(PluginFieldsField::class)
            || !class_exists(PluginFieldsContainer::class)
            || !class_exists(PluginFieldsDropdown::class)
        ) {
            throw new RuntimeException('Le plugin Fields doit être installé et activé.');
        }

        $priority = $this->loadField(self::PRIORITY_FIELD_ID, 'Priorité');
        $module = $this->loadField(self::MODULE_FIELD_ID, 'Module');

        $priorityContainerId = (int) ($priority['plugin_fields_containers_id'] ?? 0);
        $moduleContainerId = (int) ($module['plugin_fields_containers_id'] ?? 0);
        if ($priorityContainerId <= 0 || $priorityContainerId !== $moduleContainerId) {
            throw new RuntimeException('Les champs Priorité et Module doivent appartenir au même conteneur Fields.');
        }

        $containerObject = new PluginFieldsContainer();
        if (!$containerObject->getFromDB($priorityContainerId)) {
            throw new RuntimeException('Le conteneur Fields des tâches projet est introuvable.');
        }
        $container = $containerObject->fields;

        if ((int) ($container['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('Le conteneur Fields des tâches projet est désactivé.');
        }

        $itemtypes = json_decode((string) ($container['itemtypes'] ?? '[]'), true);
        if (!is_array($itemtypes) || !in_array(ProjectTask::class, $itemtypes, true)) {
            throw new RuntimeException('Le conteneur Fields ne cible pas les tâches de projet.');
        }

        if (($module['type'] ?? '') !== 'dropdown-' . ITILCategory::class) {
            throw new RuntimeException(
                'Le champ Module (Fields #5) doit utiliser la même source ITILCategory que la catégorie du ticket.'
            );
        }

        return [$priority, $module, $container];
    }

    /**
     * @return array<string,mixed>
     */
    private function loadField(int $id, string $label): array
    {
        $field = new PluginFieldsField();
        if (!$field->getFromDB($id)) {
            throw new RuntimeException(sprintf('Le champ Fields %s (#%d) est introuvable.', $label, $id));
        }

        if ((int) ($field->fields['is_active'] ?? 0) !== 1) {
            throw new RuntimeException(sprintf('Le champ Fields %s (#%d) est désactivé.', $label, $id));
        }
        if ((int) ($field->fields['multiple'] ?? 0) === 1) {
            throw new RuntimeException(sprintf('Le champ Fields %s (#%d) ne doit pas être multivalué.', $label, $id));
        }

        $type = (string) ($field->fields['type'] ?? '');
        if ($type !== 'dropdown' && !str_starts_with($type, 'dropdown-')) {
            throw new RuntimeException(sprintf('Le champ Fields %s (#%d) doit être un dropdown.', $label, $id));
        }

        return $field->fields;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function getDropdownItemtype(array $field): string
    {
        $type = (string) $field['type'];
        if ($type === 'dropdown') {
            return PluginFieldsDropdown::getClassname((string) $field['name']);
        }

        $itemtype = substr($type, strlen('dropdown-'));
        if ($itemtype === '' || !class_exists($itemtype)) {
            throw new RuntimeException('La source du dropdown Fields est indisponible.');
        }

        return $itemtype;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function getInputKey(array $field): string
    {
        $type = (string) $field['type'];
        $name = (string) $field['name'];

        if ($type === 'dropdown') {
            return 'plugin_fields_' . $name . 'dropdowns_id';
        }

        $itemtype = $this->getDropdownItemtype($field);
        if (!method_exists($itemtype, 'getForeignKeyField')) {
            throw new RuntimeException('Impossible de déterminer la clé étrangère du dropdown Fields.');
        }

        return $itemtype::getForeignKeyField() . '_' . $name;
    }

    /**
     * @param array<string,mixed> $field
     */
    private function validateDropdownValue(array $field, int $value, string $label): void
    {
        if ($value <= 0) {
            return;
        }

        $itemtype = $this->getDropdownItemtype($field);
        $dropdown = new $itemtype();
        if (!method_exists($dropdown, 'getFromDB') || !$dropdown->getFromDB($value)) {
            throw new RuntimeException(sprintf('La valeur sélectionnée pour %s est invalide.', $label));
        }
    }
}
