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
     * @throws Exception
     */
    public function __construct(array $config)
    {
        if(empty($config)){
            throw new \Exception('Configuration can\'t empty');
        }
        $this->config = $config;
        foreach((array) $config['dir'] as $name => $dir){
            if( 0 == $this->isWriteable($dir) ){
                throw new \Exception("Directory “{$name}” write permission required");
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
        $tables = $this->connection->listTables();
        return $tables;
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
        }
        $this->table = $table;
        $infos       = $this->getTableInfo($table);
        $model       = $this->getModelName($table);

        self::$tableInfo[$table] = [
            'modelName' => $model,
            'pk'        => $infos['pk'],
            'fields'    => $infos['fields'],
        ];
        return true;
    }

    private function getTableInfo($table)
    {
        $fields      = [];
        $primarykeys = [];
        $infos       = $this->connection->fetchAll("SHOW FULL COLUMNS FROM `{$table}`");
        foreach($infos as $field){
            preg_match('/(\w+)\((\d+)\)/i', $field['Type'], $match);
            $length  = null;
            $type    = empty($match[1]) ? $field['Type'] : $match[1];

            if( !empty($match[2]) ){
                $length = intval($match[2]);
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
        }
        return [
            'fields' => $fields,
            'pk'     => $primarykeys
        ]; 
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
        $file   = rtrim($this->config['dir']['model'],'/\\').'/'.$model.'.php';

        if(file_exists($file)){

            echo '<font color="blue">Model exists,Please delete it manually to regenerate.</font>';

        }else{

            $code   = '<?php'."\r\n";
            $code  .= 'use Phalcon\Mvc\Model;'."\r\n";
            $code  .= 'use Phalcon\Validation;'."\r\n";
            $code  .= 'use Phalcon\Validation\Validator\PresenceOf;'."\r\n";
            $code  .= 'use Phalcon\Validation\Validator\StringLength;'."\r\n";
            $code  .= 'use Phalcon\Validation\Validator\Numericality;'."\r\n";
            $code  .= 'use Phalcon\Validation\Validator\Date as DateValidator;'."\r\n";
            $code  .= "class {$model} extends Model\r\n";
            $code  .= '{'."\r\n";

            $vars   = [];
            $seters = [];
            $geters = [];

            $rules  = '    public function validation()'."\r\n";
            $rules .= '    {'."\r\n";
            $rules .= '        $validator = new Validation();'."\r\n";

            foreach($info['fields'] as $fieldname => $field){

                $isAutoincrement  = ($field['extra'] == 'auto_increment');
                $isRequired       = is_null($field['default']) && !$isAutoincrement;

                if( $isRequired ){
                    $rules .= '        $validator->add(\''.$fieldname.'\', new PresenceOf(['."\r\n";
                    $rules .= '            \'message\' => \''.$field['comment'].' can\\\'t empty!\''."\r\n";
                    $rules .= '        ]));'."\r\n";
                }

                $type = '';
                if( preg_match('/int$/i', $field['type']) ){
                    $type   = 'integer';
                    if( $isRequired ){
                        $rules .= '        $validator->add(\''.$fieldname.'\', new Numericality(['."\r\n";
                        $rules .= '            \'message\' => \''.$field['comment'].' must be a number!\''."\r\n";
                        $rules .= '        ]));'."\r\n";
                    }

                }elseif( preg_match('/(text|char|datetime|date)$/i', $field['type']) ){

                    $type   = 'string';
                    if( $isRequired ){
                        if($field['type'] == 'date'){
                            $rules .= '        $validator->add(\''.$fieldname.'\', new DateValidator(['."\r\n";
                            $rules .= '            \'format\' => \'Y-m-d\','."\r\n";
                            $rules .= '            \'message\' => \''.$field['comment'].' invalid!\''."\r\n";
                            $rules .= '        ]));'."\r\n";
                        }elseif($field['type'] == 'datetime'){
                            $rules .= '        $validator->add(\''.$fieldname.'\', new DateValidator(['."\r\n";
                            $rules .= '            \'format\' => \'Y-m-d H:i:s\','."\r\n";
                            $rules .= '            \'message\' => \''.$field['comment'].' invalid!\''."\r\n";
                            $rules .= '        ]));'."\r\n";
                        }else{

                            $length = (int)$field['length'];
                            if( $length > 0 ) {
                                $rules .= '        $validator->add(\''.$fieldname.'\', new StringLength(['."\r\n";
                                $rules .= '            \'min\' => 1, \'max\' => '.$length.','."\r\n";
                                $rules .= '            \'messageMinimum\' => \''.$field['comment'].' too short!\','."\r\n";
                                $rules .= '            \'messageMaximum\' => \''.$field['comment'].' too long!\''."\r\n";
                                $rules .= '        ]));'."\r\n";
                            }
                        }
                    }
                }elseif( in_array($field['type'], ['float', 'real', 'decimal']) ){
                    $type   = 'float';
                    if( $isRequired ){
                        $rules .= '        $validator->add(\''.$fieldname.'\', new Numericality(['."\r\n";
                        $rules .= '            \'message\' => \''.$field['comment'].' must be a number!\''."\r\n";
                        $rules .= '        ]));'."\r\n";
                    }
                }

                $vars[] = '    /**';
                $vars[] = '     *@var '.$type;
                $vars[] = '     */';
                $vars[] = '    protected $'.$fieldname.';';

                if( !$isAutoincrement ){
                    $seters[] = '    public function set'.$this->camelize($fieldname).'($'.$fieldname.')';
                    $seters[] = '    {';
                    $seters[] = '        $this->'.$fieldname.' = $'.$fieldname.';';
                    $seters[] = '    }';
                }
                $geters[] = '    public function get'.$this->camelize($fieldname).'()';
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
            $code  .= '        $this->setup([\'notNullValidations\' => false]);'."\r\n";
            $rel    = $this->parseRelation($this->table);
            if(!empty($rel)){
                $code  .= '        '.$rel;
            }else{
                $code  .= "        #RELATION#\r\n";
            }
            $code  .= '    }'."\r\n";
            $rules .= '        return $this->validate($validator);'."\r\n";
            $rules .= '    }'."\r\n";
            $code  .= $rules;
            $code  .= "\r\n}";

            if(false === file_put_contents($file, $code)){
                echo '<font color="red">Generate Model failed!</font>';
            }else{
                echo '<font color="green">Generate success! Please modify according to your needs!</font>';
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
        $info  = self::$tableInfo[$this->table];
        $name  = $info['modelName'];
        $cname = ucfirst($name);
        $vname = strtolower($name);
        $pk    = $info['pk'][0];

        $file = rtrim($this->config['dir']['controller'],'/\\').'/'.$cname.'Controller.php';

        if( file_exists($file) ){

            echo '<font color="blue">Controller exists,Please delete it manually to regenerate.</font>';

        } else {

            $code  = '<?php'."\r\n";
            $code .= 'use Phalcon\Mvc\Controller;'."\r\n";
            $code .= 'use Phalcon\Paginator\Adapter\Model as Paginator;'."\r\n";

            $code .= 'class ' . $cname . 'Controller extends ' . $this->config['baseController'] . "\r\n";
            $code .= '{'."\r\n";
            
            #index Action
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
            $code .= '        $this->view->pager = $pager;'."\r\n";
            $code .= '    }'."\r\n\r\n";
            
            #Add Action
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

            #Edit Action
            $code .= '    public function editAction()'."\r\n";
            $code .= '    {'."\r\n";
            $code .= '        if( $this->request->isPost() ){'."\r\n";
            $code .= '            $id  =  $this->request->getPost(\'id\', \'int\');'."\r\n";
            $code .= '            if(!$id){'."\r\n";
            $code .= '                $response[\'msg\'] = \'id missd!\';'."\r\n";
            $code .= '                return $this->response->setJsonContent([\'status\' => 0]);'."\r\n";
            $code .= '            }'."\r\n";
            $code .= '            $'.$vname.' = ' . $cname . '::findFirst($id);'."\r\n";
            
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
            $code .= '                return $this->response->setJsonContent([\'status\' => 1]);'."\r\n";
            $code .= '            }'."\r\n";
            $code .= '            $this->view->disable();'."\r\n";
            $code .= '        } else {'."\r\n";
            $code .= '            $id = $this->request->get(\'id\', \'int\');'."\r\n";
            $code .= '            $'.$vname.' = '.$cname.'::findFirst($id);'."\r\n";
            $code .= '            $this->view->'.$vname.' = $'.$vname.';'."\r\n";
            $code .= '        }'."\r\n";
            $code .= '    }'."\r\n\r\n";

            #Delete Action
            $code .= '    public function delAction()'."\r\n";
            $code .= '    {'."\r\n";
            $code .= '        $response = ['."\r\n";
            $code .= '            \'status\' => 0, '."\r\n";
            $code .= '            \'msg\'    => \'Delete faild\''."\r\n";
            $code .= '        ];'."\r\n\r\n";
            $code .= '        if($this->request->isPost()){'."\r\n";
            $code .= '            $ids = $this->request->getPost(\'ids\');'."\r\n";
            $code .= '        }else{'."\r\n";
            $code .= '            $id  =  $this->request->get(\'id\', \'int\');'."\r\n";
            $code .= '            $ids = [$id];'."\r\n";
            $code .= '        }'."\r\n\r\n";
            $code .= '        if(!is_array($ids) || empty($ids)){'."\r\n";
            $code .= '            return $this->response->setJsonContent($response);'."\r\n";
            $code .= '        }'."\r\n\r\n";
            $code .= '        $idss = [];'."\r\n";
            $code .= '        foreach($ids as $id){'."\r\n";
            $code .= '            $id = intval($id);'."\r\n";
            $code .= '            if($id > 0){'."\r\n";
            $code .= '                $idss[]=$id;'."\r\n";
            $code .= '            }'."\r\n";
            $code .= '        }'."\r\n";
            $code .= '        if(empty($idss)){'."\r\n";
            $code .= '            return $this->response->setJsonContent($response);'."\r\n";
            $code .= '        }'."\r\n\r\n";
            $code .= '        $'.$vname.'   = '.$cname.'::find([\''.$pk.' IN ({ids:array})\',\'bind\' => [ \'ids\' => $idss]]);'."\r\n";
            $code .= '        $result = $'.$vname.'->delete();'."\r\n";
            $code .= '        if($result === false){'."\r\n";
            $code .= '            $messages = $'.$vname.'->getMessages();'."\r\n";
            $code .= '            foreach ($messages as $msg) {'."\r\n";
            $code .= '                $response[\'msg\'] = $msg->getMessage();'."\r\n";
            $code .= '                break;'."\r\n";
            $code .= '            }'."\r\n";
            $code .= '        }else{'."\r\n";
            $code .= '            $response[\'status\'] = 1;'."\r\n";
            $code .= '            unset($response[\'msg\']);'."\r\n";
            $code .= '        }'."\r\n\r\n";
            $code .= '        return $this->response->setJsonContent($response);'."\r\n";
            $code .= '    }'."\r\n";
            $code .= '}'."\r\n";

            if(false === file_put_contents($file, $code)){
                echo '<font color="red">Generate Controller failed！</font>';
            }else{
                echo '<font color="green">Generate success!,Please modify according to your needs!</font>';
            }
        }
    }
    
    /**
     * Generate views code
     *
     * @access public
     * @param string $mname
     * @return void
     */
    public function generateViews($mname)
    {
        $info    = self::$tableInfo[$this->table];
        $var     = strtolower($info['modelName']);
        $ext     = $this->config['volt_extension'];
        $viewDir = rtrim($this->config['dir']['view'],'/\\').'/'.$var;

        if(!is_dir($viewDir.'/')){
            mkdir($viewDir.'/', 0777, true);
        }
        
        //Generate add views
        $addfile = $viewDir.'/add'.$ext;
        if(file_exists($addfile)){
            echo '<font color="blue">File "add.'.$ext.'" exists!Please delete it manually to regenerate.</font><br>';
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
                if( ( $field['type'] == 'varchar' && intval($field['length']) > 100 ) ||
                    ( preg_match('/text$/', $field['type']) )
                    ){
                    $addblock .= '        <td><textarea name="'.$name.'" class="ipt" ';
                    $addblock .= 'style="width:90%;height:60px;"></textarea></td>'."\r\n";

                }elseif( $field['type'] == 'date' ){
                    $addblock .= '        <td><input type="date" name="'.$name.'" class="ipt" />';
                    $addblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }elseif( $field['type'] == 'datetime' ){
                    $addblock .= '        <td><input type="datetime-local" name="'.$name.'" class="ipt" />';
                    $addblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }else{
                    $addblock .= '        <td><input type="text" name="'.$name.'" class="ipt" />';
                    $addblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }
                $addblock .= '      </tr>'."\r\n";
            }
            $addhtml = str_replace(['##ctl##','##addblock##', '##mname##'], [$var, $addblock, $mname], $addhtml);
            if(false === file_put_contents($addfile, $addhtml)){
                echo '<font color="red">File "add.'.$ext.'" generate faild!</font><br>';
            }else{
                echo '<font color="green">Generate "add.'.$ext.'" success! Please modify according to your needs!</font><br>';
            }
        }

        //Generate edit views
        $editfile = $viewDir.'/edit'.$ext;
        if(file_exists($editfile)){
            echo '<font color="blue">File "edit.'.$ext.'" exists! Please delete it manually to regenerate.</font><br>';
        }else{
            $edithtml   = file_get_contents('./tpl/edit.tpl');
            $editblock  = '';
            foreach($info['fields'] as $name => $field){
                if($field['extra'] == 'auto_increment'){
                    continue;
                }
                if( in_array($name, ['status']) ){
                    continue;
                }
                $editblock .= '      <tr class="tb">'."\r\n";
                $editblock .= '        <td class="label">'.$field['comment'].'：</td>'."\r\n";
                if( ( $field['type'] == 'varchar' && ( intval($field['length']) > 100 ) ) ||
                    ( preg_match('/text$/', $field['type']) )
                    ){
                    $editblock .= '        <td><textarea name="'.$name.'" class="ipt" ';
                    $editblock .= 'style="width:90%;height:60px;">{{'.$var.'.'.$name.'}}</textarea></td>'."\r\n";
                }elseif( $field['type'] == 'date' ){
                    $editblock .= '        <td><input type="date" name="'.$name.'" class="ipt" value="{{'.$var.'.'.$name.'}}" />';
                    $editblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }elseif( $field['type'] == 'datetime' ){
                    $editblock .= '        <td><input type="datetime-local" name="'.$name.'" class="ipt" value="{{'.$var.'.'.$name.'}}" />';
                    $editblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }else{
                    $editblock .= '        <td><input type="text" name="'.$name.'" class="ipt" value="{{'.$var.'.'.$name.'}}" />';
                    $editblock .= '<span class="tips">'.$field['comment'].'</span></td>'."\r\n";
                }
                $editblock .= '      </tr>'."\r\n";
            }
            $edithtml = str_replace(
                ['##ctl##','##editblock##', '##mname##', '##pk##'] , 
                [$var, $editblock, $mname, $info['pk'][0]] , 
                $edithtml);
            if(false === file_put_contents($editfile, $edithtml)){
                echo '<font color="red">File "edit.'.$ext.'" generate faild!</font><br>';
            }else{
                echo '<font color="green">Generate "edit.'.$ext.'" success! Please modify according to your needs!</font><br>';
            }
        }

        //Generate index/list views
        $listfile = $viewDir. '/index' . $ext;
        if(file_exists($listfile)){
            echo '<font color="blue">File "index.'.$ext.'" exists!Please delete it manually to regenerate.</font><br>';
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
                ['##ctl##', '##pk##', '##thblock##', '##tdblock##', '##colspan##', '##mname##'] , 
                [$var, $info['pk'][0], $thblock, $tdblock, $colspan, $mname] , 
                $listhtml );
            if(false === file_put_contents($listfile, $listhtml)){
                echo '<font color="red">File "index.'.$ext.'" generate faild!</font><br>';
            }else{
                echo '<font color="green">Generate "index.'.$ext.'" success! Please modify according to your needs!</font><br>';
            }
        }
    }

    /**
     * Prepare Model name from a table name
     *
     * @access private
     * @param string $table 
     * @return string
     */
    private function getModelName($table)
    {
        $prefix = $this->config['db']['tablePrefix'];
        $table  = str_replace($prefix, '', $table);
        return $this->camelize($table);
    }
    
    /**
     * Parse relation code
     *
     * @access private
     * @param string $selfTable 
     * @return string
     */
    private function parseRelation($selfTable)
    {
        $code = '';
        $refs = $this->connection->describeReferences($selfTable);
        foreach($refs as $ref){
            $selfColumns  = $ref->getColumns();
            $distTable    = $ref->getReferencedTable();
            $distColumns  = $ref->getReferencedColumns();

            //Only one column relation
            if( (count($selfColumns) * count($distColumns)) == 1){
                $codes = $this->getRelation($selfTable, $selfColumns[0], $distTable, $distColumns[0]);
                $code .= $codes['self'];
                $distModel = $this->getModelName($distTable);
                $file = $this->config['dir']['model'].'/'.$distModel.'.php';
                if( file_exists($file) && !empty($codes['dist']) ){
                    $content = file_get_contents($file);
                    $content = str_replace('#RELATION#', $codes['dist'], $content);
                    file_put_contents($file, $content);
                }
            }else{
                //TODO 
            }
        }
        return $code;
    }

    /**
     * Generate relation code
     *
     * @access private
     * @param string $selfTable 
     * @param string $selfColumn
     * @param string $distTable 
     * @param string $distColumn
     * @return array
     */
    private function getRelation($selfTable, $selfColumn, $distTable, $distColumn)
    {
        $selfKeyInfo = $this->getIndexType($selfTable, $selfColumn);
        $distKeyInfo = $this->getIndexType($distTable, $distColumn);
        $tableInfo   = $this->getTableInfo($selfTable);
        $selfColumn  = $tableInfo['fields'][$selfColumn];
        unset($tableInfo);
        $tableInfo   = $this->getTableInfo($distTable);
        $distColumn  = $tableInfo['fields'][$distColumn];
        unset($tableInfo);
        if( $selfColumn['key'] == 'MUL' ){
            if( $distColumn['key'] == 'PRI' || in_array('UNI', $distKeyInfo) ){
                $selfRelType = 'belongsTo';
                $objRelType  = 'hasMany';
            }elseif($distColumn['key'] == 'MUL' ){
                $selfRelType = 'hasManyToMany';
                $objRelType  = 'hasManyToMany';
            }
        }elseif( $selfColumn['key'] == 'PRI' || in_array('UNI', $selfKeyInfo) ){
            if(  $distColumn['key'] == 'PRI' || in_array('UNI', $distKeyInfo) ){
                $selfRelType = 'hasOne';
                $objRelType  = 'hasOne';
            }elseif($distColumn['key'] == 'MUL' ){
                $selfRelType = 'hasMany';
                $objRelType  = 'belongsTo';
            }
        }
        $self  = '';
        $dist  = '';
        $distModel = $this->getModelName($distTable);
        $selfModel = $this->getModelName($selfTable);
        if(!empty($selfRelType)){
            $self .= "\$this->{$selfRelType}('{$selfColumn['name']}','{$distModel}','{$distColumn['name']}');\r\n";
            $self .= "        #RELATION#\r\n";
        }
        if(!empty($objRelType)){
            $dist .= "\$this->{$objRelType}('{$distColumn['name']}','{$selfModel}','{$selfColumn['name']}');\r\n";
            $dist .= "        #RELATION#\r\n";
        }
        return [
            'self' => $self,
            'dist' => $dist,
        ];
    }
    
    /**
     * Get index infomation
     *
     * @access private
     * @param string $table 
     * @param string $field
     * @return array
     */
    private function getIndexType($table, $field)
    {
        //TODO:How to support Union PRIMARY KEY
        $types  = [];
        $indexs = $this->connection->describeIndexes($table);
        foreach($indexs as $indexName => $index){
            $columns = $index->getColumns();
            $type    = $index->getType();
            if( $indexName == 'PRIMARY' && 
                count($columns)==1 && 
                $columns[0] == $field ){

                $types[]='PRI';

            }elseif(count($columns)==1 &&
                $columns[0] == $field &&
                $type == 'UNIQUE' ){

                $types[]='UNI';

            }else{
                $types[]='MUL';
            }
        }

        return $types;
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
     * Camelize a string
     *
     * @access private
     * @param string $str
     * @param boolean $ucfirst
     * @return string
     */
    private function camelize($str, $ucfirst = true)
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
$table   = preg_replace('/[^a-z0-9_]+/i', '', isset($_GET['table']) ? trim($_GET['table']) : '' );
$mname   = isset($_GET['mname']) ? htmlspecialchars(trim($_GET['mname'])) : 'Something';
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
            $pw->generateViews($mname);
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
      <td colspan="2">PhalconWings(Phalcon 3.0.4 + MySQL Backend Code Generator)</td>
  </tr>
  <?php if(!empty($message)):?>
  <tr class="tb2">
    <td colspan="2">
        <div class="notice"><?php echo $message;?></div>
    </td>
  </tr>
  <?php endif;?>
  <tr class="tb">
    <td class="label" style="width:120px;">Database table : </td>
    <td><select name="table" id="table" class="ipt">
    <option value="">Select a table</option>
    <?php foreach((array)$tables as $tablename):?>
    <option value="<?php echo $tablename;?>"><?php echo $tablename;?></option>
    <?php endforeach;?>
    </select>
    </td>
  </tr>
  <tr class="tb">
    <td class="label">Generate items: </td>
    <td>
        <input type="checkbox" name="ctl" id="ctlc" /> <label for="ctlc">Controller</label>
        &nbsp;&nbsp;
        <input type="checkbox" name="mod" id="modc" /> <label for="modc">Model</label>
        &nbsp;&nbsp;
        <input type="checkbox" name="tpl" id="tplc" /> <label for="tplc">Views</label>
    </td>
  </tr>
  <tr class="tb">
    <td class="label">Item name: </td>
    <td>
        <input type="text" name="name" id="mname" class="ipt" /> Add "what" in buttons,For example : Add "News" , Add "Product"
    </td>
  </tr>
  <tr class="tb">
    <td>&nbsp;</td>
    <td><input class="sbtn blue" type="button" value=" Do Generate " id="docreate" /></td>
  </tr>
  <tr class="tb">
    <td class="label">Generate result :</td>
    <td>
      <table width="100%" cellpadding="5" border="0" style="border:1px solid #ccc;">
        <tr>
          <td width="100" class="label">Model : </td>
          <td><div id="modcon" class="ctrs">...</div></td>
        </tr>
        <tr>
          <td class="label">Controller : </td>
          <td><div id="ctlcon" class="ctrs">...</div></td>
        </tr>
        <tr class="lasttr">
          <td class="label">View : </td>
          <td><div id="tplcon" class="ctrs">...</div></td>
        </tr>
      </table>
    </td>
  </tr>
</table>
<script type="text/javascript" src="/static/js/jquery-1.8.3.min.js"></script>
<script type="text/javascript" src="/static/js/artDialog/jquery.artDialog.js?skin=default"></script>
<script type="text/javascript">
$(document).ready(function(){
  $('#docreate').click(function(){
    var table = $('#table').val();
    var mname = $('#mname').val();
    if( table == '' ){
      art.dialog.alert('Please select a table...');
      return;
    }
    if($('#modc').get(0).checked) $('#modcon').load('?action=model&table='+table);
    if($('#ctlc').get(0).checked) $('#ctlcon').load('?action=controller&table='+table);
    if($('#tplc').get(0).checked) $('#tplcon').load('?action=view&table='+table+'&mname='+mname);
  });
});
</script>
</body>
</html>