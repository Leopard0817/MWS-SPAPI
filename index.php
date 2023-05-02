<?php
    require_once(__DIR__ . '/vendor/autoload.php');

    use SellingPartnerApi\Configuration;
    use SellingPartnerApi\Endpoint;

    use SellingPartnerApi\Api\SellersV1Api;
    use SellingPartnerApi\Api\OrdersV0Api;
    use SellingPartnerApi\Api\ReportsV20210630Api;
    use SellingPartnerApi\Api\FeedsV20210630Api;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedDocumentSpecification;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedSpecification;
    use SellingPartnerApi\Model\ReportsV20210630\CreateReportSpecification;
    

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
        }

        private function isJson($string) {
            json_decode($string);
            return json_last_error() === JSON_ERROR_NONE;
        }

        private function fn_CreateFeedDocument(){
            $apiInstance = new FeedsV20210630Api($this->config);
            $body = new CreateFeedDocumentSpecification();
            $body->setContentType("text/xml; charset=UTF-8");
            try {
                $result = $apiInstance->createFeedDocument($body);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling FeedsV20210630Api->createFeedDocument: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_CreateTrackingXML(array $csv) {
            $dom  = new DOMDocument("1.0", "UTF-8");
            $dom->formatOutput = TRUE;
            $document = $dom->appendChild(
                $dom->createElement('AmazonEnvelope')
            );
            $document->setAttribute("xmlns:xsi", "http://www.w3.org/2001/XMLSchema-instance");
            $document->setAttribute("xsi:noNamespaceSchemaLocation", "amznenvelope.xsd");
            $header = $document->appendChild(
                $dom->createElement('Header')
            );
            $headerContent = $header->appendChild(
                $dom->createElement('DocumentVersion', '1.01')
            );
            $headerContent = $header->appendChild(
                $dom->createElement('MerchantIdentifier', 'DEI_APP')
            );
            $MessageType = $document->appendChild(
                $dom->createElement('MessageType', 'OrderFulfillment')
            );
            // All the magic here
            $lll = 444;
            foreach($csv as $row) {
                $lll++;
                // $shipping_carrier = explode("|", $row['shipping_carrier']);
                // echo ($row['selling_channel']."\n");
                // echo ("Order No.: ".$row['order-id']."\n");
                // echo ("Tracking Number: ".$row['tracking_number']."\n");
                // echo ("Shipping Carrier: ".$row['shipping_carrier']."\n");
                // echo ("Ship Method: ".$row['shipping_method']."\n");
                // echo ("Ship Date: ".$row['ship_date']."\n\n");
                
                $Message = $document->appendChild(
                    $dom->createElement('Message')
                );
                //@TODO MessageID is taking the order for now, its not necessary
                $messageid = $dom->createElement('MessageID', substr($row['order-id'], -7));
                $order = $dom->createElement('OrderFulfillment');

                $order->appendChild(
                    $dom->createElement('AmazonOrderID', $row['order-id'])
                );
                $order->appendChild(
                    // $dom->createElement('FulfillmentDate', date("Y-m-d\TH:i:s-00:00", strtotime($row['ship_date'])))
                    $dom->createElement('FulfillmentDate', date("Y-m-d\TH:i:s-00:00", strtotime($row['earliest-ship-date'])))
                );

                $orderdata = $order->appendChild(
                    $dom->createElement('FulfillmentData')
                );
                
                //New Amazon requirement 05/24/21 - If carrier is "Other" add Carrier Code
                // if (count($shipping_carrier) > 1 ) {
                    $orderdata->appendChild(
                        // $dom->createElement('CarrierCode', $shipping_carrier[0])
                        $dom->createElement('CarrierCode', 111)
                    );
                    $orderdata->appendChild(
                        // $dom->createElement('CarrierName', $shipping_carrier[1])
                        $dom->createElement('CarrierName', '222')
                    );
                // } else {
                    // $orderdata->appendChild(
                    //     // $dom->createElement('CarrierName', $row['shipping_carrier'])
                    //     $dom->createElement('CarrierName', '333')
                    // );
                // }
                
                $orderdata->appendChild(
                    // $dom->createElement('ShippingMethod', $row['shipping_method'])
                    $dom->createElement('ShippingMethod', 'Other')
                );
                $orderdata->appendChild(
                    // $dom->createElement('ShipperTrackingNumber', $row['tracking_number'])
                    $dom->createElement('ShipperTrackingNumber', $lll)
                );
                    
                
                $Message->appendChild($messageid);
                $Message->appendChild($order);
            };
            $dom->save('test.xml');
            return $dom->saveXML(); // returns the formatted XML
        }

        private function fn_ConstructFeed($filename){
            $rows   = array_map('str_getcsv', file($filename));
            $header = array_shift($rows);
            $csv    = array();
            foreach($rows as $row) {
                $csv[] = array_combine($header, $row);
            }
            $result = $this->fn_CreateTrackingXML($csv);
            return $result;
        }

        private function fn_UploadFeedData($url, $content) {

            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");

            $headers = array(
                "Content-Type: text/xml; charset=UTF-8"
            );
            curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

            $data = <<<DATA
            $content
            DATA;

            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

            $resp = curl_exec($curl);
            curl_close($curl);

            return $resp;
        }

        private function fn_UploadFeedDocument($feedUploadUrl, $feedContentFilePath){
            $fileResourceType = gettype($feedContentFilePath);

            // resource or string ? make it to a string
            if ($fileResourceType == 'resource') {
                $file_content = stream_get_contents($feedContentFilePath);
            } else {
                $file_content = file_get_contents($feedContentFilePath);
            }

            // utf8 !
            $file_content = utf8_encode($file_content);
            
            $response = $this->fn_UploadFeedData($feedUploadUrl, $file_content);

            return $response;
        }

        private function fn_CreateFeed($feedDocumentId, $feedType, $marketplaceId){
            $apiInstance = new FeedsV20210630Api($this->config);
            $body = new CreateFeedSpecification();
            $body->setFeedType($feedType);
            $body->setMarketplaceIds(array($marketplaceId));
            $body->setInputFeedDocumentId($feedDocumentId);
            try {
                $result = $apiInstance->createFeed($body);
                return $result;

            } catch (Exception $e) {
                echo 'Exception when calling FeedsV20210630Api->createFeed: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_GetFeed($feedId){
            $apiInstance = new FeedsV20210630Api($this->config);
            try {
                $result = $apiInstance->getFeed($feedId);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling FeedsV20210630Api->getFeed: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_GetFeedDocumentation($feedDocumentId){
            $apiInstance = new FeedsV20210630Api($this->config);
            try {
                $result = $apiInstance->getFeedDocument($feedDocumentId);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling FeedsV20210630Api->getFeedDocument: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_DownloadFeedProcessingReport($url, $compressionAlgorithm){
            $feed_processing_report_content = file_get_contents($url);
            if(isset($compressionAlgorithm) && $compressionAlgorithm == 'GZIP') {
                $feed_processing_report_content = gzdecode($feed_processing_report_content);
            }

            // check if report content is json encoded or not
            if ($this->isJson($feed_processing_report_content) == true) {
                $json = $feed_processing_report_content;
            } else {
                $feed_processing_report_content = preg_replace('/\s+/S', " ", $feed_processing_report_content);
                $xml = simplexml_load_string($feed_processing_report_content);
                $json = json_encode($xml);
            }

            return json_decode($json, TRUE);
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

        private function fn_DownloadReportDocument($url, $compressionAlgorithm){
            $report_document_content = file_get_contents($url);
            if(isset($compressionAlgorithm) && $compressionAlgorithm == 'GZIP') {
                $report_document_content = gzdecode($report_document_content);
            }

            $report_document_content = explode("\n", $report_document_content);
            $result = array();
            $header = array_shift($report_document_content);
            $header = explode("\t", $header);
            foreach ($report_document_content as $row){
                if ($row != ''){
                    $row = explode("\t", $row);
                    $temp = array_combine($header, $row);
                    array_push($result, $temp);
                }
            }
            return $result;
        }

        private function fn_GetOrder($order_id){
            $apiInstance = new OrdersV0Api($this->config);
            try {
                $result = $apiInstance->getOrder($order_id);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling OrdersV0Api->getOrder: ', $e->getMessage(), PHP_EOL;
            }
        }

        public function fn_GetOrders($startDateTime, $endDateTime){
            $apiInstance = new OrdersV0Api($this->config);
            $marketplace_ids = array(US_MARKETPLACE); // string[] | A list of MarketplaceId values. Used to select orders that were placed in the specified marketplaces. See the [Selling Partner API Developer Guide](https://developer-docs.amazon.com/sp-api/docs/marketplace-ids) for a complete list of marketplaceId values.
            $created_after = null; // string | A date used for selecting orders created after (or at) a specified time. Only orders placed after the specified time are returned. Either the CreatedAfter parameter or the LastUpdatedAfter parameter is required. Both cannot be empty. The date must be in ISO 8601 format.
            $created_before = null; // string | A date used for selecting orders created before (or at) a specified time. Only orders placed before the specified time are returned. The date must be in ISO 8601 format.
            $last_updated_after = $startDateTime; // string | A date used for selecting orders that were last updated after (or at) a specified time. An update is defined as any change in order status, including the creation of a new order. Includes updates made by Amazon and by the seller. The date must be in ISO 8601 format.
            $last_updated_before = null; // string | A date used for selecting orders that were last updated before (or at) a specified time. An update is defined as any change in order status, including the creation of a new order. Includes updates made by Amazon and by the seller. The date must be in ISO 8601 format.
            $order_statuses = array('Shipped', 'Pending', 'Unshipped'); // string[] | A list of `OrderStatus` values used to filter the results.
            //     // **Possible values:**
            //     // - `PendingAvailability` (This status is available for pre-orders only. The order has been placed, payment has not been authorized, and the release date of the item is in the future.)
            //     // - `Pending` (The order has been placed but payment has not been authorized.)
            //     // - `Unshipped` (Payment has been authorized and the order is ready for shipment, but no items in the order have been shipped.)
            //     // - `PartiallyShipped` (One or more, but not all, items in the order have been shipped.)
            //     // - `Shipped` (All items in the order have been shipped.)
            //     // - `InvoiceUnconfirmed` (All items in the order have been shipped. The seller has not yet given confirmation to Amazon that the invoice has been shipped to the buyer.)
            //     // - `Canceled` (The order has been canceled.)
            //     // - `Unfulfillable` (The order cannot be fulfilled. This state applies only to Multi-Channel Fulfillment orders.)
            // $fulfillment_channels = array('fulfillment_channels_example'); // string[] | A list that indicates how an order was fulfilled. Filters the results by fulfillment channel. Possible values: AFN (Fulfillment by Amazon); MFN (Fulfilled by the seller).
            // $payment_methods = array('payment_methods_example'); // string[] | A list of payment method values. Used to select orders paid using the specified payment methods. Possible values: COD (Cash on delivery); CVS (Convenience store payment); Other (Any payment method other than COD or CVS).
            // $buyer_email = 'buyer_email_example'; // string | The email address of a buyer. Used to select orders that contain the specified email address.
            // $seller_order_id = 'seller_order_id_example'; // string | An order identifier that is specified by the seller. Used to select only the orders that match the order identifier. If SellerOrderId is specified, then FulfillmentChannels, OrderStatuses, PaymentMethod, LastUpdatedAfter, LastUpdatedBefore, and BuyerEmail cannot be specified.
            $max_results_per_page = 100; // int | A number that indicates the maximum number of orders that can be returned per page. Value must be 1 - 100. Default 100.
            // $easy_ship_shipment_statuses = array('easy_ship_shipment_statuses_example'); // string[] | A list of `EasyShipShipmentStatus` values. Used to select Easy Ship orders with statuses that match the specified values. If `EasyShipShipmentStatus` is specified, only Amazon Easy Ship orders are returned.
            //     // **Possible values:**
            //     // - `PendingSchedule` (The package is awaiting the schedule for pick-up.)
            //     // - `PendingPickUp` (Amazon has not yet picked up the package from the seller.)
            //     // - `PendingDropOff` (The seller will deliver the package to the carrier.)
            //     // - `LabelCanceled` (The seller canceled the pickup.)
            //     // - `PickedUp` (Amazon has picked up the package from the seller.)
            //     // - `DroppedOff` (The package is delivered to the carrier by the seller.)
            //     // - `AtOriginFC` (The packaged is at the origin fulfillment center.)
            //     // - `AtDestinationFC` (The package is at the destination fulfillment center.)
            //     // - `Delivered` (The package has been delivered.)
            //     // - `RejectedByBuyer` (The package has been rejected by the buyer.)
            //     // - `Undeliverable` (The package cannot be delivered.)
            //     // - `ReturningToSeller` (The package was not delivered and is being returned to the seller.)
            //     // - `ReturnedToSeller` (The package was not delivered and was returned to the seller.)
            //     // - `Lost` (The package is lost.)
            //     // - `OutForDelivery` (The package is out for delivery.)
            //     // - `Damaged` (The package was damaged by the carrier.)
            // $electronic_invoice_statuses = array('electronic_invoice_statuses_example'); // string[] | A list of `ElectronicInvoiceStatus` values. Used to select orders with electronic invoice statuses that match the specified values.
            //     // **Possible values:**
            //     // - `NotRequired` (Electronic invoice submission is not required for this order.)
            //     // - `NotFound` (The electronic invoice was not submitted for this order.)
            //     // - `Processing` (The electronic invoice is being processed for this order.)
            //     // - `Errored` (The last submitted electronic invoice was rejected for this order.)
            //     // - `Accepted` (The last submitted electronic invoice was submitted and accepted.)
            // $next_token = 'next_token_example'; // string | A string token returned in the response of your previous request.
            // $amazon_order_ids = array('amazon_order_ids_example'); // string[] | A list of AmazonOrderId values. An AmazonOrderId is an Amazon-defined order identifier, in 3-7-7 format.
            // $actual_fulfillment_supply_source_id = 'actual_fulfillment_supply_source_id_example'; // string | Denotes the recommended sourceId where the order should be fulfilled from.
            // $is_ispu = True; // bool | When true, this order is marked to be picked up from a store rather than delivered.
            // $store_chain_store_id = 'store_chain_store_id_example'; // string | The store chain store identifier. Linked to a specific store in a store chain.
            // $item_approval_types = array(new \SellingPartnerApi\Model\OrdersV0\\SellingPartnerApi\Model\OrdersV0\ItemApprovalType()); // \SellingPartnerApi\Model\OrdersV0\ItemApprovalType[] | When set, only return orders that contain items which approval type is contained in the specified approval types.
            // $item_approval_status = array(new \SellingPartnerApi\Model\OrdersV0\\SellingPartnerApi\Model\OrdersV0\ItemApprovalStatus()); // \SellingPartnerApi\Model\OrdersV0\ItemApprovalStatus[] | When set, only return orders that contain items which approval status is contained in the specified approval status.
            // $data_elements = array('data_elements_example'); // string[] | An array of restricted order data elements to retrieve (valid array elements are \"buyerInfo\" and \"shippingAddress\")

            try {
                $result = $apiInstance->getOrders($marketplace_ids, $created_after, $created_before, $last_updated_after, $last_updated_before, $order_statuses, null, null, null, null, $max_results_per_page);
                $orders = $result->getPayload()->getOrders();
                if (count($orders) !== 0){
                    $fh = fopen('fileout.csv', 'w+');
                    $field = array('No', );
                    foreach ($orders as $i => $order){
                        $value = array($i+1);
                        $jsonDecoded = json_decode($order, true);
                        if (is_array($jsonDecoded)) {
                            foreach ($jsonDecoded as $key => $line) {
                                if (is_array($line)){
                                    foreach ($line as $key1 => $line1) {
                                        if ($i === 0){
                                            array_push($field, $key.'_'.$key1);
                                        }
                                        if (gettype($line1) == 'boolean'){
                                            array_push($value, $line ? 'TRUE' : 'FALSE');
                                        }
                                        else{
                                            array_push($value, $line1);
                                        }
                                    }
                                }
                                else {
                                    if ($i === 0){
                                        array_push($field, $key);
                                    }
                                    if (gettype($line) == 'boolean'){
                                        array_push($value, $line ? 'TRUE' : 'FALSE');
                                    }
                                    else{
                                        array_push($value, $line);
                                    }
                                }
                            }
                            // print
                            if ($i === 0){
                                fputcsv($fh, $field);
                            }
                            fputcsv($fh, $value);
                        }
                    }
                    fclose($fh);
                }
            } catch (Exception $e) {
                echo 'Exception when calling OrdersV0Api->getOrders: ', $e->getMessage(), PHP_EOL;
            }

            
        }

        public function fn_GetReports($startDateTime, $endDateTime){
            // step 1
            $marketplace_ids = array(US_MARKETPLACE);
            // $report_option = NULL;
            // $report_option = '"custom":"true"';
            $report_type = 'GET_FLAT_FILE_ALL_ORDERS_DATA_BY_LAST_UPDATE_GENERAL';
            // $report_type = 'GET_MERCHANT_LISTINGS_ALL_DATA';

            // POST_FULFILLMENT_ORDER_REQUEST_DATA
            
            $data_start_time = $startDateTime;
            $data_end_time = NULL;

            $result = $this->fn_CreateReport($marketplace_ids, $report_option, $report_type, $data_start_time, $data_end_time);

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
            $report_type = $result->getReportType();

            $result = $this->fn_GetReportDocument($report_document_id, $report_type);

            // step 4
            $url = $result->getUrl();
            $compression_algorithm = $result->getCompressionAlgorithm();

            $result = $this->fn_DownloadReportDocument($url, $compression_algorithm);

            // save csv
            $fh = fopen('fileout1.csv', 'w+');
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

        public function fn_ReportFeed($startDateTime, $endDateTime){
            // step 1
            $result = $this->fn_CreateFeedDocument();

            $feed_document_id = $result->getFeedDocumentId();
            $feed_document_url = $result->getUrl();

            print('<pre>');
            var_dump($feed_document_id);
            var_dump($feed_document_url);
            print('</pre>');

            // step 2
            $filename = 'amazon_orders.csv';
            $feed = $this->fn_ConstructFeed($filename);

            print('<pre>');
            print_r($feed);
            print('</pre>');

            // step 3
            $result = $this->fn_UploadFeedDocument($feed_document_url, './test.xml');

            print('<pre>');
            var_dump($result);
            print('</pre>');

            // step 4   POST_FULFILLMENT_ORDER_REQUEST_DATA
            $result = $this->fn_CreateFeed($feed_document_id, 'POST_FLAT_FILE_CONVERGENCE_LISTINGS_DATA', US_MARKETPLACE);
            $feed_id = $result->getFeedId();

            // step 5
            do {
                $result = $this->fn_GetFeed($feed_id);
                $processingStatus = $result->getProcessingStatus();
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0){
                    print('<pre>');
                    var_dump($processingStatus);
                    print('</pre>');
                    return;
                }
            } while (strcmp($processingStatus, 'DONE') != 0);
            $feed_document_id = $result->getResultFeedDocumentId();

            // step 6
            $result = $this->fn_GetFeedDocumentation($feed_document_id);
            $url = $result->getUrl();
            $compressionAlgorithm = $result->getCompressionAlgorithm();

            // step 7
            $result = $this->fn_DownloadFeedProcessingReport($url, $compressionAlgorithm);

            print('<pre>');
            var_dump($result);
            print('</pre>');
        }
    }


    $currentDateTime = new DateTime('UTC');
    $endDateTime = $currentDateTime->format('Y-m-d\TH:i:s.000\Z');
    $startDateTime = date('Y-m-d\TH:i:s.000\Z',strtotime('- 2 days'));
    $amazon = new Amazon();

    // $amazon->fn_GetOrders($startDateTime, $endDateTime);
    $amazon->fn_GetReports($startDateTime, $endDateTime);
    // $amazon->fn_ReportFeed();

?>