<?php
declare(strict_types=1);

/**
 * The fixed list of items and places.
 * Every item has one standard unit so prices can be compared fairly.
 * To add an item: add a line here. The slug must never change once people have posted prices.
 */
final class Catalog
{
    public const CATEGORIES = [
        'staples'   => 'Food staples',
        'protein'   => 'Meat, fish & eggs',
        'cooking'   => 'Cooking',
        'fuel'      => 'Fuel & gas',
        'drinks'    => 'Drinks',
        'household' => 'Household',
    ];

    /** slug => [name, unit, category] */
    public const ITEMS = [
        'rice-50kg'         => ['Rice', '50kg bag', 'staples'],
        'rice-paint'        => ['Rice', 'paint bucket', 'staples'],
        'beans-paint'       => ['Beans', 'paint bucket', 'staples'],
        'garri-paint'       => ['Garri', 'paint bucket', 'staples'],
        'yam-tuber'         => ['Yam', 'medium tuber', 'staples'],
        'bread-loaf'        => ['Bread', 'big loaf', 'staples'],
        'indomie-carton'    => ['Indomie noodles', 'carton (40 packs)', 'staples'],
        'spaghetti-500g'    => ['Spaghetti', '500g pack', 'staples'],
        'semovita-5kg'      => ['Semovita', '5kg bag', 'staples'],
        'tomatoes-basket'   => ['Tomatoes', 'big basket', 'staples'],
        'onions-bag'        => ['Onions', '50kg bag', 'staples'],
        'eggs-crate'        => ['Eggs', 'crate of 30', 'protein'],
        'chicken-1kg'       => ['Chicken', '1kg', 'protein'],
        'beef-1kg'          => ['Beef', '1kg', 'protein'],
        'fish-frozen-1kg'   => ['Frozen fish (Titus)', '1kg', 'protein'],
        'veg-oil-5l'        => ['Vegetable oil', '5 litres', 'cooking'],
        'palm-oil-5l'       => ['Palm oil', '5 litres', 'cooking'],
        'sugar-1kg'         => ['Sugar', '1kg', 'cooking'],
        'salt-1kg'          => ['Salt', '1kg', 'cooking'],
        'milk-tin'          => ['Peak milk', 'small tin', 'cooking'],
        'petrol-litre'      => ['Petrol', '1 litre', 'fuel'],
        'diesel-litre'      => ['Diesel', '1 litre', 'fuel'],
        'kerosene-litre'    => ['Kerosene', '1 litre', 'fuel'],
        'gas-12kg'          => ['Cooking gas', '12.5kg refill', 'fuel'],
        'sachet-water-bag'  => ['Sachet water', 'bag of 20', 'drinks'],
        'bottled-water-75cl'=> ['Bottled water', '75cl', 'drinks'],
        'coke-50cl'         => ['Coke', '50cl bottle', 'drinks'],
        'malt-can'          => ['Malt', 'can', 'drinks'],
        'soap-bar'          => ['Bathing soap', 'one bar', 'household'],
        'detergent-900g'    => ['Detergent', '900g pack', 'household'],
        'toilet-roll-12'    => ['Toilet roll', 'pack of 12', 'household'],
    ];

    public const STATES = [
        'abia' => 'Abia', 'adamawa' => 'Adamawa', 'akwa-ibom' => 'Akwa Ibom', 'anambra' => 'Anambra',
        'bauchi' => 'Bauchi', 'bayelsa' => 'Bayelsa', 'benue' => 'Benue', 'borno' => 'Borno',
        'cross-river' => 'Cross River', 'delta' => 'Delta', 'ebonyi' => 'Ebonyi', 'edo' => 'Edo',
        'ekiti' => 'Ekiti', 'enugu' => 'Enugu', 'fct' => 'FCT (Abuja)', 'gombe' => 'Gombe',
        'imo' => 'Imo', 'jigawa' => 'Jigawa', 'kaduna' => 'Kaduna', 'kano' => 'Kano',
        'katsina' => 'Katsina', 'kebbi' => 'Kebbi', 'kogi' => 'Kogi', 'kwara' => 'Kwara',
        'lagos' => 'Lagos', 'nasarawa' => 'Nasarawa', 'niger' => 'Niger', 'ogun' => 'Ogun',
        'ondo' => 'Ondo', 'osun' => 'Osun', 'oyo' => 'Oyo', 'plateau' => 'Plateau',
        'rivers' => 'Rivers', 'sokoto' => 'Sokoto', 'taraba' => 'Taraba', 'yobe' => 'Yobe',
        'zamfara' => 'Zamfara',
    ];

    public static function item(string $slug): ?array
    {
        if (!isset(self::ITEMS[$slug])) return null;
        [$name, $unit, $cat] = self::ITEMS[$slug];
        return ['slug' => $slug, 'name' => $name, 'unit' => $unit, 'category' => $cat, 'label' => "$name ($unit)"];
    }

    public static function state(string $slug): ?string
    {
        return self::STATES[$slug] ?? null;
    }

    /** @return array<string, list<array>> items grouped by category */
    public static function grouped(): array
    {
        $out = [];
        foreach (self::ITEMS as $slug => $_) {
            $i = self::item($slug);
            $out[$i['category']][] = $i;
        }
        return $out;
    }
}
