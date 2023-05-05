<?php
    require_once(__DIR__ . '/vendor/autoload.php');

    use SellingPartnerApi\Configuration;
    use SellingPartnerApi\Endpoint;
    use SellingPartnerApi\Document;
    use SellingPartnerApi\Api\SellersV1Api;
    use SellingPartnerApi\Api\OrdersV0Api;
    use SellingPartnerApi\Api\ReportsV20210630Api;
    use SellingPartnerApi\ReportType;
    use SellingPartnerApi\Api\FeedsV20210630Api;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedDocumentSpecification;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedSpecification;
    use SellingPartnerApi\Model\ReportsV20210630\CreateReportSpecification;
    use SellingPartnerApi\Model\OrdersV0\ConfirmShipmentRequest;
    use SellingPartnerApi\Model\OrdersV0\PackageDetail;
    use SellingPartnerApi\Model\OrdersV0\ConfirmShipmentOrderItem;

    define('LWA_CLIENT_ID', 'amzn1.application-oa2-client.c61e4e0db5d04b1aae280454412cae23');
    define('LWA_CLIENT_SECRET', 'amzn1.oa2-cs.v1.9c67e9db746644379b864887d6da051458d57f3d655374c588f3d15d1398fa80');
    define('LWA_REFRESH_TOKEN', 'Atzr|IwEBIMog3_zCD6umGDnhLthHP6bhPQqRv5eQn3znMSH5bt699WFWrgSX6gHOel8GzqtwV2WwCCv14dFP8M66PAv0s3CQp0kH-jIsP-iDjR-HRycURRzsPSx07pFOhAqBaZQNvRbkCf82nvJo4SC6R5CD9l_i-7NFCvmgEP84FYbT90fa8n_Y4hgyz3z5_xQgRWm2N5BoA14difFTm8ppnXmRAnuQXmuyjIVmXiyMXOFIUcn5Ci-nzhOeuacsDm86NZ_f5U9BWrZFxiVPnQlZYejDnKmjywinFBLVIjsCiVQ1WA4BsFcyNKknz1GCOSy-LyXVG3k');

    define('AWS_ACCESS_KEY_ID', 'AKIAT5MW4DNGXPKSJ3OV');
    define('AWS_SECRET_KEY', '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');

    define('ROLE_ARN', 'arn:aws:iam::269288741709:role/Developer');

    define('US_MARKETPLACE', 'ATVPDKIKX0DER');
    
    class Amazon {
        private $config;

        public function __construct(){
            $this->config = new Configuration([
                "lwaClientId" => LWA_CLIENT_ID,
                "lwaClientSecret" => LWA_CLIENT_SECRET,
                "lwaRefreshToken" => LWA_REFRESH_TOKEN,
                "awsAccessKeyId" => AWS_ACCESS_KEY_ID,
                "awsSecretAccessKey" => AWS_SECRET_KEY,
                "roleArn" => ROLE_ARN,
                // "endpoint" => Endpoint::NA_SANDBOX  // or another endpoint from lib/Endpoints.php,
                "endpoint" => Endpoint::NA  // or another endpoint from lib/Endpoints.php,
            ]);
            set_time_limit (0);
        }

        private function fn_CreateReport($marketplace_ids, $report_option = NULL, $report_type, $data_start_time, $data_end_time = NULL){
            $apiInstance = new ReportsV20210630Api($this->config);
            $body = new CreateReportSpecification();
            try {
                $body->setMarketplaceIds($marketplace_ids);
                if ($report_option != NULL){
                    $body->setReportOptions($report_option);
                }
                $body->setReportType($report_type);
                $body->setDataStartTime($data_start_time);
                if ($data_end_time != NULL){
                    $body->setDataEndTime($data_end_time);
                }
                $result = $apiInstance->createReport($body);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling ReportsV20210630Api->createReport: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_GetReport($reportId){
            $apiInstance = new ReportsV20210630Api($this->config);
            try {
                $result = $apiInstance->getReport($reportId);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling ReportsV20210630Api->getReport: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_GetReportDocument($report_document_id, $report_type){
            $apiInstance = new ReportsV20210630Api($this->config);
            try {
                $result = $apiInstance->getReportDocument($report_document_id, $report_type);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling ReportsV20210630Api->getReportDocument: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_GetOrder($order_id){
            $apiInstance = new OrdersV0Api($this->config);
            try {
                $result = $apiInstance->getOrder($order_id);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling OrdersV0Api->getOrder: ', $e->getMessage(), PHP_EOL;
                return null;
            }
        }
        
        private function fn_GetOrderItems($order_id){
            $apiInstance = new OrdersV0Api($this->config);
            try {
                $result = $apiInstance->getOrderItems($order_id);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling OrdersV0Api->getOrderItems: ', $e->getMessage(), PHP_EOL;
            }

        }

        public function fn_GetPendingOrders($startDateTime, $endDateTime){
            // step 1
            $report_option = NULL;
            $report_type = ReportType::GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL;
            $marketplace_ids = array(US_MARKETPLACE);
            $data_start_time = $startDateTime;
            $data_end_time = $endDateTime;
            $result = $this->fn_CreateReport($marketplace_ids, $report_option, $report_type['name'], $data_start_time, $data_end_time);

            // step 2
            $report_id = $result->getReportId();
            $result = $this->fn_GetReport($report_id);
            $processingStatus = $result->getProcessingStatus();
            while (strcmp($processingStatus, 'DONE') != 0) {
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0) {
                    usleep(500);
                    $result = $this->fn_GetReport($report_id);
                    $processingStatus = $result->getProcessingStatus();
                }
                else {
                    return;
                }
            }

            // step 3
            $report_document_id = $result->getReportDocumentId();
            $result = $this->fn_GetReportDocument($report_document_id, $report_type['name']);

            // step 4
            $docToDownload = new Document($result, $report_type);
            $docToDownload->download();
            $result = $docToDownload->getData();

            // save csv
            $currentDateTime = new DateTime('UTC');
            $currentDateTime = $currentDateTime->format('Y-m-d_H-i-s');
            $fh = fopen("pending_orders_$currentDateTime.csv", 'w+');
            $header = array('No', 'order-id',  'order-status', 'order-item-id', 'purchase-date', 'payments-date', 
                'buyer-email', 'buyer-name', 'buyer-phone-number', 'buyer-phone-number', 'sku', 
                'product-name', 'quantity-purchased', 'currency', 'item-price', 'item-tax', 'shipping-price', 
                'shipping-tax', 'ship-service-level', 'ship-service-name', 'recipient-name', 'ship-address-1', 
                'ship-address-2', 'ship-address-3', 'address-type', 'ship-city', 'ship-state', 
                'ship-postal-code', 'ship-country', 'item-promotion-discount', 'item-promotion-id', 
                'ship-promotion-discount', 'ship-promotion-id', 'delivery-start-date', 'delivery-end-date', 
                'delivery-time-zone', 'delivery-Instructions', 'sales-channel', 'earliest-ship-date', 
                'latest-ship-date', 'earliest-delivery-date', 'latest-delivery-date', 
                'is-business-order', 'purchase-order-number', 'price-designation', 
                'is-prime', 'buyer-company-name', 'signature-confirmation-recommended');
            fputcsv($fh, $header);
            $i = 0;
            foreach ($result as $order){
                if ($order['order-status'] != 'Pending' && $order['order-status'] != 'Unshipped'){
                    continue;
                }
                $row = array($i + 1);
                $i++;
                usleep(10000);
                $order_temp = $this->fn_GetOrder($order['amazon-order-id'])->getPayload();
                $order_items_list = $this->fn_GetOrderItems($order['amazon-order-id']);
                $order_item = $order_items_list->getPayload()->getOrderItems()[0];
                $row[] = $order['amazon-order-id']; // order-id
                $row[] = $order['order-status']; // order-status
                $row[] = $order_item->getOrderItemId(); // order-item-id
                $row[] = $order['purchase-date']; // purchase-date
                $row[] = ''; // payments-date
                $row[] = $order_temp->getBuyerInfo()->getBuyerEmail(); // buyer-email
                $row[] = $order_temp->getBuyerInfo()->getBuyerName(); // buyer-name
                $row[] = ''; // buyer-phone-number
                $row[] = $order_temp->getDefaultShipFromLocationAddress()->getPhone(); // buyer-phone-number
                $row[] = $order_item->getSellerSku(); // sku
                $row[] = $order['product-name']; // product-name
                $row[] = $order['quantity']; // quantity-purchased
                $row[] = $order['currency']; // currency
                $row[] = $order['item-price']; // item-price
                $row[] = $order['item-tax']; // item-tax
                $row[] = $order['shipping-price']; // shipping-price
                $row[] = $order['shipping-tax']; // shipping-tax
                $row[] = $order['ship-service-level']; // ship-service-level
                $row[] = $order_temp->getShipServiceLevel(); // ship-service-name
                $row[] = ''; // recipient-name
                $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine1(); // ship-address-1
                $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine2(); // ship-address-2
                $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine3(); // ship-address-3
                $row[] = $order['address-type']; // address-type
                $row[] = $order['ship-city']; // ship-city
                $row[] = $order['ship-state']; // ship-state
                $row[] = $order['ship-postal-code']; // ship-postal-code
                $row[] = $order['ship-country']; // ship-country
                $row[] = $order['item-promotion-discount']; // item-promotion-discount
                $row[] = ''; // item-promotion-id
                $row[] = $order['ship-promotion-discount']; // ship-promotion-discount
                $row[] = ''; // ship-promotion-id
                $row[] = $order_item->getScheduledDeliveryStartDate(); // delivery-start-date
                $row[] = $order_item->getScheduledDeliveryEndDate(); // delivery-end-date
                $row[] = ''; // delivery-time-zone
                $row[] = ''; // delivery-Instructions
                $row[] = $order['sales-channel'];
                $row[] = $order_temp->getEarliestShipDate(); // earliest-ship-date
                $row[] = $order_temp->getLatestShipDate(); // latest-ship-date
                $row[] = $order_temp->getEarliestDeliveryDate(); // earliest-delivery-date
                $row[] = $order_temp->getLatestDeliveryDate(); // latest-delivery-date
                $row[] = $order['is-business-order']; // $order_temp->getIsBusinessOrder(); // is-business-order
                $row[] = $order['purchase-order-number']; // purchase-order-number
                $row[] = $order['price-designation']; // price-designation
                $row[] = $order_temp->getIsPrime() ? 'TRUE' : 'FALSE'; // is-prime
                $row[] = $order['buyer-company-name']; // buyer-company-name
                $row[] = $order['signature-confirmation-recommended']; // signature-confirmation-recommended

                fputcsv($fh, $row);
            }
            fclose($fh);
        }

        public function fn_GetShippedOrders($startDateTime, $endDateTime){
            // step 1
            $report_option = NULL;
            $report_type = ReportType::GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL;
            $marketplace_ids = array(US_MARKETPLACE);
            $order_result = array();
            $data_end_time = $endDateTime;
            $startTime = new DateTime($startDateTime);
            $end = false;
            do {
                $endTime = new DateTime($data_end_time);
                $time_diff = date_diff($startTime, $endTime);
                if ($time_diff->days > 30 && $time_diff->invert == 0){
                    $data_start_time = date('Y-m-d\TH:i:s.000\Z', strtotime('- 30 days', strtotime($data_end_time)));
                }
                else{
                    $data_start_time = $startDateTime;
                    $end = true;
                }
                $result = $this->fn_CreateReport($marketplace_ids, $report_option, $report_type['name'], $data_start_time, $data_end_time);

                // step 2
                $report_id = $result->getReportId();
                $result = $this->fn_GetReport($report_id);
                $processingStatus = $result->getProcessingStatus();
                while (strcmp($processingStatus, 'DONE') != 0) {
                    if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0) {
                        usleep(500);
                        $result = $this->fn_GetReport($report_id);
                        $processingStatus = $result->getProcessingStatus();
                    }
                    else {
                        return;
                    }
                }
                
                // step 3
                $report_document_id = $result->getReportDocumentId();
                $result = $this->fn_GetReportDocument($report_document_id, $report_type['name']);

                // step 4
                $docToDownload = new Document($result, $report_type);
                $docToDownload->download();
                $result = $docToDownload->getData();

                $order_result = array_merge($order_result, $result);

                $data_end_time = $data_start_time;
            } while ($end == false);

            // save csv
            $currentDateTime = new DateTime('UTC');
            $currentDateTime = $currentDateTime->format('Y-m-d_H-i-s');
            $fh = fopen("shipped_orders_$currentDateTime.csv", 'w+');
            $header = array('No');
            $i = 1;
            foreach ($order_result as $j => $order){
                $row = array($i);
                foreach ($order as $key => $col) {
                    if ($j === 0){
                        array_push($header, $key);
                    }
                    array_push($row, $col);
                }
                if ($j === 0){
                    fputcsv($fh, $header);
                }
                if ($order['order-status'] != 'Shipped'){
                    continue;
                }
                $i++;
                fputcsv($fh, $row);
            }
            fclose($fh);
        }

        public function fn_GetInventoryReports($startDateTime, $endDateTime){
            // step 1
            $report_option = '"custom":"true"';
            $report_type = ReportType::GET_MERCHANT_LISTINGS_ALL_DATA;
            $marketplace_ids = array(US_MARKETPLACE);
            $data_start_time = $startDateTime;
            $data_end_time = NULL;

            $result = $this->fn_CreateReport($marketplace_ids, $report_option, $report_type['name'], $data_start_time, $data_end_time);

            // step 2
            $report_id = $result->getReportId();

            $result = $this->fn_GetReport($report_id);
            $processingStatus = $result->getProcessingStatus();
            while (strcmp($processingStatus, 'DONE') != 0) {
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0) {
                    usleep(500);
                    $result = $this->fn_GetReport($report_id);
                    $processingStatus = $result->getProcessingStatus();
                }
                else {
                    return;
                }
            }

            // step 3
            $report_document_id = $result->getReportDocumentId();
            $result = $this->fn_GetReportDocument($report_document_id, $report_type['name']);

            // step 4
            $docToDownload = new Document($result, $report_type);
            $docToDownload->download();
            $result = $docToDownload->getData();
            
            // save csv
            $currentDateTime = new DateTime('UTC');
            $currentDateTime = $currentDateTime->format('Y-m-d_H-i-s');
            $fh = fopen("inventory_report_$currentDateTime.csv", 'w+');
            $field = array('No');
            foreach ($result as $i => $order){
                $row = array($i+1);
                foreach ($order as $key => $col) {
                    if ($i === 0){
                        array_push($field, $key);
                    }
                    array_push($row, $col);
                }
                if ($i === 0){
                    fputcsv($fh, $field);
                }
                fputcsv($fh, $row);
            }
            fclose($fh);
        }
    }
?>