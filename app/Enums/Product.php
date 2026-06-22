<?php

namespace App\Enums;

enum Product: string
{
    case OwnFleet = 'ownfleet';
    case Parchun = 'parchun';
    case Broker = 'broker';

    public function label(): string
    {
        return match ($this) {
            self::OwnFleet => 'OwnFleet',
            self::Parchun => 'Parchun',
            self::Broker => 'Broker',
        };
    }

    public function loginUrl(): string
    {
        return match ($this) {
            self::OwnFleet => config('crm.products.ownfleet.login_url'),
            self::Parchun => config('crm.products.parchun.login_url'),
            self::Broker => config('crm.products.broker.login_url'),
        };
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::OwnFleet => config('crm.products.ownfleet.api_url'),
            self::Parchun => config('crm.products.parchun.api_url'),
            self::Broker => config('crm.products.broker.api_url'),
        };
    }

    public function signingSecret(): string
    {
        return match ($this) {
            self::OwnFleet => (string) config('crm.products.ownfleet.signing_secret'),
            self::Parchun => (string) config('crm.products.parchun.signing_secret'),
            self::Broker => (string) config('crm.products.broker.signing_secret'),
        };
    }
}
