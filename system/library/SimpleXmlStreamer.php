<?php
require('xml/XmlExchange.php');
class SimpleXmlStreamer extends XmlExchange {

    private $dbModel = null;

    public function setModel(
        $dbModel
    ) {
        $this->dbModel = $dbModel;
    }

    public function processNode($xmlString, $elementName, $nodeIndex) {
        $model = simplexml_load_string($xmlString);

        $productModel = $model->attributes()->ID->__toString();

        // get product id
        $data_product_id = $this->dbModel->getProductId($productModel);
        $dataProductDiscounts = $this->dbModel->getProductDiscounts($data_product_id);

        // customers groups
        foreach ($model->prices->price as $price) {
            $idPrice = $price->attributes()->ID->__toString();
            $int_price = ceil((int)str_replace(" ", "", $price->__toString()));

            $data_customer_id = $this->dbModel->getCustomerGroup($idPrice);

            // get product discount
            if($data_customer_id && $data_product_id){
                $arrayFilter = array_filter($dataProductDiscounts, function($v, $k) use ($data_customer_id) {
                    return $v['customer_group_id'] == $data_customer_id;
                }, ARRAY_FILTER_USE_BOTH);

                if($arrayFilter){
                    $this->dbModel->updateDiscount($int_price,(int)$data_product_id,$data_customer_id);
                }else{
                    $this->dbModel->save((int)$data_product_id, (int)$data_customer_id, $int_price);
                }
            }
        }

        // stores
        $quantity = 0;
        foreach ($model->storages->storage as $storage) {
            $idStorage = $storage->attributes()->ID->__toString();
            $result = $this->dbModel->getCountStoresProduct($productModel,$idStorage);
            $data_stores = $this->dbModel->getCountStore($idStorage);

            if($result){
                $this->dbModel->updateStoresProducts((int)$storage->__toString(),(int)$productModel,$idStorage);
            }else{
                if($data_stores){
                    $this->dbModel->saveStorageProducts((int)$productModel,(int)$storage->__toString(),$idStorage);
                }
            }
            $quantity = $quantity + (int)$storage;
        }
        $this->dbModel->updateProductQuantity($quantity,(int)$productModel);

        return true;
    }
}