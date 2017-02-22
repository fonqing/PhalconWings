# PhalconWings
An automatic code generator for Phalcon Framework.Used to generator backend Controllers,Models and Views code.She can help you generate most of the code, to reduce basic mechanical works. 
The main commit Coming Soon!
( PhalconWings是适用于Phalcon(3.0.4)框架的后台代码生成工具，她可以生成控制器、模型和视图，尽可能降低重复性的机械工作。使用PhalconWings生成代码后，您可以根据自己的需求少量的更改模型、控制器和视图的相关细节，即可达到快速开发的目的。目前本项目还在完善中，敬请期待更多功能。)

### Requirements（开发环境）
* Phalcon >= 3.0.4
* PHP >= 5.4
* MySQL >= 5.0

### Other Packages（其他依赖的项目）
* artDialog 4.1.7
* jQuery 1.8 +

### Usage (使用方法)
- Check your development environment and install git.
  (检查您的开发环境与要求相匹配并安装好git版本工具)
- Open *Git Bash Here* on your web root directory run ```git clone https://github.com/fonqing/PhalconWings.git ./```
  (在您的项目对外访问的根目录文件夹上单击右键，选择“Git Bash here”，在命令行中执行 ```git clone https://github.com/fonqing/PhalconWings.git ./```)
- Open your-web-root/phalconwings/index.php (打开phalconwings下面的index.php)
  ```
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
  ```
  Configure your items and make sure the dir configuration that have write permission.
  (根据你的具体情况修改配置，并保证dir设置对应的目录具有写权限)
- Visit PhalconWings by the browser likes ```http://path-to-your-project/phalconwings/``` 
  (通过浏览器访问phalconwings)