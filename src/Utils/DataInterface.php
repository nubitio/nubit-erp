<?php

interface DataInterface
{
    public function get_one($id);

    public function get_all($filter = "1=1");

    public function add($obj);

    public function edit($obj);

    public function delete($obj);
}