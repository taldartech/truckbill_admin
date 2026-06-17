<?php

namespace App\Enums;

enum Product: string
{
    case OwnFleet = 'ownfleet';
    case Parchun = 'parchun';
    case TruckBill = 'truckbill';

    public function label(): string
    {
        return match ($this) {
            self::OwnFleet => 'OwnFleet',
            self::Parchun => 'Parchun',
            self::TruckBill => 'TruckBill',
        };
    }

    public function loginUrl(): string
    {
        return match ($this) {
            self::OwnFleet => config('crm.products.ownfleet.login_url'),
            self::Parchun => config('crm.products.parchun.login_url'),
            self::TruckBill => config('crm.products.truckbill.login_url'),
        };
    }

    public function apiBaseUrl(): string
    {
        return match ($this) {
            self::OwnFleet => config('crm.products.ownfleet.api_url'),
            self::Parchun => config('crm.products.parchun.api_url'),
            self::TruckBill => config('crm.products.truckbill.api_url'),
        };
    }

    public function signingSecret(): string
    {
        return match ($this) {
            self::OwnFleet => (string) config('crm.products.ownfleet.signing_secret'),
            self::Parchun => (string) config('crm.products.parchun.signing_secret'),
            self::TruckBill => (string) config('crm.products.truckbill.signing_secret'),
        };
    }
}
