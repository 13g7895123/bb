<?php

include_once(__DIR__ . '/__Class/ClassLoad.php');

$NOW_Page = 'business_price_tracking';
if (BaseWork::_get('PageName') != "" && file_exists('Pages/' . BaseWork::_get('PageName') . '.php')) {
    $NOW_Page = BaseWork::_get('PageName'); //帶入目前PageName
}

?>
<!DOCTYPE html>
<html lang="zh-tw" >
    <head>
        <meta charset="UTF-8">
        <title>Blind box</title>

        <!-- 引入JQuery -->
        <script src="https://code.jquery.com/jquery-1.10.1.min.js"></script>
        
        <!-- 引入Bootstrap的CSS -->
        <link rel="stylesheet" href="https://cdn.staticfile.org/twitter-bootstrap/5.1.1/css/bootstrap.min.css">

        <!-- 引入font-awesome的CSS -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css">

        <!-- 引入自寫CSS -->
        <link rel="stylesheet" type="text/css" href="./assets/css/index.css">

        <!-- 引入字體 -->
        <link href="https://fonts.googleapis.com/css2?family=Oleo+Script&display=swap" rel="stylesheet"> 

    </head>
    <body>
        <div class='nav_bar'>
            <div class='left'>
                <div class='logo'>
                    <img src='./assets/image/nav_logo.png' heigh="30" alt="LOGO">
                </div>
            </div>
            
            <div class='right'>
                <div class='nav_item member'>
                    <div><i class="fa-solid fa-user"></i></div>
                    <!-- <div class='drop_down'>123</div> -->
                </div>
                <div class='nav_item cart'><i class="fa-solid fa-cart-shopping"></i></div>
                <div class='nav_item search'><i class="fa-solid fa-magnifying-glass"></i></div>
            </div>
            
        </div>
        <div class='nav_bar2'>
            <div class='nav_main2'>
                <div class='nav_item2'>所有商品</div>
                <a href='?PageName=bb_group'><div class='nav_item2'>盲盒湊團</div></a>
                <div class='nav_item2'>購物須知</div>
                <div class='nav_item2'>聯繫我們</div>
            </div>
        </div>
        
        <div class="container">
            <!-- PAGE CONTENT BEGINS -->                
            <?php
                //顯示頁面
                include('Pages/' . $NOW_Page . '.php');
            ?>
            <!-- PAGE CONTENT ENDS -->
        </div><!-- /.page-content -->
        
    </body>
    <script>
        $(document).ready(function() {
            const ajax_url = "<?php if ($NOW_Page) echo './Pages/ajax/' . $NOW_Page . '.php'; ?>";
            <?php
                include(__DIR__ . '/Pages/js/' . $NOW_Page . '.js');
            ?>
        })
    </script>
</html>