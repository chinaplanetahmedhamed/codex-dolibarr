<?php
class modPdftemplates {
    public function __construct($db) {
        $this->db = $db;
        $this->number = 918274;
        $this->name = "pdftemplates";
        $this->description = "Custom PDF Templates";
        $this->version = '1.0';
        $this->family = "other";
        $this->enabled = 1;
        $this->picto = "pdf";
    }
}
