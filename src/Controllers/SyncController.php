<?php

/**
 * @Author: Johan Guerreros <johangm90@gmail.com>
 * @License: Copyright (c) 2016. All Rights Reserved.
 */

class SyncController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    function beforeroute()
    {
        //needed for avoid redirect loop
    }

    public function sync()
    {
        $settings = array(
            'baseUri' => 'https://cloud.jgm90.com/remote.php/webdav/',
            'userName' => 'rbchachani',
            'password' => 'Admin.3000'
        );

        $client = new Sabre\DAV\Client($settings);

        $folder_content = $client->propFind('backups', array(
            '{DAV:}displayname',
            '{DAV:}getcontentlength',
            '{DAV:}getlastmodified',
            '{DAV:}getcontenttype',
        ), 1);
        $len = array();
        foreach ($folder_content as $key => $row) {
            $len[$key] = $row['{DAV:}getcontentlength'];
        }
        array_multisort($len, SORT_DESC, $folder_content);
        //echo '<pre>';
        //print_r($folder_content);
        //echo '</pre>';
        $files = array();
        foreach ($folder_content as $key => $row) {
            array_push($files, $key);
        }
        $file = $client->request('GET', $files[0]);

        $this->db->exec("TRUNCATE TABLE caja_detalle");
        $this->db->exec("TRUNCATE TABLE caja");
        $this->db->exec("DELETE FROM existencia WHERE almacen_id='4014f9c4-c01d-11e7-91af-5600005a2745'");
        $this->db->exec("DELETE md FROM movimiento_detalle md INNER JOIN movimiento m ON m.movimiento_id = md.movimiento_id WHERE m.almacen_id='4014f9c4-c01d-11e7-91af-5600005a2745'");
        $this->db->exec("DELETE FROM movimiento WHERE almacen_id='4014f9c4-c01d-11e7-91af-5600005a2745'");
        $this->db->exec("TRUNCATE TABLE orden_detalle");
        $this->db->exec("TRUNCATE TABLE orden");

        $bkp_path = $this->app->get('UPLOADS') . 'sync/';
        if (!file_exists($bkp_path)) {
            if (!@mkdir($bkp_path, 0775, true)) {
                $error = error_get_last();
                echo json_encode(array('msg' => $error));
                exit;
            }
        }
        file_put_contents($bkp_path . 'bkp.gz', $file['body']);
        $bkp_file = $bkp_path . 'bkp.gz';
        shell_exec('gunzip < ' . $bkp_file . ' | mysql -u ' . $this->app->get('DB_USER') . ' -p' . $this->app->get('DB_PASSWORD') . ' -h ' . $this->app->get('DB_HOST') . ' ' . $this->app->get('DB_NAME'));
        echo json_encode(array('success' => true, 'msg' => 'Copia de seguridad restaurada.'));
        unlink($bkp_file);
    }

    public function sync_server()
    {
        $settings = array(
            'baseUri' => 'https://cloud.jgm90.com/remote.php/webdav/',
            'userName' => 'rbchachani',
            'password' => 'Admin.3000'
        );

        $client = new Sabre\DAV\Client($settings);

        $folder_content = $client->propFind('server', array(
            '{DAV:}displayname',
            '{DAV:}getcontentlength',
            '{DAV:}getlastmodified',
            '{DAV:}getcontenttype',
        ), 1);
        $len = array();
        foreach ($folder_content as $key => $row) {
            $len[$key] = $row['{DAV:}getcontentlength'];
        }
        array_multisort($len, SORT_DESC, $folder_content);
        //echo '<pre>';
        //print_r($folder_content);
        //echo '</pre>';
        $files = array();
        foreach ($folder_content as $key => $row) {
            array_push($files, $key);
        }
        $file = $client->request('GET', $files[0]);

        //$this->db->exec("TRUNCATE TABLE productos");

        $bkp_path = $this->app->get('UPLOADS') . 'sync/';
        if (!file_exists($bkp_path)) {
            if (!@mkdir($bkp_path, 0775, true)) {
                $error = error_get_last();
                echo json_encode(array('msg' => $error));
                exit;
            }
        }
        file_put_contents($bkp_path . 'bkp.gz', $file['body']);
        $bkp_file = $bkp_path . 'bkp.gz';
        shell_exec('gunzip < ' . $bkp_file . ' | mysql -u ' . $this->app->get('DB_USER') . ' -p' . $this->app->get('DB_PASSWORD') . ' -h ' . $this->app->get('DB_HOST') . ' ' . $this->app->get('DB_NAME'));
        echo json_encode(array('success' => true, 'msg' => 'Copia de seguridad restaurada.'));
        unlink($bkp_file);
    }
}