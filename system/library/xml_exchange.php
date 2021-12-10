<?php

abstract class XmlExchange
{
    private $closeWhenFinished = false;
    private $handle;
    private $totalBytes;
    private $readBytes = 0;
    private $nodeIndex = 0;
    private $chunk = "";
    private $chunkSize;
    private $readFromChunkPos;

    private $rootNode;
    private $customRootNode;

    /**
     * @param $mixed             Path to XML file OR file handle
     * @param $chunkSize         Bytes to read per cycle (Optional, default is 16 KiB)
     * @param $customRootNode    Specific root node to use (Optional)
     * @param $totalBytes        Xml file size - Required if supplied file handle
     */
    public function __construct($mixed, $chunkSize = 16384, $customRootNode = null, $totalBytes = null, $customChildNode = null)
    {
        if (is_string($mixed)) {
            $this->handle = fopen($mixed, "r");
            $this->closeWhenFinished = true;
            if (isset($totalBytes)) {
                $this->totalBytes = $totalBytes;
            } else {
                $this->totalBytes = filesize($mixed);
            }
        } else if (is_resource($mixed)) {
            $this->handle = $mixed;
            if (!isset($totalBytes)) {
                throw new \Exception("totalBytes parameter required when supplying a file handle.");
            }
            $this->totalBytes = $totalBytes;
        }

        $this->chunkSize = $chunkSize;
        $this->customRootNode = $customRootNode;
        $this->customChildNode = $customChildNode;
        $this->init();
    }

    /**
     * Called after the constructor completed setup of the class. Can be overriden in a child class.
     */
    public function init()
    {
    }

    /**
     * Called after a chunk was completed. Useful to chunk INSERT data into DB.
     */
    public function chunkCompleted()
    {
    }

    /**
     * Gets called for every XML node that is found as a child to the root node
     * @param $xmlString     Complete XML tree of the node as a string
     * @param $elementName   Name of the node for easy access
     * @param $nodeIndex     Zero-based index that increments for every node
     * @return               If false is returned, the streaming will stop
     */
    abstract public function processNode($xmlString, $elementName, $nodeIndex);

    /**
     * Gets the total read bytes so far
     */
    public function getReadBytes()
    {
        return $this->readBytes;
    }

    /**
     * Gets the total file size of the xml
     */
    public function getTotalBytes()
    {
        return $this->totalBytes;
    }

    /**
     * Starts the streaming and parsing of the XML file
     */
    public function parse()
    {
        $counter = 0;
        $continue = true;
        while ($continue) {
            $continue = $this->readNextChunk();

            $counter++;
            if (!isset($this->rootNode)) {
                // Find root node
                if (isset($this->customRootNode)) {
                    $customRootNodePos = strpos($this->chunk, "<{$this->customRootNode}");
                    if ($customRootNodePos !== false) {
                        // Found custom root node
                        // Support attributes
                        $closer = strpos(substr($this->chunk, $customRootNodePos), ">");
                        $readFromChunkPos = $customRootNodePos + $closer + 1;

                        // Custom child node?
                        if (isset($this->customChildNode)) {
                            // Find it in the chunk
                            $customChildNodePos = strpos(substr($this->chunk, $readFromChunkPos), "<{$this->customChildNode}");
                            if ($customChildNodePos !== false) {
                                // Found it!
                                $readFromChunkPos = $readFromChunkPos + $customChildNodePos;
                            } else {
                                // Didn't find it - read a larger chunk and do everything again
                                continue;
                            }
                        }

                        $this->rootNode = $this->customRootNode;
                        $this->readFromChunkPos = $readFromChunkPos;
                    } else {
                        // Clear chunk to save memory, it doesn't contain the root anyway
                        $this->readFromChunkPos = 0;
                        $this->chunk = "";
                        continue;
                    }
                } else {

                    // XML1.0 standard allows almost all Unicode characters even Chinese and Cyrillic.
                    // see: http://en.wikipedia.org/wiki/XML#International_use
                    preg_match('/<([^>\?]+)>/', $this->chunk, $matches);
                    if (isset($matches[1])) {
                        // Found root node
                        $this->rootNode = $matches[1];
                        $this->readFromChunkPos = strpos($this->chunk, $matches[0]) + strlen($matches[0]);
                    } else {
                        // Clear chunk to save memory, it doesn't contain the root anyway
                        $this->readFromChunkPos = 0;
                        $this->chunk = "";
                        continue;
                    }
                }
            }

            while (true) {

                $fromChunkPos = substr($this->chunk, $this->readFromChunkPos);

                // Find element
                // XML1.0 standard allows almost all Unicode characters even Chinese and Cyrillic.
                // see: http://en.wikipedia.org/wiki/XML#International_use
                preg_match('/<([^>]+)>/', $fromChunkPos, $matches);
                if (isset($matches[1])) {

                    // Found element
                    $element = $matches[1];

                    // Is there an end to this element tag?
                    $spacePos = strpos($element, " ");
                    $crPos = strpos($element, "\r");
                    $lfPos = strpos($element, "\n");
                    $tabPos = strpos($element, "\t");

                    // find min. (exclude false, as it would convert to int 0)
                    $aPositionsIn = array($spacePos, $crPos, $lfPos, $tabPos);
                    $aPositions = array();
                    foreach ($aPositionsIn as $iPos) {
                        if ($iPos !== false) {
                            $aPositions[] = $iPos;
                        }
                    }

                    $minPos = $aPositions === array() ? false : min($aPositions);

                    if ($minPos !== false && $minPos != 0) {
                        $sElementName = substr($element, 0, $minPos);
                        $endTag = "</" . $sElementName . ">";
                    } else {
                        $sElementName = $element;
                        $endTag = "</$sElementName>";
                    }

                    $endTagPos = false;

                    // try selfclosing first!
                    // NOTE: selfclosing is inside the element
                    $lastCharPos = strlen($element) - 1;
                    if (substr($element, $lastCharPos) == "/") {
                        $endTag = "/>";
                        $endTagPos = $lastCharPos;

                        $iPos = strpos($fromChunkPos, "<");
                        if ($iPos !== false) {

                            // correct difference between $element and $fromChunkPos
                            // "+1" is for the missing '<' in $element
                            $endTagPos += $iPos + 1;
                        }
                    }

                    if ($endTagPos === false) {
                        $endTagPos = strpos($fromChunkPos, $endTag);
                    }

                    if ($endTagPos !== false) {

                        // Found end tag
                        $endTagEndPos = $endTagPos + strlen($endTag);
                        $elementWithChildren = trim(substr($fromChunkPos, 0, $endTagEndPos));

                        $continueParsing = $this->processNode($elementWithChildren, $sElementName, $this->nodeIndex++);
                        $this->chunk = substr($this->chunk, strpos($this->chunk, $endTag) + strlen($endTag));
                        $this->readFromChunkPos = 0;

                        if (isset($continueParsing) && $continueParsing === false) {
                            $this->chunkCompleted();
                            break(2);
                        }
                    } else {
                        break;
                    }
                } else {
                    break;
                }
            }
            $this->chunkCompleted();
        }

        // If we opened, we need to close..
        if ($this->closeWhenFinished) {
            fclose($this->handle);
        }

        return isset($this->rootNode);
    }

    private function readNextChunk()
    {
        $this->chunk .= fread($this->handle, $this->chunkSize);
        $this->readBytes += $this->chunkSize;
        if ($this->readBytes >= $this->totalBytes) {
            $this->readBytes = $this->totalBytes;
            return false;
        }
        return true;
    }
}

class SimpleXmlStreamer extends XmlExchange {
    public function processNode($xmlString, $elementName, $nodeIndex) {
        $model = simplexml_load_string($xmlString);
        $dbCon = new PDO('mysql:host=localhost;dbname=api', 'root', '');
        $dbCon->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $id = $model->attributes()->ID->__toString();
//        $data = $dbCon->query('SELECT * FROM oc_product WHERE model = ' . $id);
//        foreach ($data as $rows){
//            print("<pre>" . print_r('__Product_id: ' . $rows['product_id'] . ', Model: ' . $rows['model'] . '' . PHP_EOL, true) . "</pre>");
//        }
//        print("<pre>" . print_r('____________________ModelDB: ' . $data . '____________________' . PHP_EOL, true) . "</pre>");
//        print("<pre>" . print_r('Model: ' . $id . PHP_EOL, true) . "</pre>");
//        print("<pre>" . print_r('____________________Model: ' . $id . '____________________' . PHP_EOL, true) . "</pre>");
//        print("<pre>" . print_r('--------------------Price--------------------' . PHP_EOL, true) . "</pre>");

        // customers groups
        foreach ($model->prices->price as $price) {
            $idPrice = $price->attributes()->ID->__toString();
            $int_price = ceil((int)str_replace(" ", "", $price->__toString()));

            // Get customer group id
            $data_customer_id = $dbCon->query("SELECT customer_group_id AS id FROM " . DB_PREFIX . "customer_group WHERE customer_group_id_1c = '" . $idPrice . "'");
            $data_customer_id->execute();
            $customer_id = $data_customer_id->fetch(PDO::FETCH_ASSOC);

            // get product id
            $data_product_id = $dbCon->query("SELECT product_id AS id FROM " . DB_PREFIX . "product WHERE model = '" . $id . "'");
            $data_product_id->execute();
            $product_id = $data_product_id->fetch(PDO::FETCH_ASSOC);
            print("<pre>" . print_r('| Product_id:' . $product_id['id'] . ' | price:' . $int_price . ' |' . ' | customer_id:' . $customer_id['id'] . ' |' . PHP_EOL, true) . "</pre>");
            // get product discount
            if($customer_id['id'] && $product_id['id']){
                $discount_exists = $dbCon->query("SELECT count(*) AS count FROM " . DB_PREFIX . "product_discount WHERE product_id = '" . $product_id['id'] . "' AND customer_group_id='" . $customer_id['id'] . "'");
                $discount_exists->execute();
                $exists = $discount_exists->fetch(PDO::FETCH_ASSOC);
                if($exists['count']){
                    $dbCon->query("UPDATE " . DB_PREFIX . "product_discount SET price = '" . $int_price . '.0000' . "' WHERE product_id = '" . (int)$product_id['id'] . "' AND customer_group_id='". $customer_id['id'] ."'");
                }else{
                    $dbCon->query("INSERT INTO `" . DB_PREFIX . "product_discount` SET product_id = '" . (int)$product_id['id'] . "', customer_group_id='" . (int)$customer_id['id'] . "', price='" . $int_price . '.0000' . "'");
                }
            }


        }
//        print("<pre>" . print_r('--------------------Storage--------------------' . PHP_EOL, true) . "</pre>");

        // stores
//        $total = 0;
//        foreach ($model->storages->storage as $storage) {
//            $idStorage = $storage->attributes()->ID->__toString();
//            $data = $dbCon->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores_products WHERE product_model = '" . $id . "' AND store_alias='" . $idStorage . "'" );
//            $data_stores = $dbCon->query("SELECT COUNT(*) AS count FROM " . DB_PREFIX . "stores WHERE store_alias='" . $idStorage . "'" );
//            $data->execute();
//            $data_stores->execute();
//            $result = $data->fetch(PDO::FETCH_ASSOC);
//            $store_include = $data_stores->fetch(PDO::FETCH_ASSOC);
//            print("<pre>" . print_r('| Storage id:' . $idStorage . ' | storage:' . (int)$storage->__toString() . ' |' . PHP_EOL, true) . "</pre>");
//            if($result['count']){
//                $dbCon->query("UPDATE " . DB_PREFIX . "stores_products SET quantity = '" . (int)$storage->__toString() . "' WHERE product_model = '" . (int)$id . "' AND store_alias='". $idStorage ."'");
//            }else{
//                if($store_include['count']){
//                    $dbCon->query("INSERT INTO `" . DB_PREFIX . "stores_products` SET product_model = '" . (int)$id . "', quantity = '" . (int)$storage->__toString() . "', store_alias = '". $idStorage ."'" );
//                }
//            }
//            $total = $total + (int)$storage;
//        }
//        print("<pre>" . print_r('| total: ' . $total . ' |' . PHP_EOL, true) . "</pre>");
//        $dbCon->query("UPDATE " . DB_PREFIX . "product SET quantity = '" . $total . "' WHERE model = '" . (int)$id . "'");
        return true;
    }
}