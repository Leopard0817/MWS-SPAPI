<?php
    require_once(__DIR__ . '/index.php');
    $amazon = new Amazon();

    $currentDateTime = new DateTime('UTC');
    $endDateTime = $currentDateTime->format('Y-m-d\TH:i:s.000\Z');

    $startDateTime = date('Y-m-d\TH:i:s.000\Z',strtotime('- 15 days'));
    $amazon->fn_GetPendingOrders($startDateTime, $endDateTime);
    
    $startDateTime = date('Y-m-d\TH:i:s.000\Z',strtotime('- 75 days'));
    $amazon->fn_GetShippedOrders($startDateTime, $endDateTime);
?>