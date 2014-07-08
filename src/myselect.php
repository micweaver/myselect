#!/usr/local/bin/php
<?php

class MySelect {

    public $sql = '';
    public $tokens;
    public $key_word_status = array(
            'select' => 1,
            'from' => 2,
            'where' => 3,
            'group' => 4,
            'having' => 5,
            'order' => 6,
            'limit' => 7,
    );

    public $key_word_vals = array();
    public $key_work_parse_vals = array();
    
    public $fun_pattern = '/^([a-zA-Z]{1,8})\((\S+)\)$/';
    public $fun_params_separater = '@';
    public $field_fun = array(
        'regsub' => true,
        'strsub' => true,
    );
    
    public $new_fun_field = array();
    public $select_group_fun = array();
    
    
    public $group_all = false;
    
    public $view_syntax_parse = false;
    public $view_compute_process = false;
    
    
    public function __construct($sql_str){
        $this->sql = $sql_str;
    }
    
    public function setViewSynTaxParse($val){
        $this->view_syntax_parse = $val ? true: false;
    }
    
    public function setViewComputeProcess($val){
        $this->view_compute_process = $val? true : false;
    }
    
    public function printParseVal($val, $comment = ''){
        
        if($this->view_syntax_parse){
            if(!empty($comment)){
                echo "**$comment**".PHP_EOL;
            }
            print_r($val);
            echo PHP_EOL;
        }
        
    }
    
    public function printComputeVal($val, $comment = ''){
    
        if($this->view_compute_process){
            if(!empty($comment)){
                echo "**$comment**".PHP_EOL;
            }
            print_r($val);
            echo PHP_EOL;
        }
    
    }
    
    public function isSpace($char){
        //HT (9), LF (10), VT (11), FF (12), CR (13), and space (32).
       // if(in_array($char, array('\n','\r','\t','\v','\f',' '))){
         //   return true;
      //  }
        if($char == '\n' || $char =='\r' || $char == '\t' || $char =='\v' || $char == '\f' || $char == ' '){
            return true;
        }
        return false;
    }
    
    //0ab1c 2 "a3bc 3bcd" 2 ab1c
    public function splitLine($line){
        $res = array();
        $len = strlen($line);
        $status = 0;
        $item = '';
        for($i=0;$i< $len; $i++){
           // echo  $line[$i].PHP_EOL;
            if(($line[$i] == '\'' || $line[$i] == '"') &&($i ==0 || $line[$i-1] !='\\')){
                    if($status == 3){
                        $res[] = $item;
                        $item = '';
                        $status = 0;
                    } else {
                        if($status == 1){
                            $res[] = $item;
                            $item = '';
                        }
                        $status = 3;
                    }
            } elseif($this->isSpace($line[$i])) {
                if($status == 3) {
                    $item.=$line[$i];
                } elseif($status == 2 || $status==0) {
                  $status = 2;
                } else {
                    $res[] = $item;
                    $item = '';
                    $status =2;
                }
            } else {
                if($status == 3){
                    $item.=$line[$i];
                } else {
                    $item.=$line[$i];
                    $status = 1;
                }
            }
        }
        
        if(!empty($item)) {
            $res[] = $item;
        }
        
        return $res;
    }
    
    public function replace_fun_param_sep($matches){
        return str_replace(',', $this->fun_params_separater, $matches[0]);
    }
    
    public function replace_regsub($matches){
        $str =  str_replace(array('(',')'), array('<<<','>>>'), $matches[2]);
        $str = "regsub({$matches[1]},{$str},{$matches[3]})";
        return $str;
        
    }
    
   
    public function getTokenArray(){
        $this->sql = trim($this->sql);
        $this->printParseVal($this->sql,"input sql");
        
        $this->sql = preg_replace('/([a-zA-Z]{1,8})\s*\(\s*(\S+)\s*\)/', '\\1(\\2)', $this->sql);
        $this->sql = preg_replace_callback('/regsub\(\s*([^,]+),\s*(\/\S+\/[imsxeADSUXJu]*)\s*,\s*([^,]+)\)/Ui', array($this,'replace_regsub'), $this->sql);
//echo $this->sql.PHP_EOL;
        $this->sql = preg_replace('/strsub\s*\(\s*([\$0-9]+)\s*,\s*([0-9]+)\s*,\s*([0-9]+)\s*\)/Ui','strsub(\\1,\\2,\\3)', $this->sql);
        $this->sql = preg_replace('/strsub\s*\(\s*([\$0-9]+)\s*,\s*([0-9]+)\s*\)/Ui','strsub(\\1,\\2)', $this->sql);
       
        $this->sql = preg_replace('/regsub\s*\(\s*([\$0-9]+)\s*,\s*(\/\S+\/[imsxeADSUXJu]*)\s*,\s*(\S+)\s*\)/Ui','regsub(\\1,\\2,\\3)', $this->sql);
        $this->sql = preg_replace_callback('/[a-zA-Z]{1,8}\([^\(\)]+\)/', array($this,'replace_fun_param_sep'), $this->sql);

        $this->printParseVal($this->sql,"clean sql");
        
        $this->tokens = $this->splitLine($this->sql);
        
        $this->printParseVal($this->tokens,'sql tokens');
        
    }
    
    public function parseError($str){
        $db = debug_backtrace();
        echo $db[1]['function'].':'.$db[0]['line'].PHP_EOL;
        echo $str.PHP_EOL;
        exit();
    }
   
    function parseSql(){
        
        $this->getTokenArray();
        
        $cur_status = 0;
        $cur_val = '';
        $cur_key = '';
        foreach ($this->tokens as $val){
            switch (strtolower($val)){
                case 'select':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'from':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'where':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'group':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'having':
                    if($cur_status != $this->key_word_status['group']){
                        $this->parseError(" having  should after group");
                    }
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'order':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;
                case 'limit':
                    $val = strtolower($val);
                    $next_status = $this->key_word_status[$val];
                    $this->key_word_vals[$cur_key] = trim($cur_val);
                    $cur_key = $val;
                    $cur_val = '';
                    break;       
                default:
                    $cur_val.="\t".$val;
            }
            
            if($cur_status == 0 && !empty($cur_val)){
                $this->parseError("sql syntax error,some thing before select");
            }
            if($next_status < $cur_status){
                $this->parseError("sql syntax error,wrong sequence");
            }
            $cur_status = $next_status;
            
        }
        $this->key_word_vals[$cur_key] = trim($cur_val);
        
        if(!isset($this->key_word_vals['select']) || !isset($this->key_word_vals['from'])){
            $this->parseError("sql syntax error,need select and from");
        }
        
        foreach ($this->key_word_vals  as $key => &$val){
            if($key != 'from'){
                $val = strtolower($val);               
            }
            $method = 'parse'.ucfirst($key);
            $this->key_work_parse_vals[$key] = $this->$method($val);
        }
        
        $this->printParseVal($this->key_work_parse_vals,"syntax pares res");
        
        if($this->view_syntax_parse) exit();
 
    }
    
    public function getFunSignature($funname,$params){
        
        if(is_array($params)) $params = implode($this->fun_params_separater, $params);
        return $funname.'_'.$params;
    }
    
    public function getItemKey($val){
        if(is_array($val)){
            return $this->getFunSignature($val['fun'], implode($this->fun_params_separater,$val['param']));
        }
        if(trim($val) == '*') return '*';
        return intval($val) -1;
        
    }
    //包括聚合函数，它只能有一个数字参数
    public function checkFunParam($funname,$params){
        
        $params = explode($this->fun_params_separater, $params);
        switch ($funname){
            case 'regsub':
                if(count($params) != 3 || !preg_match('/^\$\d{1,3}/', $params[0])){
                     $this->parseError(" {$funname} function params error");
                }
                break;
            case 'strsub':
                if(count($params) > 3 || count($params) < 2 || !preg_match('/^\$\d{1,3}/', $params[0])){
                     $this->parseError(" {$funname} function params error");
                }
                break;
            case 'count':
            case 'sum':
            case 'avg':
            case 'max':
            case 'min':
                if(count($params) != 1 || !preg_match('/^\$\d{1,3}/', $params[0])){
                    $this->parseError(" {$funname} function params error");
                }
                break;
        }
    }
    
    
    public $GroupFun = array(
            //字符串提取函数
            'regsub' => true,
            'strsub' => true,
    );
    
    public $GroupComputeFun = array(
            'count' => true,
            'sum' => true,
            'avg' => true,
            'max' => true,
            'min' => true,
    );
    
    public function getField($str,$arrFun){
        
        if($str[0] == '$'){
            $field = substr($str, 1);
            if(ctype_digit($field)){
                $field = intval($field);
                if($field < 1){
                    $this->parseError(" field num should greater than 1");
                }
                return $field;
            } else {
                $this->parseError("field {$str} wrong");
            }
        
        } elseif(preg_match($this->fun_pattern, $str,$matches)){
            if(!isset($arrFun[$matches[1]])){
                $this->parseError("unsupport fun {$matches[1]}");
            }
            if(isset($this->field_fun[$matches[1]])){
                $this->new_fun_field[$this->getFunSignature($matches[1],$matches[2])]= array(
                        'fun' => $matches[1],
                        'param' => explode($this->fun_params_separater,$matches[2]),
                );
            }
            $this->checkFunParam($matches[1], $matches[2]);
            return  array(
                    'fun' => $matches[1],
                    'param' =>  explode($this->fun_params_separater,$matches[2]),
            );
        }else {
                $this->parseError("field {$str} wrong");
        }
    }
    
  
    public $relation_operator = array(
          '=' => true,
          '!=' => true,
          '>' => true,
          '<' => true,
          '>=' => true,
          '<=' => true,
          'like' => true,
          'rlike' => true,
    );
    
    public $special_relation_operator = array(
            '>=' ,
            '<=',
            '!=' ,
            '=' ,
            '>' ,
            '<' ,
    );
    
    public function getExpression($str){
        
        $items = $this->splitLine(trim($str));
        if(count($items) == 3 && ($items[1] == 'like' || $items['1'] == 'rlike')){
            return $items;
        }
        
        $op = implode('|', $this->special_relation_operator);
        $pattern = '/(\S+)\s*('.$op.')\s*(\S+)/';
        if(preg_match($pattern, $str,$matches)){
            return array(
                    $matches[1],
                    $matches[2],
                    strval($matches[3]),
            );
        }
        
        $this->parseError("expression '{$str}' wrong");
        
    }
    
    public $selectFun = array(
            //字符串提取函数
            'regsub' => true,
            'strsub' => true,
            //聚合函数
            'count' => true,
            'sum' => true,
            'avg' => true,
            'max' => true,
            'min' => true,
    );
    
    function parseSelect($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after select');
        }
        $items = explode(',', $str);
        $out = array();
        foreach ($items as $val){
            $val = trim($val);
            if($val == '*'){ 
                if(!empty($out)) {
                    $this->parseError('field error,include * and other fields');
                }
                $out[] = $val;
                break;
            }else {
                $out[] = $this->getField($val, $this->selectFun);
            }
        }
        
        foreach ($out as $val){
           if(is_array($val) && isset($this->GroupComputeFun[$val['fun']])){
                if(!empty($this->select_group_fun)) {
                    $this->parseError('should be only one group function');
                }
                $this->select_group_fun = $val;
                $this->group_all = true;
                $this->printParseVal($this->select_group_fun,"select group fun");
            } 
        }
        
        return $out;
    }
    
    function parseFrom($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after from');
        }
        
        if(!file_exists($str)){
            $this->parseError("file {$str} not exist");
        }
       
        return trim($str);
        
    }
    
    public $whereFun = array(
            //字符串提取函数
            'regsub' => true,
            'strsub' => true,
    );
    
    function parseWhere($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after where');
        }
       
        $expression = explode('and', $str);
       
        $out = array();
        foreach ($expression as $key => $val){
            if(empty($val)){
                $this->parseError("some error near and");
            }
            $ex_items = $this->getExpression($val);
            $ex_items[0] = $this->getField($ex_items[0], $this->whereFun);
            $out[] = $ex_items;
            
         }
         return $out;
        
    }
    

    
    
    function parseGroup($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after group');
        }
        
        $str = trim($str);
        if(strncmp($str, 'by',2)) {
            $this->parseError('should group by');
        }
        $fields = explode(',', trim(substr($str, 2)));
        $out = array();
        foreach ($fields as $val){
            $out[] = $this->getField($val, $this->GroupFun);
        }
        
        $select_fields = $this->key_work_parse_vals['select'];
        foreach ($select_fields as $val){
            if(is_array($val) && isset($this->GroupComputeFun[$val['fun']])){
               continue;
            } elseif(!in_array($val, $out)){
                if(is_array($val)){
                    $val = $this->getFunSignature($val['fun'], $val['param']);
                }
                $this->parseError("select field {$val} not in group fields");
            }
        }
        
        if(empty($this->select_group_fun)){
            $this->parseError("select should have group function");
        }
        
        $this->group_all = false;
        return $out;
    }
    
    
    public $havingFun = array(
            //聚合函数
            'count' => true,
            'sum' => true,
            'avg' => true,
            'max' => true,
            'min' => true,
    );
    
    function parseHaving($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after having');
        }
        
        $out = $this->getExpression($str);
        $out[0] = $this->getField($out[0], $this->havingFun);
        if(!is_array($out[0])){
            $this->parseError('having field wrong');
        }
        if($out[0] != $this->select_group_fun){
            $this->parseError('the field is not the same as the select field');
        }
        
        return $out;
      
    }
    
    
    function parseOrder($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after order');
        }
        
        $str = trim($str);
        if(strncmp($str, 'by',2)) {
            $this->parseError('should order by');
        }
        
        $out['sort'] = 'asc';
        if(preg_match('/(asc|desc)$/', $str,$matches)){
            $out['sort'] = $matches[1];
            $str = preg_replace('/(asc|desc)$/', '', $str);
        }
        
        $fields = explode(',', trim(substr($str, 2)));
        foreach ($fields as $val){
            $out['field'][] = $this->getField($val, $this->selectFun);
        }
       
        if(isset($this->key_work_parse_vals['group'])){
            foreach ($out['field'] as $field){
                if(!in_array($field,$this->key_work_parse_vals['select'])){
                    $this->parseError('the order by field should in the select field');
                }
            }
        }
        
        return $out;
    }
    
    
    function parseLimit($str){
        
        $this->printParseVal($str,__FUNCTION__);
        if(empty($str)){
            $this->parseError('sql syntax error after limit');
        }
        
        $items = explode(',', trim($str));
        if(count($items) == 2){
            $out['start'] = $items[0];
            $out['count'] = $items[1];
        } elseif(count($items) == 1) {
            $out['start'] = 0;
            $out['count'] = $items[0];
        } else {
            $this->parseError('sql syntax error after limit');
        }
        return $out;
    }
    
    
    public $line_data = array();
    
    function compute(){
        
        $file = $this->key_work_parse_vals['from'];
        $handle = fopen($file, "r");
        while (($buffer = fgets($handle)) !== false) {
              $res = $this->tackleWhere($buffer);
              if($res !==false){
                  $this->line_data[]=$res;
              }
       }
        
       $this->printComputeVal($this->line_data,'where line data');
       
       if($this->group_all || isset($this->key_work_parse_vals['group'])){
           $this->tackleGroupBy();
           $this->printComputeVal($this->line_data,'group by line data');
       }
       if(isset($this->key_work_parse_vals['having'])){
           $this->tackleHaving();
           $this->printComputeVal($this->line_data,'having line data');
       }
       if(isset($this->key_work_parse_vals['order'])){
           $this->tackleOrderBy();
           $this->printComputeVal($this->line_data,'order by line data');
       }
      
       if(isset($this->key_work_parse_vals['limit'])){
           $this->tackleLimit();
       }
       
    }
    
    
    function funStrSub($param,$items){
        
        $field = $this->getField($param[0],array());
        $field_val = $items[$field-1];
        if(!empty($param[2])){
            return substr($field_val, $param[1],$param[2]);
        } else {
            return substr($field_val, $param[1]);
        }
    }
    
    function funRegSub($param,$items){
    
        $field = $this->getField($param[0],array());
        $field_val = $items[$field-1];
        $param[1] = str_replace(array('<<<','>>>'), array('(',')'), $param[1]);
        $res = preg_replace($param[1], $param[2],$field_val);
        if($res === NULL) {
            $this->parseError("the regex express is wrong");
        }
        return $res;
         
    }
    
    
    function cmpEqual($op1,$op2){
        if(strval($op1) == strval($op2)) return true;
        return false;
    }
    
    function cmpNotEqual($op1,$op2){
        if(strval($op1) != strval($op2)) return true;
        return false;
    }
    
    function cmpGreater($op1,$op2){
        if(intval($op1) > intval($op2)) return true;
        return false;
    }
    
    function cmpLess($op1,$op2){
        if(intval($op1) < intval($op2)) return true;
        return false;
    }
    
    function cmpGequal($op1,$op2){
        if(intval($op1) >= intval($op2)) return true;
        return false;
    }
    
    function cmpLequal($op1,$op2){
        if(intval($op1) <= intval($op2)) return true;
        return false;
    }
    
    function cmpLike($op1,$op2){
        if(strstr($op1,$op2)) return true;
        return false;
    }
    
    function cmpRlike($op1,$op2){
        if(preg_match($op2, $op1)) return true;
        return false;
    }

    public $opFunMap = array(
            '=' => 'cmpEqual',
            '!=' => 'cmpNotEqual',
            '>' => 'cmpGreater',
            '<' => 'cmpLess',
            '>=' => 'cmpGequal',
            '<=' =>  'cmpLequal',
            'like' => 'cmpLike',
            'rlike' => 'cmpRlike',
    );
    
    function tackleWhere(&$line){
        
        if(empty($line)) return false;
        $items = $this->splitLine(trim($line));
  
        foreach ($this->new_fun_field as $key => $val){
            $method = 'fun'.ucfirst($val['fun']);
            $items[$key] = $this->$method($val['param'],$items);
        }
        if(!isset($this->key_work_parse_vals['where'])) return $items;
     
        $where_out = $this->key_work_parse_vals['where'];
        foreach ($where_out as $val){
            $fun = $this->opFunMap[$val[1]];
            $k = $this->getItemKey($val[0]);
            if(!isset($items[$k])) return false;
            $v = $items[$k];
            //var_dump($this->$fun($v,$val[2]));
            if(!$this->$fun($v,$val[2])){
                return false;
            }
        }
      
        return $items;
      
    }
    
    function group_cmp($params1,$params2){
        
        foreach ($this->key_work_parse_vals['group'] as  $val){
            $val = $this->getItemKey($val);
            if($params1[$val] > $params2[$val]) return 1;
            elseif($params1[$val] < $params2[$val]) return -1;
        }
        return 0;
    }
    
    function isEqual(&$linecur,&$linelast){
        if($this->group_all) {
            if(empty($linelast)) return false;
            return true;
        }
        
        foreach ($this->key_work_parse_vals['group'] as $val){
            $val = $this->getItemKey($val);
            if($linecur[$val] != $linelast[$val]) return false;
        }
        return true;
    }
        
    function tackleGroupBy(){
        
        if(!$this->group_all) {
            usort($this->line_data, array($this,'group_cmp'));
        }

        $cur_sum = 0;
        $cur_cnt = 0;
        $cur_max = intval(0x80000001);
        $cur_min = intval(0x7fffffff);
      
        $linelast = array();
        $linegroup = array();
        $f = $this->getField($this->select_group_fun['param'][0],array());
        $k = $this->getItemKey($f);
        $funkey = $this->getFunSignature($this->select_group_fun['fun'], $this->select_group_fun['param']);
        
        foreach ($this->line_data as $key => &$val){
            $field_val = $val[$k];
         
            if($this->isEqual($val, $linelast)){
                switch ($this->select_group_fun['fun']){
                    case 'count':
                        $cur_cnt++;
                        break;
                    case 'sum':
                        $cur_sum+=$field_val;
                        break;
                    case 'avg':
                        $cur_sum+=$field_val;
                        $cur_cnt++;
                        break;
                    case 'max':
                        if($field_val > $cur_max) $cur_max = $field_val;
                        break;
                    case 'min':
                        if($field_val < $cur_min) $cur_min = $field_val;
                        break;
                }
               
                unset($this->line_data[$key]);
            } else {
                if(!empty($linegroup)) {
                    switch ($this->select_group_fun['fun']){
                        case 'count':
                            $linegroup[$funkey] = $cur_cnt;
                            break;
                        case 'sum':
                            $linegroup[$funkey] = $cur_sum;
                            break;
                        case 'avg':
                            $linegroup[$funkey] = $cur_sum/$cur_cnt;
                            break;
                        case 'max':
                            $linegroup[$funkey]= $cur_max;
                            break;
                        case 'min':
                            $linegroup[$funkey] = $cur_min;
                            break;
                    }
                } 
                  
                $cur_sum = $field_val;
                $cur_cnt = 1;
                $cur_max = $field_val;
                $cur_min = $field_val;
                $linegroup = &$val;
                
            }
            $linelast = &$val;
        }
        
        if(!empty($linegroup)) {
            switch ($this->select_group_fun['fun']){
                case 'count':
                    $linegroup[$funkey] = $cur_cnt;
                    break;
                case 'sum':
                    $linegroup[$funkey] = $cur_sum;
                    break;
                case 'avg':
                    $linegroup[$funkey] = $cur_sum/$cur_cnt;
                    break;
                case 'max':
                    $linegroup[$funkey]= $cur_max;
                    break;
                case 'min':
                    $linegroup[$funkey] = $cur_min;
                    break;
            }
        }
        
    }
    
    function tackleHaving(){
        
        $out = $this->key_work_parse_vals['having'];
        $funkey = $this->getItemKey($out[0]);
        foreach ($this->line_data as $key => $val){
            $fun = $this->opFunMap[$out[1]];
            if(!$this->$fun($val[$funkey],$out[2])){
               unset($this->line_data[$key]);
            }
        }
    }
    

    function order_cmp_asc($params1,$params2){
    
        foreach ($this->key_work_parse_vals['order']['field'] as  $val){
            $val = $this->getItemKey($val);
            if($params1[$val] > $params2[$val]) return 1;
            elseif($params1[$val] < $params2[$val]) return -1;
        }
        return 0;
    }
    

    function order_cmp_desc($params1,$params2){
    
        foreach ($this->key_work_parse_vals['order']['field'] as  $val){
            $val = $this->getItemKey($val);
            if($params1[$val] < $params2[$val]) return 1;
            elseif($params1[$val] > $params2[$val]) return -1;
        }
        return 0;
    }
    
    function tackleOrderBy(){
        
        if($this->key_work_parse_vals['order']['sort'] == 'asc'){
            usort($this->line_data, array($this,'order_cmp_asc'));
        } else {
            usort($this->line_data, array($this,'order_cmp_desc'));
        }
    }
    
    function tackleLimit(){
        
       
    }

   function printRes(){
      
       $fields = $this->key_work_parse_vals['select'];
       foreach ($fields as &$field){
           $field = $this->getItemKey($field);
       }
   
       $this->printComputeVal('','last data');
       
       $start  = isset($this->key_work_parse_vals['limit']['start'])?$this->key_work_parse_vals['limit']['start']:0;
       $count  = isset($this->key_work_parse_vals['limit']['count'])?$this->key_work_parse_vals['limit']['count']:PHP_INT_MAX;
       $end = $start+$count-1;
       $now_key = 0;
       foreach ($this->line_data as $key => $val){
           if($now_key >=$start && $now_key <= $end){
               if($fields[0] === '*'){
                   $separater = "";
                   foreach ($val as $k => $v){
                       if(ctype_digit(strval($k))){
                           echo $separater.$v;
                           $separater = "\t";
                       }
                   }
                   echo "\n";
                   continue;
               }
               $separater = "";
               reset($fields);
               foreach ($fields as $v){
                    echo $separater.$val[$v];
                    $separater = "\t";
               }
               echo "\n";
           }
           $now_key++;
       }
   }
}

//--- test sql

//$sql = '  select $1,regsub($2,  /(.):(.+):(.)/i,  \\2), strsub($3,3,6),sum ($2 ) FROM a.txt  where $2 > 1  group by $1,regsub($2,  /(.):(.+):(.)/i,  \\\2),strsub($3,3,6) having sum ($2 ) > 1 order by sum ($2 ) desc LIMIT 2';
//$sql = "  select count ($1 ) FROM a.txt where $1 > 1 and $2 = 3 order by $3  group by $1,$2 having cnt > 5 asc LIMIT 5";
//$sql = " select strsub( $1,2,3),regsub( $2,/(.*):(.+):(.*)/i,\\2) from b.txt where strsub($1,2,3) = abc ";
//$sql = "select $1,count ($2 ) FROM c.txt   group by $1  order by count ($2 ) desc LIMIT 5";
//$sql = "select count($1),$1 from accesstest.log group by $1 order by count($1) desc limit 10";

//$sql = '  select $1,$10, count ($2 ) ,sum($3) ,avg($4),max( $5 ) ,min($6) ,regsub($1,  /(.):(.+):(.)/i,  \\2),strsub(  $1,  2),strsub( $2, 3, 4) 
//          FROM a.txt 
//          where $1 > 2 and $2 < 3 and  $3 >= 4 and $4 <=5 and $5 = 5  and $6 !=6  and  $7 like abc  and $8 rlike eee';

//$sql = 'select $1,regsub($2,  /(.+):(.+):(.+)/i,\\2),strsub($1,2) ,strsub($1,1,2) from b.txt ';
//$sql  = 'select * from a.txt where $1 > 1 and $2 < 2 and $3 = 3 and $4 !=4 and $5 >=5 and $6 <=6 and $7 like abc and $8 rlike /a\d{3}b/i and strsub($7,2,3) = abc';
//$sql = 'select regsub($2,/(.+):(.+):(.+)/i,\\2),count($1) from b.txt group by regsub($2,  /(.+):(.+):(.+)/i,\\2)';
//$sql = 'select $1,count($1) FROM a.txt group by $1 having count($1) >= 1 order by $1,count($1)  limit 5';



$usage =<<<EOF
usage:
myselect  'sql sentence'; 用 sql进行统计分析
myselect -s 'log line';对日志行按空格进行分割编号
myselect -n 'log line' 'sql sentence'; 对日志行用sql进行解析
myselect -p 'sql sentence'; 查看sql语法解析结果
myselect -c 'sql sentence'; 查看sql计算过程

EOF;


if($GLOBALS['argc'] < 2){
    echo $usage;
    exit;
}

$options= getopt('hspcn:');
if($options === false) {
    echo $usage;
    exit;
}

if(isset($options['h'])){
    echo $usage;
    exit;
}

$sql = $GLOBALS['argv'][$GLOBALS['argc']-1];
//print_r($options);
if(isset($options['s'])){
     if($GLOBALS['argc'] < 3){
        echo $usage;
        exit;
     }
     echo PHP_EOL;
     echo "**log fields**".PHP_EOL;
     $objMyselect = new MySelect('');
     $items = $objMyselect->splitLine(trim($sql));
     foreach ($items as $key => $val){
         echo '$'.($key+1)."\t".$val.PHP_EOL;
     }
     exit;
} 


$objMyselect = new MySelect($sql);

if(isset($options['p'])){
    if($GLOBALS['argc'] < 3){
        echo $usage;
        exit;
    }
    $objMyselect->setViewSynTaxParse(true);
}

if(isset($options['c'])){
    if($GLOBALS['argc'] < 3){
        echo $usage;
        exit;
    }
    $objMyselect->setViewComputeProcess(true);
}

$objMyselect->parseSql();

if(isset($options['n'])){
    echo PHP_EOL;
    echo "**log line**".PHP_EOL;
    echo $options['n'].PHP_EOL;
    $res = $objMyselect->tackleWhere($options['n']);
    echo "**log fields**".PHP_EOL;
    if($res === false){
        echo "empty res";
    } else {
        print_r($res);
    }
    echo PHP_EOL;
    exit;
}


$objMyselect->compute();
$objMyselect->printRes();


