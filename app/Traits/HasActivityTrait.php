<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\Models\Concerns\CausesActivity;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\Config;
use Spatie\Activitylog\Support\LogOptions;

trait HasActivityTrait
{
    use CausesActivity;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logExcept(['password', 'remember_token'])
            ->dontLogIfAttributesChangedOnly(['updated_at']);
    }

    /**
     * @return MorphMany<Model, $this>
     */
    public function subjects(): MorphMany
    {
        $activityModel = Config::activityModel();
        assert(is_a($activityModel, Model::class, true));

        return $this->morphMany($activityModel, 'subject');
    }
}
