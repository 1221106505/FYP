<?php
include 'config.php';
session_start();

$user_id = $_SESSION['user_id'];

if(isset($_POST['order_btn'])){
   $name = mysqli_real_escape_string($conn, $_POST['name']);
   $number = $_POST['number'];
   $email = mysqli_real_escape_string($conn, $_POST['email']);
   $method = mysqli_real_escape_string($conn, $_POST['method']);
   $address = mysqli_real_escape_string($conn, $_POST['flat'].', '. $_POST['city']);
   $placed_on = date('d-M-Y');

   $cart_total = 0;
   $cart_products[] = '';

   // 1. Fetch cart items to verify total and product list
   $cart_query = mysqli_query($conn, "SELECT * FROM `cart` WHERE user_id = '$user_id'") or die('query failed');
   if(mysqli_num_rows($cart_query) > 0){
      while($cart_item = mysqli_fetch_assoc($cart_query)){
         $cart_products[] = $cart_item['name'].' ('.$cart_item['quantity'].') ';
         $sub_total = ($cart_item['price'] * $cart_item['quantity']);
         $cart_total += $sub_total;
      }
   }

   $total_products = implode(', ',$cart_products);

   if($cart_total == 0){
      die('Your cart is empty');
   } else {
      // 2. Insert the data into the confirm_order table
      mysqli_query($conn, "INSERT INTO `confirm_order`(user_id, name, number, email, method, address, total_products, total_price, placed_on) VALUES('$user_id', '$name', '$number', '$email', '$method', '$address', '$total_products', '$cart_total', '$placed_on')") or die('query failed');
      
      // 3. Clear the cart for this user
      mysqli_query($conn, "DELETE FROM `cart` WHERE user_id = '$user_id'") or die('query failed');
      
      echo "<script>alert('Order placed successfully!'); window.location.href='PaymentHistory.html';</script>";
   }
}
?>