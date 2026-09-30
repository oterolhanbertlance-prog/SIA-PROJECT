-- POS v23 repair
ALTER TABLE products ADD COLUMN IF NOT EXISTS customization_v23_repaired TINYINT(1) NOT NULL DEFAULT 0;
UPDATE products p
LEFT JOIN categories c ON c.id=p.category_id
SET p.allow_size=1,
    p.size_options=CASE WHEN TRIM(COALESCE(p.size_options,''))='' THEN 'Regular|0\nLarge|20' ELSE p.size_options END,
    p.allow_toppings=1,
    p.topping_options=CASE WHEN TRIM(COALESCE(p.topping_options,''))='' THEN 'Pearls|15\nNata de Coco|15\nCoffee Jelly|15' ELSE p.topping_options END,
    p.customization_v23_repaired=1
WHERE p.customization_v23_repaired=0
AND (
 REPLACE(REPLACE(REPLACE(LOWER(COALESCE(c.name,'')),' ',''),'-',''),'_','') LIKE '%milktea%'
 OR REPLACE(REPLACE(REPLACE(LOWER(COALESCE(c.name,'')),' ',''),'-',''),'_','') LIKE '%smoothie%'
);
