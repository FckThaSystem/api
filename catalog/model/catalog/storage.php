<?php

class ModelCatalogStorage extends Model
{
    /**
     * @param $idPrice
     * @return mixed
     */
    public function getCustomerGroup($idPrice)
    {
        $query = $this->db->query("SELECT customer_group_id AS id FROM " . DB_PREFIX . "customer_group WHERE customer_group_id_1c = '" . $idPrice . "'");

        if($query->num_rows){
            return $query->row['id'];
        }

        return $query->num_rows;
    }

    /**
     * @param $productModel
     * @return mixed
     */
    public function getProductId($productModel)
    {
        $query = $this->db->query("SELECT product_id AS id FROM " . DB_PREFIX . "product WHERE model = '" . $productModel . "'");

        if($query->num_rows){
            return $query->row['id'];
        }

        return $query->num_rows;
    }

    /**
     * @param $product_id
     * @param $customer_id
     * @return mixed
     */
    public function getProductDiscount($product_id, $customer_id){
        $query = $this->db->query("SELECT count(*) AS count FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . $product_id . "' AND customer_group_id='" . $customer_id . "'");

        return $query->row;
    }

    /**
     * @param $int_price
     * @param $product_id
     * @param $customer_id
     * @return void
     */
    public function updateDiscount($int_price, $product_id, $customer_id)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product_discount SET price = '" . $int_price . '.0000' . "' WHERE product_id = '" . (int)$product_id . "' AND customer_group_id='". $customer_id ."'");
    }

    /**
     * @param $product_id
     * @param $customer_id
     * @param $int_price
     * @return void
     */
    public function save($product_id, $customer_id, $int_price)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET product_id = '" . (int)$product_id . "', customer_group_id='" . (int)$customer_id . "', price='" . $int_price . '.0000' . "'");
    }

    /**
     * @param $productModel
     * @param $idStorage
     * @return mixed
     */
    public function getCountStoresProduct($productModel, $idStorage)
    {
        $query =  $this->db->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores_products WHERE product_model = '" . $productModel . "' AND store_alias='" . $idStorage . "'" );

        if($query->num_rows){
            return $query->row['count'];
        }

        return $query->num_rows;
    }

    /**
     * @param $idStorage
     * @return mixed
     */
    public function getCountStore($idStorage)
    {
        $query = $this->db->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores WHERE store_alias='" . $idStorage . "'");

        if($query->num_rows){
            return $query->row['count'];
        }

        return $query->num_rows;
    }

    /**
     * @param $quantity
     * @param $productModel
     * @param $idStorage
     * @return void
     */
    public function updateStoresProducts($quantity, $productModel, $idStorage)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "stores_products SET quantity = '" . (int)$quantity . "' WHERE product_model = '" . (int)$productModel . "' AND store_alias='". $idStorage ."'");
    }

    /**
     * @param $productModel
     * @param $quantity
     * @param $idStorage
     * @return void
     */
    public function saveStorageProducts($productModel, $quantity, $idStorage)
    {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "stores_products` SET product_model = '" . (int)$productModel . "', quantity = '" . (int)$quantity . "', store_alias = '". $idStorage ."'");
    }

    /**
     * @param $quantity
     * @param $productModel
     * @return void
     */
    public function updateProductQuantity($quantity, $productModel)
    {
        $this->db->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . $quantity . "' WHERE model = '" . (int)$productModel . "'");
    }
}