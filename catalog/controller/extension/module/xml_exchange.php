<?php
include 'system/library/xml_exchange.php';

$streamer = new SimpleXmlStreamer("C:\laragon\www\api\Test1.XML");
$streamer->parse();