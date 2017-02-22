<?php
/**
 * Please modify following line to load your configuration
 */
$config   = new Phalcon\Config\Adapter\Php('../../app/config/config.php');
/**
 * Please modify the following configuration array according to your situation
 */
$pwConfig = [
    'db' => [
        'host'     => $config->db->host,
        'username' => $config->db->username,
        'password' => $config->db->password,
        'dbname'   => $config->db->dbname,
        'tablePrefix' => 'se_',//Table prefix 
    ],
    'dir' => [
        'controller' => '../../app/controllers',
        'model'      => '../../app/models',
        'view'       => '../../app/views',
    ]
];
/*
$pwConn   = new Phalcon\Db\Adapter\Pdo\Mysql([
    'host'     => $pwConfig['db']['host'],
    'username' => $pwConfig['db']['username'],
    'password' => $pwConfig['db']['password'],
    'dbname'   => $pwConfig['db']['dbname'], 
]);*/
//echo '<pre>';
//var_dump($pwConn->describeColumns('se_user'));
//var_dump($pwConn->fetchAll('SHOW FULL COLUMNS FROM `se_user`'));
//var_dump($pwConn->describeReferences('se_user_gids'));
//var_dump($pwConn->describeIndexes('se_user'));
//var_dump($pwConn->describeReferences('se_user'));
//exit;
class PhalconWings 
{
    /**
     *@var array $config PhalconWings config
     */
    private $config = [];

    public $table = '';

    /**
     *@var array $tableinfo Table infomation
     */
    private static $tableInfo = [];

    /**
     *@var object $connection 
     */
    private $connection = null;
    /**
     * Constructor
     * Initilize Envriment
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        foreach((array) $config['dir'] as $name => $dir){
            if( 0 == $this->isWriteable($dir) ){
                die($name.' directory is not writable!');
            }
        }
    }

    public function setConnection()
    {
        $this->connection = new Phalcon\Db\Adapter\Pdo\Mysql([
            'host'     => $this->config['db']['host'],
            'username' => $this->config['db']['username'],
            'password' => $this->config['db']['password'],
            'dbname'   => $this->config['db']['dbname'], 
        ]);
        $this->connection->execute("SET NAMES 'utf8'");
    }

    public function setTable($table)
    {
        if(!$this->connection->tableExists($table)){
            die("Table {$table} dose not exists!");
        }
        $this->table = $table;
        $infos  = $this->connection->fetchAll("SHOW FULL COLUMNS FROM `{$table}`");
        $fields  = [];
        $pk      = [];
        $comment = [];
        $types   = [];
        $defaults = [];
        $length   = [];
        foreach($infos as $field){

            $fields[]=$field['Field'];
            $comment[$field['Field']] = $field['Comment'];
            preg_match('/(\w+)\((\d+)\)/i', $field['Type'],$match);
            $types[$field['Field']] = empty($match[1]) ? $field['Type'] : $match[1];
            if(!empty($match[2])){
                $length[$field['Field']]=$match[2];
            }
            if( $field['Extra'] == 'auto_increment' ){
                if( $field['Key'] == 'PRI' ){
                    $pk[]=$field['Field'];
                }
                continue;
            }
            $defaults[$field['Field']] = $field['Default'];
        }
        $model = $this->camlize(str_replace($this->config['db']['tablePrefix'],'',$table));
        self::$tableInfo[$table] = [
            'modelName' => $model,
            'pk'        => $pk,
            'allFields' => $fields,
            'comment'   => $comment,
            'types'     => $types,
            'defaults'  => $defaults,
            'length'    => $length,
        ];
        return true;
    }

    public function generateModel()
    {
        $code   = ['<?php'];
        $code[] = 'use Phalcon\Mvc\Model;';
        $code[] = 'use Phalcon\Validation;';
        $code[] = 'use Phalcon\Validation\Validator\PresenceOf;';
        $code[] = 'use Phalcon\Validation\Validator\StringLength;';
        $code[] = 'use Phalcon\Validation\Validator\Numericality;';

        $info   = self::$tableInfo[$this->table];
        $model  = $info['modelName'];
        $code[] = "class {$model} extends Model";
        $code[] = '{';
        
        $vars   = ["\r\n"];
        $seters = ["\r\n"];
        $geters = ["\r\n"];

        $rules  = "\r\n".'    public function validation()'."\r\n";
        $rules .= '    {'."\r\n";
        $rules .= '        $validator = new Validation();'."\r\n";

        foreach($info['allFields'] as $field){

            $type = '';
            if(preg_match('/int$/i', $info['types'][$field])){
                $type = 'integer';
            }elseif(preg_match('/(text|char|datetime|date)$/i', $info['types'][$field])){
                $type = 'string';
            }elseif(in_array($info['types'][$field], ['float','real','decimal'])){
                $type = 'float';
            }

            if(!isset($info['defaults'][$field]) && !in_array($field, $info['pk'])){
                $rules .='        $validation->add(\''.$field.'\', new PresenceOf(['."\r\n";
                $memo   = $info['comment'][$field];
                $rules .='            \'message\' => \''.$memo.'不能为空\''."\r\n";
                $rules .='        ]));'."\r\n";
            }

            $vars[]   = '    /**';
            $vars[]   = '     *@var '.$type;
            $vars[]   = '     */';
            $vars[]   = '    protected $'.$field.';';

            if(!in_array($field, $info['pk'])){
                $seters[] = '    public function set'.$this->camlize($field).'($'.$field.')';
                $seters[] = '    {';
                $seters[] = '        $this->'.$field.' = $'.$field.';';
                $seters[] = '    }'."\r\n";
                $geters[] = '    public function get'.$this->camlize($field).'()';
                $geters[] = '    {';
                $geters[] = '        return $this->'.$field.';';
                $geters[] = '    }'."\r\n";
            }
        }
        $code = implode("\r\n", $code);
        $code.= implode("\r\n", $vars);
        $code.= implode("\r\n", $seters);
        $code.= implode("\r\n", $geters);
        $code.= "\r\n".'    public function initialize()';
        $code.= "\r\n".'    {';
        $code.= "\r\n".'        $this->setSource(\''.$this->table.'\');';
        $code.= "\r\n".'    }';
        $rules .= '        return $this->validate($validator);'."\r\n";
        $rules .= '    }'."\r\n";
        $code.= $rules;
        $code.="\r\n}";
        
        $file = rtrim($this->config['dir']['model'],'/\\').'/'.$model.'.php';
        if(file_exists($file)){
            echo '<font color="blue">Model file exists,Delete it to generate a new one!</font>';
        }else{
            if(false === file_put_contents($file, $code)){
                echo '<font color="red">Model generate faild!</font>';
            }else{
                echo '<font color="green">Model generate success</font>';
            }
        }
    }

    public function generateController()
    {
        $code = ['<?php'];
        $pkgs = [];
        $code[]= 'use Phalcon\Mvc\Model;';
    }

    public function generateViews()
    {
        $info = self::$tableInfo[$this->table];
        $var  = strtolower($info['modelName']);
        $addhtml   = '<!doctype html>
<html lang="en">
<head>
<meta name="renderer" content="webkit" />
<meta charset="UTF-8">
<title>新增xxxx</title>
<link rel="stylesheet" type="text/css" href="/static/css/sind.css" />
</head>
<body>
<table width="100%" cellspacing="0" cellpadding="0" border="0">
  <tr>
    <td>
      <div class="path">
        <a href="{{url(\'index/index\')}}">首页</a> <span class="split">/</span>
        <a href="{{url(\''.$var.'/index\')}}">xxxx管理</a> <span class="split">/</span>
        <a>新增xxxx</a>
      </div>
    </td>
  </tr>
</table>
<form action="{{url(\''.$var.'/add\')}}" onsubmit="return false;">
    <table width="100%" cellspacing="0" cellpadding="8" border="0" class="formtable">
      <tr class="th">
        <td colspan="2">新增xxxx</td>
      </tr>';
      foreach($info['comment'] as $name => $label){
          if($name=='status'){
              continue;
          }
          $addhtml.='
      <tr class="tb">
        <td class="label">'.$label.'：</td>
        <td>
          <input type="text" name="'.$name.'" class="ipt" />
          <span class="tips">'.$label.'</span>
        </td>
      </tr>';
      }
        $addhtml.='<tr class="tb2">
        <td>&nbsp;</td>
        <td>
          <input type="button" role="submit" class="sbtn blue" msg="保存成功" redirect="{{url(\''.$var.'/index\')}}" value="提交保存" />
        </td>
      </tr>
    </table>
</form>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
</body>
</html>';
        $file = rtrim($this->config['dir']['view'],'/\\').'/user/add.htm';
        if(file_exists($file)){
            echo '<font color="blue">View add file exists,Delete it to generate a new one!</font>';
        }else{
            if(false === file_put_contents($file, $addhtml)){
                echo '<font color="red">View add generate faild!</font>';
            }else{
                echo '<font color="green">View add generate success</font>';
            }
        }
    }

    public function responseJSON(array $response)
    {
        header('Content-Type:application/json');
        die(json_encode($response));
    }

    public function isWriteable($dir)
    {
        if ($fp = fopen("$dir/pw.txt", 'w')) {
            fclose($fp);
            unlink("$dir/pw.txt");
            $writeable = 1;
        } else {
            $writeable = 0;
        }
        return $writeable;
    }

    public function camlize($str, $ucfirst = true)
    {
        $str = ucwords(str_replace('_', ' ', $str));
        $str = str_replace(' ','',lcfirst($str));
        return $ucfirst ? ucfirst($str) : $str;
    }
}

$pw     = new PhalconWings($pwConfig);
$pw->setConnection();
$action = $_GET['action'];
$table  = preg_replace('/[^a-z0-9_]+/i','',$_GET['table']);

if(!empty($table)){
    $pw->setTable($table);
    if(in_array('model', $action)){
        $pw->generateModel();
        $pw->generateViews();
    }
}
