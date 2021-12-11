<?php
include '../system/library/SimpleXmlStreamer.php';
require 'DbModel.php';
$streamer = new SimpleXmlStreamer('../Test1.XML');
$streamer->setModel(new DbModel());
$streamer->parse();

echo 'Import data finished';