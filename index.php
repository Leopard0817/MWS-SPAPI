<?php
    require_once(__DIR__ . '/config.php');
    require_once(__DIR__ . '/vendor/autoload.php');

    use SellingPartnerApi\Configuration;
    use SellingPartnerApi\Endpoint;
    use SellingPartnerApi\Document;
    use SellingPartnerApi\ReportType;
    use SellingPartnerApi\FeedType;
    use SellingPartnerApi\Api\SellersV1Api;
    use SellingPartnerApi\Api\OrdersV0Api;
    use SellingPartnerApi\Api\ReportsV20210630Api;
    use SellingPartnerApi\Api\FeedsV20210630Api;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedDocumentSpecification;
    use SellingPartnerApi\Model\FeedsV20210630\CreateFeedSpecification;
    use SellingPartnerApi\Model\ReportsV20210630\CreateReportSpecification;
    use SellingPartnerApi\Model\OrdersV0\ConfirmShipmentRequest;
    use SellingPartnerApi\Model\OrdersV0\PackageDetail;
    use SellingPartnerApi\Model\OrdersV0\ConfirmShipmentOrderItem;
    
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

        public function fn_GetOrder($order_id){
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

        private function fn_CreateFeedDocument($feed_type){
            $apiInstance = new FeedsV20210630Api($this->config);

            $body = new CreateFeedDocumentSpecification(['content_type' => $feed_type['contentType']]);

            print('<pre> content type:');
            var_dump($feed_type['contentType']);
            print('</pre>');
            try {
                $result = $apiInstance->createFeedDocument($body);
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling FeedsV20210630Api->createFeedDocument: ', $e->getMessage(), PHP_EOL;
            }
        }

        private function fn_CreateOrderTrackingXML(array $csv) {
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
            foreach($csv as $row) {
                
                $shipping_carrier = explode("|", $row['shipping_carrier']);
                
                $Message = $document->appendChild(
                    $dom->createElement('Message')
                );
                //@TODO MessageID is taking the order for now, its not necessary
                $messageid = $dom->createElement('MessageID', substr($row['order_no'], -7));
                $order = $dom->createElement('OrderFulfillment');

                $order->appendChild(
                    $dom->createElement('AmazonOrderID', $row['order_no'])
                );
                $order->appendChild(
                    $dom->createElement('FulfillmentDate', date("Y-m-d\TH:i:s-00:00", strtotime($row['ship_date'])))
                );

                $orderdata = $order->appendChild(
                    $dom->createElement('FulfillmentData')
                );
                
                //New Amazon requirement 05/24/21 - If carrier is "Other" add Carrier Code
                if (count($shipping_carrier) > 1 ) {
                    $orderdata->appendChild(
                        $dom->createElement('CarrierCode', $shipping_carrier[0])
                    );
                    $orderdata->appendChild(
                        $dom->createElement('CarrierName', $shipping_carrier[1])
                    );
                } else {
                    $orderdata->appendChild(
                        $dom->createElement('CarrierName', $row['shipping_carrier'])
                    );
                }
                
                $orderdata->appendChild(
                    $dom->createElement('ShippingMethod', $row['shipping_method'])
                );
                $orderdata->appendChild(
                    $dom->createElement('ShipperTrackingNumber', $row['tracking_number'])
                );
                    
                
                $Message->appendChild($messageid);
                $Message->appendChild($order);
            };
            $dom->save('order_feed.xml');
            return $dom->saveXML();
        }

        private function fn_CreateInventoryPriceXML(array $csv) {
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
                $dom->createElement('MessageType', 'Price')
            );
            // All the magic here
            foreach($csv as $row) {
                
                $shipping_carrier = explode("|", $row['shipping_carrier']);
                
                $Message = $document->appendChild(
                    $dom->createElement('Message')
                );
                //@TODO MessageID is taking the order for now, its not necessary
                $messageid = $dom->createElement('MessageID', substr($row['order_no'], -7));
                $order = $dom->createElement('OrderFulfillment');

                $order->appendChild(
                    $dom->createElement('AmazonOrderID', $row['order_no'])
                );
                $order->appendChild(
                    $dom->createElement('FulfillmentDate', date("Y-m-d\TH:i:s-00:00", strtotime($row['ship_date'])))
                );

                $orderdata = $order->appendChild(
                    $dom->createElement('FulfillmentData')
                );
                
                //New Amazon requirement 05/24/21 - If carrier is "Other" add Carrier Code
                if (count($shipping_carrier) > 1 ) {
                    $orderdata->appendChild(
                        $dom->createElement('CarrierCode', $shipping_carrier[0])
                    );
                    $orderdata->appendChild(
                        $dom->createElement('CarrierName', $shipping_carrier[1])
                    );
                } else {
                    $orderdata->appendChild(
                        $dom->createElement('CarrierName', $row['shipping_carrier'])
                    );
                }
                
                $orderdata->appendChild(
                    $dom->createElement('ShippingMethod', $row['shipping_method'])
                );
                $orderdata->appendChild(
                    $dom->createElement('ShipperTrackingNumber', $row['tracking_number'])
                );
                    
                
                $Message->appendChild($messageid);
                $Message->appendChild($order);
            };
            $dom->save('order_feed.xml');
            return $dom->saveXML();
        }

        private function fn_ConstructOrderFeed($file_path){
            if (!file_exists($file_path)) {
                die("There is not CSV file to process. Enjoy the life :-)");
            }
            $rows   = array_map('str_getcsv', file($file_path));
            $header = array_shift($rows);
            $csv    = array();
            foreach($rows as $row) {
                $csv[] = array_combine($header, $row);
            }
            $result = $this->fn_CreateOrderTrackingXML($csv);
            return $result;
        }

        private function fn_CreatePriceXML(array $csv){
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
                $dom->createElement('MessageType', 'Price')
            );
            // All the magic here
            foreach($csv as $i => $row) {
                $Message = $document->appendChild(
                    $dom->createElement('Message')
                );
                //@TODO MessageID is taking the order for now, its not necessary
                $messageid = $dom->createElement('MessageID', $i + 1);
                $order = $dom->createElement('Price');

                $order->appendChild(
                    $dom->createElement('SKU', $row['SKU'])
                );
                $order->appendChild(
                    $dom->createElement('StandardPrice', $row['Price'])
                );
                $order->appendChild(
                    $dom->createElement('MinimumSellerAllowedPrice', $row['MinimumSellerAllowedPrice'])
                );
                $order->appendChild(
                    $dom->createElement('MaximumSellerAllowedPrice', $row['MaximumSellerAllowedPrice'])
                );
                $order->appendChild(
                    $dom->createElement('BusinessPrice', $row['BusinessPrice'])
                );
                $order->appendChild(
                    $dom->createElement('QuantityPriceType', $row['QuantityPriceType'])
                );

                $quantity_price = $dom->createElement('QuantityPrice');

                $quantity_price->appendChild(
                    $dom->createElement('QuantityLowerBound1', $row['QuantityLowerBound1'])
                );
                $quantity_price->appendChild(
                    $dom->createElement('QuantityPrice1', $row['QuantityPrice1'])
                );
                $quantity_price->appendChild(
                    $dom->createElement('QuantityLowerBound2', $row['QuantityLowerBound2'])
                );
                $quantity_price->appendChild(
                    $dom->createElement('QuantityPrice2', $row['QuantityPrice2'])
                );
                $quantity_price->appendChild(
                    $dom->createElement('QuantityLowerBound3', $row['QuantityLowerBound3'])
                );
                $quantity_price->appendChild(
                    $dom->createElement('QuantityPrice3', $row['QuantityPrice3'])
                );

                $order->appendChild($quantity_price);

                $order->appendChild(
                    $dom->createElement('PricingAction', $row['PricingAction'])
                );

                $Message->appendChild($messageid);
                $Message->appendChild($order);
            };
            $dom->save('price_feed.xml');
            return $dom->saveXML();
        }

        private function fn_CreateInvLoaderXML(array $csv){
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
                $dom->createElement('MessageType', 'Price')
            );
            // All the magic here
            foreach($csv as $i => $row) {
                $Message = $document->appendChild(
                    $dom->createElement('Message')
                );
                //@TODO MessageID is taking the order for now, its not necessary
                $messageid = $dom->createElement('MessageID', $i + 1);
                $order = $dom->createElement('Price');

                $order->appendChild(
                    $dom->createElement('SKU', $row['sku'])
                );
                $order->appendChild(
                    $dom->createElement('StandardPrice', $row['price'])
                );
                $order->appendChild(
                    $dom->createElement('MinimumSellerAllowedPrice', $row['minimum-seller-allowed-price'])
                );
                $order->appendChild(
                    $dom->createElement('MaximumSellerAllowedPrice', $row['maximum-seller-allowed-price'])
                );
                $order->appendChild(
                    $dom->createElement('Quantity', $row['quantity'])
                );
                $order->appendChild(
                    $dom->createElement('Add-Delete', $row['add-delete'])
                );
                $order->appendChild(
                    $dom->createElement('Handling-time', $row['handling-time'])
                );
                $order->appendChild(
                    $dom->createElement('Merchant_shipping_group_name', $row['merchant_shipping_group_name'])
                );

                $Message->appendChild($messageid);
                $Message->appendChild($order);
            };
            $dom->save('inv_loader_feed.xml');
            return $dom->saveXML();
        }

        private function fn_ConstructInventoryFeed($file_path){
            if (!file_exists($file_path)) {
                die("There is not CSV file to process. Enjoy the life :-)");
            }
            // $rows   = array_map('str_getcsv', file($file_path));
            // $header = array_shift($rows);
            // $csv    = array();
            // foreach($rows as $row) {
            //     $csv[] = array_combine($header, $row);
            // }
            $lines = file($file_path);
            $header = array_shift($lines);
            $header = preg_replace("/[\r\n]+/", "", $header);
            $header = explode("\t", $header);
            $csv = array();
            foreach ($lines as $line) {
                $row = preg_replace("/[\r\n]+/", "", $line);
                $row = explode("\t", $row);
                $csv[] = array_combine($header, $row);
            }
            print('<pre>');
            var_dump($csv);
            print('</pre>');
            // $result = $this->fn_CreatePriceXML($csv);
            $result = $this->fn_CreateInvLoaderXML($csv);
            // return $result;
        }

        private function fn_UploadFeedDocument($feed_document_info, $feed_type, $feed){
            $feedContents = file_get_contents($feed);
            $docToUpload = new Document($feed_document_info, $feed_type);
            $docToUpload->upload($feedContents);
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
            $start_time = microtime(true);
            $result = $this->fn_GetReport($report_id);
            $processingStatus = $result->getProcessingStatus();
            while (strcmp($processingStatus, 'DONE') != 0) {
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0) {
                    $execution_time = microtime(true) - $start_time;
                    $sleep_time = max(0, 0.5 - $execution_time);
                    usleep($sleep_time * 1000000);
                    $start_time = microtime(true);
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
                usleep(60000);
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
                if(!!$order_temp->getDefaultShipFromLocationAddress()) {
                    $row[] = $order_temp->getDefaultShipFromLocationAddress()->getPhone(); // buyer-phone-number
                } else {
                    $row[] = '';
                }
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
                if(!!$order_temp->getDefaultShipFromLocationAddress()) {
                    $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine1(); // ship-address-1
                    $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine2(); // ship-address-2
                    $row[] = $order_temp->getDefaultShipFromLocationAddress()->getAddressLine3(); // ship-address-3
                } else {
                    $row[] = '';
                    $row[] = '';
                    $row[] = '';
                }
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
                $start_time = microtime(true);
                $result = $this->fn_GetReport($report_id);
                $processingStatus = $result->getProcessingStatus();
                while (strcmp($processingStatus, 'DONE') != 0) {
                    if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0) {
                        $execution_time = microtime(true) - $start_time;
                        $sleep_time = max(0, 0.5 - $execution_time);
                        usleep($sleep_time * 1000000);
                        $start_time = microtime(true);
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

        public function fn_OrderFulfillment($file_path){
            // step 1
            $feed_type = FeedType::POST_ORDER_FULFILLMENT_DATA;
            $feed_document_info = $this->fn_CreateFeedDocument($feed_type);
            $feed_document_id = $feed_document_info->getFeedDocumentId();

            // step 2
            $feed = $this->fn_ConstructOrderFeed($file_path);

            // step 3
            $file_path = './order_feed.xml';
            $result = $this->fn_UploadFeedDocument($feed_document_info, $feed_type, $feed);

            // step 4
            $result = $this->fn_CreateFeed($feed_document_id, $feed_type['name'], US_MARKETPLACE);
            $feed_id = $result->getFeedId();

            // step 5
            $start_time = microtime(true);
            $result = $this->fn_GetFeed($feed_id);
            $processingStatus = $result->getProcessingStatus();
            while (strcmp($processingStatus, 'DONE') != 0) {
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0){
                    $execution_time = microtime(true) - $start_time;
                    $sleep_time = max(0, 0.5 - $execution_time);
                    usleep($sleep_time * 1000000);
                    $start_time = microtime(true);
                    $result = $this->fn_GetFeed($feed_id);
                    $processingStatus = $result->getProcessingStatus();
                }
                else{
                    return;
                }
            }
            $feed_document_id = $result->getResultFeedDocumentId();

            // step 6
            $result = $this->fn_GetFeedDocumentation($feed_document_id);

            // step 7
            $docToDownload = new Document($result, $feed_type);
            $content = $docToDownload->download();
            $result = $docToDownload->getData();
            return $result;
        }

        public function test($file_path){
            $this->fn_ConstructInventoryFeed($file_path);
        }

        public function fn_InventoryFeed(){
            $file_path = __DIR__ . '/POST_FLAT_FILE_INVLOADER_DATA.txt';
            $feedContents = file_get_contents($file_path);

            if($feedContents != false) {
                $this->fn_SubmitInventoryFeed(true);
            }

            print('<pre> feed contents:');
            var_dump($feedContents);
            print('</pre>');
        }

        public function fn_SubmitInventoryFeed($flat_file){
            // step 1
            $feed_type = $flat_file ? FeedType::POST_FLAT_FILE_INVLOADER_DATA : FeedType::POST_PRODUCT_PRICING_DATA;
            
            $feed_document_info = $this->fn_CreateFeedDocument($feed_type);

            $feed_document_id = $feed_document_info->getFeedDocumentId();

            // step 2
            // $file_path = './amazon_orders.csv';
            // $feed = $this->fn_ConstructInventoryFeed($file_path);

            // print('<pre>');
            // print_r($feed);
            // print('</pre>');

            // step 3
            $file_path = __DIR__ . ($flat_file ? '/POST_FLAT_FILE_INVLOADER_DATA.txt' : '/feed1.xml');
            // $file_path = __DIR__ . '/POST_FLAT_FILE_INVLOADER_DATA.txt';
            // $file_path = __DIR__ . '/feed1.xml';
            $result = $this->fn_UploadFeedDocument($feed_document_info, $feed_type, $file_path);

            
            // step 4
            $result = $this->fn_CreateFeed($feed_document_id, $feed_type['name'], US_MARKETPLACE);
            $feed_id = $result->getFeedId();

            print('<pre> feed_id:');
            var_dump($feed_id);
            print('</pre>');

            // step 5
            $start_time = microtime(true);
            $result = $this->fn_GetFeed($feed_id);
            $processingStatus = $result->getProcessingStatus();
            while (strcmp($processingStatus, 'DONE') != 0) {
                if (strcmp($processingStatus, 'CANCELLED') != 0 && strcmp($processingStatus, 'FATAL') != 0){
                    $execution_time = microtime(true) - $start_time;
                    $sleep_time = max(0, 0.5 - $execution_time);
                    usleep($sleep_time * 1000000);
                    $start_time = microtime(true);
                    $result = $this->fn_GetFeed($feed_id);
                    $processingStatus = $result->getProcessingStatus();
                }
                else{
                    return;
                }
            }

            print('<pre> get_feed:');
            var_dump($result);
            print('</pre>');

            $feed_document_id = $result->getResultFeedDocumentId();

            // step 6
            $result = $this->fn_GetFeedDocumentation($feed_document_id);
            $url = $result->getUrl();
            $compressionAlgorithm = $result->getCompressionAlgorithm();

            print('<pre>');
            var_dump($url);
            var_dump($compressionAlgorithm);
            print('</pre>');

            // step 7
            $docToDownload = new Document($result, $feed_type);
            $content = $docToDownload->download();
            $result = $docToDownload->getData();

            print('<pre>');
            var_dump($result);
            print('</pre>');
        }

        public function fn_GetOrderBuyerInfo($order_id) {
            $apiInstance = new OrdersV0Api($this->config);
            try {
                $result = $apiInstance->getOrderBuyerInfo($order_id);
                print('<pre>');
                var_dump($result);
                print('</pre>');
                return $result;
            } catch (Exception $e) {
                echo 'Exception when calling OrdersV0Api->getOrderBuyerInfo: ', $e->getMessage(), PHP_EOL;
            }
        }
    }
?>