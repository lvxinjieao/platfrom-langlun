<?php
namespace Admin\Event;
use Think\Controller;

abstract class StrategyController{
    abstract protected function listRow();
}

/**
 * 全局分页数设置
 */
class listRowGlobalController extends StrategyController{
    public function listRow(){
        return empty(C("ADMIN_LIST_ROW")) ? 10 : C("ADMIN_LIST_ROW");
    }
}


/**
 * 特定分页数设置
 */
class listRowItemController extends StrategyController{
    private $model;
    private function __construct($model){
      $this->$model = M('Model')->getByName($model);
    }

    public function listRow(){
       return empty($this->$model['list_row'])?10:$this->$model['list_row'];
    }
}

class listRowEvent extends Controller{
    private $items;  
  
    public function getItem($item_name,$modelName="Model")  
    {  
        try  
        {  
            //$class=new ReflectionClass($item_name);  
            //$this->item=$class->newInstance();  
            switch($item_name){
                case "global":
                    $this->items = new \Admin\Event\listRowGlobalController();
                break;
                case "item":
                    $this->items = new listRowItemController($modelName);
                break;
                default:
                    $this->items = new listRowGlobalController();
                break;
            }
        }  
        catch(\Exception $e)  
        {  
            return $e->getMessage();
        }
    }  
  
    public function listRows()  
    {  
        return $this->items->listRow();  
    }  
}