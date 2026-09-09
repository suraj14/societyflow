<?php

namespace App\Enums;

/**
 * What kinds of property a society contains.
 *
 * This is the single source of truth for the whole application: it decides
 * which property types may be created, which navigation appears, and which
 * dashboard figures are meaningful. Users pick it once, when the society is
 * configured - never again on individual screens.
 */
enum SocietyPropertyType: string
{
    case Apartment = 'apartment';
    case Villa = 'villa';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Apartment => 'Apartment Society',
            self::Villa => 'Villa Township',
            self::Both => 'Mixed (Apartments + Villas)',
        };
    }

    /**
     * The concrete property types this society permits.
     *
     * @return array<int,PropertyType>
     */
    public function allows(): array
    {
        return match ($this) {
            self::Apartment => [PropertyType::Apartment],
            self::Villa => [PropertyType::Villa],
            self::Both => [PropertyType::Apartment, PropertyType::Villa],
        };
    }

    /**
     * Allowed values as plain strings, for validation rules.
     *
     * @return array<int,string>
     */
    public function allowedValues(): array
    {
        return array_map(fn (PropertyType $t) => $t->value, $this->allows());
    }

    public function supports(PropertyType|string|null $type): bool
    {
        if ($type === null) {
            return false;
        }

        $type = PropertyType::tryFromMixed($type);

        return $type !== null && in_array($type, $this->allows(), true);
    }

    public function supportsApartments(): bool
    {
        return $this->supports(PropertyType::Apartment);
    }

    public function supportsVillas(): bool
    {
        return $this->supports(PropertyType::Villa);
    }

    /** True when both kinds coexist and the UI must distinguish them. */
    public function isMixed(): bool
    {
        return $this === self::Both;
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** value => label, for select inputs. @return array<string,string> */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
