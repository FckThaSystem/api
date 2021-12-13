<?php
class ControllerOcCliXml extends Controller {
    public function index() {
        $this->load->library('SimpleXmlStreamer');

        $this->load->model('catalog/storage');

        $xmlReader = new SimpleXmlStreamer(
            'small.xml'
        );
        $xmlReader->setModel($this->model_catalog_storage);
        $xmlReader->parse();

        oc_cli_output("Import data finished");
    }
}