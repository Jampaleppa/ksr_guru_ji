<?php
// ============================================================
// bakery.php - Dynamic Bakery page (All 210 products)
// ============================================================
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
// Start session for cart
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
// Get cart count
$cartCount = array_sum(array_column($_SESSION['cart'], 'qty'));
$cartTotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $_SESSION['cart']));
// ============================================================
// COMPLETE PRODUCT DATA - All 210 items
// ============================================================
$bakeryProducts = [
    ["id"=>1,"name"=>"Whole Wheat Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Whole Wheat Bread","image"=>"images\\Whole Wheat Bread.jpg","weights"=>[["label"=>"400g","price"=>50]]],
    ["id"=>2,"name"=>"Multigrain Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Multigrain Bread","image"=>"images\\Multigrain Bread.jpg","weights"=>[["label"=>"400g","price"=>60]]],
    ["id"=>3,"name"=>"White Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious White Bread","image"=>"images\\White Bread.jpg","weights"=>[["label"=>"400g","price"=>45]]],
    ["id"=>4,"name"=>"Brown Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Brown Bread","image"=>"images\\Brown Bread.jpg","weights"=>[["label"=>"400g","price"=>55]]],
    ["id"=>5,"name"=>"Sourdough Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Sourdough Bread","image"=>"images\\Sourdough Bread.jpg","weights"=>[["label"=>"400g","price"=>70]]],
    ["id"=>6,"name"=>"Baguette","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Baguette","image"=>"images\\Baguette.jpg","weights"=>[["label"=>"300g","price"=>80]]],
    ["id"=>7,"name"=>"Ciabatta","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Ciabatta","image"=>"images\\Ciabatta.jpg","weights"=>[["label"=>"300g","price"=>85]]],
    ["id"=>8,"name"=>"Focaccia","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Focaccia","image"=>"images\\Focaccia.jpg","weights"=>[["label"=>"350g","price"=>90]]],
    ["id"=>9,"name"=>"Rye Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Rye Bread","image"=>"images\\Rye Bread.jpg","weights"=>[["label"=>"400g","price"=>65]]],
    ["id"=>10,"name"=>"Gluten-Free Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Gluten-Free Bread","image"=>"images\\Gluten-Free Bread.jpg","weights"=>[["label"=>"400g","price"=>120]]],
    ["id"=>11,"name"=>"Whole Wheat Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Whole Wheat Loaf","image"=>"images\\Whole Wheat Loaf.jpg","weights"=>[["label"=>"400g","price"=>50]]],
    ["id"=>12,"name"=>"Multigrain Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Multigrain Loaf","image"=>"images\\Multigrain Loaf.jpg","weights"=>[["label"=>"400g","price"=>60]]],
    ["id"=>13,"name"=>"White Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious White Loaf","image"=>"images\\White Loaf.jpg","weights"=>[["label"=>"400g","price"=>45]]],
    ["id"=>14,"name"=>"Brown Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Brown Loaf","image"=>"images\\Brown Loaf.jpg","weights"=>[["label"=>"400g","price"=>55]]],
    ["id"=>15,"name"=>"Rye Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Rye Loaf","image"=>"images\\Rye Loaf.jpg","weights"=>[["label"=>"400g","price"=>65]]],
    ["id"=>16,"name"=>"Sandwich Bread","nameKn"=>"","category"=>"Bread","description"=>"Delicious Sandwich Bread","image"=>"images\\Sandwich Bread.jpg","weights"=>[["label"=>"400g","price"=>50]]],
    ["id"=>17,"name"=>"French Baguette","nameKn"=>"","category"=>"Bread","description"=>"Delicious French Baguette","image"=>"images\\French Baguette.jpg","weights"=>[["label"=>"300g","price"=>80]]],
    ["id"=>18,"name"=>"Italian Ciabatta","nameKn"=>"","category"=>"Bread","description"=>"Delicious Italian Ciabatta","image"=>"images\\Italian Ciabatta.jpg","weights"=>[["label"=>"300g","price"=>85]]],
    ["id"=>19,"name"=>"Focaccia Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Focaccia Loaf","image"=>"images\\Focaccia Loaf.jpg","weights"=>[["label"=>"350g","price"=>90]]],
    ["id"=>20,"name"=>"Gluten-Free Loaf","nameKn"=>"","category"=>"Bread","description"=>"Delicious Gluten-Free Loaf","image"=>"images\\Gluten-Free Loaf.jpg","weights"=>[["label"=>"400g","price"=>120]]],
    ["id"=>21,"name"=>"Chocolate Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Chocolate Pastry","image"=>"images\\Chocolate Pastry.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>22,"name"=>"Strawberry Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Strawberry Pastry","image"=>"images\\Strawberry Pastry.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>23,"name"=>"Vanilla Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Vanilla Pastry","image"=>"images\\Vanilla Pastry.jpg","weights"=>[["label"=>"150g","price"=>55]]],
    ["id"=>24,"name"=>"Pineapple Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Pineapple Pastry","image"=>"images\\Pineapple Pastry.jpg","weights"=>[["label"=>"150g","price"=>55]]],
    ["id"=>25,"name"=>"Mango Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Mango Pastry","image"=>"images\\Mango Pastry.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>26,"name"=>"Blueberry Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Blueberry Pastry","image"=>"images\\Blueberry Pastry.jpg","weights"=>[["label"=>"150g","price"=>65]]],
    ["id"=>27,"name"=>"Black Forest Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Black Forest Pastry","image"=>"images\\Black Forest Pastry.jpg","weights"=>[["label"=>"150g","price"=>70]]],
    ["id"=>28,"name"=>"Coffee Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Coffee Pastry","image"=>"images\\Coffee Pastry.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>29,"name"=>"Almond Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Almond Pastry","image"=>"images\\Almond Pastry.jpg","weights"=>[["label"=>"150g","price"=>65]]],
    ["id"=>30,"name"=>"Cashew Pastry","nameKn"=>"","category"=>"Pastry","description"=>"Delicious Cashew Pastry","image"=>"images\\Cashew Pastry.jpg","weights"=>[["label"=>"150g","price"=>65]]],
    ["id"=>31,"name"=>"Chocolate Circle Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Circle Cake","image"=>"images\\CAke\\chocolate circle.jpg","weights"=>[["label"=>"250g","price"=>90]]],
    ["id"=>32,"name"=>"Anniversary Cake 1","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Anniversary Cake 1","image"=>"images\\CAke\\Anniversary Cake 3.jpg","weights"=>[["label"=>"250g","price"=>90]]],
    ["id"=>33,"name"=>"Anniversary Cake 2","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Anniversary Cake 2","image"=>"images\\CAke\\Anniversary Cake.jpg","weights"=>[["label"=>"250g","price"=>50]]],
    ["id"=>34,"name"=>"Anniversary Cake 3","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Anniversary Cake 3","image"=>"images\\CAke\\Anniversary Cake2.jpg","weights"=>[["label"=>"250g","price"=>60]]],
    ["id"=>35,"name"=>"Birthday Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Birthday Cake","image"=>"images\\CAke\\Birthday Cake 3.jpg","weights"=>[["label"=>"250g","price"=>60]]],
    ["id"=>36,"name"=>"Birthday Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Birthday Cake","image"=>"images\\CAke\\Birthday Cake.jpg","weights"=>[["label"=>"250g","price"=>55]]],
    ["id"=>37,"name"=>"Blueberry Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Blueberry Pastry","image"=>"images\\CAke\\Blueberry Pastry.jpg","weights"=>[["label"=>"250g","price"=>65]]],
    ["id"=>38,"name"=>"Butterscotch Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Butterscotch Cake","image"=>"images\\CAke\\Butterscotch Cake.jpg","weights"=>[["label"=>"250g","price"=>55]]],
    ["id"=>39,"name"=>"Butterscotch Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Butterscotch Pastry","image"=>"images\\CAke\\Butterscotch Pastry.jpg","weights"=>[["label"=>"250g","price"=>60]]],
    ["id"=>40,"name"=>"Almond Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Almond Cake","image"=>"images\\CAke\\cake almond.jpg","weights"=>[["label"=>"250g","price"=>55]]],
    ["id"=>41,"name"=>"Cheese Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Cheese Cake","image"=>"images\\CAke\\Cheesecake.jpg","weights"=>[["label"=>"250g","price"=>65]]],
    ["id"=>42,"name"=>"Chocolate Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Pastry","image"=>"images\\CAke\\Chocolate Pastry.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>43,"name"=>"Cup Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Cup Cake","image"=>"images\\CAke\\Cupcake.jpg","weights"=>[["label"=>"250g","price"=>75]]],
    ["id"=>44,"name"=>"Dual Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Dual Cake","image"=>"images\\CAke\\dual cake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>45,"name"=>"Golden Butterscotch Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Golden Butterscotch Cake","image"=>"images\\CAke\\golden Butterscotch Cake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>46,"name"=>"Mango Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Mango Pastry","image"=>"images\\CAke\\Mango Pastry.jpg","weights"=>[["label"=>"250g","price"=>55]]],
    ["id"=>47,"name"=>"Pineapple cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Pineapple cake","image"=>"images\\CAke\\pineapple cake.jpg","weights"=>[["label"=>"250g","price"=>50]]],
    ["id"=>48,"name"=>"Pineapple Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Pineapple Pastry","image"=>"images\\CAke\\Pineapple Pastry.jpg","weights"=>[["label"=>"250g","price"=>55]]],
    ["id"=>49,"name"=>"Red Velvet Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Red Velvet Pastry","image"=>"images\\CAke\\Red Velvet Pastry.jpg","weights"=>[["label"=>"250g","price"=>60]]],
    ["id"=>50,"name"=>"Theme Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Theme Cake","image"=>"images\\CAke\\Theme Cake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>51,"name"=>"Truffle Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Truffle Cake","image"=>"images\\CAke\\Truffle Cake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>52,"name"=>"Vanilla Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Vanilla Cake","image"=>"images\\CAke\\Vanilla Cupcake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>53,"name"=>"Vanilla Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Vanilla Pastry","image"=>"images\\CAke\\Vanilla Pastry.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>54,"name"=>"Wedding Cake 2","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Wedding Cake 2","image"=>"images\\CAke\\Wedding Cake 2.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>55,"name"=>"Wedding Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Wedding Cake","image"=>"images\\CAke\\Wedding Cake.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>56,"name"=>"Wedding Cake 3","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Wedding Cake 3","image"=>"images\\CAke\\Wedding Cake 3.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>57,"name"=>"White Forest Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious White Forest Pastry","image"=>"images\\CAke\\White Forest Pastry.jpg","weights"=>[["label"=>"250g","price"=>70]]],
    ["id"=>58,"name"=>"Black Forest Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Black Forest Pastry","image"=>"images\\Black Forest Pastry.jpg","weights"=>[["label"=>"250g","price"=>75]]],
    ["id"=>59,"name"=>"Chocolate Truffle Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Truffle Pastry","image"=>"images\\Chocolate Truffle Pastry.jpg","weights"=>[["label"=>"250g","price"=>90]]],
    ["id"=>60,"name"=>"Pineapple Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Pineapple Pastry","image"=>"images\\Pineapple Pastry.jpg","weights"=>[["label"=>"250g","price"=>65]]],
    ["id"=>61,"name"=>"Butterscotch Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Butterscotch Pastry","image"=>"images\\CAke\\Butterscotch Pastry.jpg","weights"=>[["label"=>"250g","price"=>80]]],
    ["id"=>62,"name"=>"Red Velvet Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Red Velvet Pastry","image"=>"images\\Red Velvet Pastry.jpg","weights"=>[["label"=>"250g","price"=>95]]],
    ["id"=>63,"name"=>"Vanilla Cupcake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Vanilla Cupcake","image"=>"images\\Vanilla Cupcake.jpg","weights"=>[["label"=>"100g","price"=>40]]],
    ["id"=>64,"name"=>"Chocolate Cupcake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Cupcake","image"=>"images\\CAke\\Chocolate Cupcake.jpg","weights"=>[["label"=>"100g","price"=>45]]],
    ["id"=>65,"name"=>"Blueberry Muffin","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Blueberry Muffin","image"=>"images\\Blueberry Muffin.jpg","weights"=>[["label"=>"120g","price"=>55]]],
    ["id"=>66,"name"=>"Chocolate Chip Muffin","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Chip Muffin","image"=>"images\\Chocolate Chip Muffin.jpg","weights"=>[["label"=>"120g","price"=>60]]],
    ["id"=>67,"name"=>"Butter Croissant","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Butter Croissant","image"=>"images\\Butter Croissant.jpg","weights"=>[["label"=>"80g","price"=>50]]],
    ["id"=>68,"name"=>"Garlic Bread","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Garlic Bread","image"=>"images\\Garlic Bread Loaf.jpg","weights"=>[["label"=>"150g","price"=>70]]],
    ["id"=>69,"name"=>"Veg Puff","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Veg Puff","image"=>"images\\Veg Puff.jpg","weights"=>[["label"=>"120g","price"=>35]]],
    ["id"=>70,"name"=>"Egg Puff","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Egg Puff","image"=>"images\\Egg Puff.jpg","weights"=>[["label"=>"120g","price"=>40]]],
    ["id"=>71,"name"=>"Chicken Puff","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chicken Puff","image"=>"images\\Chicken Puff.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>72,"name"=>"Donut (Chocolate)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Donut (Chocolate)","image"=>"images\\Chocolate Donut.jpg","weights"=>[["label"=>"90g","price"=>50]]],
    ["id"=>73,"name"=>"Donut (Strawberry)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Donut (Strawberry)","image"=>"images\\Strawberry Donut.jpg","weights"=>[["label"=>"90g","price"=>50]]],
    ["id"=>74,"name"=>"Fruit Cake Slice","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Fruit Cake Slice","image"=>"images\\Fruit Cake Slice.jpg","weights"=>[["label"=>"250g","price"=>85]]],
    ["id"=>75,"name"=>"Mango Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Mango Pastry","image"=>"images\\Mango Pastry.jpg","weights"=>[["label"=>"250g","price"=>80]]],
    ["id"=>76,"name"=>"KitKat Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious KitKat Pastry","image"=>"images\\KitKat Pastry.jpg","weights"=>[["label"=>"250g","price"=>100]]],
    ["id"=>77,"name"=>"Oreo Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Oreo Pastry","image"=>"images\\Oreo Pastry.jpg","weights"=>[["label"=>"250g","price"=>95]]],
    ["id"=>78,"name"=>"Rasmalai Pastry","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Rasmalai Pastry","image"=>"images\\Rasmalai Pastry.jpg","weights"=>[["label"=>"250g","price"=>110]]],
    ["id"=>79,"name"=>"Cheese Cake Slice","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Cheese Cake Slice","image"=>"images\\Cheese Cake Slice.jpg","weights"=>[["label"=>"200g","price"=>120]]],
    ["id"=>80,"name"=>"Brownie (Chocolate)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Brownie (Chocolate)","image"=>"images\\Chocolate Brownie.jpg","weights"=>[["label"=>"150g","price"=>70]]],
    ["id"=>81,"name"=>"Walnut Brownie","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Walnut Brownie","image"=>"images\\Walnut Brownie.jpg","weights"=>[["label"=>"150g","price"=>80]]],
    ["id"=>82,"name"=>"Choco Lava Cake","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Choco Lava Cake","image"=>"images\\Choco Lava Cake.jpg","weights"=>[["label"=>"120g","price"=>90]]],
    ["id"=>83,"name"=>"Banana Cake Slice","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Banana Cake Slice","image"=>"images\\Banana Cake Slice.jpg","weights"=>[["label"=>"200g","price"=>75]]],
    ["id"=>84,"name"=>"Plain Bread Loaf","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Plain Bread Loaf","image"=>"images\\Plain Bread Loaf.jpg","weights"=>[["label"=>"400g","price"=>50]]],
    ["id"=>85,"name"=>"Brown Bread Loaf","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Brown Bread Loaf","image"=>"images\\Brown Bread Loaf.jpg","weights"=>[["label"=>"400g","price"=>55]]],
    ["id"=>86,"name"=>"Multigrain Bread","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Multigrain Bread","image"=>"images\\Multigrain Bread.jpg","weights"=>[["label"=>"400g","price"=>65]]],
    ["id"=>87,"name"=>"Pav Bread (6 pcs)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Pav Bread (6 pcs)","image"=>"images\\Pav Bread (6 pcs).jpg","weights"=>[["label"=>"300g","price"=>40]]],
    ["id"=>88,"name"=>"Burger Buns (4 pcs)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Burger Buns (4 pcs)","image"=>"images\\Burger Buns (4 pcs).jpg","weights"=>[["label"=>"250g","price"=>45]]],
    ["id"=>89,"name"=>"Pizza Base","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Pizza Base","image"=>"images\\Pizza Base.jpg","weights"=>[["label"=>"200g","price"=>60]]],
    ["id"=>90,"name"=>"Khari Biscuit","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Khari Biscuit","image"=>"images\\Khari Biscuit.jpg","weights"=>[["label"=>"200g","price"=>50]]],
    ["id"=>91,"name"=>"Butter Cookies","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Butter Cookies","image"=>"images\\Butter Cookies.jpg","weights"=>[["label"=>"200g","price"=>80]]],
    ["id"=>92,"name"=>"Chocolate Cookies","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Chocolate Cookies","image"=>"images\\Chocolate Cookies.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>93,"name"=>"Oatmeal Cookies","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Oatmeal Cookies","image"=>"images\\Oatmeal Cookies.jpg","weights"=>[["label"=>"200g","price"=>85]]],
    ["id"=>94,"name"=>"Coconut Cookies","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Coconut Cookies","image"=>"images\\Coconut Cookies.jpg","weights"=>[["label"=>"200g","price"=>75]]],
    ["id"=>95,"name"=>"Rusk (Toast)","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Rusk (Toast)","image"=>"images\\Rusk (Toast).jpg","weights"=>[["label"=>"300g","price"=>60]]],
    ["id"=>96,"name"=>"Elaichi Rusk","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Elaichi Rusk","image"=>"images\\Elaichi Rusk.jpg","weights"=>[["label"=>"300g","price"=>65]]],
    ["id"=>97,"name"=>"Milk Bread","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Milk Bread","image"=>"images\\Milk Bread.jpg","weights"=>[["label"=>"400g","price"=>55]]],
    ["id"=>98,"name"=>"Sweet Bun","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Sweet Bun","image"=>"images\\Sweet Bun.jpg","weights"=>[["label"=>"100g","price"=>20]]],
    ["id"=>99,"name"=>"Cream Bun","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Cream Bun","image"=>"images\\Cream Bun.jpg","weights"=>[["label"=>"120g","price"=>30]]],
    ["id"=>100,"name"=>"Jam Bun","nameKn"=>"","category"=>"Cookies","description"=>"Delicious Jam Bun","image"=>"images\\Jam Bun.jpg","weights"=>[["label"=>"120g","price"=>25]]],
    ["id"=>101,"name"=>"Premium Multigrain Loaf","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Premium Multigrain Loaf","image"=>"images\\Premium Multigrain Loaf.jpg","weights"=>[["label"=>"400g","price"=>75]]],
    ["id"=>102,"name"=>"Seeded Whole Wheat Loaf","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Seeded Whole Wheat Loaf","image"=>"images\\Seeded Whole Wheat Loaf.jpg","weights"=>[["label"=>"400g","price"=>80]]],
    ["id"=>103,"name"=>"French Country Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious French Country Bread","image"=>"images\\French Country Bread.jpg","weights"=>[["label"=>"350g","price"=>90]]],
    ["id"=>104,"name"=>"Italian Focaccia","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Italian Focaccia","image"=>"images\\Italian Focaccia.jpg","weights"=>[["label"=>"350g","price"=>95]]],
    ["id"=>105,"name"=>"Sourdough Rye","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Sourdough Rye","image"=>"images\\Sourdough Rye.jpg","weights"=>[["label"=>"400g","price"=>100]]],
    ["id"=>106,"name"=>"Ciabatta Rolls","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Ciabatta Rolls","image"=>"images\\Ciabatta Rolls.jpg","weights"=>[["label"=>"300g","price"=>85]]],
    ["id"=>107,"name"=>"Baguette Classic","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Baguette Classic","image"=>"images\\Baguette Classic.jpg","weights"=>[["label"=>"300g","price"=>90]]],
    ["id"=>108,"name"=>"Olive Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Olive Bread","image"=>"images\\Olive Bread.jpg","weights"=>[["label"=>"400g","price"=>110]]],
    ["id"=>109,"name"=>"Garlic Bread Loaf","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Garlic Bread Loaf","image"=>"images\\Garlic Bread Loaf.jpg","weights"=>[["label"=>"400g","price"=>100]]],
    ["id"=>110,"name"=>"Cheese Bread","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cheese Bread","image"=>"images\\Cheese Bread.jpg","weights"=>[["label"=>"400g","price"=>120]]],
    ["id"=>111,"name"=>"Dilkhush Bun","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Dilkhush Bun","image"=>"images\\Dilkhush Bun.jpg","weights"=>[["label"=>"150g","price"=>35]]],
    ["id"=>112,"name"=>"Tutti Frutti Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Tutti Frutti Cake","image"=>"images\\Cake\\Tutti Frutti Cake.jpg","weights"=>[["label"=>"300g","price"=>90]]],
    ["id"=>113,"name"=>"Marble Cake Slice","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Marble Cake Slice","image"=>"images\\Marble Cake Slice.jpg","weights"=>[["label"=>"200g","price"=>70]]],
    ["id"=>114,"name"=>"Plum Cake Slice","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Plum Cake Slice","image"=>"images\\Plum Cake Slice.jpg","weights"=>[["label"=>"200g","price"=>80]]],
    ["id"=>115,"name"=>"Dry Fruit Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Dry Fruit Cake","image"=>"images\\Dry Fruit Cake.jpg","weights"=>[["label"=>"500g","price"=>180]]],
    ["id"=>116,"name"=>"Honey Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Honey Cake","image"=>"images\\Honey Cake.jpg","weights"=>[["label"=>"250g","price"=>85]]],
    ["id"=>117,"name"=>"Black Current Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Black Current Cake","image"=>"images\\Black Current Cake.jpg","weights"=>[["label"=>"500g","price"=>200]]],
    ["id"=>118,"name"=>"Strawberry Cake Slice","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Strawberry Cake Slice","image"=>"images\\Strawberry Cake Slice.jpg","weights"=>[["label"=>"200g","price"=>75]]],
    ["id"=>119,"name"=>"Pineapple Upside Down Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Pineapple Upside Down Cake","image"=>"images\\Pineapple Upside Down Cake.jpg","weights"=>[["label"=>"500g","price"=>190]]],
    ["id"=>120,"name"=>"Swiss Roll Chocolate","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Swiss Roll Chocolate","image"=>"images\\Swiss Roll Chocolate.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>121,"name"=>"Swiss Roll Strawberry","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Swiss Roll Strawberry","image"=>"images\\Swiss Roll Strawberry.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>122,"name"=>"Mini Cupcake Pack","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mini Cupcake Pack","image"=>"images\\Mini Cupcake Pack.jpg","weights"=>[["label"=>"200g","price"=>120]]],
    ["id"=>123,"name"=>"Choco Chip Cupcake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Choco Chip Cupcake","image"=>"images\\Choco Chip Cupcake.jpg","weights"=>[["label"=>"100g","price"=>50]]],
    ["id"=>124,"name"=>"Red Velvet Cupcake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Red Velvet Cupcake","image"=>"images\\Red Velvet Cupcake.jpg","weights"=>[["label"=>"100g","price"=>60]]],
    ["id"=>125,"name"=>"Coffee Muffin","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Coffee Muffin","image"=>"images\\Coffee Muffin.jpg","weights"=>[["label"=>"120g","price"=>65]]],
    ["id"=>126,"name"=>"Banana Walnut Muffin","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Banana Walnut Muffin","image"=>"images\\Banana Walnut Muffin.jpg","weights"=>[["label"=>"120g","price"=>70]]],
    ["id"=>127,"name"=>"Lemon Muffin","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Lemon Muffin","image"=>"images\\Lemon Muffin.jpg","weights"=>[["label"=>"120g","price"=>60]]],
    ["id"=>128,"name"=>"Cheese Croissant","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cheese Croissant","image"=>"images\\Cheese Croissant.jpg","weights"=>[["label"=>"100g","price"=>70]]],
    ["id"=>129,"name"=>"Chocolate Croissant","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chocolate Croissant","image"=>"images\\Chocolate Croissant.jpg","weights"=>[["label"=>"100g","price"=>75]]],
    ["id"=>130,"name"=>"Paneer Puff","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Paneer Puff","image"=>"images\\Paneer Puff.jpg","weights"=>[["label"=>"150g","price"=>50]]],
    ["id"=>131,"name"=>"Mushroom Puff","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mushroom Puff","image"=>"images\\Mushroom Puff.jpg","weights"=>[["label"=>"150g","price"=>55]]],
    ["id"=>132,"name"=>"Corn Puff","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Corn Puff","image"=>"images\\Corn Puff.jpg","weights"=>[["label"=>"150g","price"=>45]]],
    ["id"=>133,"name"=>"Paneer Roll","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Paneer Roll","image"=>"images\\Paneer Roll.jpg","weights"=>[["label"=>"200g","price"=>70]]],
    ["id"=>134,"name"=>"Veg Roll","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Veg Roll","image"=>"images\\Veg Roll.jpg","weights"=>[["label"=>"200g","price"=>60]]],
    ["id"=>135,"name"=>"Chicken Roll","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chicken Roll","image"=>"images\\Chicken Roll.jpg","weights"=>[["label"=>"200g","price"=>80]]],
    ["id"=>136,"name"=>"Egg Roll","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Egg Roll","image"=>"images\\Egg Roll.jpg","weights"=>[["label"=>"180g","price"=>50]]],
    ["id"=>137,"name"=>"Samosa","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Samosa","image"=>"images\\Samosa.jpg","weights"=>[["label"=>"120g","price"=>20]]],
    ["id"=>138,"name"=>"Kachori","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Kachori","image"=>"images\\Kachori.jpg","weights"=>[["label"=>"120g","price"=>25]]],
    ["id"=>139,"name"=>"Veg Sandwich","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Veg Sandwich","image"=>"images\\Veg Sandwich.jpg","weights"=>[["label"=>"200g","price"=>50]]],
    ["id"=>140,"name"=>"Grilled Sandwich","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Grilled Sandwich","image"=>"images\\Grilled Sandwich.jpg","weights"=>[["label"=>"220g","price"=>70]]],
    ["id"=>141,"name"=>"Cheese Sandwich","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cheese Sandwich","image"=>"images\\Cheese Sandwich.jpg","weights"=>[["label"=>"220g","price"=>80]]],
    ["id"=>142,"name"=>"Chicken Sandwich","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chicken Sandwich","image"=>"images\\Chicken Sandwich.jpg","weights"=>[["label"=>"220g","price"=>90]]],
    ["id"=>143,"name"=>"Veg Burger","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Veg Burger","image"=>"images\\Veg Burger.jpg","weights"=>[["label"=>"200g","price"=>60]]],
    ["id"=>144,"name"=>"Chicken Burger","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chicken Burger","image"=>"images\\Chicken Burger.jpg","weights"=>[["label"=>"220g","price"=>90]]],
    ["id"=>145,"name"=>"Paneer Burger","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Paneer Burger","image"=>"images\\Paneer Burger.jpg","weights"=>[["label"=>"220g","price"=>80]]],
    ["id"=>146,"name"=>"French Fries","nameKn"=>"","category"=>"Bakery","description"=>"Delicious French Fries","image"=>"images\\French Fries.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>147,"name"=>"Cheese Balls","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cheese Balls","image"=>"images\\Cheese Balls.jpg","weights"=>[["label"=>"150g","price"=>80]]],
    ["id"=>148,"name"=>"Spring Roll Veg","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Spring Roll Veg","image"=>"images\\Spring Roll Veg.jpg","weights"=>[["label"=>"150g","price"=>70]]],
    ["id"=>149,"name"=>"Spring Roll Chicken","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Spring Roll Chicken","image"=>"images\\Spring Roll Chicken.jpg","weights"=>[["label"=>"150g","price"=>90]]],
    ["id"=>150,"name"=>"Nachos","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Nachos","image"=>"images\\Nachos.jpg","weights"=>[["label"=>"150g","price"=>70]]],
    ["id"=>151,"name"=>"Garlic Toast","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Garlic Toast","image"=>"images\\Garlic Toast.jpg","weights"=>[["label"=>"150g","price"=>50]]],
    ["id"=>152,"name"=>"Cheese Toast","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cheese Toast","image"=>"images\\Cheese Toast.jpg","weights"=>[["label"=>"150g","price"=>60]]],
    ["id"=>153,"name"=>"Chocolate Donut Sprinkles","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chocolate Donut Sprinkles","image"=>"images\\Chocolate Donut Sprinkles.jpg","weights"=>[["label"=>"100g","price"=>60]]],
    ["id"=>154,"name"=>"Glazed Donut","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Glazed Donut","image"=>"images\\Glazed Donut.jpg","weights"=>[["label"=>"100g","price"=>55]]],
    ["id"=>155,"name"=>"Caramel Donut","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Caramel Donut","image"=>"images\\Caramel Donut.jpg","weights"=>[["label"=>"100g","price"=>65]]],
    ["id"=>156,"name"=>"Jelly Donut","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Jelly Donut","image"=>"images\\Jelly Donut.jpg","weights"=>[["label"=>"100g","price"=>60]]],
    ["id"=>157,"name"=>"Mini Pizza Veg","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mini Pizza Veg","image"=>"images\\Mini Pizza Veg.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>158,"name"=>"Mini Pizza Chicken","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mini Pizza Chicken","image"=>"images\\Mini Pizza Chicken.jpg","weights"=>[["label"=>"220g","price"=>110]]],
    ["id"=>159,"name"=>"Margherita Pizza","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Margherita Pizza","image"=>"images\\Margherita Pizza.jpg","weights"=>[["label"=>"400g","price"=>180]]],
    ["id"=>160,"name"=>"Farmhouse Pizza","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Farmhouse Pizza","image"=>"images\\Farmhouse Pizza.jpg","weights"=>[["label"=>"450g","price"=>220]]],
    ["id"=>161,"name"=>"Chicken Tikka Pizza","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chicken Tikka Pizza","image"=>"images\\Chicken Tikka Pizza.jpg","weights"=>[["label"=>"450g","price"=>250]]],
    ["id"=>162,"name"=>"Veg Pasta","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Veg Pasta","image"=>"images\\Veg Pasta.jpg","weights"=>[["label"=>"250g","price"=>120]]],
    ["id"=>163,"name"=>"White Sauce Pasta","nameKn"=>"","category"=>"Bakery","description"=>"Delicious White Sauce Pasta","image"=>"images\\White Sauce Pasta.jpg","weights"=>[["label"=>"250g","price"=>140]]],
    ["id"=>164,"name"=>"Red Sauce Pasta","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Red Sauce Pasta","image"=>"images\\Red Sauce Pasta.jpg","weights"=>[["label"=>"250g","price"=>130]]],
    ["id"=>165,"name"=>"Chicken Pasta","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chicken Pasta","image"=>"images\\Chicken Pasta.jpg","weights"=>[["label"=>"300g","price"=>160]]],
    ["id"=>166,"name"=>"Cold Coffee","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cold Coffee","image"=>"images\\Cold Coffee.jpg","weights"=>[["label"=>"250ml","price"=>90]]],
    ["id"=>167,"name"=>"Cappuccino","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cappuccino","image"=>"images\\Cappuccino.jpg","weights"=>[["label"=>"200ml","price"=>100]]],
    ["id"=>168,"name"=>"Latte","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Latte","image"=>"images\\Latte.jpg","weights"=>[["label"=>"200ml","price"=>110]]],
    ["id"=>169,"name"=>"Hot Chocolate","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Hot Chocolate","image"=>"images\\Hot Chocolate.jpg","weights"=>[["label"=>"200ml","price"=>120]]],
    ["id"=>170,"name"=>"Milkshake Chocolate","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Milkshake Chocolate","image"=>"images\\Milkshake Chocolate.jpg","weights"=>[["label"=>"300ml","price"=>130]]],
    ["id"=>171,"name"=>"Strawberry Milkshake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Strawberry Milkshake","image"=>"images\\Strawberry Milkshake.jpg","weights"=>[["label"=>"300ml","price"=>120]]],
    ["id"=>172,"name"=>"Vanilla Milkshake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Vanilla Milkshake","image"=>"images\\Vanilla Milkshake.jpg","weights"=>[["label"=>"300ml","price"=>110]]],
    ["id"=>173,"name"=>"Mango Milkshake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mango Milkshake","image"=>"images\\Mango Milkshake.jpg","weights"=>[["label"=>"300ml","price"=>120]]],
    ["id"=>174,"name"=>"Fresh Lime Juice","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Fresh Lime Juice","image"=>"images\\Fresh Lime Juice.jpg","weights"=>[["label"=>"250ml","price"=>60]]],
    ["id"=>175,"name"=>"Oreo Shake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Oreo Shake","image"=>"images\\Oreo Shake.jpg","weights"=>[["label"=>"300ml","price"=>140]]],
    ["id"=>176,"name"=>"KitKat Shake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious KitKat Shake","image"=>"images\\KitKat Shake.jpg","weights"=>[["label"=>"300ml","price"=>150]]],
    ["id"=>177,"name"=>"Brownie Shake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Brownie Shake","image"=>"images\\Brownie Shake.jpg","weights"=>[["label"=>"300ml","price"=>150]]],
    ["id"=>178,"name"=>"Ice Cream Sundae","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Ice Cream Sundae","image"=>"images\\Ice Cream Sundae.jpg","weights"=>[["label"=>"250g","price"=>120]]],
    ["id"=>179,"name"=>"Chocolate Sundae","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chocolate Sundae","image"=>"images\\Chocolate Sundae.jpg","weights"=>[["label"=>"250g","price"=>130]]],
    ["id"=>180,"name"=>"Fruit Salad Cream","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Fruit Salad Cream","image"=>"images\\Fruit Salad Cream.jpg","weights"=>[["label"=>"250g","price"=>110]]],
    ["id"=>181,"name"=>"Gulab Jamun (2 pcs)","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Gulab Jamun (2 pcs)","image"=>"images\\Gulab Jamun (2 pcs).jpg","weights"=>[["label"=>"150g","price"=>40]]],
    ["id"=>182,"name"=>"Rasgulla (2 pcs)","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Rasgulla (2 pcs)","image"=>"images\\Rasgulla (2 pcs).jpg","weights"=>[["label"=>"150g","price"=>40]]],
    ["id"=>183,"name"=>"Kaju Katli","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Kaju Katli","image"=>"images\\Kaju Katli.jpg","weights"=>[["label"=>"200g","price"=>180]]],
    ["id"=>184,"name"=>"Laddu (Boondi)","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Laddu (Boondi)","image"=>"images\\Laddu (Boondi).jpg","weights"=>[["label"=>"200g","price"=>120]]],
    ["id"=>185,"name"=>"Mysore Pak","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Mysore Pak","image"=>"images\\Mysore Pak.jpg","weights"=>[["label"=>"200g","price"=>150]]],
    ["id"=>186,"name"=>"Soan Papdi","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Soan Papdi","image"=>"images\\Soan Papdi.jpg","weights"=>[["label"=>"200g","price"=>100]]],
    ["id"=>187,"name"=>"Halwa (Carrot)","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Halwa (Carrot)","image"=>"images\\Halwa (Carrot).jpg","weights"=>[["label"=>"250g","price"=>90]]],
    ["id"=>188,"name"=>"Halwa (Badam)","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Halwa (Badam)","image"=>"images\\Halwa (Badam).jpg","weights"=>[["label"=>"250g","price"=>120]]],
    ["id"=>189,"name"=>"Jalebi","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Jalebi","image"=>"images\\Jalebi.jpg","weights"=>[["label"=>"200g","price"=>80]]],
    ["id"=>190,"name"=>"Imarti","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Imarti","image"=>"images\\Imarti.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>191,"name"=>"Milk Cake","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Milk Cake","image"=>"images\\Milk Cake.jpg","weights"=>[["label"=>"250g","price"=>110]]],
    ["id"=>192,"name"=>"Rabri","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Rabri","image"=>"images\\Rabri.jpg","weights"=>[["label"=>"250g","price"=>120]]],
    ["id"=>193,"name"=>"Peda","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Peda","image"=>"images\\Peda.jpg","weights"=>[["label"=>"200g","price"=>130]]],
    ["id"=>194,"name"=>"Barfi Mix","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Barfi Mix","image"=>"images\\Barfi Mix.jpg","weights"=>[["label"=>"250g","price"=>150]]],
    ["id"=>195,"name"=>"Chocolate Barfi","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chocolate Barfi","image"=>"images\\Chocolate Barfi.jpg","weights"=>[["label"=>"250g","price"=>160]]],
    ["id"=>196,"name"=>"Coconut Barfi","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Coconut Barfi","image"=>"images\\Coconut Barfi.jpg","weights"=>[["label"=>"250g","price"=>140]]],
    ["id"=>197,"name"=>"Dry Fruit Laddu","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Dry Fruit Laddu","image"=>"images\\Dry Fruit Laddu.jpg","weights"=>[["label"=>"250g","price"=>180]]],
    ["id"=>198,"name"=>"Anjeer Barfi","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Anjeer Barfi","image"=>"images\\Anjeer Barfi.jpg","weights"=>[["label"=>"250g","price"=>200]]],
    ["id"=>199,"name"=>"Dates Roll","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Dates Roll","image"=>"images\\Dates Roll.jpg","weights"=>[["label"=>"250g","price"=>170]]],
    ["id"=>200,"name"=>"Chocolate Fudge","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Chocolate Fudge","image"=>"images\\Chocolate Fudge.jpg","weights"=>[["label"=>"200g","price"=>150]]],
    ["id"=>201,"name"=>"Peanut Chikki","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Peanut Chikki","image"=>"images\\Peanut Chikki.jpg","weights"=>[["label"=>"200g","price"=>90]]],
    ["id"=>202,"name"=>"Sesame Chikki","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Sesame Chikki","image"=>"images\\Sesame Chikki.jpg","weights"=>[["label"=>"200g","price"=>100]]],
    ["id"=>203,"name"=>"Cornflakes Chikki","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cornflakes Chikki","image"=>"images\\Cornflakes Chikki.jpg","weights"=>[["label"=>"200g","price"=>110]]],
    ["id"=>204,"name"=>"Protein Bar","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Protein Bar","image"=>"images\\Protein Bar.jpg","weights"=>[["label"=>"100g","price"=>120]]],
    ["id"=>205,"name"=>"Oats Bar","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Oats Bar","image"=>"images\\Oats Bar.jpg","weights"=>[["label"=>"100g","price"=>100]]],
    ["id"=>206,"name"=>"Energy Ball","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Energy Ball","image"=>"images\\Energy Ball.jpg","weights"=>[["label"=>"100g","price"=>90]]],
    ["id"=>207,"name"=>"Granola Pack","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Granola Pack","image"=>"images\\Granola Pack.jpg","weights"=>[["label"=>"250g","price"=>180]]],
    ["id"=>208,"name"=>"Peanut Butter Cookies","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Peanut Butter Cookies","image"=>"images\\Peanut Butter Cookies.jpg","weights"=>[["label"=>"200g","price"=>120]]],
    ["id"=>209,"name"=>"Almond Cookies","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Almond Cookies","image"=>"images\\Almond Cookies.jpg","weights"=>[["label"=>"200g","price"=>140]]],
    ["id"=>210,"name"=>"Cashew Cookies","nameKn"=>"","category"=>"Bakery","description"=>"Delicious Cashew Cookies","image"=>"images\\Cashew Cookies.jpg","weights"=>[["label"=>"200g","price"=>150]]]
];
// ============================================================
// USE PRODUCTS - From array (skip DB to ensure all display)
// ============================================================
$products = [];
foreach ($bakeryProducts as $item) {
    $firstWeight = $item['weights'][0] ?? ['label' => '250g', 'price' => 0];
    $price = $firstWeight['price'];
    // Fix image path: convert Windows path to web path
    $imagePath = $item['image'];
    $imagePath = str_replace('', '', $imagePath);
    $imagePath = str_replace('\\', '/', $imagePath);
    // If path doesn't start with 'images/', add it
    if (!str_starts_with($imagePath, 'images/') && !str_starts_with($imagePath, '../')) {
        $imagePath = 'images/' . $imagePath;
    }
    // Make relative to web root
    $imagePath = '../' . $imagePath;
    $products[] = [
        'id' => $item['id'],
        'name' => $item['name'],
        'kn_name' => $item['nameKn'] ?? $item['name'],
        'price' => $price,
        'image_url' => $imagePath,
        'description_en' => $item['description'],
        'category' => $item['category'] ?? 'Bakery',
        'weights' => $item['weights']
    ];
}
// ============================================================
// CUSTOM CAKE FORM HANDLING
// ============================================================
$cakeMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cake_submit'])) {
    $cakeName = trim($_POST['cake_name'] ?? '');
    $cakeShape = trim($_POST['cake_shape'] ?? '');
    $cakeWeight = trim($_POST['cake_weight'] ?? '');
    $cakeMsg = trim($_POST['cake_message'] ?? '');
    $cakeSugg = trim($_POST['cake_suggestions'] ?? '');
    $cakeProductId = (int)($_POST['cake_product_id'] ?? 0);
    $cakeCustomPrice = floatval($_POST['cake_price'] ?? 500.00);
    $cakeImage = trim($_POST['cake_image'] ?? '../images/Bakery/CAke/Chocolate Cake.jpg');
    if (!empty($cakeName)) {
        $customCakeItem = [
            'id' => 'custom-cake-' . time(),
            'product_id' => $cakeProductId,
            'name' => htmlspecialchars('Custom Cake: ' . $cakeName . ' (Shape: ' . $cakeShape . ', Weight: ' . $cakeWeight . ')'),
            'kn' => htmlspecialchars('ಕಸ್ಟಮ್ ಕೇಕ್: ' . $cakeName),
            'price' => $cakeCustomPrice,
            'qty' => 1,
            'image' => htmlspecialchars($cakeImage),
            'variant' => 'Shape: ' . $cakeShape . ', Weight: ' . $cakeWeight,
            'description' => htmlspecialchars("Message: $cakeMsg | Notes: $cakeSugg")
        ];
        $_SESSION['cart'][] = $customCakeItem;
        $cakeMessage = '✅ Custom Cake added to cart! Shape: ' . $cakeShape . ', Weight: ' . $cakeWeight;
        header('Location: ../cart/cart.php');
        exit;
    } else {
        $cakeMessage = '⚠️ Please enter a Name for your Custom Cake.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
  <title>KSR Guru JI Karavali Bakery Shop</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    /* ===== WARM MINIMALISM DESIGN ===== */
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background: #f9f6f2; padding: 24px 20px; min-height: 100vh; }
    #app { max-width: 1400px; width: 100%; margin: 0 auto; background: white; border-radius: 40px; box-shadow: 0 20px 40px rgba(0,0,0,0.05); padding: 28px 32px 40px; }
    
    .secondary-nav { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
    .secondary-nav a { background: #f3eee9; padding: 10px 20px; border-radius: 50px; font-size: 0.9rem; font-weight: 500; color: #5c3e24; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: background 0.2s; }
    .secondary-nav a:hover { background: #e5d5c4; }

    .shop-header { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 2rem; }
    .branding h1 { font-size: 2.2rem; font-weight: 700; color: #3e2c1b; line-height: 1.2; }
    .branding .kannada-tag { font-size: 0.85rem; color: #b45f2b; font-weight: 500; margin-top: 4px; }
    .cart-link { background: #f7ede3; border-radius: 80px; padding: 12px 24px; display: inline-flex; align-items: center; gap: 14px; transition: 0.2s; border: 1px solid #e6d9cd; box-shadow: 0 4px 10px rgba(0,0,0,0.02); text-decoration: none; color: #5c3e24; font-weight: 600; font-size: 0.95rem; }
    .cart-link:hover { background: #e5d5c4; transform: translateY(-2px); }
    .cart-link i { font-size: 1.5rem; color: #b45f2b; }
    .cart-link .cart-count { background: #b45f2b; color: white; font-weight: 700; padding: 2px 12px; border-radius: 60px; font-size: 1rem; margin-left: 4px; }
    .search-panel { margin-bottom: 32px; }
    .search-bar { background: #f3eee9; border-radius: 70px; padding: 8px 24px; display: flex; align-items: center; border: 1px solid #e6d9cd; gap: 12px; margin-bottom: 24px; box-shadow: 0 6px 12px rgba(0,0,0,0.02); }
    .search-bar i { font-size: 1.2rem; color: #b45f2b; }
    .search-bar input { flex: 1; border: none; padding: 12px 0; background: transparent; font-size: 1rem; outline: none; color: #2f2216; font-weight: 500; }
    .filter-row { overflow-x: auto; padding-bottom: 8px; scrollbar-width: thin; }
    .pill-group { display: flex; gap: 12px; }
    .pill { background: #f3eee9; padding: 10px 24px; border-radius: 60px; font-weight: 600; font-size: 0.85rem; color: #4f3e2e; cursor: pointer; white-space: nowrap; border: 1px solid transparent; transition: all 0.2s; }
    .pill.active { background: #5c3e24; color: white; border-color: #5c3e24; box-shadow: 0 6px 12px rgba(92,62,36,0.2); }
    .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 30px; margin: 32px 0 48px; }
    .product-card { background: white; border-radius: 28px; padding: 22px 18px 24px; transition: all 0.25s ease; border: 1px solid #f2e5d9; box-shadow: 0 8px 24px rgba(0,0,0,0.04); display: flex; flex-direction: column; position: relative; }
    .product-card:hover { transform: translateY(-6px); box-shadow: 0 20px 30px rgba(0,0,0,0.08); border-color: #e5d5c4; }
    .product-img { width: 90px; height: 90px; object-fit: cover; border-radius: 32px; background: #fef0e6; border: 2px solid #ffe2cf; margin: 0 auto 16px; flex-shrink: 0; }
    .product-title { font-weight: 800; font-size: 1.15rem; color: #2f2216; line-height: 1.3; }
    .product-title-kn { font-size: 0.75rem; color: #7b5f45; margin: 5px 0 12px; font-weight: 500; }
    .product-description { font-size: 0.8rem; background: #fefaf6; padding: 8px 12px; border-radius: 12px; color: #5c3e24; margin: 8px 0 14px; line-height: 1.4; border-left: 3px solid #b45f2b; }
    .weight-select { width: 100%; background: #fef8f2; border: 1px solid #e6d9cd; border-radius: 50px; padding: 8px 12px; font-size: 0.85rem; margin: 8px 0 12px; cursor: pointer; font-weight: 500; }
    .price-row { display: flex; justify-content: space-between; align-items: baseline; flex-wrap: wrap; margin-top: 6px; }
    .price { font-weight: 800; font-size: 1.5rem; color: #b45f2b; letter-spacing: -0.02em; }
    .price small { font-size: 0.7rem; font-weight: 500; color: #a07c5e; }
    .descriptive-cart-btn { background: #b45f2b; border: none; border-radius: 60px; padding: 12px 8px; font-weight: 700; font-size: 0.75rem; color: white; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 18px; transition: 0.2s; box-shadow: 0 4px 12px rgba(180,95,43,0.25); }
    .descriptive-cart-btn i { font-size: 0.8rem; }
    .descriptive-cart-btn:hover { transform: scale(0.96); background: #9c5022; }
    .cake-order-btn { background: #b45f2b; color: white; border: none; padding: 10px 24px; border-radius: 60px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; margin-top: 8px; }
    .cake-order-btn:hover { background: #9c5022; }
    .custom-cake-section { background: #fefaf6; border-radius: 36px; padding: 24px 28px; margin: 32px 0; border: 1px solid #f2e5d9; display: none; }
    .custom-cake-section.visible { display: block; }
    .cake-form { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; }
    .form-group { display: flex; flex-direction: column; gap: 6px; }
    .form-group label { font-weight: 600; color: #5c3e24; font-size: 0.9rem; }
    .form-group input, .form-group select, .form-group textarea { padding: 10px 16px; border-radius: 60px; border: 1px solid #e6d9cd; background: white; font-size: 0.95rem; outline: none; }
    .form-group textarea { border-radius: 24px; min-height: 70px; resize: vertical; }
    .submit-cake-btn { background: #b45f2b; color: white; border: none; padding: 12px 28px; border-radius: 60px; font-weight: 600; cursor: pointer; transition: 0.2s; width: fit-content; display: flex; align-items: center; gap: 8px; }
    .submit-cake-btn:hover { background: #9c5022; }
    .cake-message { padding: 12px 20px; border-radius: 60px; margin-bottom: 16px; font-weight: 500; }
    .cake-message.success { background: #d4edda; color: #155724; }
    .cake-message.error { background: #f8d7da; color: #721c24; }
    .cake-message.info { background: #d1ecf1; color: #0c5460; }
    footer { text-align: center; font-size: 0.75rem; color: #b9a690; margin-top: 32px; padding-top: 18px; border-top: 1px solid #f0e3d7; }
    .design-credits { margin-top: 20px; text-align: center; font-size: 0.7rem; color: #a07c5e; }
    
    .ksr-sort-bar { display: flex; justify-content: flex-end; align-items: center; margin-top: 16px; width: 100%; }
    .ksr-sort-inner { display: inline-flex; align-items: center; gap: 8px; background: white; padding: 6px 14px; border-radius: 50px; border: 1.5px solid #f0e6dd; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .ksr-sort-label { font-size: 0.8rem; font-weight: 700; color: #b45f2b; display: flex; align-items: center; gap: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
    .ksr-sort-select { border: none; background: transparent; font-size: 0.85rem; font-weight: 600; color: #4a3424; cursor: pointer; outline: none; }
    .ksr-sort-count { display: none !important; }
    
    @media (max-width: 640px) { #app { padding: 20px; } .product-grid { gap: 18px; } .product-img { width: 72px; height: 72px; } .price { font-size: 1.3rem; } }
  </style>
  <link rel="stylesheet" href="category-style.css">
    <link rel="stylesheet" href="../index.css">
</head>
<body>
<div id="app">
    <?php
  $base_url = '../';
  include __DIR__ . '/../includes/global-header.php';
?>
  <div class="search-panel">
    <div class="search-bar">
      <i class="fa-solid fa-magnifying-glass"></i>
      <input type="text" id="searchInput" placeholder="Search product / ಹುಡುಕಿ" autocomplete="off">
    </div>

    <div class="filter-row">
      <div class="pill-group" id="filterPills">
        <div class="pill active" data-category="all">All / ಎಲ್ಲ</div>
        <div class="pill" data-category="bread">Bread / ಬ್ರೆಡ್</div>
        <div class="pill" data-category="pastry">Pastry / ಪೇಸ್ಟ್ರಿ</div>
        <div class="pill" data-category="cookies">Cookies / ಕುಕೀಸ್</div>
      </div>
    </div>
    <button class="cake-order-btn" id="showCakeBtn"><i class="fas fa-cake-candles"></i> 🎂 Custom Cake Order</button>
  </div>
  <div class="custom-cake-section" id="customCakeSection">
    <h3 style="font-size:1.5rem; color:#3e2c1b; margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
      <span><i class="fas fa-cake-candles"></i> Custom Cake Order · ವೈಯಕ್ತಿಕ ಕೇಕ್ ಆರ್ಡರ್</span>
      <button class="cake-order-btn" id="hideCakeBtn" style="background:#9a4e22;">✕ Close</button>
    </h3>
    <?php if ($cakeMessage): ?>
      <div class="cake-message <?php echo strpos($cakeMessage, '✅') !== false ? 'success' : (strpos($cakeMessage, '⚠️') !== false ? 'error' : 'info'); ?>">
        <?php echo htmlspecialchars($cakeMessage); ?>
      </div>
    <?php endif; ?>
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="cakeOrderForm">
      <input type="hidden" name="cake_product_id" id="cakeProductId" value="0">
      <input type="hidden" name="cake_price" id="cakePriceInput" value="500.00">
      <input type="hidden" name="cake_image" id="cakeImageInput" value="../images/Bakery/CAke/Chocolate Cake.jpg">
      <div class="cake-form">
        <div class="form-group">
          <label><i class="fas fa-tag"></i> Cake Name / ಕೇಕ್ ಹೆಸರು</label>
          <input type="text" id="cakeName" name="cake_name" placeholder="e.g., Chocolate Truffle" required autocomplete="off">
        </div>
        <div class="form-group">
          <label><i class="fas fa-shapes"></i> Shape / ಆಕಾರ</label>
          <select id="cakeShape" name="cake_shape" required>
            <option value="Round">Round (ಗುಂಡಗೆ)</option>
            <option value="Square">Square (ಚೌಕ)</option>
            <option value="Heart">Heart (ಹೃದಯ)</option>
            <option value="Rectangle">Rectangle (ಆಯತ)</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-weight-hanging"></i> Weight / ತೂಕ</label>
          <select id="cakeWeight" name="cake_weight" required>
            <option value="500g">500g</option>
            <option value="1kg">1kg</option>
            <option value="2kg">2kg</option>
            <option value="3kg">3kg</option>
            <option value="4kg">4kg</option>
            <option value="5kg">5kg</option>
            <option value="10kg">10kg</option>
          </select>
        </div>
        <div class="form-group">
          <label><i class="fas fa-comment-dots"></i> Custom Message / ಸಂದೇಶ</label>
          <textarea id="customMessage" name="cake_message" placeholder="Write your message..."></textarea>
        </div>
        <div class="form-group">
          <label><i class="fas fa-lightbulb"></i> Suggestions / ಸಲಹೆಗಳು</label>
          <textarea id="suggestions" name="cake_suggestions" placeholder="Special requests..."></textarea>
        </div>
        <div class="form-group" style="justify-content: flex-end; align-items: flex-end;">
          <div style="font-size: 1.1rem; font-weight: 700; color: #c45a2c; margin-bottom: 8px;">
            Estimated Price: <span id="customCakePriceDisplay">₹500.00</span>
          </div>
          <button type="submit" class="submit-cake-btn" name="cake_submit"><i class="fas fa-paper-plane"></i> Request Custom Cake</button>
        </div>
      </div>
    </form>
  </div>
  <div class="product-grid" id="productGrid"></div>
</div>
<script>
// ============================================================
// JAVASCRIPT – All 210 products from PHP
// ============================================================
(function() {
  // ----- PRODUCT DATA FROM PHP (ALL 210) -----
  const productsData = <?php
    // Build JS-friendly product array
    $jsProducts = [];
    foreach ($products as $p) {
        $cat = 'cookies';
        $nameLower = strtolower($p['name']);
        if (strpos($nameLower, 'bread') !== false || strpos($nameLower, 'loaf') !== false || strpos($nameLower, 'baguette') !== false || strpos($nameLower, 'croissant') !== false || strpos($nameLower, 'bun') !== false) {
            $cat = 'bread';
        } elseif (strpos($nameLower, 'pastry') !== false || strpos($nameLower, 'cake') !== false || strpos($nameLower, 'cupcake') !== false || strpos($nameLower, 'muffin') !== false || strpos($nameLower, 'cheese cake') !== false) {
            $cat = 'pastry';
        }
        $weights = $p['weights'] ?? [];
        $baseWeight = 250;
        $basePrice = $p['price'] ?? 0;
        if (!empty($weights)) {
            $firstWeight = $weights[0];
            preg_match('/(\d+)/', $firstWeight['label'], $matches);
            if (isset($matches[1])) $baseWeight = (int)$matches[1];
            $basePrice = $firstWeight['price'];
        }
        $kn = $p['kn_name'] ?? $p['name'] . ' (ಬೇಕರಿ)';
        $desc = $p['description_en'] ?? 'Fresh bakery item';
        $buttonText = '➕ Add to cart';
        if (stripos($p['name'], 'bread') !== false) $buttonText = '➕ Fresh bread';
        elseif (stripos($p['name'], 'pastry') !== false) $buttonText = '➕ Sweet pastry';
        elseif (stripos($p['name'], 'cake') !== false) $buttonText = '➕ Cake slice';
        elseif (stripos($p['name'], 'cookie') !== false) $buttonText = '➕ Crunchy cookie';
        $img = $p['image_url'] ?? '';
        if ($img && !str_starts_with($img, 'http') && !str_starts_with($img, 'data:')) {
            $img = str_replace('\\', '/', $img);
            // Already fixed in PHP, but ensure it starts with ../
            if (!str_starts_with($img, '../') && !str_starts_with($img, 'http')) {
                $img = '../' . ltrim($img, '/');
            }
        }
        $jsProducts[] = [
            'id' => $p['id'],
            'nameEn' => $p['name'],
            'kn' => $kn,
            'category' => $cat,
            'price' => $basePrice,
            'image' => $img,
            'description' => $desc,
            'buttonText' => $buttonText,
            'originalGrams' => $baseWeight,
            'originalPrice' => $basePrice,
            'weights' => $weights
        ];
    }
    echo json_encode($jsProducts);
  ?>;
  console.log('✅ Loaded ' + productsData.length + ' bakery products');
  // ----- WEIGHT OPTIONS -----
  const WEIGHT_OPTS = [
    { grams: 50, label: "50g" }, { grams: 100, label: "100g" }, { grams: 250, label: "250g" },
    { grams: 500, label: "500g" }, { grams: 750, label: "750g" }, { grams: 1000, label: "1kg" },
    { grams: 2000, label: "2kg" }, { grams: 2500, label: "2.5kg" }, { grams: 3000, label: "3kg" },
    { grams: 4000, label: "4kg" }, { grams: 5000, label: "5kg" }, { grams: 6000, label: "6kg" },
    { grams: 7000, label: "7kg" }, { grams: 8000, label: "8kg" }, { grams: 10000, label: "10kg" }
  ];
  // ----- CART STATE -----
  let cart = JSON.parse(localStorage.getItem('karavalliCartWeightExtended')) || [];
  let activeCategory = 'all';
  let searchTerm = '';
  const productGridEl = document.getElementById('productGrid');
  const cartCountSpan = document.getElementById('cartCount');
  const searchInput = document.getElementById('searchInput');
  const filterPills = document.querySelectorAll('.pill');
  // Custom cake toggle
  const customCakeSection = document.getElementById('customCakeSection');
  document.getElementById('showCakeBtn').addEventListener('click', () => {
    customCakeSection.classList.toggle('visible');
    if (customCakeSection.classList.contains('visible')) {
      document.getElementById('cakeProductId').value = "0";
      document.getElementById('cakeName').value = "";
      document.getElementById('cakeImageInput').value = "../images/Bakery/CAke/Chocolate Cake.jpg";
      document.getElementById('customMessage').value = "";
      document.getElementById('suggestions').value = "";
      updateCustomCakePrice();
      customCakeSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
  document.getElementById('hideCakeBtn').addEventListener('click', () => {
    customCakeSection.classList.remove('visible');
  });
  // ----- HELPERS -----
  function calcPrice(product, targetGrams) {
    return (product.originalPrice / product.originalGrams) * targetGrams;
  }
  function convertWeightLabelToGrams(label) {
    const lower = label.toLowerCase();
    if (lower.endsWith('kg')) {
      return parseFloat(lower) * 1000;
    }
    if (lower.endsWith('g')) {
      return parseFloat(lower);
    }
    return 1000; // default 1kg
  }
  function updateCustomCakePrice() {
    const pid = parseInt(document.getElementById('cakeProductId').value) || 0;
    const weightVal = document.getElementById('cakeWeight').value;
    const grams = convertWeightLabelToGrams(weightVal);
    let finalPrice = 500.00;
    if (pid !== 0) {
      const product = productsData.find(p => p.id === pid);
      if (product) {
        finalPrice = calcPrice(product, grams);
      }
    } else {
      // General custom cake: flat rate of ₹500 per kg
      finalPrice = (500.00 / 1000) * grams;
    }
    document.getElementById('cakePriceInput').value = finalPrice.toFixed(2);
    document.getElementById('customCakePriceDisplay').innerText = `₹${finalPrice.toFixed(2)}`;
  }
  document.getElementById('cakeWeight').addEventListener('change', updateCustomCakePrice);
  function openCustomCakeOrder(product, grams) {
    customCakeSection.classList.add('visible');
    // Set form fields
    document.getElementById('cakeName').value = product.nameEn;
    document.getElementById('cakeProductId').value = product.id;
    document.getElementById('cakeImageInput').value = product.image;
    // Select the weight matching grams
    const weightLabel = WEIGHT_OPTS.find(w => w.grams === grams)?.label || `${grams}g`;
    const cakeWeightSelect = document.getElementById('cakeWeight');
    let matchedOption = false;
    for (let i = 0; i < cakeWeightSelect.options.length; i++) {
      if (cakeWeightSelect.options[i].value.toLowerCase() === weightLabel.toLowerCase() || cakeWeightSelect.options[i].value.toLowerCase().includes(weightLabel.toLowerCase())) {
        cakeWeightSelect.selectedIndex = i;
        matchedOption = true;
        break;
      }
    }
    if (!matchedOption) {
      if (grams <= 500) {
        cakeWeightSelect.value = "500g";
      } else if (grams <= 1000) {
        cakeWeightSelect.value = "1kg";
      } else if (grams <= 2000) {
        cakeWeightSelect.value = "2kg";
      } else if (grams <= 3000) {
        cakeWeightSelect.value = "3kg";
      } else if (grams <= 4000) {
        cakeWeightSelect.value = "4kg";
      } else if (grams <= 5000) {
        cakeWeightSelect.value = "5kg";
      } else {
        cakeWeightSelect.value = "10kg";
      }
    }
    updateCustomCakePrice();
    customCakeSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    setTimeout(() => {
      document.getElementById('customMessage').focus();
    }, 500);
  }
  function updateCartBadge() {
    const totalQty = cart.reduce((sum, i) => sum + i.quantity, 0);
    cartCountSpan.innerText = totalQty;
    localStorage.setItem('karavalliCartWeightExtended', JSON.stringify(cart));
  }
  function addToCart(productId, weightGrams) {
    const product = productsData.find(p => p.id === productId);
    if (!product) return;
    const weightLabel = WEIGHT_OPTS.find(w => w.grams === weightGrams)?.label || `${weightGrams}g`;
    const unitPrice = calcPrice(product, weightGrams);
    const formData = new FormData();
    formData.append('id', productId);
    formData.append('qty', 1);
    formData.append('variant', weightLabel);
    formData.append('custom_price', unitPrice.toFixed(2));
    formData.append('name', product.nameEn || product.name || '');
    formData.append('kn', product.kn || product.nameKn || product.knName || '');
    formData.append('image', product.image || product.imageUrl || product.image_url || '');
    fetch('../cart/add_cart.php', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
                    if (typeof cart !== "undefined") {
                        cart = data.cart || cart;
                    } else {
                        window.cart = data.cart;
                    }
                    const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge, #cartBadgeCount");
                    badges.forEach(b => {
                        b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
                    });
                    if (typeof openCart === "function") {
                        openCart();
                    }
        } else {
            alert(data.error || 'Failed to add product to cart');
        }
    })
    .catch(err => {
            console.error(err);
            alert("Error adding product to cart.");
        });
  }

  // ----- RENDER -----
  function renderProducts() {
    let filtered = productsData.filter(p => {
      if (activeCategory !== 'all' && p.category !== activeCategory) return false;
      const term = searchTerm.trim().toLowerCase();
      if (term) {
        return p.nameEn.toLowerCase().includes(term) || p.kn.toLowerCase().includes(term);
      }
      return true;
    });

    if (filtered.length === 0) {
      productGridEl.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:3rem; color:#bb8a69;">✨ No items found / ಯಾವುದೇ ಉತ್ಪನ್ನ ಇಲ್ಲ</div>`;
      return;
    }
    
    productGridEl.innerHTML = filtered.map(p => {
      const defaultGrams = 250;
      const defaultPrice = calcPrice(p, defaultGrams);
      return `
        <div class="product-card" data-id="${p.id}">
          <img class="product-img" src="${p.image}" alt="" loading="lazy" onerror="this.src='https://placehold.co/90x90/fef0e6/c45a2c?text=🍞'">
          <div class="product-title">${p.nameEn}</div>
          <div class="product-title-kn">${p.kn}</div>
          <div class="product-description"><i class="fa-regular fa-note-sticky"></i> ${p.description}</div>
          <select class="weight-select" data-id="${p.id}">
            ${WEIGHT_OPTS.map(w => `<option value="${w.grams}" ${w.grams===defaultGrams ? 'selected' : ''}>${w.label}</option>`).join('')}
          </select>
          <div class="price-row">
            <span class="price" id="price-${p.id}">₹${defaultPrice.toFixed(2)} <small>inr</small></span>
            <button class="descriptive-cart-btn" data-id="${p.id}" data-add>
              <i class="fa-solid fa-seedling"></i>
              <span>${p.buttonText}</span>
            </button>
          </div>
        </div>
      `;
    }).join('');
    // Weight selector - update price
    document.querySelectorAll('.weight-select').forEach(sel => {
      const pid = parseInt(sel.dataset.id);
      const prod = productsData.find(p => p.id === pid);
      if (!prod) return;
      const updatePrice = () => {
        const grams = parseInt(sel.value);
        const newPrice = calcPrice(prod, grams);
        const span = document.getElementById(`price-${pid}`);
        if (span) span.innerHTML = `₹${newPrice.toFixed(2)} <small>inr</small>`;
      };
      sel.addEventListener('change', updatePrice);
      updatePrice();
    });
    // Add to cart buttons
    document.querySelectorAll('.descriptive-cart-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const pid = parseInt(this.dataset.id);
        const product = productsData.find(p => p.id === pid);
        if (!product) return;
        // Check if it is a cake product
        if (product.nameEn.toLowerCase().includes('cake')) {
          const card = this.closest('.product-card');
          const weightSelect = card.querySelector('.weight-select');
          const grams = parseInt(weightSelect.value);
          openCustomCakeOrder(product, grams);
          return;
        }
        const card = this.closest('.product-card');
        const weightSelect = card.querySelector('.weight-select');
        const grams = parseInt(weightSelect.value);
        addToCart(pid, grams);
        const orig = this.innerHTML;
        this.innerHTML = '<i class="fas fa-check"></i> Added';
        setTimeout(() => this.innerHTML = orig, 800);
        updateCartBadge();
      });
    });
  }
  // ----- EVENT BINDING -----
  filterPills.forEach(pill => {
    pill.addEventListener('click', () => {
      filterPills.forEach(p => p.classList.remove('active'));
      pill.classList.add('active');
      activeCategory = pill.dataset.category;
      renderProducts();
    });
  });
  searchInput.addEventListener('input', e => { searchTerm = e.target.value; renderProducts(); });
  

  // ----- INIT -----
  renderProducts();
  updateCartBadge();
  console.log('🍞 Bakery page loaded with ' + productsData.length + ' products!');
  // Removed inline cart block in favor of unified global cart drawer
})();
</script>


<script>
/* Injected Cart Drawer JavaScript Handlers */
  function openCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
    if (typeof renderCartItems === "function") {
      renderCartItems();
    }
  }
  function closeCart() {
    const sidebar = document.getElementById('cartSidebar');
    const overlay = document.getElementById('overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
    document.body.style.overflow = '';
  }
  function updateCartQty(productId, qty) {
    const formData = new FormData();
    formData.append('id', productId);
    formData.append('qty', qty);
    fetch('../index.php?ajax=update_cart', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (typeof cart !== "undefined") {
              cart = data.cart;
          }
          const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
          badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
          });
          if (typeof renderCartItems === "function") {
              renderCartItems();
          }
        }
      })
      .catch(console.error);
  }
  function removeFromCart(productId) {
    const formData = new FormData();
    formData.append('id', productId);
    fetch('../index.php?ajax=remove_cart', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          if (typeof cart !== "undefined") {
              cart = data.cart;
          }
          const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
          badges.forEach(b => {
              b.innerText = data.cart ? data.cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
          });
          if (typeof renderCartItems === "function") {
              renderCartItems();
          }
        }
      })
      .catch(console.error);
  }
  function renderCartItems() {
    const container = document.getElementById('cartItems');
    const totalSpan = document.querySelector('.total-price');
    if (!container) return;
    const currentCart = (typeof cart !== "undefined") ? cart : [];
    if (!currentCart.length) {
      container.innerHTML = `<div class="empty-cart"><i class="fas fa-shopping-basket"></i><p>Your cart is empty</p></div>`;
      if (totalSpan) totalSpan.innerText = '₹0';
      return;
    }
    let total = 0;
    let html = '';
    currentCart.forEach(item => {
      const itemTotal = item.price * item.qty;
      total += itemTotal;
      const itemImgSrc = item.image || '';
      const imgHtml = itemImgSrc ? 
        `<img src="${itemImgSrc}" alt="${item.name}" onerror="this.outerHTML='<span class=\'emoji-fallback\'>🥣</span>'" style="width:100%; height:100%; object-fit:cover; border-radius:50%;" />` : 
        `<span class="emoji-fallback">🥣</span>`;
      html += `
        <div class="cart-item" data-id="${item.id}">
          <div class="cart-item-image">${imgHtml}</div>
          <div class="cart-item-details">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-price">₹${item.price} × ${item.qty} = ₹${itemTotal.toFixed(2)}</div>
            <div class="cart-item-actions">
              <button onclick="window.changeQty('${item.id}', ${item.qty - 1})">-</button>
              <span>${item.qty}</span>
              <button onclick="window.changeQty('${item.id}', ${item.qty + 1})">+</button>
              <button onclick="window.removeItem('${item.id}')"><i class="fas fa-trash-alt"></i></button>
            </div>
          </div>
        </div>
      `;
    });
    container.innerHTML = html;
    if (totalSpan) totalSpan.innerText = `₹${total.toFixed(2)}`;
  }
  function setupCartSidebarEvents() {
    const closeBtn = document.getElementById('closeCart');
    const overlay = document.getElementById('overlay');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const continueBtn = document.getElementById('continueShopping');
    if (closeBtn) closeBtn.addEventListener('click', closeCart);
    if (overlay) overlay.addEventListener('click', closeCart);
    if (continueBtn) continueBtn.addEventListener('click', closeCart);
    if (checkoutBtn) {
        checkoutBtn.addEventListener('click', function() {
            const currentCart = (typeof cart !== "undefined") ? cart : [];
            if (currentCart.length) {
                window.location.href = '../checkout/checkout.php';
            } else {
                window.showFastCartToast ? window.showFastCartToast('Your cart is empty! Add items to proceed.') : alert('Cart is empty');
            }
        });
    }
    const headerCartBtns = document.querySelectorAll('.cart-link, #cartLink, #cartInfoIcon');
    headerCartBtns.forEach(btn => {
        const clone = btn.cloneNode(true);
        btn.parentNode.replaceChild(clone, btn);
        clone.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openCart();
        });
    });
    const legacyOverlay = document.getElementById('cartOverlay');
    if (legacyOverlay) {
        legacyOverlay.style.display = 'none';
        legacyOverlay.style.pointerEvents = 'none';
    }
    window.changeQty = updateCartQty;
    window.removeItem = removeFromCart;
    fetch('../index.php?ajax=cart')
      .then(res => res.json())
      .then(data => {
        if (data && typeof cart !== "undefined") {
            cart = data;
            const badges = document.querySelectorAll("#cartCount, .cart-count, .cart-badge");
            badges.forEach(b => {
                b.innerText = cart ? cart.reduce((acc, i) => acc + (i.qty || 1), 0) : "";
            });
        }
      }).catch(console.error);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCartSidebarEvents);
  } else {
    setupCartSidebarEvents();
  }
</script>
<!-- Injected Cart Sidebar Markup -->
<div class="cart-sidebar" id="cartSidebar">
    <div class="cart-header">
      <h3><i class="fas fa-shopping-cart"></i> Your Cart</h3>
      <button class="close-cart" id="closeCart"><i class="fas fa-times"></i></button>
    </div>
    <div class="cart-items" id="cartItems"></div>
    <div class="cart-footer">
      <div class="cart-total"><span>Total</span><span class="total-price">₹0</span></div>
      <button class="checkout-btn" id="checkoutBtn">Proceed to Checkout</button>
      <button class="continue-shopping" id="continueShopping">Continue Shopping</button>
    </div>
</div>
<div class="overlay" id="overlay"></div>

<?php include __DIR__ . '/../includes/user-footer.php'; ?>
<script src="sorting.js"></script>
</body>
</html>
