# PhalconWings
  An automatic code generator for Phalcon Framework.Used to generate backend Controllers,Models and Views.She can help you generate most of the basic code, to reduce basic mechanical works. 
  
  The first release version Coming Soon!
  
  PhalconWings 是适用于 Phalcon(3.0.4) 框架的后台代码生成工具，她可以生成控制器、模型和视图，尽可能降低重复性的机械工作。使用 PhalconWings 生成代码后，您可以根据自己的需求少量的更改模型、控制器和视图的相关细节，即可达到快速开发的目的。目前本项目还在完善中，敬请期待更多功能。

### Features (特点)

1. 生成的控制器具备列表、新增、编辑和删除的具体逻辑代码，而不只是一个空的架子。(Generate the Controller with the list, add, edit and delete logic code, not just an empty frame.)
2. 生成的模型代码自动添加模型属性和set、get方法，并且能够根据数据表的外键定义生成简单的模型关系。(The generated Model code automatically add model attributes and set, get methods, and can be based on the foreign key definition of the data table to generate model relationships.)
3. 生成的视图代码包含了新增、编辑和列表的具体HTML，以及自动的提交逻辑。(The generated View code contains new, edit and list specific HTML and submit logic.)

总之，对于一些非常简单的模型，只要生成完毕即可使用。对于其他的情况，只要修改部分逻辑就可以了。(In short, for some very simple model, as long as the generated finished can be used. For other cases, just modify some of the logic on it.)

### Requirements（开发环境）
* Phalcon >= 3.0.4
* PHP >= 5.4
* MySQL >= 5.0

### Other Packages（其他依赖的项目）
* artDialog 4.1.7
* jQuery 1.8 +

### Usage (使用方法)

1. Check your develop environment and install git. (检查您的开发环境与要求相匹配并安装好git版本工具)

2. Open *Git Bash Here* on your web root directory, clone the project to your web root: (在您的项目对外访问的根目录文件夹上单击右键，选择“Git Bash here”，在命令行中执行:)

  ```
  git clone https://github.com/fonqing/PhalconWings.git ./
  ```

3. Open your-web-root/phalconwings/index.php (打开phalconwings下面的index.php)


  ```PHP
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
        ],
        'volt_extension' => '.htm',//Your volt view file extension
        'baseController' => 'Controller',//your base Controller，default value is Phalcon\Mvc\Controller
    ];
  ```
  Configure your items and make sure the dir configuration that have write permission.
  (根据你的具体情况修改配置，并保证dir设置对应的目录具有写权限)


4. Create mysql data table. Tips: Please add a comment for each field ( 建立模型数据表，注意：请给字段加上简要注释，注释会出现在模板的表单label中)


5. Visit PhalconWings by the browser likes (通过浏览器访问phalconwings)


  ```
  http://path-to-your-project/phalconwings/
  ``` 