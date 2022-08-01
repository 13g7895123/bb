<?php

/**
 * 網站初始作業
 */
class BaseWork
{
    /**
     * SESSION設定時間
     * @param int $expire
     */
    public static function start_session($expire = 0)
    {
        if ($expire == 0) {
            $expire = ini_get('session.gc_maxlifetime');
        } else {
            ini_set('session.gc_maxlifetime', $expire);
        }

        if (empty($_COOKIE['PHPSESSID'])) {
            session_set_cookie_params($expire);
            session_start();
        } else {
            session_start();
            setcookie('PHPSESSID', session_id(), time() + $expire);
        }
    }

    /**
     * +----------------------------------------------------------
     * Cookie 設置、獲取、清除 (支持數組或對像直接設置) 2009-07-9
     * +----------------------------------------------------------
     * 1 獲取cookie: cookie('name')
     * 2 清空當前設置前綴的所有cookie: cookie(null)
     * 3 刪除指定前綴所有cookie: cookie(null,'think_') | 註：前綴將不區分大小寫
     * 4 設置cookie: cookie('name','value') | 指定保存時間: cookie('name','value',3600)
     * 5 刪除cookie: cookie('name',null)
     * +----------------------------------------------------------
     * @param string $name cookie名稱
     * @param string $value cookie值
     * @param string $option cookie設置
     * +----------------------------------------------------------
     * $option 可用設置prefix,expire,path,domain
     * 支持數組形式:cookie('name','value',array('expire'=>1,'prefix'=>'think_'))
     * 支持query形式字符串:cookie('name','value','prefix=tp_&expire=10000')
     */
    public static function cookie($name, $value = '', $option = null)
    {
        // 默認設置
        $config = array(
            'prefix' => '', // cookie 名稱前綴
            'expire' => 3600, // cookie 保存時間
            'path' => '/',   // cookie 保存路徑
            'domain' => '', // cookie 有效域名
        );
        // 參數設置(會覆蓋默認設置)
        if (!empty($option)) {
            if (is_numeric($option))
                $option = array('expire' => $option);
            elseif (is_string($option))
                parse_str($option, $option);
            $config = array_merge($config, array_change_key_case($option));
        }
        // 清除指定前綴的所有cookie
        if (is_null($name)) {
            if (empty($_COOKIE)) return;
            // 要刪除的cookie前綴，不指定則刪除config設置的指定前綴
            $prefix = empty($value) ? $config['prefix'] : $value;
            if (!empty($prefix)) // 如果前綴為空字符串將不作處理直接返回
            {
                foreach ($_COOKIE as $key => $val) {
                    if (0 === stripos($key, $prefix)) {
                        setcookie($key, '', time() - 3600, $config['path'], $config['domain']);
                        unset($_COOKIE[$key]);
                    }
                }
            } else { //參數為空 設置也為空 刪除所有cookie
                foreach ($_COOKIE as $key => $val) {
                    setcookie($key, '', time() - 3600, $config['path'], $config['domain']);
                    unset($_COOKIE[$key]);
                }
            }
            return;
        }
        $name = $config['prefix'] . $name;
        if ('' === $value) {
            return isset($_COOKIE[$name]) ? unserialize($_COOKIE[$name]) : null; // 獲取指定Cookie
        } else {
            if (is_null($value)) {
                setcookie($name, '', time() - 3600, $config['path'], $config['domain']);
                unset($_COOKIE[$name]); // 刪除指定cookie
            } else {
                // 設置cookie
                $expire = !empty($config['expire']) ? time() + intval($config['expire']) : 0;
                setcookie($name, serialize($value), $expire, $config['path'], $config['domain']);
                $_COOKIE[$name] = serialize($value);
            }
        }
    }

    /**
     * get變數替代方案
     * @param string $str 名稱
     * @return mixed|null
     */
    public static function _get($str)
    {
        $val = !empty($_GET[$str]) ? $_GET[$str] : null;
        return $val;
    }

    /**
     * post變數替代方案
     * @param string $str 名稱
     * @return mixed|null
     */
    public static function _post($str)
    {
        $val = !empty($_POST[$str]) ? $_POST[$str] : null;
        return $val;
    }
}

/**
 * 系統行為
 */
class SYSAction
{

    /**
     * Dialog(自定義彈出對話框)
     */
    public static function DialogMsg()
    {
        if (BaseWork::cookie('Msg')) {
            echo "<div id=\"dialog-message\" class=\"hide\"><p>" . BaseWork::cookie('Msg') . "</p></div>";
        }
    }

    /**
     * Dialog js code(自定義彈出對話框)
     */
    public static function DialogJs()
    {
        if (BaseWork::cookie('Msg')) {
            echo "
					//override dialog's title function to allow for HTML titles
					$.widget(\"ui.dialog\", $.extend({}, $.ui.dialog.prototype, {
						_title: function(title) {
						var \$title = this.options.title || '&nbsp;'
						if( (\"title_html\" in this.options) && this.options.title_html == true )
							title.html(\$title);
						else title.text(\$title);
						}
					}));
					var dialog = $( \"#dialog-message\" ).removeClass('hide').dialog({
							modal: true,
							title: \"<div class='widget-header widget-header-small'><h4><i class='ace-icon fa fa-info-circle'></i> <b>訊息通知</b></h4></div>\",
							title_html: true,
							buttons: [{
								text: \"OK\",
								\"class\" : \"btn btn-primary btn-minier\",
								click: function() {
									$( this ).dialog( \"close\" ); 
								} 
							}]
					});
			";
        }
    }

    /**
     * 驗證是否有登入和此帳號是否擁有檢視當前頁面的權限
     * @param string $MM_restrictGoTo 驗證失敗要跳轉的頁面
     */
    public static function Login_Chk($MM_restrictGoTo)
    {
        //判斷此帳號是否擁有檢視當前頁面的權限
        $page = $_GET['PageName'];
        if ($page == "" || $page == "profile") {
            if (!isset($_SESSION['SYS_Username']) && !isset($_SESSION['COM_UserID'])) {
                //如果未登入即跳轉
                header("Location: " . $MM_restrictGoTo);
                exit;
            }
        } else {
            //如果是首頁或自己的帳號修改頁面則略過此判斷
            MYPDO::$table = 'sys_left_menu';
            if (isset($_SESSION['SYS_Username'])) {
                //如果是工業設定登入則執行
                MYPDO::$join = ['sys_grp_menu' => ['sys_left_menu.left_menu_id', 'sys_grp_menu.left_menu_id', 'LEFT', '=']];
                MYPDO::$where = [
                    'left_menu_url' => $page,
                    'group_id' => $_SESSION['SYS_UserGroup']
                ];
            } elseif (isset($_SESSION['COM_UserID'])) {
                //如果是廟方登入則執行
                MYPDO::$join = ['com_grp_menu' => ['sys_left_menu.left_menu_id', 'com_grp_menu.left_menu_id', 'LEFT', '=']];
                MYPDO::$where = [
                    'left_menu_url' => $page,
                    'temple_id' => $_SESSION['COM_UserID']
                ];
            } else {
                //如果未登入即跳轉
                header("Location: " . $MM_restrictGoTo);
                exit;
            }
            $results = MYPDO::select();
            $count = count($results);
            $on = SYSAction::SQL_Data('sys_left_menu', 'left_menu_url', $page, 'left_menu_on');
            if ($count == 0 && $on == 'ON') {
                //如果沒有檢視此頁面的權限即跳轉
                header("Location: " . $MM_restrictGoTo);
                exit;
            }
        }

    }

    /**
     * 取出某資料表單筆資料(自訂篩選條件)
     * @param string $table_name 資料表名稱。
     * @param string $where 篩選條件語句。
     * @param string $out_title 輸出欄位。
     */
    public static function SQL_Data_Where($table_name, $where, $out_title)
    {
        MYPDO::$table = $table_name;
        MYPDO::$where = $where;

        $row = MYPDO::first();
        if (!empty($row))
            return $row[$out_title];
    }

    /**
     * 編輯某資料表單筆資料
     * @param string $table_name 資料表名稱。
     * @param string $where_title 篩選條件欄位名稱。
     * @param string $where_val 篩選值。
     * @param string $edit_title 編輯欄位。
     * @param string $edit_val 編輯值。
     */
    public static function SQL_Data_Edit($table_name, $where_title, $where_val, $edit_title, $edit_val)
    {
        //編輯
        MYPDO::$table = $table_name;
        MYPDO::$where = [
            $where_title => $where_val
        ];
        MYPDO::$data = [
            $edit_title => $edit_val
        ];
        MYPDO::save();
    }

    /**
     * 左側選單
     */
    public static function LeftMenu()
    {

        MYPDO::$table = 'sys_left_menu';
        if (isset($_SESSION['SYS_Username'])) {
            MYPDO::$join = [
                'sys_grp_menu' => ['sys_left_menu.left_menu_id', 'sys_grp_menu.left_menu_id', 'LEFT', '=']
            ];
            MYPDO::$where = [
                'group_id' => $_SESSION['SYS_UserGroup'],
                'left_menu_on' => 'ON',
                'left_menu_type' => 1
            ];
        } elseif (isset($_SESSION['COM_Username'])) {
            MYPDO::$join = [
                'com_grp_menu' => ['sys_left_menu.left_menu_id', 'com_grp_menu.left_menu_id', 'LEFT', '=']
            ];
            MYPDO::$where = [
                'temple_id' => $_SESSION['COM_UserID'],
                'left_menu_on' => 'ON',
                'left_menu_type' => 2
            ];
        }

        MYPDO::$field = 'sys_left_menu.*';
        MYPDO::$order = [
            'left_menu_sn' => 'asc',
            'left_menu_id' => 'asc'
        ];
        $results = MYPDO::select();

        echo '<ul class="nav nav-list">';

        foreach ($results as $row) {
            $li_class = $row['left_menu_url'] == BaseWork::_get('PageName') ? "active open" : "";
            ////當選單為單層選單時
            if ($row['left_menu_belong_id'] == "" && $row['left_menu_url'] != "") {
                echo '
					<li class="' . $li_class . '">
						<a href="?PageName=' . $row['left_menu_url'] . '">
							<i class="menu-icon fa fas fa-wrench"></i>
							<span class="menu-text"> ' . $row['left_menu_name'] . ' </span>
						</a>

						<b class="arrow"></b>
					</li>
				';
            }
            //當選單為兩層選單時
            if ($row['left_menu_belong_id'] == "" && $row['left_menu_url'] == "") {
                $NOW_belong_id = self::SQL_Data('sys_left_menu', 'left_menu_url', BaseWork::_get('PageName'), 'left_menu_belong_id');
                $li_class2 = $NOW_belong_id == $row['left_menu_id'] ? "active open" : "";
                echo '
					<li class="' . $li_class2 . '">
						<a href="#" class="dropdown-toggle">
							<i class="menu-icon fa fas fa-wrench"></i>

							<span class="menu-text"> ' . $row['left_menu_name'] . ' </span>

							<b class="arrow fa fa-angle-down"></b>
						</a>

						<b class="arrow"></b>

						<ul class="submenu">

					';

                //撈出第二層選單資料
                MYPDO::$table = 'sys_left_menu';

                if (isset($_SESSION['SYS_Username'])) {
                    MYPDO::$join = [
                        'sys_grp_menu' => ['sys_left_menu.left_menu_id', 'sys_grp_menu.left_menu_id', 'LEFT', '=']
                    ];
                    MYPDO::$where = [
                        'group_id' => $_SESSION['SYS_UserGroup'],
                        'left_menu_belong_id' => $row['left_menu_id'],
                        'left_menu_on' => 'ON',
                        'left_menu_type' => 1
                    ];
                } elseif (isset($_SESSION['COM_Username'])) {
                    MYPDO::$join = [
                        'com_grp_menu' => ['sys_left_menu.left_menu_id', 'com_grp_menu.left_menu_id', 'LEFT', '=']
                    ];
                    MYPDO::$where = [
                        'temple_id' => $_SESSION['COM_UserID'],
                        'left_menu_belong_id' => $row['left_menu_id'],
                        'left_menu_on' => 'ON',
                        'left_menu_type' => 2
                    ];
                }
                MYPDO::$field = 'sys_left_menu.*';
                MYPDO::$order = [
                    'left_menu_sn' => 'asc',
                    'left_menu_id' => 'asc'
                ];
                $results2 = MYPDO::select();
                foreach ($results2 as $row2) {
                    if ($row2['left_menu_url'] == BaseWork::_get('PageName'))
                        $li_class = "active open";
                    else
                        $li_class = "";
                    echo '
							<li class="' . $li_class . '">
								<a href="?PageName=' . $row2['left_menu_url'] . '">
									<i class="menu-icon fa fa-caret-right"></i>
									' . $row2['left_menu_name'] . '
								</a>

								<b class="arrow"></b>
							</li>
					';
                }
                echo '
							
						</ul>
					</li>
				';
            }
        }
        echo '</ul>';
    }

    /**
     * 取出某資料表單筆資料
     * @param string $table_name 資料表名稱。
     * @param string $in_title 篩選條件欄位名稱。
     * @param string $val 篩選值。
     * @param string $out_title 輸出欄位。
     */
    public static function SQL_Data($table_name, $in_title, $val, $out_title)
    {
        MYPDO::$table = $table_name;
        MYPDO::$where = [
            $in_title => $val
        ];

        $row = MYPDO::first();
        if (!empty($row))
            return $row[$out_title];
    }

    /**
     * 上方選單導覽
     */
    public static function Menu_MAP()
    {
        //取得目前頁面名稱
        $Now_Page_Name = self::SQL_Data('sys_left_menu', 'left_menu_url', BaseWork::_get('PageName'), 'left_menu_name');
        //取得目前上層頁面id
        $Belong_Page_ID = self::SQL_Data('sys_left_menu', 'left_menu_url', BaseWork::_get('PageName'), 'left_menu_belong_id');
        //目前頁面
        $menu_arr[] = '<li>' . $Now_Page_Name . '</li>';
        //迴圈取得上層頁面名稱
        while (!empty($Belong_Page_ID)) {
            //取得頁面名稱(上層)
            $Belong_Page_Name = self::SQL_Data('sys_left_menu', 'left_menu_id', $Belong_Page_ID, 'left_menu_name');
            //取得頁面url(上層)
            $Belong_Page_Url = self::SQL_Data('sys_left_menu', 'left_menu_id', $Belong_Page_ID, 'left_menu_url');
            //如果有url則變成超連結狀態
            if ($Belong_Page_Url) {
                $menu_arr[] = '<li><a href="index.php?PageName=' . $Belong_Page_Url . '">' . $Belong_Page_Name . '</a></li>';
            } else {
                $menu_arr[] = '<li>' . $Belong_Page_Name . '</li>';
            }
            //取得上層頁面id(上層)
            $Belong_Page_ID = self::SQL_Data('sys_left_menu', 'left_menu_id', $Belong_Page_ID, 'left_menu_belong_id');
        }
        //將陣列順序顛倒
        $menu_arr = array_reverse($menu_arr);
        //顯示陣列內容
        foreach ($menu_arr as $menu) {
            echo $menu;
        }
    }

    /**
     * 獲取副檔名
     * @param string $path 帶入值為檔案名稱.副檔名。
     */
    public static function extension($path)
    {
        $qpos = strpos($path, "?");
        if ($qpos !== false)
            $path = substr($path, 0, $qpos);

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        return $extension;
    }

    /**
     * 米瑟奇簡訊發送功能api
     * @param string $user 簡訊平台帳號。
     * @param string $pwd 簡訊平台密碼。
     * @param string $c 簡訊內容。
     * @param string $p 要接收簡訊的手機號碼。
     * URL參數說明:
     * id 使用者帳號 必填
     * sdate 發送時間(直接發送則不用設)
     * tel 電話一;電話二;電話三 必填 *max:100
     * password 密碼 必填
     * msg 簡訊內容 若使用URL編碼,參考附表二
     * mtype 簡訊種類 (預設G) G:一般簡訊（G為大寫）
     * encoding 簡訊內容的編碼方式 big5 (預設值)
     * utf8:簡訊內容採用UTF-8編碼
     * urlencode:簡訊內容採用URL編碼
     * urlencode_utf8:簡訊內容採用URL與UTF-8編碼
     */
    public static function MIKI_sms_send($user, $pwd, $c, $p)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://api.message.net.tw/send.php?id=' . $user . '&password=' . $pwd . '&tel=' . $p . '&msg=' . urlencode($c) . '&encoding=utf8');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //將curl_exec()獲取的訊息以文件流的形式返回，而不是直接輸出。 這參數很重要 因為如果有輸出的話你api 解析json時會有錯誤
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * google api 二維碼生成【QRcode可以存儲最多4296個字母數字類型的任意文本，具體可以查看二維碼數據格式】
     * @param string $chl 二維碼包含的信息，可以是數字、字符、二進制信息、漢字。
     * 不能混合數據類型，數據必須經過UTF-8 URL-encoded
     * @param int $widhtHeight 生成二維碼的尺寸設置
     * @param string $EC_level 可選糾錯級別，QR碼支持四個等級糾錯，用來恢復丟失的、讀錯的、模糊的、數據。
     * L-默認：可以識別已損失的7%的數據
     * M-可以識別已損失15%的數據
     * Q-可以識別已損失25%的數據
     * H-可以識別已損失30%的數據
     * @param int $margin 生成的二維碼離圖片邊框的距離
     */
    public static function generateQRfromGoogle($chl, $widhtHeight = '150', $EC_level = 'L', $margin = '0')
    {
        $chl = urlencode($chl);
        return '<img src="https://chart.apis.google.com/chart?chs=' . $widhtHeight . 'x' . $widhtHeight . '&cht=qr&chld=' . $EC_level . '|' . $margin . '&chl=' . $chl . '" border="0" alt="QR code" />';
    }
}

/**
 * 使用者行為
 */
class UserAction
{

    /**
     * 工業設定登入
     * @param string $loginUsername 帳號。
     * @param string $password 密碼
     * @param string $RememberMe 是否記住帳號(任意值存在代表記住)。
     */
    public static function SYS_Login($loginUsername, $password, $RememberMe)
    {
        if (isset($loginUsername)) {

            if (isset($RememberMe)) { //判斷是否要記住帳密
                BaseWork::cookie('s_remuser', $loginUsername, 86400 * 30); //設定使用者名稱的 Cookie 值,保留30天
                BaseWork::cookie('s_rempwd', Form_token_Core::URIAuthcode($password, 'ENCODE'), 86400 * 30); //設定使用者密碼的 Cookie 值,保留30天
                BaseWork::cookie('s_remchk', $RememberMe, 86400 * 30); //設定核取的 Cookie 值,保留30天
            } else {
                BaseWork::cookie('s_remuser', null); //去除使用者名稱的 Cookie 值
                BaseWork::cookie('s_rempwd', null); //去除使用者密碼的 Cookie 值
                BaseWork::cookie('s_remchk', null); //去除核取的 Cookie 值
            }

            MYPDO::$table = 'sys_administrator';
            MYPDO::$where = [
                'administrator_user' => $loginUsername,
                'administrator_pwd' => hash('sha512', $password)
            ];
            $row = MYPDO::first();
            //判斷帳號是否存在
            if (!empty($row)) {
                //判斷帳號是否停用
                if ($row['administrator_on'] == 0) {
                    //帳號停用
                    BaseWork::cookie('Msg', '此帳號停用', 1);
                    header("Location:login.php");
                } else {
                    $_SESSION['SYS_Username'] = $loginUsername;
                    $_SESSION['SYS_UserGroup'] = $row['group_id'];

                    if (isset($_SESSION['PrevUrl']) && false) {
                        $MM_redirectLoginSuccess = $_SESSION['PrevUrl'];
                    }
                    //帳號密碼正確
                    BaseWork::cookie('Msg', $loginUsername . ' 您好，歡迎登入!!', 1);
                    header("Location:index.php");
                }
            } else {
                //帳號密碼不正確
                BaseWork::cookie('Msg', '您輸入的帳號或密碼有誤', 1);
                header("Location:login.php");
            }
        }
    }

    /**
     * 企業設定登入
     * @param string $loginUsername 帳號。
     * @param string $password 密碼
     * @param string $RememberMe 是否記住帳號(任意值存在代表記住)。
     * @param boolean $sha512 代入的密碼是否加密，false(不加密)。
     */
    public static function COM_Login($loginUsername, $password, $RememberMe, $sha512 = true)
    {
        if (isset($loginUsername)) {

            if (isset($RememberMe)) { //判斷是否要記住帳密
                BaseWork::cookie('remuser', $loginUsername, 86400 * 30); //設定使用者名稱的 Cookie 值,保留30天
                BaseWork::cookie('rempwd', Form_token_Core::URIAuthcode($password, 'ENCODE'), 86400 * 30); //設定使用者密碼的 Cookie 值,保留30天
                BaseWork::cookie('remchk', $RememberMe, 86400 * 30); //設定核取的 Cookie 值,保留30天
            } else {
                BaseWork::cookie('remuser', null); //去除使用者名稱的 Cookie 值
                BaseWork::cookie('rempwd', null); //去除使用者密碼的 Cookie 值
                BaseWork::cookie('remchk', null); //去除核取的 Cookie 值
            }

            if ($sha512 === true)
                $password = hash('sha512', $password);

            MYPDO::$table = 'com_temple';
            MYPDO::$where = [
                'temple_account' => $loginUsername,
                'temple_password' => $password
            ];
            $row = MYPDO::first();
            //判斷帳號是否存在
            if (!empty($row)) {
                //判斷帳號是否停用
                if ($row['temple_switch'] == 0) {
                    //帳號停用
                    BaseWork::cookie('Msg', '此帳號停用', 1);
                    header("Location:login.php");
                } else {
                    $_SESSION['COM_Username'] = $loginUsername;
                    $_SESSION['COM_UserID'] = $row['temple_id'];
                    //如有上一層父帳號則找出第一層的父帳號ID寫入$_SESSION['COM_MasterID']
                    if ($row['temple_master_id'] > 0) {
                        $_SESSION['COM_MasterID'] = $row['temple_master_id'];
                        $temple_master_id[0] = $row['temple_master_id'];
                        for ($i = 0; $i < 100; $i++) {
                            $temple_master_id[$i + 1] = SYSAction::SQL_Data('com_temple', 'temple_id', $temple_master_id[$i], 'temple_master_id');
                            if ($temple_master_id[$i + 1] > 0) {
                                $_SESSION['COM_MasterID'] = $temple_master_id[$i + 1];
                            } else {
                                break;
                            }
                        }
                    }

                    if (isset($_SESSION['cPrevUrl']) && false) {
                        $MM_redirectLoginSuccess = $_SESSION['cPrevUrl'];
                    }
                    //帳號密碼正確
                    BaseWork::cookie('Msg', $loginUsername . ' 您好，歡迎登入!!', 1);
                    header("Location:index.php");
                }
            } else {
                //帳號密碼不正確
                BaseWork::cookie('Msg', '您輸入的帳號或密碼有誤', 1);
                header("Location:login.php");
            }
        }
    }

    /**
     * 創建多重資料夾函數
     * @param string $path 資料夾/路徑
     */
    public static function creatdir($path)
    {
        if (!is_dir($path)) {
            if (self::creatdir(dirname($path))) {
                $old = umask(0);
                mkdir($path, 0777);
                umask($old);
                return true;
            }
        } else {
            return true;
        }
        return false;
    }

    /**
     * 將欲上傳圖片按照等比例進行縮圖
     * 注意:png透明背景經過縮圖會失去透明效果
     * @param string $src 原圖存放路徑
     * @param string $dest 縮圖存放路徑
     * @param int $destW 縮圖寬
     * @param int $destH 縮圖高
     */
    public static function imagesResize($src, $dest, $destW, $destH)
    {
        if (file_exists($src) && isset($dest)) {
            //取得檔案資訊
            $srcSize = getimagesize($src);
            $srcExtension = $srcSize[2];
            $srcRatio = $srcSize[0] / $srcSize[1];
            //依長寬比判斷長寬像素
            if ($srcRatio > 1) {
                $destH = $destW / $srcRatio;
            } else {
                $destH = $destW;
                $destW = $destW * $srcRatio;
            }
        }
        //建立影像 
        $destImage = imagecreatetruecolor($destW, $destH);

        //根據檔案格式讀取圖檔 
        switch ($srcExtension) {
            case 1:
                $srcImage = imagecreatefromgif($src);
                break;
            case 2:
                $srcImage = imagecreatefromjpeg($src);
                break;
            case 3:
                $srcImage = imagecreatefrompng($src);
                break;
        }

        //取樣縮圖 
        imagecopyresampled(
            $destImage,
            $srcImage,
            0,
            0,
            0,
            0,
            $destW,
            $destH,
            imagesx($srcImage),
            imagesy($srcImage)
        );

        //輸出圖檔 
        switch ($srcExtension) {
            case 1:
                imagegif($destImage, $dest);
                break;
            case 2:
                imagejpeg($destImage, $dest, 85);
                break;
            case 3:
                imagepng($destImage, $dest);
                break;
        }
        //釋放資源
        imagedestroy($destImage);
    }
}

/**
 * 表單令牌(防止表單惡意提交)
 */
class Form_token_Core
{

    const SESSION_KEY = 'SESSION_KEY';

    /**
     * 生成一個當前的token
     * @param string $form_name
     * @return string
     */
    public static function grante_token()
    {
        $key = self::grante_key();
        $_SESSION['SESSION_KEY'] = $key;
        $token = md5(substr(time(), 0, 3) . $key);
        return $token;
    }

    /**
     * 生成一個密鑰
     * @return string
     */
    public static function grante_key()
    {
        $encrypt_key = md5(((float)date("YmdHis") + rand(100, 999)) . rand(1000, 9999));
        return $encrypt_key;
    }

    /**
     * 驗證一個當前的token
     * @param string $form_name
     * @return string
     */
    public static function is_token($token)
    {
        $key = $_SESSION['SESSION_KEY'];
        $old_token = md5(substr(time(), 0, 3) . $key);
        if ($old_token == $token) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 刪除一個token
     * @param string $form_name
     * @return boolean
     */
    public static function drop_token()
    {
        unset($_SESSION['SESSION_KEY']);
        return true;
    }

    /**
     * 將字串進行加解密
     * @param string $string 明文 或 密文
     * @param string $operation DECODE表示解密,其它表示加密
     * @param string $key 密匙
     * @param int $expiry 密文有效期
     * @return false|string
     */
    public static function URIAuthcode($string, $operation = 'DECODE', $key = 'Winmai#Astra|45894216', $expiry = 0)
    {
        if ($operation == 'DECODE')
            $string = str_replace(array("-", "_"), array('+', '/'), $string);
        $ckey_length = 4;
        $key = md5($key ? $key : $GLOBALS['discuz_auth_key']);
        $keya = md5(substr($key, 0, 16));
        $keyb = md5(substr($key, 16, 16));
        $keyc = $ckey_length ? ($operation == 'DECODE' ? substr($string, 0, $ckey_length) : substr(md5(microtime()), -$ckey_length)) : '';
        $cryptkey = $keya . md5($keya . $keyc);
        $key_length = strlen($cryptkey);
        $string = $operation == 'DECODE' ? base64_decode(substr($string, $ckey_length)) : sprintf('%010d', $expiry ? $expiry + time() : 0) . substr(md5($string . $keyb), 0, 16) . $string;
        $string_length = strlen($string);
        $result = '';
        $box = range(0, 255);
        $rndkey = array();
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $key_length]);
        }
        for ($j = $i = 0; $i < 256; $i++) {
            $j = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }
        for ($a = $j = $i = 0; $i < $string_length; $i++) {
            $a = ($a + 1) % 256;
            $j = ($j + $box[$a]) % 256;
            $tmp = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $result .= chr(ord($string[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }
        if ($operation == 'DECODE') {
            if ((substr($result, 0, 10) == 0 || substr($result, 0, 10) - time() > 0) && substr($result, 10, 16) == substr(md5(substr($result, 26) . $keyb), 0, 16)) {
                return substr($result, 26);
            } else {
                return '';
            }
        } else {
            return $keyc . str_replace(array("=", "+", "/"), array('', '-', '_'), base64_encode($result));
        }
    }
}

/**
 * WebService API專用
 */
class Params
{

    const GET = "GET";
    const POST = "POST";
    const PUT = "PUT";
    const DELETE = "DELETE";

    private $params = array();
    private $method;

    public function __construct()
    {
        $this->_parseParams();
    }

    private function _parseParams()
    {
        $this->method = $_SERVER['REQUEST_METHOD'];

        switch ($this->method) {
            case self::PUT:
                parse_str(file_get_contents('php://input'), $this->params);
                $GLOBALS["_{$this->method}"] = $this->params;

                // Add these request vars into _REQUEST, mimicing default behavior, PUT/DELETE will override existing COOKIE/GET vars
                $_REQUEST = $this->params + $_REQUEST;
                break;
            case self::DELETE:
                parse_str(file_get_contents('php://input'), $this->params);
                $GLOBALS["_{$this->method}"] = $this->params;

                // Add these request vars into _REQUEST, mimicing default behavior, PUT/DELETE will override existing COOKIE/GET vars
                $_REQUEST = $this->params + $_REQUEST;
                break;
            case self::GET:
                $this->params = $_GET;
                break;
            case self::POST:
                $this->params = $_POST;
                break;
        }
    }

    /**
     * @brief Lookup request params
     *
     * @param string $name
     *            Name of the argument to lookup
     * @param mixed $default
     *            Default value to return if argument is missing
     * @returns The value from the GET/POST/PUT/DELETE value, or $default if not set
     */
    public function get($name, $default = null)
    {
        if (!empty($this->params[$name])) {
            return $this->params[$name];
        } else {
            return $default;
        }
    }

    public function getMethoad()
    {
        return $this->method;
    }
}

/**
 * MultiProgress 多進程運行類
 * @author Terrence
 */
class MProgress
{

    /** error_msg */
    const ERROR_MSG = array('status' => 'error', 'message' => 'popen is error');

    public function __construct()
    {
        /** 初始化進程池 */
        $this->pids = array();
    }

    /**
     * set 在進程池中進行任務設置
     * @param any 進程名字
     * @param string task任務路徑
     * @author Terrence
     */
    public function set($taskName = '', $taskPath)
    {
        if (empty($taskName)) {
            $this->pids[] = popen($taskPath, 'r');
        } else {
            $this->pids[$taskName] = popen($taskPath, 'r');
        }
    }

    /**
     * get 獲取進程執行結果
     * @param any $taskName 進程名字
     * @param boolean $isJson 返回的結果是否進行解json操作
     * @return array 執行結果
     * @author Terrence
     */
    public function get($taskName, $isJson = false)
    {
        try {
            // 讀取進程的執行結果 (讀取進程中 echo 出來的信息)
            $result = fgets($this->pids[$taskName]);
            if ($isJson) {
                try {
                    $result = json_decode($result, true);
                } catch (Exception $th) {
                    $result = [];
                }
            }
        } catch (Exception $th) {
            $result = $isJson ? [] : '';
        }
        // 殺死進程
        pclose($this->pids[$taskName]);
        unset($this->pids[$taskName]);
        return $result;
    }

    /**
     * getPids 獲取當前的進程池裡的進程名稱
     * @author Terrence
     */
    public function getPids()
    {
        return array_keys($this->pids);
    }

    /**
     * clear 清空進程池
     * @author Terrence
     */
    public function clear()
    {
        foreach ($this->pids as &$pid) {
            try {
                $single = fgets($pid);
                pclose($pid);
            } catch (Exception $th) {
            }
        }
        $this->pids = [];
    }
}

/*
 * Response JSON Format
 */

class Response
{

    /**
     * Response JSON Format
     *
     * @param string $status 狀態
     * @param string $message 狀態訊息
     * @param array $value json data array
     * @return array[[String]] Get JSON Text
     */
    public static function getResponseData($status, $message, $value): string
    {
        $responseJson = array("status" => $status, "message" => $message, "value" => $value);

        // 加 JSON_UNESCAPED_UNICODE，表示不被 UNICODE
        return stripslashes(json_encode($responseJson, JSON_UNESCAPED_UNICODE));
    }

    public static function decode($text): array
    {
        // json_decode 無法處理換行標記，所以要轉換
        //        $text = str_replace("\n", '\\\\n', $text);
        return json_decode($text, true);
    }
}

/*
 * Webservice API用
 */

class UrlManager
{

    const GET = "GET";
    const POST = "POST";
    const PUT = "PUT";

    public static function runRequest($url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        // Set so curl_exec returns the result instead of outputting it.
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        // Get the response and close the channel.
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }

    public static function runRequestWithAuth($method, $url, $dataArray)
    {
        $username = AUTH_USER;
        $password = AUTH_PW;

        $headers = array(
            'Authorization: Basic ' . base64_encode("$username:$password")
        );

        if ($method === UrlManager::GET) {
            $url = $url . "?";
            foreach ($dataArray as $key => $value) {
                $url = $url . $key . "=" . $value . "&";
            }
            $url = substr($url, 0, -1);

            // Create a stream
            $opts = array(
                'http' => array(
                    'method' => $method,
                    'header' => $headers
                )
            );
        } else {
            $opts = array(
                'http' => array(
                    'method' => $method,
                    'header' => $headers,
                    'content' => http_build_query($dataArray)
                )
            );
        }

        if (preg_match("/^https/", $url)) {
            $opts["ssl"] = [
                "verify_peer" => false,
                "verify_peername" => false
            ];
        }

        $context = stream_context_create($opts);

        // Open the file using the HTTP headers set above
        return file_get_contents($url, false, $context);
    }
}

/**
 * 第三方支付 - 台新ONE碼
 */
class ONE
{

    /**
     * 陣列轉化url參數
     * @param $ScanCode
     * @param $merchantid
     * @param $NowDate
     * @param $NowTime
     * @param $storeid
     * @param $storename
     * @param $terminalid
     * @param $MerchantTradeNom
     * @param $TokenKey1
     * @param $TokenKey2
     * @return false|SimpleXMLElement
     */
    public static function ApiQuery($ScanCode, $merchantid, $NowDate, $NowTime, $storeid, $storename, $terminalid, $MerchantTradeNom, $TokenKey1, $TokenKey2)
    {
        sleep(10);
        $param = array(
            'barcode1' => $ScanCode,
            'merchantid' => $merchantid,
            'merchantquerydatetime' => date("Ymd") . date("His"),
            'merchanttradedate' => $NowDate,
            'merchanttradetime' => $NowTime,
            'querytype' => 'A',
            'storeid' => $storeid,
            'storename' => $storename,
            'terminalid' => $terminalid,
            'tradeno' => $MerchantTradeNom
        );

        $str = self::getUrlString($param);
        $sign = hash('sha256', $str . $TokenKey1 . $TokenKey2);
        $paramurl = http_build_query($param, '', '&');
        $url = "https://tscbiweb.taishinbank.com.tw/TSCBIgwAPI/gwMerchantApiQuery.ashx?" . $paramurl . "&sign=" . $sign;

        $rss = simplexml_load_file($url);

        return $rss->Data->RtnPOSActionCode;
    }

    public static function getUrlString($array_query)
    {
        $tmp = array();
        foreach ($array_query as $k => $param) {
            $tmp[] = $k . '=' . $param;
        }
        $params = implode('&', $tmp);
        return $params;
    }

    /**
     * 與銀行端串接交易
     * @param int $pay_type 支付方式id
     * @param int $amount 自訂數字(金額)
     * @param string $ScanCode QR CODE字串
     * @param string $shopOrder 訂單編號字串
     * @param string $CommodityInfo 商品名稱字串
     * @param int $vm_id 機台編號字串
     * @return int 回傳結果:1成功，0失敗
     */
    public static function pay($pay_type, $amount, $ScanCode, $shopOrder, $CommodityInfo, $vm_id)
    {

        $NowDate = date("Ymd");
        $NowTime = date("His");

        //從設備id取得廠商的shop id和key
        //$merchantid = SYSAction::SQL_Data_Where('sys_mid','mid_device_id = '.$vm_id.'&mid_pay_type_id = '.$pay_type,'device_shop_id');
        //$token = SYSAction::SQL_Data_Where('sys_mid','mid_device_id = '.$vm_id.'&mid_pay_type_id = '.$pay_type,'mid_key');
        //判斷如果是境外支付(微信、支付寶、HANA)，否則為其它境內支付
        if ($pay_type == 306 || $pay_type == 307 || $pay_type == 308) {

            //判斷為何種支付方式代入相對應的參數
            switch ($pay_type) {
                    //支付寶
                case 307:

                    //$token = "26711ecae93247132f8419e05133ec706a29cff48011e57e62150ad87ee0b0d3";
                    $pay_name = "ALIPAY_O";
                    $storeid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_store_id');
                    //$merchantid = "000812461061019";
                    //從設備id取得廠商的shop id和key
                    $merchantid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_shop_id');
                    $token = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_key');
                    break;
                    //HANA
                case 308:

                    //$token = "521d4846981975994066a77a6c6936249f75b637c0be430d8e85a2cf14943cf8";
                    $pay_name = "HANA_O";
                    $storeid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_store_id');
                    //$merchantid = "000812464061019";
                    $merchantid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_shop_id');
                    $token = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_key');
                    break;
                    //微信
                case 306:

                    //$token = "d56f525bcab6f6e4cb521881398cbc3690ef0db2e295aceca2287a584861b525";
                    $pay_name = "WEIXIN_O";
                    $storeid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_store_id');
                    //$merchantid = "000812463061019";
                    $merchantid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_shop_id');
                    $token = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id = ' . $pay_type, 'mid_key');
                    break;
            }

            $param = array(
                'amount' => $amount,
                'barcode' => $ScanCode,
                'gw' => $pay_name,
                'merchantid' => $merchantid,
                'orderid' => $shopOrder,
                'ordermemo' => $CommodityInfo . " - " . $amount,
                'ordername' => $CommodityInfo,
                'storeid' => $storeid,
                'terminalid' => 'A01',
                'timestamp' => $NowDate . $NowTime
            );

            $str = self::getUrlString($param);
            $sign = hash('sha256', $str . $token);
            $paramurl = http_build_query($param, '', '&');
            $url = "https://tscbiweb.taishinbank.com.tw/TSCBOgwAPI/gwMerchantApiPay.ashx?" . $paramurl . "&sign=" . $sign;

            $rss = simplexml_load_file($url);
            $RtnCode = $rss->return_code;
            $RtnMsg = $rss->return_message;
            $result = json_encode($rss);
        } else {

            $storeid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id is null', 'mid_store_id');
            $terminalid = "t1";
            $storename = "穩賣";
            //$merchantid = "000812469061019";
            //$TokenKey1 = "4c3f7e554689d6afa4282b79799b8a2a";
            //$TokenKey2 = "c659d2247411177d5f3053b1eb8748ee";
            //從設備id取得廠商的shop id和key
            $merchantid = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id is null', 'mid_shop_id');
            $token = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id is null', 'mid_key');

            $param = array(
                'amount' => $amount,
                'barcode1' => $ScanCode,
                'barcode2' => '',
                'barcode3' => '',
                'barcode4' => '',
                'barcode5' => '',
                'merchantid' => $merchantid,
                'merchanttradedate' => $NowDate,
                'merchanttradeno' => $shopOrder,
                'merchanttradetime' => $NowTime,
                'orderextrainfo1' => '',
                'orderextrainfo2' => '',
                'orderextrainfo3' => '',
                'orderitem' => $CommodityInfo,
                'remark1' => '',
                'remark2' => '',
                'remark3' => '',
                'storeid' => $storeid,
                'storename' => $storename,
                'terminalid' => $terminalid
            );

            $str = self::getUrlString($param);
            $sign = hash('sha256', $str . $token);
            $paramurl = http_build_query($param, '', '&');
            $url = "https://tscbiweb.taishinbank.com.tw/TSCBIgwAPI/gwMerchantApiPay.ashx?" . $paramurl . "&sign=" . $sign;

            $rss = simplexml_load_file($url);
            $RtnCode = $rss->Data->RtnCode;
            $RtnMsg = $rss->Data->RtnMsg;
            $RtnPOSActionCode = $rss->Data->RtnPOSActionCode;
            $result = json_encode($rss);
        }

        $payment = '';

        if ($RtnCode == '000') {
            //交易成功
            $code = 1;
            $payment = 'done';
        } else {
            //交易失敗
            $code = 0;
            $payment = 'nack';
        }
        $msg = 'code:' . $code . ',ckid:' . date("YmdHis") . ',payment:' . $payment . ",cc:" . $result;

        //寫入登入紀錄
        MYPDO::$table = 'one_log';
        MYPDO::$data = [
            'msg' => $msg
        ];
        MYPDO::insert();

        return $code;
    }
}

/**
 * 第三方支付 - 連宇UIC
 */
class UIC
{
    /**
     * 與銀行端串接交易
     * @param string $payname 交易類型
     * @param int $amount 金額
     * @param string $ScanCode QR CODE字串
     * @param string $CommodityInfo 商品資訊
     * @param int $vm_id 機台編號字串
     * @return int 回傳結果:1成功，0失敗
     */
    // public static function pay($payname, $amount, $ScanCode, $CommodityInfo, $vm_id)
    public static function pay($pay_type, $amount, $ScanCode, $shopOrder, $CommodityInfo, $vm_id)
    {

        //從設備id取得廠商UIC的shop id和key
        $uic_shop_id = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id is null ', 'mid_shop_id');
        $uic_key = SYSAction::SQL_Data_Where('sys_mid', 'mid_device_id = ' . $vm_id . ' and mid_pay_type_id is null', 'mid_key');
        // $field = SYSAction::SQL_Data('tb_device', '_id', $vm_id, '_field');
        // $firm = SYSAction::SQL_Data('tb_field', '_id', $field, '_firm');
        // $uic_shop_id = SYSAction::SQL_Data('tb_firm', '_id', $firm, '_uic_shop_id');
        // $uic_key = SYSAction::SQL_Data('tb_firm', '_id', $firm, '_uic_key');

        // $shopID = "H00000000000278";       //測試用
        // $keyword = "17be6ea6951f47fd867e3d1f4d29fd6e"; //測試用
        $shopID = $uic_shop_id;                      //正式用
        $keyword = $uic_key;    //正式用
        $type = "PayOff";
        $shopOrder = $vm_id . date("YmdHis");
        // $url = "https://linepaytest99.uicpayment.com.tw/API/" . $type; //測試用
        $url = "https://off.uicpayment.com.tw/API/" . $type; //正式用
        $Currency = "TWD";
        $VersionNo = "1.0";
        $pay = self::PayTypeID($pay_type);

        switch ($type) {
                //付款
            case 'PayOff':
                //驗證串
                $text = $keyword . '&ShopID=' . $shopID
                    . '&ShopOrderNo=' . $shopOrder
                    . '&PaymentType=' . $pay
                    . '&Amount=' . $amount
                    . '&DeviceID=' . $vm_id
                    . '&' . $keyword;

                break;

                //退款
            case 'RefundOff':
                //驗證串
                $text = $keyword . '&ShopID=' . $shopID
                    . '&ShopOrderNo=' . $shopOrder
                    . '&PaymentType=' . $pay
                    . '&Amount=' . $amount
                    . '&DeviceID=' . $vm_id
                    . '&' . $keyword;

                break;

                //單筆交易查詢
            case 'QueryOneOff':
                //驗證串
                $text = $keyword . '&ShopID=' . $shopID
                    . '&ShopOrderNo=' . $shopOrder
                    . '&PaymentType=' . $pay
                    . '&DeviceID=' . $vm_id
                    . '&' . $keyword;
                break;
        }


        $sign = hash('sha256', $text);

        $response = json_encode([
            'ShopID' => $shopID,
            'ShopOrderNo' => $shopOrder,
            'PaymentType' => $pay,
            'ScanCode' => $ScanCode,
            'Currency' => $Currency,
            'Amount' => $amount,
            'CommodityInfo' => $CommodityInfo,
            'DeviceID' => $vm_id,
            'VersionNo' => $VersionNo,
            'Sign' => $sign
        ]);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-type: application/json"));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $response);

        // Will return the response, if false it print the response
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        $result = curl_exec($curl);
        curl_close($curl);

        $json = json_decode($result, true);
        $code = $json['ResponseCode'];

        $payment = '';
        if (self::endsWith($code, '0000')) {
            //交易成功
            $code = 1;
            $payment = 'done';
        } else {
            //交易失敗
            $code = 0;
            $payment = 'nack';
        }
        $msg = 'code:' . $code . ',ckid:' . date("YmdHis") . ',payment:' . $payment . ",cc:" . $result;

        //寫入登入紀錄
        MYPDO::$table = 'uic_log';
        MYPDO::$data = [
            'msg' => $msg
        ];
        MYPDO::insert();

        return $code;
    }

    /**
     * 支付類型
     * @param int $payname PayTypeID
     * @return string|void
     */
    public static function PayTypeID($payname)
    {
        switch ($payname) {
                //台灣 Pay
            case 9:
                return "T0";
                break;
                //Pi 錢包
            case 10:
                return "P0";
                break;
                //LINE PAY
            case 15:
                return "L0";
                break;
        }
    }

    /**
     * @param $haystack
     * @param $needle
     * @return bool
     */
    private static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length == 0)
            return true;
        return (substr($haystack, -$length) === $needle);
    }
}

/**
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 * Ecloud 雲端行動科技串接
 *
 * @author mingchi
 */
class Ecloud
{

    // 正式環境
    const BUYER = "00000000";     // 統編
    const APIURL = 'https://box.ecloudlife.com/customer/api/';

    //const API_KEY = 'ZGJlZDNjNTItYWFhNy00Mzk4LTk3YmEtOWY5YzVhYzhmMTkzMjAxOTA3MTgxNDUw';
    //const API_SECRET = 'NmUzMzQ3OWEtMGZiMi00NmQ4LWI2YzQtYmU0ZTQ0MmFlMzJhMjAxOTA3MTgxNDUw';
    // 測試環境
    /*
      const BUYER = "00000000";     // 統編
      const APIURL = 'https://boxtest.ecloudlife.com/customer/api/';
      const API_KEY = 'YjI3MDkxYjgtNDAxYy00MmFmLWI1ODMtYTE0NzYwNmEyOTcyMjAxOTAzMTExNzUy';
      const API_SECRET = 'YzkzNDMyOGQtYzgxOS00MzZjLWFhNGEtMDA4M2M5M2E5OGJjMjAxOTAzMTExNzUy';
     */

    /**
     * @param int $vid pos機台編號
     * @param int $amount 交易金額
     * @param $rand 隨機碼
     * @param $item
     * @param string $print_mark 是否列印紙本 Y=列印
     * @param string $carrier_id1 QR碼1
     * @param string $carrier_id2 QR碼2
     * @param string $carrier_type
     * @param string $donation_mark
     * @param string $npo_ban
     * @return mixed
     */
    public static function issueInvoice($vid, $amount, $rand, $item, $print_mark, $carrier_id1 = '', $carrier_id2 = '', $carrier_type = '', $donation_mark = '', $npo_ban = '')
    {

        //從設備id取得廠商UIC的shop id和key
        $field = SYSAction::SQL_Data('tb_device', '_id', $vid, '_field');
        $firm = SYSAction::SQL_Data('tb_field', '_id', $field, '_firm');
        $ecloud_key = SYSAction::SQL_Data('tb_firm', '_id', $firm, '_ecloud_key');
        $ecloud_secret = SYSAction::SQL_Data('tb_firm', '_id', $firm, '_ecloud_secret');

        //print_r($print_mark);
        $amount_int = intval($amount);
        $order_id = "VID_" . $vid . '_' . date("Ymdhis");
        $buyer = [];
        $buyer["identifier"] = self::BUYER;
        $buyer["name"] = "客戶";
        // 處理訂單內容
        $details = [];
        $details["description"] = $item;
        $details["quantity"] = 1;
        $details["unit_price"] = $amount_int;
        $details["amount"] = $details["unit_price"] * $details["quantity"];
        $details["sequence_number"] = "001";
        $current_datetime = Date("Y-m-d H:i:s");
        $date = substr($current_datetime, 0, 10);
        $date = str_replace("-", "", $date);
        $time = substr($current_datetime, 11, 8);
        $time = str_replace(":", "", $time);
        $invoice = [];
        $invoice["invoice_date"] = $date;
        $invoice["invoice_time"] = $time;
        $invoice["buyer"] = $buyer;
        $invoice["tax_type"] = "1";
        $invoice["tax_amount"] = 0;
        $invoice["sales_amount"] = $amount_int;
        $invoice["tax_rate"] = 0.05;
        $invoice["free_tax_sales_amount"] = 0;
        $invoice["zero_tax_sales_amount"] = 0;
        $invoice["total_amount"] = $amount_int;
        $invoice["random_number"] = $rand;
        $invoice["details"][] = $details;
        $invoice["order_id"] = $order_id;
        $invoice["carrier_type"] = $carrier_type;
        $invoice["carrier_id1"] = $carrier_id1;
        $invoice["carrier_id2"] = $carrier_id2;

        //        if($carrier_id1 == '' && $carrier_id2 == ''){
        //            $invoice["print_mark"] = "Y";
        //        }else{
        $invoice["print_mark"] = $print_mark;
        //        }
        if ($donation_mark != '') {
            $invoice["donation_mark"] = $donation_mark;
        }
        if ($npo_ban != '') {
            $invoice["npo_ban"] = $npo_ban;
        }
        $invoices = [];
        $invoices["invoices"] = [];
        $invoices["invoices"][] = $invoice;
        $invoices = json_encode($invoices);

        // 搞定UTC時間
        $timestamp_utc = new DateTime();
        $timestamp_utc->setTimezone(new DateTimeZone('UTC'));
        $timestamp_array = (array)$timestamp_utc;
        $timestamp_seconds = strtotime($timestamp_array["date"]);
        // 處理header
        $post_data['api_key'] = $ecloud_key;
        $post_data['auto_assign_invoice_track'] = 'true';
        if ($carrier_id1 == '' || $carrier_id2 == '') {
            $post_data['for_print'] = 'true';
        }
        $post_data['invoice'] = $invoices;
        $post_data['timestamp'] = $timestamp_seconds;
        $API_Secret = $ecloud_secret;
        $result = self::runCurl(self::APIURL . 'C0401', $post_data, $API_Secret);
        $data = json_decode($result, true);

        //if (array_key_exists('error', $data)) {
        //throw new Exception($data['error']['code'].':'.$data['error']['message']." //param=$vid,$amount_int,$item,cid1=$carrier_id1,cid2=$carrier_id2,ctype=$carrier_type,mark=$donation_mark,npo=$npo_ban");
        //} else {
        return $data;
        //}
    }

    /**
     * 執行API串接
     * @param string $url 串接網址
     * @param $post_data
     * @param $API_Secret
     * @return bool|string
     */
    private static function runCurl($url, $post_data, $API_Secret)
    {
        // print_r($post_data);
        foreach ($post_data as $key => $value) {
            $post_items[] = $key . '=' . $value;
        }

        foreach ($post_data as $key => $value) {
            $urlencoded_post_items[] = $key . '=' . urlencode($value);
        }

        //create the final string to be posted using implode()
        $post_string = implode('&', $post_items);
        $urlencoded_post_string = implode('&', $urlencoded_post_items);
        $header_string = base64_encode(hash_hmac("sha1", $post_string, $API_Secret, true));
        $request = curl_init();
        curl_setopt($request, CURLINFO_HEADER_OUT, true);
        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded', 'signature:' . $header_string));
        curl_setopt($request, CURLOPT_POST, true);
        curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($request, CURLOPT_POSTFIELDS, $urlencoded_post_string);

        // output the response
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $msg = curl_exec($request);
        if (curl_error($request)) {
            return FALSE;
        }
        return $msg;
    }
}

class LunarProcess
{

    var $MIN_YEAR = 1891;
    var $MAX_YEAR = 2100;
    var $lunarInfo = array(
        array(0, 2, 9, 21936), array(6, 1, 30, 9656), array(0, 2, 17, 9584), array(0, 2, 6, 21168), array(5, 1, 26, 43344), array(0, 2, 13, 59728),
        array(0, 2, 2, 27296), array(3, 1, 22, 44368), array(0, 2, 10, 43856), array(8, 1, 30, 19304), array(0, 2, 19, 19168), array(0, 2, 8, 42352),
        array(5, 1, 29, 21096), array(0, 2, 16, 53856), array(0, 2, 4, 55632), array(4, 1, 25, 27304), array(0, 2, 13, 22176), array(0, 2, 2, 39632),
        array(2, 1, 22, 19176), array(0, 2, 10, 19168), array(6, 1, 30, 42200), array(0, 2, 18, 42192), array(0, 2, 6, 53840), array(5, 1, 26, 54568),
        array(0, 2, 14, 46400), array(0, 2, 3, 54944), array(2, 1, 23, 38608), array(0, 2, 11, 38320), array(7, 2, 1, 18872), array(0, 2, 20, 18800),
        array(0, 2, 8, 42160), array(5, 1, 28, 45656), array(0, 2, 16, 27216), array(0, 2, 5, 27968), array(4, 1, 24, 44456), array(0, 2, 13, 11104),
        array(0, 2, 2, 38256), array(2, 1, 23, 18808), array(0, 2, 10, 18800), array(6, 1, 30, 25776), array(0, 2, 17, 54432), array(0, 2, 6, 59984),
        array(5, 1, 26, 27976), array(0, 2, 14, 23248), array(0, 2, 4, 11104), array(3, 1, 24, 37744), array(0, 2, 11, 37600), array(7, 1, 31, 51560),
        array(0, 2, 19, 51536), array(0, 2, 8, 54432), array(6, 1, 27, 55888), array(0, 2, 15, 46416), array(0, 2, 5, 22176), array(4, 1, 25, 43736),
        array(0, 2, 13, 9680), array(0, 2, 2, 37584), array(2, 1, 22, 51544), array(0, 2, 10, 43344), array(7, 1, 29, 46248), array(0, 2, 17, 27808),
        array(0, 2, 6, 46416), array(5, 1, 27, 21928), array(0, 2, 14, 19872), array(0, 2, 3, 42416), array(3, 1, 24, 21176), array(0, 2, 12, 21168),
        array(8, 1, 31, 43344), array(0, 2, 18, 59728), array(0, 2, 8, 27296), array(6, 1, 28, 44368), array(0, 2, 15, 43856), array(0, 2, 5, 19296),
        array(4, 1, 25, 42352), array(0, 2, 13, 42352), array(0, 2, 2, 21088), array(3, 1, 21, 59696), array(0, 2, 9, 55632), array(7, 1, 30, 23208),
        array(0, 2, 17, 22176), array(0, 2, 6, 38608), array(5, 1, 27, 19176), array(0, 2, 15, 19152), array(0, 2, 3, 42192), array(4, 1, 23, 53864),
        array(0, 2, 11, 53840), array(8, 1, 31, 54568), array(0, 2, 18, 46400), array(0, 2, 7, 46752), array(6, 1, 28, 38608), array(0, 2, 16, 38320),
        array(0, 2, 5, 18864), array(4, 1, 25, 42168), array(0, 2, 13, 42160), array(10, 2, 2, 45656), array(0, 2, 20, 27216), array(0, 2, 9, 27968),
        array(6, 1, 29, 44448), array(0, 2, 17, 43872), array(0, 2, 6, 38256), array(5, 1, 27, 18808), array(0, 2, 15, 18800), array(0, 2, 4, 25776),
        array(3, 1, 23, 27216), array(0, 2, 10, 59984), array(8, 1, 31, 27432), array(0, 2, 19, 23232), array(0, 2, 7, 43872), array(5, 1, 28, 37736),
        array(0, 2, 16, 37600), array(0, 2, 5, 51552), array(4, 1, 24, 54440), array(0, 2, 12, 54432), array(0, 2, 1, 55888), array(2, 1, 22, 23208),
        array(0, 2, 9, 22176), array(7, 1, 29, 43736), array(0, 2, 18, 9680), array(0, 2, 7, 37584), array(5, 1, 26, 51544), array(0, 2, 14, 43344),
        array(0, 2, 3, 46240), array(4, 1, 23, 46416), array(0, 2, 10, 44368), array(9, 1, 31, 21928), array(0, 2, 19, 19360), array(0, 2, 8, 42416),
        array(6, 1, 28, 21176), array(0, 2, 16, 21168), array(0, 2, 5, 43312), array(4, 1, 25, 29864), array(0, 2, 12, 27296), array(0, 2, 1, 44368),
        array(2, 1, 22, 19880), array(0, 2, 10, 19296), array(6, 1, 29, 42352), array(0, 2, 17, 42208), array(0, 2, 6, 53856), array(5, 1, 26, 59696),
        array(0, 2, 13, 54576), array(0, 2, 3, 23200), array(3, 1, 23, 27472), array(0, 2, 11, 38608), array(11, 1, 31, 19176), array(0, 2, 19, 19152),
        array(0, 2, 8, 42192), array(6, 1, 28, 53848), array(0, 2, 15, 53840), array(0, 2, 4, 54560), array(5, 1, 24, 55968), array(0, 2, 12, 46496),
        array(0, 2, 1, 22224), array(2, 1, 22, 19160), array(0, 2, 10, 18864), array(7, 1, 30, 42168), array(0, 2, 17, 42160), array(0, 2, 6, 43600),
        array(5, 1, 26, 46376), array(0, 2, 14, 27936), array(0, 2, 2, 44448), array(3, 1, 23, 21936), array(0, 2, 11, 37744), array(8, 2, 1, 18808),
        array(0, 2, 19, 18800), array(0, 2, 8, 25776), array(6, 1, 28, 27216), array(0, 2, 15, 59984), array(0, 2, 4, 27424), array(4, 1, 24, 43872),
        array(0, 2, 12, 43744), array(0, 2, 2, 37600), array(3, 1, 21, 51568), array(0, 2, 9, 51552), array(7, 1, 29, 54440), array(0, 2, 17, 54432),
        array(0, 2, 5, 55888), array(5, 1, 26, 23208), array(0, 2, 14, 22176), array(0, 2, 3, 42704), array(4, 1, 23, 21224), array(0, 2, 11, 21200),
        array(8, 1, 31, 43352), array(0, 2, 19, 43344), array(0, 2, 7, 46240), array(6, 1, 27, 46416), array(0, 2, 15, 44368), array(0, 2, 5, 21920),
        array(4, 1, 24, 42448), array(0, 2, 12, 42416), array(0, 2, 2, 21168), array(3, 1, 22, 43320), array(0, 2, 9, 26928), array(7, 1, 29, 29336),
        array(0, 2, 17, 27296), array(0, 2, 6, 44368), array(5, 1, 26, 19880), array(0, 2, 14, 19296), array(0, 2, 3, 42352), array(4, 1, 24, 21104),
        array(0, 2, 10, 53856), array(8, 1, 30, 59696), array(0, 2, 18, 54560), array(0, 2, 7, 55968), array(6, 1, 27, 27472), array(0, 2, 15, 22224),
        array(0, 2, 5, 19168), array(4, 1, 25, 42216), array(0, 2, 12, 42192), array(0, 2, 1, 53584), array(2, 1, 21, 55592), array(0, 2, 9, 54560)
    );

    /**
     * 将阳历转换为阴历
     * @param year 公历-年
     * @param month 公历-月
     * @param date 公历-日
     */
    function convertSolarToLunar($year, $month, $date)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];

        if ($year == $this->MIN_YEAR && $month <= 2 && $date <= 9) {
            return array(1891, '正月', '初一', '辛卯', 1, 1, '兔');
        }

        return $this->getLunarByBetween(
            $year, $this->getDaysBetweenSolar(
            $year, $month, $date, $yearData[1], $yearData[2]));
    }

    /**
     * 根据距离正月初一的天数计算阴历日期
     * @param year 阳历年
     * @param between 天数
     */
    function getLunarByBetween($year, $between)
    {
        $lunarArray = array();
        $yearMonth = array();
        $t = 0;
        $e = 0;
        $leapMonth = 0;
        $m = '';
        if ($between == 0) {
            array_push($lunarArray, $year, '正月', '初一');
            $t = 1;
            $e = 1;
        } else {
            $year = $between > 0 ? $year : ($year - 1);
            $yearMonth = $this->getLunarYearMonths($year);
            $leapMonth = $this->getLeapMonth($year);
            $between = $between > 0 ? $between : ($this->getLunarYearDays($year) + $between);
            for ($i = 0; $i < 13; $i++) {
                if ($between == $yearMonth[$i]) {
                    $t = $i + 2;
                    $e = 1;
                    break;
                } else if ($between < $yearMonth[$i]) {
                    $t = $i + 1;
                    $e = $between - (empty($yearMonth[$i - 1]) ? 0 : $yearMonth[$i - 1]) + 1;
                    break;
                }
            }
            $m = ($leapMonth != 0 && $t == $leapMonth + 1) ? ('閏' . $this->getCapitalNum($t - 1, true)) : $this->getCapitalNum(($leapMonth != 0 && $leapMonth + 1 < $t ? ($t - 1) : $t), true);
            array_push($lunarArray, $year, $m, $this->getCapitalNum($e, false));
        }
        array_push($lunarArray, $this->getLunarYearName($year)); // 天干地支
        array_push($lunarArray, $t, $e);
        array_push($lunarArray, $this->getYearZodiac($year)); // 12生肖
        array_push($lunarArray, $leapMonth); // 闰几月
        return $lunarArray;
    }

    function getLunarYearMonths($year)
    {
        $monthData = $this->getLunarMonths($year);
        $res = array();
        $temp = 0;
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        $len = ($yearData[0] == 0 ? 12 : 13);
        for ($i = 0; $i < $len; $i++) {
            $temp = 0;
            for ($j = 0; $j <= $i; $j++) {
                $temp += $monthData[$j];
            }
            array_push($res, $temp);
        }
        return $res;
    }

    /**
     * 获取阴历每月的天数的数组
     * @param year
     */
    function getLunarMonths($year)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        $leapMonth = $yearData[0];
        $bit = decbin($yearData[3]);
        for ($i = 0; $i < strlen($bit); $i++) {
            $bitArray[$i] = substr($bit, $i, 1);
        }
        for ($k = 0, $klen = 16 - count($bitArray); $k < $klen; $k++) {
            array_unshift($bitArray, '0');
        }
        $bitArray = array_slice($bitArray, 0, ($leapMonth == 0 ? 12 : 13));
        for ($i = 0; $i < count($bitArray); $i++) {
            $bitArray[$i] = $bitArray[$i] + 29;
        }
        return $bitArray;
    }

    /**
     * 获取闰月
     * @param year 阴历年份
     */
    function getLeapMonth($year)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        return $yearData[0];
    }

    /**
     * 获取农历每年的天数
     * @param year 农历年份
     */
    function getLunarYearDays($year)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        $monthArray = $this->getLunarYearMonths($year);
        $len = count($monthArray);
        return ($monthArray[$len - 1] == 0 ? $monthArray[$len - 2] : $monthArray[$len - 1]);
    }

    /**
     * 获取数字的阴历叫法
     * @param num 数字
     * @param isMonth 是否是月份的数字
     */
    function getCapitalNum($num, $isMonth)
    {
        $isMonth = $isMonth || false;
        $dateHash = array(
            '0' => '',
            '1' => '一',
            '2' => '二',
            '3' => '三',
            '4' => '四',
            '5' => '五',
            '6' => '六',
            '7' => '七',
            '8' => '八',
            '9' => '九',
            '10' => '十 '
        );
        $monthHash = array(
            '0' => '',
            '1' => '正月',
            '2' => '二月',
            '3' => '三月',
            '4' => '四月',
            '5' => '五月',
            '6' => '六月',
            '7' => '七月',
            '8' => '八月',
            '9' => '九月',
            '10' => '十月',
            '11' => '冬月',
            '12' => '臘月'
        );
        $res = '';
        if ($isMonth) {
            $res = $monthHash[$num];
        } else {
            if ($num <= 10) {
                $res = '初' . $dateHash[$num];
            } else if ($num > 10 && $num < 20) {
                $res = '十' . $dateHash[$num - 10];
            } else if ($num == 20) {
                $res = "二十";
            } else if ($num > 20 && $num < 30) {
//                $res = "廿" . $dateHash[$num - 20]; // 直式
                $res = "二十" . $dateHash[$num - 20]; // 橫式
            } else if ($num == 30) {
                $res = "三十";
            }
        }
        return $res;
    }

    /**
     * 获取干支纪年
     * @param year
     */
    function getLunarYearName($year)
    {
        $sky = array('庚', '辛', '壬', '癸', '甲', '乙', '丙', '丁', '戊', '己');
        $earth = array('申', '酉', '戌', '亥', '子', '丑', '寅', '卯', '辰', '巳', '午', '未');
        $year = $year . '';
        return $sky[$year{3}] . $earth[$year % 12];
    }

    /**
     * 根据阴历年获取生肖
     * @param year 阴历年
     */
    function getYearZodiac($year)
    {
        $zodiac = array('猴', '雞', '狗', '豬', '鼠', '牛', '虎', '兔', '龍', '蛇', '馬', '羊');
        return $zodiac[$year % 12];
    }

    /**
     * 计算2个阳历日期之间的天数
     * @param year 阳历年
     * @param cmonth
     * @param cdate
     * @param dmonth 阴历正月对应的阳历月份
     * @param ddate 阴历初一对应的阳历天数
     */
    function getDaysBetweenSolar($year, $cmonth, $cdate, $dmonth, $ddate)
    {
        $a = mktime(0, 0, 0, $cmonth, $cdate, $year);
        $b = mktime(0, 0, 0, $dmonth, $ddate, $year);
        return ceil(($a - $b) / 24 / 3600);
    }

    function convertSolarMonthToLunar($year, $month)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        if ($year == $this->MIN_YEAR && $month <= 2 && $date <= 9) {
            return array(1891, '正月', '初一', '辛卯', 1, 1, '兔');
        }

        $month_days_ary = array(31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31);
        $dd = $month_days_ary[$month];
        if ($this->isLeapYear($year) && $month == 2) {
            $dd++;
        }

        $lunar_ary = array();
        for ($i = 1; $i < $dd; $i++) {
            $array = $this->getLunarByBetween($year, $this->getDaysBetweenSolar($year, $month, $i, $yearData[1], $yearData[2]));
            $array[] = $year . '-' . $month . '-' . $i;
            $lunar_ary[$i] = $array;
        }
        return $lunar_ary;
    }

    /**
     * 判断是否是闰年
     * @param year
     */
    function isLeapYear($year)
    {
        return (($year % 4 == 0 && $year % 100 != 0) || ($year % 400 == 0));
    }

    /**
     * 将阴历转换为阳历
     * @param year 阴历-年
     * @param month 阴历-月，闰月处理：例如如果当年闰五月，那么第二个五月就传六月，相当于阴历有13个月，只是有的时候第13个月的天数为0
     * @param date 阴历-日
     */
    function convertLunarToSolar($year, $month, $date)
    {
        $yearData = $this->lunarInfo[$year - $this->MIN_YEAR];
        $between = $this->getDaysBetweenLunar($year, $month, $date);
        $res = mktime(0, 0, 0, $yearData[1], $yearData[2], $year);
        $res = date('Y-m-d', $res + $between * 24 * 60 * 60);
        $day = explode('-', $res);
        $year = $day[0];
        $month = $day[1];
        $day = $day[2];
        return array($year, $month, $day);
    }

    /**
     * 计算阴历日期与正月初一相隔的天数
     * @param year
     * @param month
     * @param date
     */
    function getDaysBetweenLunar($year, $month, $date)
    {
        $yearMonth = $this->getLunarMonths($year);
        $res = 0;
        for ($i = 1; $i < $month; $i++) {
            $res += $yearMonth[$i - 1];
        }
        $res += $date - 1;
        return $res;
    }

    /**
     * 获取阳历月份的天数
     * @param year 阳历-年
     * @param month 阳历-月
     */
    function getSolarMonthDays($year, $month)
    {
        $monthHash = array('1' => 31, '2' => $this->isLeapYear($year) ? 29 : 28, '3' => 31, '4' => 30, '5' => 31, '6' => 30, '7' => 31, '8' => 31, '9' => 30, '10' => 31, '11' => 30, '12' => 31);
        return $monthHash["$month"];
    }

    /**
     * 获取阴历月份的天数
     * @param year 阴历-年
     * @param month 阴历-月，从一月开始
     */
    function getLunarMonthDays($year, $month)
    {
        $monthData = $this->getLunarMonths($year);
        return $monthData[$month - 1];
    }

}
