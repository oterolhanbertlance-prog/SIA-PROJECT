<?php
/** Robust category filtering for POS product lists. */
function posCategoryKey(?string $name): string {
    $v = strtolower(trim((string)$name));
    $v = preg_replace('/[^a-z0-9]+/', '', $v);
    $aliases = [
        'milkteas' => 'milktea',
        'milkteadrinks' => 'milktea',
        'smoothies' => 'smoothie',
        'smoothiedrinks' => 'smoothie',
        'fruitteas' => 'fruittea',
        'coffees' => 'coffee',
        'snacks' => 'snack',
        'addons' => 'addon',
        'add ons' => 'addon',
    ];
    return $aliases[$v] ?? $v;
}

function posCategoryLabelForKey(array $categories, string $key): string {
    foreach ($categories as $cat) {
        if (posCategoryKey($cat['name'] ?? '') === $key) return (string)$cat['name'];
    }
    return '';
}

function posResolveCategoryKey(array $categories, $numericId, ?string $requestedKey): string {
    $requestedKey = posCategoryKey($requestedKey ?? '');
    if ($requestedKey !== '') return $requestedKey;
    $id = (int)$numericId;
    if ($id > 0) {
        foreach ($categories as $cat) {
            if ((int)($cat['id'] ?? 0) === $id) return posCategoryKey($cat['name'] ?? '');
        }
    }
    return '';
}

function posProductCategoryKey(array $p): string {
    $name = strtolower(trim((string)($p['name'] ?? '')));
    $declared = posCategoryKey($p['category_name'] ?? '');
    // Legacy databases have several drinks attached to the wrong numeric category.
    // A clear product-name family wins so Milk Tea/Fruit Tea/Smoothies remain browsable.
    if (preg_match('/\\bmilk[ -]?tea\\b/i', $name)) return 'milktea';
    if (preg_match('/\\bfruit[ -]?tea\\b/i', $name)) return 'fruittea';
    if (preg_match('/\\bsmooth(?:ie|ies)\\b/i', $name)) return 'smoothie';
    return $declared;
}
function posFilterProductsByCategory(array $products, string $key): array {
    if ($key === '') return $products;
    return array_values(array_filter($products, static function ($p) use ($key) {
        return posProductCategoryKey($p) === $key;
    }));
}
