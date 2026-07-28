<?php

namespace App\Nova\Traits;

use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Panel;

trait NovaIdentificationPanelTrait
{
    /**
     * @param  list<Field>  $fields
     */
    public function identificationPanel(bool $displayId = true, bool $displayUuid = false, int $limit = 1, array $fields = []): Panel
    {
        return (new Panel(trans('nova.panels.identification.title'), $this->identification($displayId, $displayUuid, $fields)))->limit($limit);
    }

    /**
     * @param  list<Field>  $fields
     * @return list<Field|Text>
     */
    protected function identification(bool $displayId, bool $displayUuid, array $fields): array
    {
        $values = [];

        if ($displayId) {
            $values[] = $this->getId();
        }

        if ($displayUuid) {
            $values[] = $this->getUuid();
        }

        foreach ($fields as $field) {
            $values[] = $field;
        }

        return $values;
    }

    protected function getId(): Text
    {
        return Text::make(trans('nova.panels.identification.id'), 'id')
            ->onlyOnDetail()
            ->readonly();
    }

    protected function getUuid(): Text
    {
        return Text::make(trans('nova.panels.identification.uuid'), 'uuid')
            ->onlyOnDetail()
            ->readonly();
    }
}
