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
        'charset'  => 'utf8',
    ],
    'dir' => [
        'controller' => '../../app/controllers',
        'model'      => '../../app/models',
        'view'       => '../../app/views',
    ],
    'volt_extension' => '.htm',
];
/**
 * Main class
 */
class PhalconWings 
{
    /**
     *@var array $config PhalconWings config
     */
    private $config    = [];

    /**
     *@var string $table Current working table
     */
    private $table      = '';

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
     *
     * @access public
     * @param array $config
     * @return boolean
     */
    public function __construct(array $config)
    {
        if(empty($config)){
            throw new \Exception('配置不能为空');
            return false;
        }
        $this->config = $config;
        foreach((array) $config['dir'] as $name => $dir){
            if( 0 == $this->isWriteable($dir) ){
                throw new \Exception("目录“{$name}”没有写权限");
                return false;
            }
        }
        $this->initConnection();
        return true;
    }

    /**
     * Initilize the Database connection
     *
     * @access private
     * @return void
     */
    private function initConnection()
    {
        $this->connection = new Phalcon\Db\Adapter\Pdo\Mysql([
            'host'     => $this->config['db']['host'] ,
            'username' => $this->config['db']['username'] ,
            'password' => $this->config['db']['password'] ,
            'dbname'   => $this->config['db']['dbname'] , 
        ]);
        $this->connection->execute("SET NAMES '" . $this->config['db']['charset'] . "'");
    }

    /**
     * Get all tables from current database
     *
     * @access public
     * @return array
     */
    public function getTables()
    {
        $infos = $this->connection->listTables();
        return $infos;
    }

    /**
     * Set working table
     *
     * @access public
     * @param string $table
     * @return boolean
     */
    public function setTable($table)
    {
        if(!$this->connection->tableExists($table)){
            throw new \Exception("表 {$table} 不存在!");
            return false;
        }
        $this->table = $table;
        $infos    = $this->connection->fetchAll("SHOW FULL COLUMNS FROM `{$table}`");
        $fields   = [];
        $pk       = [];
        $comment  = [];
        $types    = [];
        $defaults = [];
        $length   = [];
        foreach($infos as $field){

            $fields[]=$field['Field'];
            $comment[ $field['Field'] ] = $field['Comment'];

            preg_match('/(\w+)\((\d+)\)/i', $field['Type'], $match);

            $types[ $field['Field'] ] = empty($match[1]) ? $field['Type'] : $match[1];
            if( !empty($match[2]) ){
                $length[$field['Field']]=$match[2];
            }
            if( $field['Extra'] == 'auto_increment' ){
                if( $field['Key'] == 'PRI' ){
                    $pk[]=$field['Field'];
                }
                continue;
            }
            $defaults[ $field['Field'] ] = $field['Default'];
        }
        $model = $this->camlize( str_replace($this->config['db']['tablePrefix'], '', $table) );

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

    /**
     * Generate Model code
     *
     * @access public
     * @return void
     */
    public function generateModel()
    {
        $info   = self::$tableInfo[$this->table];
        $model  = $info['modelName'];
        $code   = '<?php'."\r\n";
        $code  .= 'use Phalcon\Mvc\Model;'."\r\n";
        $code  .= 'use Phalcon\Validation;'."\r\n";
        $code  .= 'use Phalcon\Validation\Validator\PresenceOf;'."\r\n";
        $code  .= 'use Phalcon\Validation\Validator\StringLength;'."\r\n";
        $code  .= 'use Phalcon\Validation\Validator\Numericality;'."\r\n";
        $code  .= "class {$model} extends Model\n\r";
        $code  .= '{'."\r\n";

        $vars   = [];
        $seters = [];
        $geters = [];

        $rules  = '    public function validation()'."\r\n";
        $rules .= '    {'."\r\n";
        $rules .= '        $validator = new Validation();'."\r\n";

        foreach($info['allFields'] as $field){

            $type = '';
            if( preg_match('/int$/i', $info['types'][$field]) ){
                $type = 'integer';
            }elseif( preg_match('/(text|char|datetime|date)$/i', $info['types'][$field]) ){
                $type = 'string';
            }elseif( in_array($info['types'][$field], ['float', 'real', 'decimal']) ){
                $type = 'float';
            }

            if( !isset($info['defaults'][$field]) && !in_array($field, $info['pk']) ){
                $memo   = $info['comment'][$field];
                $rules .= '        $validator->add(\''.$field.'\', new PresenceOf(['."\r\n";
                $rules .= '            \'message\' => \''.$memo.'不能为空\''."\r\n";
                $rules .= '        ]));'."\r\n";
            }

            $vars[] = '    /**';
            $vars[] = '     *@var '.$type;
            $vars[] = '     */';
            $vars[] = '    protected $'.$field.';';

            if(!in_array($field, $info['pk'])){
                $seters[] = '    public function set'.$this->camlize($field).'($'.$field.')';
                $seters[] = '    {';
                $seters[] = '        $this->'.$field.' = $'.$field.';';
                $seters[] = '    }';
                $geters[] = '    public function get'.$this->camlize($field).'()';
                $geters[] = '    {';
                $geters[] = '        return $this->'.$field.';';
                $geters[] = '    }';
            }
        }
        $code  .= "\r\n".implode("\r\n", $vars)."\r\n";
        $code  .= "\r\n";
        $code  .= implode("\r\n", $seters)."\r\n";
        $code  .= implode("\r\n", $geters)."\r\n";
        $code  .= "\r\n";
        $code  .= '    public function initialize()'."\r\n";
        $code  .= '    {'."\r\n";
        $code  .= '        $this->setSource(\''.$this->table.'\');'."\r\n";
        $code  .= '        $this->setup([\'notNullValidations\'=>false]);'."\r\n";
        $code  .= '    }'."\r\n";
        $rules .= '        return $this->validate($validator);'."\r\n";
        $rules .= '    }'."\r\n";
        $code  .= $rules;
        $code  .= "\r\n}";
        
        $file = rtrim($this->config['dir']['model'],'/\\').'/'.$model.'.php';

        if(file_exists($file)){
            echo '<font color="blue">模型已存在，请手动删除后，重新生成</font>';
        }else{
            if(false === file_put_contents($file, $code)){
                echo '<font color="red">模型生成失败</font>';
            }else{
                echo '<font color="green">模型生成成功，请根据自己的情况修改相关逻辑</font>';
            }
        }
    }

    /**
     * Generate Controller code
     *
     * @access public
     * @return void
     */
    public function generateController()
    {
        //TODO
        $code  = '<?php'."\r\n";
        $code .= 'use Phalcon\Mvc\Model;'."\r\n";
    }
    
    /**
     * Generate views code
     *
     * @access public
     * @return void
     */
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
<link rel="stylesheet" type="text/css" href="/static/css/phlconwings.css" />
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
          <a role="pw_submit" class="sbtn blue" msg="保存成功" redirect="{{url(\''.$var.'/index\')}}"> 提交保存 </a>
        </td>
      </tr>
    </table>
</form>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<script type="text/javascript" src="/static/js/PhalconWings.js"></script>
</body>
</html>';
        $file = rtrim($this->config['dir']['view'],'/\\').'/'.$var.'/add'.$this->config['volt_extension'];
        if(file_exists($file)){
            echo '<font color="blue">新增模板已存在，请手动删除后，重新生成!</font>';
        }else{
            if(false === file_put_contents($file, $addhtml)){
                echo '<font color="red">新增模板创建失败!</font>';
            }else{
                echo '<font color="green">新增模板创建成功，请根据需求修改！</font>';
            }
        }
    }

    /**
     * Check a dir if readable
     *
     * @access private
     * @param string $dir
     * @return boolean
     */
    private function isWriteable($dir)
    {
        if ($fp = fopen("$dir/pw.tmp", 'w')) {
            fclose($fp);
            unlink("$dir/pw.tmp");
            $writeable = true;
        } else {
            $writeable = false;
        }
        return $writeable;
    }

    /**
     * Camlize a string
     *
     * @access private
     * @param string $str
     * @param boolean $ucfirst
     * @return string
     */
    private function camlize($str, $ucfirst = true)
    {
        $str = ucwords(str_replace('_', ' ', $str));
        $str = str_replace(' ','',lcfirst($str));
        return $ucfirst ? ucfirst($str) : $str;
    }

}//End Class

/**
 * A ugly Dispatcher ^_^
 */

$action  = isset($_GET['action']) ? $_GET['action'] : '';
$table   = preg_replace('/[^a-z0-9_]+/i', '', isset($_GET['table']) ? $_GET['table'] : '' );
$message = '';

try{

    $pw     = new PhalconWings($pwConfig);
    $tables = $pw->getTables();
    if( !empty($table) ){
        $pw->setTable($table);
        if( 'model' == $action){
            $pw->generateModel();
            exit;
        }
        if( 'controller' == $action){
            $pw->generateController();
            exit;
        }   
        if( 'view' == $action){
            $pw->generateViews();
            exit;
        }
    }

} catch( \Exception $e){
    $message = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta name="renderer" content="webkit" />
<meta charset="UTF-8">
<title>PhalconWings</title>
<link rel="stylesheet" type="text/css" href="/static/css/phalconwings.css" />
<style type="text/css">
.formtable .lasttr td { border-bottom:none;}
</style>
</head>
<body>
<table width="100%" cellspacing="0" cellpadding="8" border="0" class="formtable">
  <tr class="th">
      <td colspan="2">PhalconWings(Phalcon 3.0 + MySQL 后台代码生成工具)</td>
  </tr>
  <?php if(!empty($message)):?>
  <tr class="tb2">
    <td colspan="2">
        <div class="notice"><?php echo $message;?></div>
    </td>
  </tr>
  <?php endif;?>
  <tr class="tb">
    <td class="label" style="width:120px;">数据表：<br>Database table : </td>
    <td><select name="table" id="table" class="ipt">
    <option value="">请选择数据表</option>
    <?php foreach((array)$tables as $tablename):?>
    <option value="<?php echo $tablename;?>"><?php echo $tablename;?></option>
    <?php endforeach;?>
    </select>
    </td>
  </tr>
  <tr class="tb">
    <td class="label">生成选项：<br>Generate items: </td>
    <td>
        <input type="checkbox" name="ctl" id="ctlc" /> <label for="ctlc">控制器(Controller)</label>
        &nbsp;&nbsp;
        <input type="checkbox" name="mod" id="modc" /> <label for="modc">模型(Model)</label>
        &nbsp;&nbsp;
        <input type="checkbox" name="tpl" id="tplc" /> <label for="tplc">视图(Views)</label>
    </td>
  </tr>
  <tr class="tb">
    <td>&nbsp;</td>
    <td><input class="sbtn blue" type="button" value=" 一键生成 " id="docreate" /></td>
  </tr>
  <tr class="tb">
    <td class="label">生成结果：<br>Generate result :</td>
    <td>
      <table width="100%" cellpadding="5" border="0" style="border:1px solid #ccc;">
        <tr>
          <td width="100" class="label">模型：<br>Model : </td>
          <td><div id="modcon" class="ctrs">...</div></td>
        </tr>
        <tr>
          <td class="label">控制器：<br>Controller : </td>
          <td><div id="ctlcon" class="ctrs">...</div></td>
        </tr>
        <tr class="lasttr">
          <td class="label">视图：<br>View : </td>
          <td><div id="tplcon" class="ctrs">...</div></td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<!--
<script type="text/javascript" src="/static/js/PhalconWings.js"></script>
-->
<script type="text/javascript">
$(document).ready(function(){
  $('#docreate').click(function(){
    var table = $('#table').val();
    if( table == '' ){
      art.dialog.alert('请选择数据表！');
      return;
    }
    if($('#modc').get(0).checked) $('#modcon').load('?action=model&table='+table);
    if($('#ctlc').get(0).checked) $('#ctlcon').load('?action=controller&table='+table);
    if($('#tplc').get(0).checked) $('#tplcon').load('?action=view&table='+table);
  });
});
</script>

</body>
</html>