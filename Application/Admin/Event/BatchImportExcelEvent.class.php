<?php
namespace  Admin\Event;
use Think\Controller;
class BatchImportExcelEvent extends Controller{

    public function uploadExcel($fileData){
        header("Content-Type:text/html;charset=utf-8");
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize = 3145728;// 设置附件上传大小
        $upload->exts = array('xls', 'xlsx');// 设置附件上传类
        $upload->rootPath = './Uploads/'; // 设置附件上传目录
        $upload->savePath = 'excel/'; // 设置附件上传目录
        // 上传文件
        $info = $upload->uploadOne($fileData);
        $filename = './Uploads/' . $info['savepath'] . $info['savename'];
        $exts = $info['ext'];
        if (!$info) {// 上传错误提示错误信息
            if ($upload->getError() != "非法上传文件！"){
                return $upload->getError();
            }
        } else {// 上传成功
            return $info;
        }
    }
    /**
     * 导入excel
     */
    public  function importExcel($file=''){
        try{
            // 判断文件是什么格式
            $type = pathinfo($file); 
            $type = strtolower($type["extension"]);
            ini_set('max_execution_time', '0');
            Vendor('PHPExcel.PHPExcel');
            // 判断使用哪种格式
            switch($type){
                case 'xlsx':
                    $objReader = new \PHPExcel_Reader_Excel2007();
                break;
                case 'xls':
                    $objReader = new \PHPExcel_Reader_Excel5();
                    //$objReader = \PHPExcel_IOFactory::createReader($type);
                break;
            }
            
            $objPHPExcel = $objReader->load($file); 
            $sheet = $objPHPExcel->getSheet(0); 
            // 取得总行数 
            $highestRow = $sheet->getHighestRow();     
            // 取得总列数      
            $highestColumn = $sheet->getHighestColumn(); 
            //循环读取excel文件,读取一条,插入一条
            $data=array();
            //从第一行开始读取数据
            //循环获取表中的数据，$currentRow表示当前行，从哪行开始读取数据，索引值从0开始
            for ($currentRow = 2; $currentRow <= $highestRow; $currentRow++) {
                //从哪列开始，A表示第一列
                for ($currentColumn = 'A'; $currentColumn <= $highestColumn; $currentColumn++) {
                    //数据坐标
                    $address = $currentColumn . $currentRow;

                    $cell = $objPHPExcel->getActiveSheet()->getCell($address);
                    //读取到的数据，保存到数组$arr中
                    if($currentColumn == "C"){
												$currentvalue = $sheet->getCell($address)->getValue();
												if (empty($currentvalue)) {
													unlink($file);
													throw new \Exception($address.' 数据为空');
												}
                        if($cell->getDataType() == \PHPExcel_Cell_DataType::TYPE_NUMERIC){
                            $cellstyleformat=$cell->getStyle($address)->getNumberFormat();
                            $formatcode=$cellstyleformat->getFormatCode();  
                            if (preg_match('/^([$[A-Z]*-[0-9A-F]*])*[hmsdy]/i', $formatcode)) {
                                $date = \PHPExcel_Shared_Date::ExcelToPHP($objPHPExcel->getActiveSheet()->getCell($address)->getValue()); 
                                $datetime = new \DateTime("@$date"); //DateTime类的bug，加入@可以将Unix时间戳作为参数传入  
                                $datetime->setTimezone(new \DateTimeZone('GMT'));  
                                $data[$currentRow][$currentColumn] = $datetime->format("Y-m-d H:i:s"); 
                            }else{  
                                unlink($file);
                                throw new \Exception($address."列 时间格式输入错误");
                            }  
                        }else{
                            unlink($file);
                            throw new \Exception($address."列 时间格式输入错误");
                        }
                    }else{
											$currentvalue = $sheet->getCell($address)->getValue();
											if (empty($currentvalue)) {
												unlink($file);
												throw new \Exception($address.' 数据为空');
											}
                        $data[$currentRow][$currentColumn] = $sheet->getCell($address)->getValue();
                    }
                }
            }
            return $data;
        }catch(\Exception $e){
            return $e->getMessage();
        }
        
    }

    /**
     * 插入区服数据到数据库
     */
    public function serverDataInsert($serverData,$url=''){
        $serverModel = new \Admin\Model\ServerModel();
        $sData = [];
        $key = 0;
        foreach($serverData as $server){
            if(!empty($server['A'])){
                $gameID[] = $server['A'];
            }else{
                $this->error("游戏ID不能为空");
            }

            $key_out = $server['A']."-".$server['B']; //提取内部一维数组的key(name age)作为外部数组的键  
            if(array_key_exists($key_out,$arr_out)){  
                $this->error("游戏ID：{$server['A']} <{$server['B']}> 游戏区服名称重复");
            }  
            else{  
                $arr_out[$key_out] = $arr[$k]; //以key_out作为外部数组的键  
            }
            $old = M('server','tab_')->field('id')->where(array('server_name'=>$server['B'],'game_id'=>$server['A']))->find();
            if($old){
                unset($sData[$key]);
                continue;
            }
            $sData[$key]['game_id'] = $server['A'];
            $sData[$key]['game_name'] = get_game_name($server['A']);
            $sData[$key]['server_name'] = $server['B'];
            $sData[$key]['server_num'] = 0;
            $sData[$key]['recommend_status'] = 1;
            $sData[$key]['show_status'] = 1;
            $sData[$key]['stop_status'] = 0;
            $sData[$key]['server_status'] =0;
            $sData[$key]['icon'] = 0;
            $sData[$key]['start_time'] = strtotime($server['C']);
            $sData[$key]['desride'] = '';
            $sData[$key]['prompt'] = '';
            $sData[$key]['parent_id'] = 0;
            $sData[$key]['create_time'] = time();
            $sData[$key]['server_version'] = get_sdk_version($server['A']);
            $sData[$key]['developers'] = 0;
            
            if(!$serverModel->create($sData[$key])){
              switch ($serverModel->getError()) {
                    case '区服名称不能为空':
                            $msg = "游戏ID：{$server['A']} <{$server['B']}> 区服名称不能为空";
                            break;
                    case '区服名称不能超过30个字符':
                            $msg = "游戏ID：{$server['A']} <{$server['B']}> 区服名称不能超过30个字符";
                            break;
                    case '同游戏下区服名称已存在':
                            $msg = "游戏ID：{$server['A']} <{$server['B']}> 区服名称重复";
                            break;
                    case '开始时间不能为空':
                            $msg = "游戏ID：{$server['A']} <{$server['B']}> 开始时间不能为空";
                            break;
                    default:$msg = $serverModel->getError();
            }
            $this->error($msg);
            }
            $key++;
        }
        
        $gameModel = new \Admin\Model\GameModel();
        $gameID = array_unique($gameID);
        $map[id] = array('in',$gameID);
        $game_id = $gameModel->where($map)->getField('id',true);
        if(empty($game_id)){
            //$this->error("游戏ID:".implode(',',$gameID).'不存在');
						$this->error($this->game_server_error_array($gameID));
        }
        if(count($gameID) != count($game_id)){
            $gameID = array_diff($gameID,$game_id);
            ///$this->error("游戏ID:".implode(',',$gameID).'不存在');
						$this->error($this->game_server_error_array($gameID));
        }
        $result = $serverModel->addAll($sData);
        if($result){
            $this->success('导入成功',U('Server/lists'));
        }else{
            $this->error("导入失败");
        }
    }
		
		
		private function game_server_error_array($arr) {
			$str = '';
			foreach ($arr as $v) {
				
				$str .= 'game_id='.$v.' 数据错误，请重新上传<br/>';
				
			}
			
			return $str;
		}
}