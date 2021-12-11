<?php

interface StorageInterface
{
    public function getCustomerGroup($idPrice);

    public function getProductId($productModel);

    public function getProductDiscount($product_id, $customer_id);

    public function updateDiscount($int_price, $product_id, $customer_id);

    public function save($product_id, $customer_id, $int_price);

    public function getCountStoresProduct($productModel, $idStorage);

    public function getCountStore($idStorage);

    public function updateStoresProducts($quantity,$productModel, $idStorage);

    public function saveStorageProducts($productModel,$quantity,$idStorage);

    public function updateProductQuantity($quantity,$productModel);
}