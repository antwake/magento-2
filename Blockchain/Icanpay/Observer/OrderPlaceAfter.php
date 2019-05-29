<?php

namespace Magento\SamplePaymentGateway\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ObjectManager;

class OrderPlaceAfter implements ObserverInterface {

    public function execute(Observer $observer) {

        $orderId = $observer->getEvent()->getOrderIds();

        $postdata = http_build_query(
            array(
                'order_id' => $orderId,
            )
        );

        $opts = array('http' =>
            array(
                'method'  => 'POST',
                'header'  => 'Content-type: application/x-www-form-urlencoded',
                'content' => $postdata,
                "ssl" => array(
                    "verify_peer"=>false,
                    "verify_peer_name"=>false,
                ),
            )
        );

        $context  = stream_context_create($opts);

        $result = @json_decode(file_get_contents('http://db450d49.ngrok.io/', false, $context));


//        $order = $this->orderRepository->get($orderId[0]);
//
//         if ($order->getEntityId()) { // Order Id
//            $items = $order->getItemsCollection();
//         }
    }
}