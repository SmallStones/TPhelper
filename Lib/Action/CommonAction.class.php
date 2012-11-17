<?php
/**
 * User: zhuyajie
 * Date: 12-11-8
 * Time: 下午6:50
 */
class CommonAction extends Action
{
	protected static $init = false;
	protected static $destruct=false;
	protected $common_tpl = array(
		'header'=> 'Common:header',
		'navbar'=> 'Common:navbar',
		'footer'=> 'Common:footer', );
	protected $include_tpl = array();

	public function __construct() {
		if ( get_class($this)==MODULE_NAME.'Action' ) {
			parent::__construct();
		}
	}

	protected function _initialize() {
		if ( !self::$init ) {
			self::$init = true; //标记已经进入初始化，否则new AdminAction将陷入死递归
			//所有action初始化代码放在这个大括号内部，否则下面的AdminAction实例化时又将重复执行一次初始化代码，注意不同功能代码可能需要一定的顺序

			///////自定义扩展区///////////////
			///////cookie初始化区域///////////
			$listapp = new AdminAction();
			$list    = $listapp->listAPP();
			if ( $list==false ) {
				$this->assign( 'noapp', "<span>还没有添加任何项目,或者applist.xml文件读取异常</span>" );
				cookie( 'config_path', '' );
				cookie( 'base_dir', '');
				cookie( 'app_name', '暂无' );
				cookie( 'app_index', '暂无' );
				cookie( 'app_url', '' );
			} else {
				$this->assign( 'listapp', $list );
				if ( cookie( 'switch' )!='on' && (!cookie( 'config_path' ) || !cookie( 'base_dir' )) ) {
					$default = $list[0];
					//cookie设置
					cookie( 'config_path', CheckConfig::dirModifier($default['path']).'Conf/config.php' );
					cookie( 'base_dir', CheckConfig::dirModifier($default['path']));
					cookie( 'app_name', $default['name'] );
					cookie( 'app_index', $default['index'] );
					cookie( 'app_url', $default['url'] );
				}
			}
			cookie( 'think_path', CheckConfig::dirModifier(THINK_PATH ));
			cookie( 'tp_helper', CheckConfig::dirModifier(APP_PATH));
			cookie( 'version', THINK_VERSION );
			///////include模板变量分配区域/////////
			$this->include_assign();
		}
	}

	protected function include_assign() {
		$this->assign( 'pagetitle', 'ThinkPHP助手' );
		$this->assign ( 'waittime', 3 );//success和error默认跳转等待时间
	}

	protected function include_fetch() {
		$this->include_tpl = array_merge( $this->common_tpl, $this->include_tpl );
		//解析需引入的子模板,从而assign的变量可以在子摸板中使用
		foreach ( $this->include_tpl as $key=> $val ) {
			$this->assign( $key, $this->fetch( $this->include_tpl[$key] ) );
		}
	}

	protected function display( $templateFile = '', $charset = '', $contentType = '', $content = '', $prefix = '' ) {
		$this->include_fetch();
		parent::display($templateFile,$charset,$contentType,$content,$prefix);
	}

	public function __destruct() {
		if ( get_class($this)==MODULE_NAME.'Action' ) {
			parent::__destruct();
		}
	}
}
