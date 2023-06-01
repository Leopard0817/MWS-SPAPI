<?php
    require_once(__DIR__ . '/index.php');
    $amazon = new Amazon();
    // $file_path = __DIR__. '/amazon_tracking.csv';
    $amazon->fn_GetOrderBuyerInfo('113-8087573-3961813');
?>