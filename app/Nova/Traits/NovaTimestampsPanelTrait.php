<?php

namespace App\Nova\Traits;

use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Panel;

trait NovaTimestampsPanelTrait
{
    public function timestampsPanel(bool $displayCreatedAt = true, bool $displayUpdatedAt = true, bool $displayDeletedAt = true, int $limit = 1): Panel
    {
        return (new Panel(trans('nova.panels.timestamps.title'), $this->timestamps($displayCreatedAt, $displayUpdatedAt, $displayDeletedAt)))->limit($limit);
    }

    /**
     * @return array<DateTime>
     */
    protected function timestamps(bool $displayCreatedAt, bool $displayUpdatedAt, bool $displayDeletedAt): array
    {
        $values = [];

        if ($displayCreatedAt) {
            $values[] = $this->timestampsGetCreatedAt();
        }

        if ($displayUpdatedAt) {
            $values[] = $this->timestampsGetUpdatedAt();
        }

        if ($displayDeletedAt) {
            $values[] = $this->timestampsGetDeletedAt();
        }

        return $values;
    }

    protected function timestampsGetCreatedAt(): DateTime
    {
        return DateTime::make(trans('nova.panels.timestamps.created_at'), 'created_at')
            ->onlyOnDetail()
            ->readonly();
    }

    protected function timestampsGetUpdatedAt(): DateTime
    {
        return DateTime::make(trans('nova.panels.timestamps.updated_at'), 'updated_at')
            ->onlyOnDetail()
            ->readonly();
    }

    protected function timestampsGetDeletedAt(): DateTime
    {
        return DateTime::make(trans('nova.panels.timestamps.deleted_at'), 'deleted_at')
            ->onlyOnDetail()
            ->readonly();
    }
}
