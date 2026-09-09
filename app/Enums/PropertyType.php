<?php

namespace App\Enums;

/**
 * What a single property is.
 *
 * Backs flats.property_type, owners.property_type and every other column
 * that names one concrete unit. A society decides which of these it allows -
 * see SocietyPropertyType.
 *
 * Values match the strings already stored in the database, so this enum can
 * be introduced without touching existing rows.
 */
enum PropertyType: string
{
    case Apartment = 'apartment';
    case Villa = 'villa';

    public function label(): string
    {
        return match ($this) {
            self::Apartment => 'Apartment',
            self::Villa => 'Villa',
        };
    }

    /** Plural, for headings and counters. */
    public function pluralLabel(): string
    {
        return match ($this) {
            self::Apartment => 'Apartments',
            self::Villa => 'Villas',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromMixed(mixed $value): ?self
    {
        return $value instanceof self ? $value : self::tryFrom((string) $value);
    }
}
