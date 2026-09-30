<?php
require_once __DIR__.'/product_customization.php';
/** Persistent customer cart helpers. */
function ensureCustomerCartSchema(PDO $db): void {
    static $done=false; if($done)return; $done=true; ensureProductCustomizationSchema($db);
    $db->exec("CREATE TABLE IF NOT EXISTS customer_cart_items (
      id INT AUTO_INCREMENT PRIMARY KEY,user_id INT NOT NULL,product_id INT NOT NULL,quantity INT NOT NULL DEFAULT 1,
      sugar_level VARCHAR(40) NOT NULL DEFAULT 'N/A',ice_level VARCHAR(40) NOT NULL DEFAULT 'N/A',
      selected_size VARCHAR(80) NULL,toppings TEXT NULL,variant_key CHAR(64) NULL,
      created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      KEY idx_customer_cart_user(user_id),KEY idx_customer_cart_product(product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    foreach(['selected_size'=>"VARCHAR(80) NULL",'toppings'=>"TEXT NULL",'variant_key'=>"CHAR(64) NULL"] as $c=>$def){ if(!pcColumnExists($db,'customer_cart_items',$c))$db->exec("ALTER TABLE customer_cart_items ADD COLUMN `$c` $def"); }
    if(pcIndexExists($db,'customer_cart_items','uq_customer_cart_variant')){ try{$db->exec("ALTER TABLE customer_cart_items DROP INDEX uq_customer_cart_variant");}catch(Throwable $e){} }
    if(!pcIndexExists($db,'customer_cart_items','uq_customer_cart_variant_v2')){ try{$db->exec("ALTER TABLE customer_cart_items ADD UNIQUE KEY uq_customer_cart_variant_v2(user_id,variant_key)");}catch(Throwable $e){} }
}
function saveCustomerCart(PDO $db,int $userId,array $cart):void{
    if($userId<=0)return; ensureCustomerCartSchema($db); $db->beginTransaction();
    try{$db->prepare('DELETE FROM customer_cart_items WHERE user_id=?')->execute([$userId]);
      $ins=$db->prepare('INSERT INTO customer_cart_items(user_id,product_id,quantity,sugar_level,ice_level,selected_size,toppings,variant_key) VALUES(?,?,?,?,?,?,?,?)');
      foreach($cart as $item){$pid=(int)($item['product_id']??0);if($pid<=0)continue;$qty=max(1,(int)($item['quantity']??1));$tops=normalizeToppingNames($item['toppings']??[]);$key=cartVariantKey($pid,$item);
        $ins->execute([$userId,$pid,$qty,(string)($item['sugar_level']??'N/A'),(string)($item['ice_level']??'N/A'),(string)($item['selected_size']??''),json_encode($tops,JSON_UNESCAPED_UNICODE),$key]);}
      $db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function clearSavedCustomerCart(PDO $db,int $userId):void{if($userId<=0)return;ensureCustomerCartSchema($db);$db->prepare('DELETE FROM customer_cart_items WHERE user_id=?')->execute([$userId]);}
function loadCustomerCart(PDO $db,int $userId):array{
    if($userId<=0)return[];ensureCustomerCartSchema($db);
    $s=$db->prepare("SELECT c.*,p.*,p.id product_real_id, EXISTS(SELECT 1 FROM product_ingredients pi JOIN ingredients i ON i.id=pi.ingredient_id WHERE pi.product_id=p.id AND COALESCE(i.stock_quantity,0)<=0) has_unavailable_ingredient FROM customer_cart_items c JOIN products p ON p.id=c.product_id WHERE c.user_id=? ORDER BY c.id");$s->execute([$userId]);$cart=[];
    foreach($s->fetchAll() as $r){$stock=(int)$r['stock'];if($stock<=0||(int)$r['has_unavailable_ingredient']===1)continue;$qty=min(max(1,(int)$r['quantity']),$stock);$choice=['selected_size'=>$r['selected_size']??'','sugar_level'=>$r['sugar_level']??'N/A','ice_level'=>$r['ice_level']??'N/A','toppings'=>json_decode($r['toppings']??'[]',true)?:[]];$resolved=resolveCustomization($r,$choice);
      $cart[]=array_merge(['product_id'=>(int)$r['product_id'],'name'=>$r['name'],'price'=>(float)$resolved['unit_price'],'base_price'=>(float)$r['price'],'quantity'=>$qty],$resolved);}
    saveCustomerCart($db,$userId,$cart);return$cart;
}
?>