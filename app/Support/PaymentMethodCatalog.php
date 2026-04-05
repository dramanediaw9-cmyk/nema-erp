<?php

namespace App\Support;

class PaymentMethodCatalog
{
    public static function options(): array
    {
        return [
            'cash' => 'Especes',
            'wave' => 'Wave',
            'orange_money' => 'Orange Money',
            'moov_money' => 'Moov Money',
            'mobile_money' => 'Autre mobile money',
            'bank_transfer' => 'Virement bancaire',
            'cheque' => 'Cheque',
            'other' => 'Autre',
        ];
    }

    public static function labels(): array
    {
        return self::options();
    }

    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function label(?string $method): ?string
    {
        if (! $method) {
            return null;
        }

        return self::options()[$method] ?? str($method)->replace('_', ' ')->title()->value();
    }
}
