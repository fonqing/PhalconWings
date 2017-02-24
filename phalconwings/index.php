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
    'volt_extension' => '.htm',//Your volt view file extension
    'baseController' => 'Controller',//your base Controller，default value is Phalcon\Mvc\Controller
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
            throw new \Exception('Configuration can\'t empty');
            return false;
        }
        $this->config = $config;
        foreach((array) $config['dir'] as $name => $dir){
            if( 0 == $this->isWriteable($dir) ){
                throw new \Exception("Directory “{$name}” write permission required");
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
            throw new \Exception("Table {$table} not exists!");
            return false;
        }
        $this->table = $table;
        $infos       = $this->connection->fetchAll("SHOW FULL COLUMNS FROM `{$table}`");
        $fields      = [];
        $primarykeys = [];

        foreach($infos as $field){
            
            preg_match('/(\w+)\((\d+)\)/i', $field['Type'], $match);
            $length = null;
            $type   = empty($match[1]) ? $field['Type'] : $match[1];

            if( !empty($match[2]) ){
                $length = $match[2];
            }

            if( $field['Key'] == 'PRI' ){
                $primarykeys[]=$field['Field'];
            }

            $fields[$field['Field']] = [
                'name'    => $field['Field'],
                'type'    => $type,
                'length'  => $length,
                'default' => $field['Default'],
                'key'     => $field['Key'],
                'extra'   => $field['Extra'],
                'comment' => $field['Comment'],
            ];
            //auto_increment
        }
        $model = $this->camlize( str_replace($this->config['db']['tablePrefix'], '', $table) );
        self::$tableInfo[$table] = [
            'modelName' => $model,
            'pk'        => $primarykeys,
            'fields'    => $fields,
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

        foreach($info['fields'] as $fieldname => $field){

            $type = '';
            if( preg_match('/int$/i', $field['type']) ){
                $type = 'integer';
            }elseif( preg_match('/(text|char|datetime|date)$/i', $field['type']) ){
                $type = 'string';
            }elseif( in_array($field['type'], ['float', 'real', 'decimal']) ){
                $type = 'float';
            }

            if( is_null($field['default']) && !in_array($fieldname, $info['pk']) ){
                $rules .= '        $validator->add(\''.$fieldname.'\', new PresenceOf(['."\r\n";
                $rules .= '            \'message\' => \''.$field['comment'].'不能为空\''."\r\n";
                $rules .= '        ]));'."\r\n";
            }

            $vars[] = '    /**';
            $vars[] = '     *@var '.$type;
            $vars[] = '     */';
            $vars[] = '    protected $'.$fieldname.';';

            if(!in_array($fieldname, $info['pk'])){
                $seters[] = '    public function set'.$this->camlize($fieldname).'($'.$fieldname.')';
                $seters[] = '    {';
                $seters[] = '        $this->'.$fieldname.' = $'.$fieldname.';';
                $seters[] = '    }';
            }
            $geters[] = '    public function get'.$this->camlize($fieldname).'()';
            $geters[] = '    {';
            $geters[] = '        return $this->'.$fieldname.';';
            $geters[] = '    }';
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
        $info  = self::$tableInfo[$this->table];
        $name  = $info['modelName'];
        $cname = ucfirst($name);
        $vname = strtolower($name);

        $code  = '<?php'."\r\n";
        $code .= 'use Phalcon\Mvc\Controller;'."\r\n";

        $code .= 'class ' . $cname . 'Controller extends ' . $this->config['baseController'] . "\r\n";
        $code .= '{'."\r\n";
      
        $code .= '    public function indexAction()'."\r\n";
        $code .= '    {'."\r\n";
        $code .= '        $page = $this->request->get(\'page\', \'int\', 1);'."\r\n";
        $code .= '        $page = max(1, $page);'."\r\n\r\n";
        $code .= '        $conditions = [];'."\r\n\r\n";
        $code .= '        if ($this->request->isPost()) {'."\r\n";
        $code .= '            //add conditions'."\r\n";
        $code .= '        }'."\r\n";
        $code .= '        $'.$vname.'s = '.$cname.'::find($conditions);'."\r\n";
        $code .= '        $paginator = new Paginator(['."\r\n";
        $code .= '            \'data\'  => $'.$vname.'s,'."\r\n";
        $code .= '            \'limit\' => 20,'."\r\n";
        $code .= '            \'page\'  => $page,'."\r\n";
        $code .= '        ]);'."\r\n";
        $code .= '        $pager = $paginator->getPaginate();'."\r\n";
        $code .= '    }'."\r\n\r\n";

        $code .= '    public function addAction()'."\r\n";
        $code .= '    {'."\r\n";
        $code .= '        if( $this->request->isPost() ){'."\r\n";
        $code .= '            $'.$vname.' = new ' . $cname . '();'."\r\n";
        
        foreach($info['fields'] as $fieldname => $field){

            if($field['extra'] == 'auto_increment'){
                continue;
            }

            $filter = 'string';
            if( preg_match('/int$/i', $field['type']) ){
                $filter = 'int';
            }elseif( preg_match('/(text|char|datetime|date)$/i', $field['type']) ){
 
            }elseif( in_array($field['type'], ['float', 'real', 'decimal']) ){
                $filter = 'float';
            }elseif( preg_match('/email/i', $fieldname)){
                $filter = 'email';
            }
            if( is_null($field['default']) ) {
                $code .= '            $'.$vname.'->'.$fieldname.' = ';
                $code .= '$this->request->getPost(\''.$fieldname.'\',\''.$filter.'\');'."\r\n";
            }else{
                $code .= '            $'.$vname.'->'.$fieldname.' = ';
                $code .= '$this->request->getPost(\''.$fieldname.'\',\''.$filter.'\',\''.$field['default'].'\');'."\r\n";
            }
            

        }

        $code .= '            if ($'.$vname.'->save() === false) {'."\r\n";
        $code .= '                $messages = $'.$vname.'->getMessages();'."\r\n";
        $code .= '                $response = [\'status\' => 0 ];'."\r\n";
        $code .= '                foreach ($messages as $msg) {'."\r\n";
        $code .= '                    $response[\'msg\']=$msg->getMessage();'."\r\n";
        $code .= '                    break;'."\r\n";
        $code .= '                }'."\r\n";
        $code .= '                return $this->response->setJsonContent($response);'."\r\n";
        $code .= '            } else {'."\r\n";
        $code .= '                return $this->response->setJsonContent([ \'status\' => 1]);'."\r\n";
        $code .= '            }'."\r\n";
        $code .= '            $this->view->disable();'."\r\n";
        $code .= '        }'."\r\n";
        $code .= '    }'."\r\n\r\n";


        $code .= '}'."\r\n";
    }
    
    /**
     * Generate views code
     *
     * @access public
     * @return void
     */
    public function generateViews()
    {
        //TODO: edit view,list view
        $info    = self::$tableInfo[$this->table];
        $var     = strtolower($info['modelName']);
        
        //Generate add views
        $addfile = rtrim($this->config['dir']['view'],'/\\').'/'.$var.'/add'.$this->config['volt_extension'];
        if(file_exists($addfile)){
            echo '<font color="blue">新增模板已存在，请手动删除后，重新生成!</font>';
        }else{
            $addhtml   = file_get_contents('./tpl/add.tpl');
            $addblock  = '';
            foreach($info['fields'] as $name => $field){
                if($field['extra'] == 'auto_increment'){
                    continue;
                }
                if( in_array($name, ['status']) ){
                    continue;
                }
                $addblock .= '      <tr class="tb">'."\r\n";
                $addblock .= '        <td class="label">'.$field['comment'].'：</td>'."\r\n";
                $addblock .= '        <td><input type="text" name="'.$name.'" class="ipt" />';
                $addblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                $addblock .= '      </tr>'."\r\n";
            }
            $addhtml = str_replace(['##ctl##','##addblock##'], [$var, $addblock], $addhtml);
            if(false === file_put_contents($addfile, $addhtml)){
                echo '<font color="red">新增模板创建失败!</font>';
            }else{
                echo '<font color="green">新增模板创建成功，请根据需求修改！</font>';
            }
        }

        //Generate index/list views
        $listfile = rtrim($this->config['dir']['view'],'/\\').'/'.$var.'/index'.$this->config['volt_extension'];
        if(file_exists($addfile)){
            echo '<font color="blue">列表模板已存在，请手动删除后，重新生成!</font>';
        }else{
            $listhtml = file_get_contents('./tpl/index.tpl');
            $thblock  = '';
            $tdblock  = '';
            foreach($info['fields'] as $name => $field){
                $thblock .= '<td>'.$field['comment'].'</td>'."\r\n";
                $tdblock .= '<td>{{'.$var.'.'.$name.'}}</td>'."\r\n";
            }
            $colspan = count($info['fields'])+2;
            $listhtml = str_replace(
                ['##ctl##','##pk##', '##thblock##','##tdblock##','##colspan##'] , 
                [$var, $info['pk'][0], $thblock, $tdblock, $colspan] , 
                $listhtml );

            if(false === file_put_contents($listfile, $listhtml)){
                echo '<font color="red">列表模板创建失败!</font>';
            }else{
                echo '<font color="green">列表模板创建成功，请根据需求修改！</font>';
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
        $str = str_replace(' ', '', lcfirst($str));
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