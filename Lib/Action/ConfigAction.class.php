<?php
/**
 * User: zhuyajie
 * Date: 12-10-19
 * Time: 上午4:40
 */
class ConfigAction extends CommonAction
{

	/**
	 * 保存修整后的POST数据
	 * @var array
	 */
	protected $conf_info = array();

	/**
	 * 可能含有路径常量的配置
	 * @var array
	 */
	protected $const_path = array(
		'DATA_CACHE_PATH',
		'TMPL_EXCEPTION_FILE',
		'TMPL_ACTION_ERROR',
		'TMPL_ACTION_SUCCESS',
		'TMPL_TRACE_FILE' );
	/**
	 * 需要处理的二维数组配置项目
	 * @var array
	 */
	protected $deal_array = array(
		'URL_ROUTE_RULES',
		'HTML_CACHE_RULES' );

	/**
	 * 为一维数组的配置项，需要合并处理k/v，将[k]域的值作为配置的数组元素key,对应的[v]域的值作为对应元素的value
	 * @var array
	 */
	protected $source = array(
		'URL_ROUTE_RULES',
		'TMPL_PARSE_STRING',
		'LOAD_EXT_CONFIG',
		'REST_OUTPUT_TYPE',
		'HTML_CACHE_RULES' );
	/**
	 * 需要过滤的键为固定字符串的一维数组配置
	 * @var array
	 */
	protected $tobefilter = array( 'SESSION_OPTIONS' );
	protected $error = array();

	public function index() {
		if ( isset($_GET['config_path']) ) {
			cookie( 'config_path', realpath( $_GET['config_path'] ) );//为前台ajax更新config_path
		}
		$dir     = cookie( 'base_dir' );
		if ( is_dir( $dir ) ) {
			$configs = include $dir.'Conf/config.php';
			chdir( $dir );
			$config_list = glob( 'Conf'.DIRECTORY_SEPARATOR.'{*,*'.DIRECTORY_SEPARATOR.'*}.php', GLOB_BRACE );
			if ( isset($configs['APP_GROUP_MODE'], $configs['APP_GROUP_PATH']) && $configs['APP_GROUP_MODE']=='1' && $configs['APP_GROUP_PATH'] ) {
				$config_list2 = glob( CheckConfig::dirModifier( $configs['APP_GROUP_PATH'] ).'*/Conf/{*,*/*}.php', GLOB_BRACE );
				$config_list  = array_merge( $config_list, $config_list2 );
			}
			chdir( APP_PATH );
			$config_list = preg_grep( '/alias.php$|tags.php$/iU', $config_list, PREG_GREP_INVERT );
			if ( count( $config_list )>0 ) {
				$this->assign( 'config_list', $config_list );
				$this->assign( 'dir', $dir );
			}
		}
		$this->display();
	}

	public function getConfigList() {
	}

	public function build() {
		$this->source = isset($_GET['filter']) ? array_filter( array_unique( array_merge( $this->source, explode( ",", trim( $_GET['filter'], ', ' ) ) ) ) ) : $this->source;
		$config_path  = cookie( 'config_path' );
		if ( is_file( $config_path ) ) {
			$this->setConfig();
			$this->bulidConfig( $config_path );
			$this->assign( 'waittime', 5 );
			$this->success( '操作成功，即将返回', U( 'Config/index' ) );
		} else {
			$this->success( '还木有任何项目', U( 'Config/index' ) );
		}
	}

	private function setConfig() {
		$this->conf_info = $_POST;
		$this->mergeKV();
		foreach ( $this->tobefilter as $item ) {
			$this->conf_info[$item] = array_filter( $this->conf_info[$item], array(
																				  $this,
																				  'filter' ) );
		}
		$this->conf_info = array_filter( $this->conf_info );
	}

	private function bulidConfig( $config_path ) {
		$base = cookie( 'base_dir' );
		if (substr(PHP_SAPI,0,6)=='apache' && is_dir( $base ) && isset($this->conf_info['URL_MODEL']) && $this->conf_info['URL_MODEL']==2 && !file_exists( $base.'.htaccess' ) ) {
			file_put_contents( $base.'.htaccess', '<IfModule mod_rewrite.c>'.PHP_EOL.'#此文件由ThinPHP助手自动创建'.PHP_EOL.'#ThinkPHP URL去index.php规则'.PHP_EOL.'RewriteEngine on'.PHP_EOL.'RewriteCond %{REQUEST_FILENAME} !-d'.PHP_EOL.'RewriteCond %{REQUEST_FILENAME} !-f'.PHP_EOL.'RewriteRule ^(.*)$ index.php/$1 [QSA,PT,L]'.PHP_EOL.'</IfModule>' );
		}
		$config = var_export( $this->conf_info, true );
		$config = "<?php\nreturn ".preg_replace( array(
													  '/\'true\'/i',
													  '/\'false\'/i',
													  '/\'null\'/i' ), array(
																			'true',
																			'false',
																			'null' ), $config ).';';
		foreach ( $this->const_path as $path ) {
			$pattern = "/(?<='{$path}'\s=>\s)'(.*)(\\\\)(.*)(\\\\)''/Um";
			$config  = preg_replace( $pattern, '$1$3\'', $config );
		}
		file_put_contents( $config_path, $config );
	}

	private function mergeKV() {
		foreach ( $this->source as $conf_item ) {
			$i       = 1;
			$item    = $this->conf_info[$conf_item]; //根据配置项目名称取出需要处理的数组保存到到$item
			$item    = array_filter( $item, array(
												 $this,
												 'filter' ) ); //过滤掉空字符串
			$compact = array(); //存放处理好的元素
			$count   = count( $item );
			while ( true ) {
				if ( isset($item['k'.$i]) && isset($item['v'.$i]) ) {
					if ( in_array( $conf_item, $this->deal_array ) && strpos( $item['v'.$i], ',' ) ) {
						$item['v'.$i] = explode( ',', $item['v'.$i] );
					}
					$compact[$item['k'.$i]] = $item['v'.$i];
				}
				if ( $i>=$count/2 ) {
					break;
				}
				$i++;
			}
			//处理之前$item是array(k1,v1,k2,v2,....)理完以后是:array('k1'=>'v1',...)
			$this->conf_info[$conf_item] = $compact;
		}
	}

	/**
	 * 根据前端ajax请求的file变量，向前端发送配置文件
	 */
	public function sendConfig() {
		if ( $this->isAjax() ) { //可判断jQuery的ajax请求
			$file = $_POST['file'];
			if ( substr( $file, 0, 4 )=="http" || is_file( $file ) && is_readable( $file ) ) {
				if ( isset($_POST['accept']) ) {
					echo  file_get_contents( $file );
				} else {
					//读取配置文件
					$content = file_get_contents( $file );
					//将前面或后面是=>的多个空格合并为1个空格
					$content_2 = preg_replace( '/\s+(?=(=>))|(?<=(=>))\s+/', ' ', $content );
					//遍历路径配置可能含有常量的项目
					foreach ( $this->const_path as $path ) {
						$pattern = "/(?<='{$path}'\s=>\s)(\w*\.)('|\")(.*)('|\")/Um";
						//将含有常量的项目两边加上单引号，内部字符串两边加转移斜杠和单引号
						$content_2 = preg_replace( $pattern, '\'$1\\\'$3\\\'\'', $content_2 );
						if ( $content_2 ) {
							//将处理好的结果保存回配置文件
							file_put_contents( $file, $content_2 );
						}
					}
					$data = include $file;
					//将原始配置文件恢复回文件
					file_put_contents( $file, $content );
					$this->ajaxReturn( $data );
				}
			} else {
				$this->ajaxReturn( array( 'error'=> '项目的配置文件'.$file.'不可读、不存在或者还您没有添加任何TP项目' ) );
			}
		} else {
			$this->error( '该url只接受ajax请求' );
		}
	}

	/**
	 * array_filter使用的回调方法
	 * 过滤严格等于空字符串的配置
	 *
	 * @param $v
	 *
	 * @return bool
	 */
	private function filter( $v ) {
		return $v==='' ? false : true;
	}
}
