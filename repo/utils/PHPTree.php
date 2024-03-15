<?php

namespace Utils;
class PHPTree{

    protected static $config = array(
        /* 主键 */
        'primary_key' 	=> 'id',
        /* 父键 */
        'parent_key'  	=> 'pid',
        /* 展开属性 */
        'expanded_key'  => 'expanded',
        /* 叶子节点属性 */
        'leaf_key'      => 'leaf',
        /* 孩子节点属性 */
        'children_key'  => 'children',
        /* 孩子节点属性 */
        'checked'  => false,
    );

    /* 结果集 */
    protected static $result = array();

    /* 层次暂存 */
    protected static $level = array();
    /**
     * @name 生成树形结构
     * @param array 二维数组
     * @return mixed 多维数组
     */
    public static function makeTree($data,$options=array(),$checks = array() ){
        $dataset = self::buildData($data,$options);
        $r = self::makeTreeCore(0,$dataset,'normal',$checks);
        return $r;
    }

    /* 生成线性结构, 便于HTML输出, 参数同上 */
    public static function makeTreeForHtml($data,$options=array(),$checks = array()){
        $dataset = self::buildData($data,$options);
        $r = self::makeTreeCore(0,$dataset,'linear',$checks);
        return $r;
    }

    /* 格式化数据, 私有方法 */
    private static function buildData($data,$options){
        $config = array_merge(self::$config,$options);
        self::$config = $config;
        extract($config);//从数组中将变量导入到当前的符号表

        $r = array();
        foreach($data as $item){
            $item = (array)$item;
            $id = $item[$primary_key];
            $parent_id = $item[$parent_key];
            $r[$parent_id][$id] = $item;
        }

        return $r;
    }

    /* 生成树核心, 私有方法  */
    private static function makeTreeCore($index,$data,$type='linear',$checks = array())
    {
        extract(self::$config);
        foreach($data[$index] as $id=>$item)
        {
            if($type=='normal'){
                if(isset($data[$id]))
                {
                    $item[$children_key]= self::makeTreeCore($id,$data,$type,$checks);
                }
                else
                {
                    $item[$leaf_key]= true;
                    if(in_array($item[$primary_key],$checks)) {
                        $item['checked'] = true;
                    }else {
                        $item['checked'] = false;
                    }

                }
                $r[] = $item;
            }else if($type=='linear'){
                $parent_id = $item[$parent_key];
                self::$level[$id] = $index==0?0:self::$level[$parent_id]+1;
                $item['level'] = self::$level[$id];
                self::$result[] = $item;
                if(isset($data[$id])){
                    self::makeTreeCore($id,$data,$type);
                }

                $r = self::$result;
            }
        }
        return $r;
    }
}