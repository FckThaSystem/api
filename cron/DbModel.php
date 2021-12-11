<?php
require '../system/ModelInterface/StorageInterface.php';
require '../config.php';

class DbModel implements StorageInterface
{
    /**
     * @var PDO
     */
    private PDO $db;

    public function __construct()
    {
        $this->db = new PDO('mysql:host=localhost;dbname=api', 'root', '');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getCustomerGroup($idPrice)
    {
        $data_customer_id = $this->db->query("SELECT customer_group_id AS id FROM " . DB_PREFIX . "customer_group WHERE customer_group_id_1c = '" . $idPrice . "'");
        $data_customer_id->execute();
        $customer_id = $data_customer_id->fetch(PDO::FETCH_ASSOC);

        if($customer_id){
            return $customer_id['id'];
        }

        return $customer_id;
    }

    public function getProductId($productModel)
    {
        $data_product_id = $this->db->query("SELECT product_id AS id FROM " . DB_PREFIX . "product WHERE model = '" . $productModel . "'");
        $data_product_id->execute();
        $product_id = $data_product_id->fetch(PDO::FETCH_ASSOC);

        if($product_id){
            return $product_id['id'];
        }

        return $product_id;
    }

    public function getProductDiscount($product_id, $customer_id)
    {
        $discount_exists = $this->db->query("SELECT count(*) AS count FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . $product_id . "' AND customer_group_id='" . $customer_id . "'");
        $discount_exists->execute();
        $exists = $discount_exists->fetch(PDO::FETCH_ASSOC);

        if($exists){
            return $exists['count'];
        }

        return $exists;
    }

    public function updateDiscount($int_price, $product_id, $customer_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product_discount SET price = '" . $int_price . '.0000' . "' WHERE product_id = '" . (int)$product_id . "' AND customer_group_id='". $customer_id ."'");
    }

    public function save($product_id, $customer_id, $int_price)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET product_id = '" . (int)$product_id . "', customer_group_id='" . (int)$customer_id . "', price='" . $int_price . '.0000' . "'");
    }

    public function getCountStoresProduct($productModel, $idStorage)
    {
        $countStoresProduct = $this->db->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores_products WHERE product_model = '" . $productModel . "' AND store_alias='" . $idStorage . "'" );
        $countStoresProduct->execute();
        $exists = $countStoresProduct->fetch(PDO::FETCH_ASSOC);

        if($exists){
            return $exists['count'];
        }

        return $exists;
    }

    public function getCountStore($idStorage)
    {
        $countStores = $this->db->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores WHERE store_alias='" . $idStorage . "'" );
        $countStores->execute();
        $exists = $countStores->fetch(PDO::FETCH_ASSOC);

        if($exists){
            return $exists['count'];
        }

        return $exists;
    }

    public function updateStoresProducts($quantity, $productModel, $idStorage)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "stores_products SET quantity = '" . (int)$quantity . "' WHERE product_model = '" . (int)$productModel . "' AND store_alias='". $idStorage ."'");
    }

    public function saveStorageProducts($productModel, $quantity, $idStorage)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "stores_products SET quantity = '" . (int)$quantity . "' WHERE product_model = '" . (int)$productModel . "' AND store_alias='". $idStorage ."'");
    }

    public function updateProductQuantity($quantity, $productModel)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . $quantity . "' WHERE model = '" . (int)$productModel . "'");
    }
}