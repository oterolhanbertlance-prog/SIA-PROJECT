<?php
session_start();
require_once '../config/db.php';require_once '../includes/auth_check.php';require_once '../includes/app_url.php';require_once '../includes/customer_cart.php';require_once '../includes/product_customization.php';
requireAnyRole(['customer','cashier'],'../auth/login.php');
if($_SERVER['REQUEST_METHOD']==='POST'){
 $productId=(int)($_POST['product_id']??0);$quantity=max(1,(int)($_POST['quantity']??1));$db=getDB();ensureProductCustomizationSchema($db);
 $s=$db->prepare('SELECT * FROM products WHERE id=? AND stock>=?');$s->execute([$productId,$quantity]);$product=$s->fetch();
 if($product){$m=$db->prepare("SELECT COUNT(*) FROM product_ingredients pi JOIN ingredients i ON i.id=pi.ingredient_id WHERE pi.product_id=? AND COALESCE(i.stock_quantity,0)<=0");$m->execute([$productId]);if($m->fetchColumn()>0)$product=false;}
 if($product){
   $choice=['selected_size'=>$_POST['selected_size']??'','sugar_level'=>$_POST['sugar_level']??'N/A','ice_level'=>$_POST['ice_level']??'N/A','toppings'=>$_POST['toppings']??[]];$resolved=resolveCustomization($product,$choice);
   if(!isset($_SESSION['cart']))$_SESSION['cart']=[];$key=cartVariantKey($productId,$resolved);$found=false;
   foreach($_SESSION['cart'] as &$item){if(cartVariantKey((int)$item['product_id'],$item)===$key){$item['quantity']+=$quantity;$found=true;break;}}unset($item);
   if(!$found)$_SESSION['cart'][]=array_merge(['product_id'=>$productId,'name'=>$product['name'],'base_price'=>(float)$product['price'],'price'=>(float)$resolved['unit_price'],'quantity'=>$quantity],$resolved);
   if(($_SESSION['role']??'')==='customer'&&!empty($_SESSION['user_id']))saveCustomerCart($db,(int)$_SESSION['user_id'],$_SESSION['cart']);
   $_SESSION['flash_success']='Added <strong>'.htmlspecialchars($product['name']).'</strong> to cart!';
 }else $_SESSION['flash_error']='Product not found, insufficient stock, or an ingredient it needs is unavailable.';
}
header('Location: '.appUrl('customer/index.php'));exit;
?>