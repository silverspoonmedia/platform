<?php

namespace App\Support\Settings;

use App\Support\Settings\Contracts\SettingContributor;
use Illuminate\Validation\Rule;

/**
 * Canonical registry of UI-manageable operational settings.
 *
 * The kernel owns the `platform.*` and `identity.*` definitions below; a product
 * domain registers the definitions it owns from its own provider through
 * {@see register()}, so the kernel never names a product. `silverspoonmedia/platform`
 * ships this class; a host with no product installed runs the same code with an
 * empty contributor list.
 *
 * Contributors are stored as class strings, resolved once per instance: an Octane
 * worker never pins a product service. An empty catalog is a legal state.
 */
final class SettingCatalog
{
    /** @var list<class-string<SettingContributor>> */
    private array $contributors = [];

    /**
     * @var array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>|null
     */
    private ?array $definitions = null;

    /**
     * Declare a contributor class. Later registrations win on a duplicate key.
     *
     * @param  class-string<SettingContributor>  ...$contributors
     */
    public function register(string ...$contributors): void
    {
        foreach ($contributors as $contributor) {
            if (! in_array($contributor, $this->contributors, true)) {
                $this->contributors[] = $contributor;
                $this->definitions = null;
            }
        }
    }

    /**
     * @return list<class-string<SettingContributor>>
     */
    public function contributors(): array
    {
        return $this->contributors;
    }

    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public function definitions(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $definitions = $this->kernelDefaults();

        foreach ($this->contributors as $contributor) {
            foreach (app($contributor)->definitions() as $key => $definition) {
                $definitions[$key] = $definition;
            }
        }

        return $this->definitions = $definitions;
    }

    /**
     * @return list<string>
     */
    public function groups(): array
    {
        return array_values(array_unique(array_column($this->definitions(), 'group')));
    }

    /**
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    public function forGroup(string $group): array
    {
        return array_filter(
            $this->definitions(),
            fn (array $def): bool => $def['group'] === $group,
        );
    }

    /**
     * @return array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}|null
     */
    public function get(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    /**
     * Config path → registry key (first definition wins on duplicate paths).
     *
     * @return array<string, string>
     */
    public function configPathIndex(): array
    {
        $index = [];

        foreach ($this->definitions() as $registryKey => $definition) {
            $index[$definition['config']] ??= $registryKey;
        }

        return $index;
    }

    /**
     * Definitions the kernel owns. A product definition belongs in a contributor.
     *
     * @return array<string, array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}>
     */
    private function kernelDefaults(): array
    {
        return [
            // Platform — JWT
            'platform.jwt.ttl' => $this->def('platform', SettingType::Int, 'jwt.ttl'),
            'platform.jwt.refresh_ttl' => $this->def('platform', SettingType::Int, 'jwt.refresh_ttl'),
            'platform.jwt.remember_ttl' => $this->def('platform', SettingType::Int, 'jwt.remember_ttl'),
            'platform.jwt.refresh_skew' => $this->def('platform', SettingType::Int, 'jwt.refresh_skew'),
            'platform.jwt.blacklist_grace_period' => $this->def('platform', SettingType::Int, 'jwt.blacklist_grace_period'),

            // Platform — auth / 2FA
            'platform.auth.default_role' => $this->def('platform', SettingType::String, 'auth.default_role'),
            'platform.auth.verification_expire' => $this->def('platform', SettingType::Int, 'auth.verification.expire'),
            'platform.two_factor.window' => $this->def('platform', SettingType::Int, 'auth.two_factor.window'),
            'platform.two_factor.issuer' => $this->def('platform', SettingType::String, 'auth.two_factor.issuer'),

            // Platform — hCaptcha
            'platform.hcaptcha.enabled' => $this->def('platform', SettingType::Bool, 'hcaptcha.enabled'),

            // Platform — notifications
            'platform.notifications.delivery' => $this->def('platform', SettingType::String, 'notifications.delivery', ['polling', 'realtime']),
            'platform.notifications.polling_interval' => $this->def('platform', SettingType::Int, 'notifications.polling_interval'),

            // Platform — error log
            'platform.errorlog.enabled' => $this->def('platform', SettingType::Bool, 'errorlog.enabled'),
            'platform.errorlog.clean_after_days' => $this->def('platform', SettingType::Int, 'errorlog.clean_after_days'),
            'platform.errorlog.max_message_length' => $this->def('platform', SettingType::Int, 'errorlog.max_message_length'),
            'platform.errorlog.max_trace_length' => $this->def('platform', SettingType::Int, 'errorlog.max_trace_length'),
            'platform.errorlog.max_context_json_length' => $this->def('platform', SettingType::Int, 'errorlog.max_context_json_length'),

            // Platform — activity log
            'platform.activitylog.enabled' => $this->def('platform', SettingType::Bool, 'activitylog.enabled'),
            'platform.activitylog.buffer_enabled' => $this->def('platform', SettingType::Bool, 'activitylog.buffer.enabled'),

            // Platform — localization
            'platform.localization.locale_cookie' => $this->def('platform', SettingType::String, 'localization.cookie_name'),

            // Identity — Discord OAuth
            'identity.discord.connections_snapshot_max_age_minutes' => $this->def(
                'identity',
                SettingType::Int,
                'services.discord.connections_snapshot_max_age_minutes',
            ),
            'platform.localization.locale_cookie_minutes' => $this->def('platform', SettingType::Int, 'localization.cookie_minutes'),

            // Platform — mail identity (transport secrets stay env)
            'platform.mail.from_address' => $this->def('platform', SettingType::String, 'mail.from.address'),
            'platform.mail.from_name' => $this->def('platform', SettingType::String, 'mail.from.name'),

            // Platform — payment checkout
            'platform.payment.channel_fee_bearer' => $this->def(
                'platform',
                SettingType::String,
                'payment.channel_fee_bearer',
                null,
                [Rule::in(['buyer', 'merchant'])],
            ),
            'platform.payment.reporting_timezone' => $this->def('platform', SettingType::String, 'payment.reporting_timezone'),
            'platform.payment.prefer_channel_checkout' => $this->def('platform', SettingType::Bool, 'payment.checkout.prefer_channel_checkout'),
            'platform.payment.default_buyer_phone' => $this->def('platform', SettingType::String, 'payment.default_buyer_phone'),

            // Platform — service
            'platform.service.grace_days' => $this->def('platform', SettingType::Int, 'service.grace_days'),
            'platform.service.settlement_currency' => $this->def('platform', SettingType::String, 'service.settlement_currency'),
            'platform.service.purchase_max_quantity' => $this->def('platform', SettingType::Int, 'service.purchase.max_quantity'),

            // Platform — inference fallbacks
            'platform.inference.default_provider_slug' => $this->def('platform', SettingType::String, 'inference.default_provider_slug'),
            'platform.inference.default_input_per_million_usd' => $this->def('platform', SettingType::Float, 'inference.default_rates.input_per_million_usd'),
            'platform.inference.default_output_per_million_usd' => $this->def('platform', SettingType::Float, 'inference.default_rates.output_per_million_usd'),
            'platform.inference.unknown_input_per_million_usd' => $this->def('platform', SettingType::Float, 'inference.unknown_model_rates.input_per_million_usd'),
            'platform.inference.unknown_output_per_million_usd' => $this->def('platform', SettingType::Float, 'inference.unknown_model_rates.output_per_million_usd'),

            // Platform — Frankfurter FX
            'platform.frankfurter.cache_ttl' => $this->def('platform', SettingType::Int, 'services.frankfurter.cache_ttl'),
            'platform.frankfurter.timeout' => $this->def('platform', SettingType::Int, 'services.frankfurter.timeout'),
        ];
    }

    /**
     * @param  list<string>|null  $options
     * @param  list<mixed>|null  $rules  extra validation rules appended after `sometimes`
     * @return array{group: string, type: SettingType, config: string, options?: list<string>, rules?: list<mixed>}
     */
    private function def(
        string $group,
        SettingType $type,
        string $config,
        ?array $options = null,
        ?array $rules = null,
    ): array {
        $definition = [
            'group' => $group,
            'type' => $type,
            'config' => $config,
        ];

        if ($options !== null && $options !== []) {
            $definition['options'] = array_values($options);
        }

        if ($rules !== null && $rules !== []) {
            $definition['rules'] = array_values($rules);
        }

        return $definition;
    }
}
