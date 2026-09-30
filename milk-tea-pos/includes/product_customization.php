<?php
/** Product-level drink customization helpers. */
function pcColumnExists(PDO $db, string $table, string $column): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?");
    $q->execute([$table,$column]); return (bool)$q->fetchColumn();
}
function pcIndexExists(PDO $db, string $table, string $index): bool {
    $q=$db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND INDEX_NAME=?");
    $q->execute([$table,$index]); return (bool)$q->fetchColumn();
}
function ensureProductCustomizationSchema(PDO $db): void {
    static $done=false; if($done) return; $done=true;
    $fresh=!pcColumnExists($db,'products','allow_size');
    $cols=[
      'allow_size'=>"TINYINT(1) NOT NULL DEFAULT 0",
      'allow_sugar'=>"TINYINT(1) NOT NULL DEFAULT 0",
      'allow_ice'=>"TINYINT(1) NOT NULL DEFAULT 0",
      'allow_toppings'=>"TINYINT(1) NOT NULL DEFAULT 0",
      'size_options'=>"TEXT NULL",
      'topping_options'=>"TEXT NULL",
    ];
    foreach($cols as $c=>$def){ if(!pcColumnExists($db,'products',$c)) $db->exec("ALTER TABLE products ADD COLUMN `$c` $def"); }
    if($fresh){
      // Sensible defaults for existing drink categories. Inventory Staff can turn any option off per product.
      $db->exec("UPDATE products p LEFT JOIN categories c ON c.id=p.category_id SET
        p.allow_size = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie|coffee|drink|beverage' THEN 1 ELSE 0 END,
        p.allow_sugar = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie' THEN 1 ELSE 0 END,
        p.allow_ice = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie|coffee|drink|beverage' THEN 1 ELSE 0 END,
        p.allow_toppings = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie' THEN 1 ELSE 0 END,
        p.size_options = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie|coffee|drink|beverage' THEN 'Regular|0\nLarge|20' ELSE NULL END,
        p.topping_options = CASE WHEN LOWER(COALESCE(c.name,'')) REGEXP 'milk tea|fruit tea|smoothie' THEN 'Pearls|15\nNata de Coco|15\nCoffee Jelly|15' ELSE NULL END");
    }
    // v23 repair: earlier builds could leave existing Milk Tea/Smoothie rows with
    // customization columns present but no Size/Topping configuration. Repair those
    // legacy rows once. Future edits remain fully product-specific.
    if(!pcColumnExists($db,'products','customization_v23_repaired')){
      $db->exec("ALTER TABLE products ADD COLUMN `customization_v23_repaired` TINYINT(1) NOT NULL DEFAULT 0");
    }
    $db->exec("UPDATE products p
      LEFT JOIN categories c ON c.id=p.category_id
      SET
        p.allow_size=1,
        p.size_options=CASE WHEN TRIM(COALESCE(p.size_options,''))='' THEN 'Regular|0\nLarge|20' ELSE p.size_options END,
        p.allow_toppings=1,
        p.topping_options=CASE WHEN TRIM(COALESCE(p.topping_options,''))='' THEN 'Pearls|15\nNata de Coco|15\nCoffee Jelly|15' ELSE p.topping_options END,
        p.customization_v23_repaired=1
      WHERE p.customization_v23_repaired=0
        AND (
          REPLACE(REPLACE(REPLACE(LOWER(COALESCE(c.name,'')),' ',''),'-',''),'_','') LIKE '%milktea%'
          OR REPLACE(REPLACE(REPLACE(LOWER(COALESCE(c.name,'')),' ',''),'-',''),'_','') LIKE '%smoothie%'
        )");

    // Transaction detail fields preserve the exact choices that were ordered.
    foreach([
      'selected_size'=>"VARCHAR(80) NULL",
      'toppings'=>"TEXT NULL",
      'customization_price'=>"DECIMAL(10,2) NOT NULL DEFAULT 0.00"
    ] as $c=>$def){ if(!pcColumnExists($db,'transaction_details',$c)) $db->exec("ALTER TABLE transaction_details ADD COLUMN `$c` $def"); }
}
function parsePricedOptions(?string $text): array {
    $out=[]; foreach(preg_split('/\\r?\\n/', trim((string)$text)) as $line){
      $line=trim($line); if($line==='') continue; $parts=array_map('trim',explode('|',$line,2));
      $name=$parts[0]??''; if($name==='') continue; $price=isset($parts[1])?max(0,(float)$parts[1]):0;
      $out[]=['name'=>$name,'price'=>$price];
    } return $out;
}
function customizationConfig(array $p): array {
    return [
      'size'=>(bool)($p['allow_size']??0),'sugar'=>(bool)($p['allow_sugar']??0),
      'ice'=>(bool)($p['allow_ice']??0),'toppings'=>(bool)($p['allow_toppings']??0),
      'sizes'=>parsePricedOptions($p['size_options']??''),'topping_options'=>parsePricedOptions($p['topping_options']??'')
    ];
}
function findPricedOption(array $options, string $name): ?array {
    foreach($options as $o){ if(strcasecmp(trim($o['name']),trim($name))===0) return $o; } return null;
}
function normalizeToppingNames($raw): array {
    if(is_string($raw)){ $j=json_decode($raw,true); $raw=is_array($j)?$j:array_filter(array_map('trim',explode(',',$raw))); }
    if(!is_array($raw)) return []; $out=[]; foreach($raw as $v){ $v=trim((string)$v); if($v!==''&&!in_array($v,$out,true)) $out[]=$v; } sort($out,SORT_NATURAL|SORT_FLAG_CASE); return $out;
}
function resolveCustomization(array $product, array $choice): array {
    $cfg=customizationConfig($product); $base=(float)$product['price']; $extra=0.0;
    $size=''; if($cfg['size'] && $cfg['sizes']){ $requested=trim((string)($choice['selected_size']??'')); $opt=findPricedOption($cfg['sizes'],$requested) ?: $cfg['sizes'][0]; $size=$opt['name']; $extra+=(float)$opt['price']; }
    $sugar=$cfg['sugar']?trim((string)($choice['sugar_level']??'50%')):'N/A';
    if(!in_array($sugar,['0%','25%','50%','75%','100%'],true)) $sugar=$cfg['sugar']?'50%':'N/A';
    $ice=$cfg['ice']?trim((string)($choice['ice_level']??'Regular Ice')):'N/A';
    if(!in_array($ice,['No Ice','Less Ice','Regular Ice','Extra Ice'],true)) $ice=$cfg['ice']?'Regular Ice':'N/A';
    $tops=[]; if($cfg['toppings']){ foreach(normalizeToppingNames($choice['toppings']??[]) as $name){ $o=findPricedOption($cfg['topping_options'],$name); if($o){$tops[]=$o['name'];$extra+=(float)$o['price'];} } }
    return ['selected_size'=>$size,'sugar_level'=>$sugar,'ice_level'=>$ice,'toppings'=>$tops,'customization_price'=>$extra,'unit_price'=>$base+$extra];
}
function cartVariantKey(int $productId,array $c): string { return hash('sha256',$productId.'|'.($c['selected_size']??'').'|'.($c['sugar_level']??'').'|'.($c['ice_level']??'').'|'.json_encode(normalizeToppingNames($c['toppings']??[]))); }
function customizationSummary(array $row): array {
    $parts=[]; if(!empty($row['selected_size']))$parts[]='Size: '.$row['selected_size'];
    if(!empty($row['sugar_level'])&&$row['sugar_level']!=='N/A')$parts[]='Sugar: '.$row['sugar_level'];
    if(!empty($row['ice_level'])&&$row['ice_level']!=='N/A')$parts[]='Ice: '.$row['ice_level'];
    $tops=normalizeToppingNames($row['toppings']??[]); if($tops)$parts[]='Toppings: '.implode(', ',$tops); return $parts;
}
?>