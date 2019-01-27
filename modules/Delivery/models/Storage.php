<?php
/**
 * Routine to store/retrieve  single value
 *
 * interface IStorage
 * classes
 *   PINStorage - ActiveRecord wrapper
 *   Storage    - Istorage implementation
 *   StorageFactory - produces instances of storage
 */


require_once 'libraries/adodb/adodb-active-record.inc.php';

class PINStorage extends ADOdb_Active_Record
{
    public $_table = 'pin_storage';
    //funciton __construct($tablename, $adapter)
}

/**
 */
class StorageFactory
{
    /**
     * @return IStorage
     */
    static function get($adapter, $svc)
    {
        static $collection = [];
        if (array_key_exists($svc, $collection)) {
            return $collection[$svc];
        }
        $collection[$svc] = new Storage(
            new PINStorage('pin_storage', $adapter),
            $svc
        );

        return $collection[$svc];
    }
}

interface IStorage
{
    public function __construct(PINStorage $model, $name);
    public function set($v);
    public function __get($property = 'auth');
    public function load();
    public function drop();
}

class Storage implements IStorage
{
    protected $service = false;
    public $model = false;
    public $type = 'direct'; //json

    public function __construct(PINStorage $model, $name)
    {
        $this->model = $model;
        $this->service = $name;
        $exist = $this->load();
        if ($exist) {
            $this->model = array_shift($exist);
        } else {
            $this->model->service = $this->service;
        }
        return $this;
    }


    /**
     * Save auth data to db
     * according to type
     *
     * @param mixed $v data to save
     *
     * @return int AR save result
     */
    public function set($v)
    {
        // TODO sanitize?
        $this->model->ts = date('Y-m-d H:i:s');
        $this->model->auth = $this->setByType($v);
        return $this->model->save();
    }

    /**
     * Getter
     *
     * @param str $property property to retrieve, default auth
     *
     * @return mixed false or auth data according to type
     */
    public function __get($property = 'auth')
    {
        $hasProp = property_exists($this->model, $property)
            && !empty($this->model->{$property});

        if (!$hasProp) {
            return false;
        }

        if ($property == 'auth') {
            return $this->getByType($this->model->auth);
        }

        return $this->model->{$property};
    }

    /**
     * Load AR data into model
     *
     * @return AR find results
     */
    public function load()
    {
        $where = "service = '{$this->service}'";
        return $this->model->find($where);
    }

    /**
     * Drop AR record
     *
     * @return AR delete result
     */
    public function drop()
    {
        if (empty($this->model->id)) {
            return false;
        }

        return $this->model->delete();
    }

    /**
     * Format value according to type
     *
     * @param $v
     *
     * @return mixed
     */
    public function getByType($v)
    {
        switch ($this->type) {
            case 'json': 
                $result = json_decode($v, 1);
            case 'direct':
            default:
                $result = $v;
        }

        return $result;
    }

    public function setByType($v)
    {
        switch ($this->type) {
            case 'json': 
                $result = json_encode($v);
            case 'direct':
            default:
                $result = $v;
        }

        return $result;
    }
}
