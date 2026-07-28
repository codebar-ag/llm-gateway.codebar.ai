<?php

namespace App\Models;

use App\Enums\LocaleEnum;
use App\Traits\HasActivityTrait;
use App\Traits\HasUuidTrait;
use CodebarAg\MicrosoftEntraSSO\Contracts\SSOAuthenticatable;
use CodebarAg\MicrosoftEntraSSO\Exceptions\SSOException;
use CodebarAg\MicrosoftEntraSSO\Traits\HasMicrosoftSSO;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string $email
 */
class User extends Authenticatable implements MustVerifyEmailContract, SSOAuthenticatable
{
    use HasActivityTrait;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasMicrosoftSSO;
    use HasRoles;
    use HasUuidTrait;
    use MustVerifyEmail;
    use Notifiable;
    use SoftDeletes;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'locale' => LocaleEnum::class,
    ];

    /**
     * @param  array<string, mixed>  $microsoftUser
     */
    public static function findOrCreateFromMicrosoft(array $microsoftUser): static
    {
        $mid = Arr::get($microsoftUser, 'id');
        if (! is_string($mid) || $mid === '') {
            throw SSOException::userNotFound('missing-id');
        }

        $existing = static::findByMicrosoftId($mid);

        if ($existing) {
            $existing->updateMicrosoftTokens($microsoftUser);

            return $existing;
        }

        $email = Arr::get($microsoftUser, 'email');
        if (! is_string($email) || $email === '') {
            throw SSOException::userNotFound('missing-email');
        }

        $byEmail = static::where('email', $email)->first();

        if ($byEmail instanceof static) {
            $byEmail->linkMicrosoftAccount($microsoftUser);

            return $byEmail;
        }

        $name = Arr::get($microsoftUser, 'name');
        if (! is_string($name)) {
            $name = '';
        }
        if ($name === '') {
            $name = Str::before($email, '@');
        }

        $user = static::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'locale' => self::defaultLocaleForMicrosoftSso(),
        ]);

        $user->forceFill(['email_verified_at' => Carbon::now()])->save();

        $user->linkMicrosoftAccount($microsoftUser);

        return static::query()->whereKey($user->getKey())->firstOrFail();
    }

    public function getGravatarUrl(int $size = 200): string
    {
        $hash = md5(strtolower(trim($this->email)));

        return "https://www.gravatar.com/avatar/$hash?s=$size&d=mp";
    }

    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    /**
     * @return Attribute<string, never>
     */
    protected function contactEmail(): Attribute
    {
        return Attribute::make(
            get: fn (): string => $this->email,
        );
    }

    /**
     * @return array<int, string>|string
     */
    public function routeNotificationForMail(Notification $notification): array|string
    {
        return $this->contactEmail;
    }

    private static function defaultLocaleForMicrosoftSso(): LocaleEnum
    {
        $locale = str_replace('-', '_', (string) config('app.locale', 'de_CH'));

        return LocaleEnum::tryFrom($locale) ?? LocaleEnum::DE;
    }
}
