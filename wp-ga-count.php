<?php
/*
Plugin Name: Show Google Analytics widget
Plugin URI: http://webdesign.sig.tw
Description: 像痞客邦的顯示今日參觀人數和總參觀人數的小工具
Version: 1.2.5
Author: Simon Chuang
*/

define( 'SIG_GA_DIR', dirname(__FILE__) );
define( 'SIG_GA_WIDGET', 'sig-show-pageview');    // widget dom id
define( 'SIG_GA_CACHE', 600);                     // today visit cache time
define( 'SIG_GA_CONFIG', 'sig-ga-config');
define( 'SIG_GA_POST_VIEW', 'views');


function sig_plugin_settings_link($links) {
  $settings_link = '<a href="options-general.php?page=sig-ga-account">'.__( 'Settings' ).'</a>';
  array_unshift($links, $settings_link);
  return $links;
}

$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'sig_plugin_settings_link' );

/*-----------------------------------------------
* add WP_Widget
-----------------------------------------------*/
class  Sig_Ga_Count_Widget extends WP_Widget {

    function __construct() {
      parent::__construct(
        SIG_GA_WIDGET,
        __('顯示GA瀏覽人次統計', 'sig_ga_widget' ),
        array (
            'description' => '顯示參觀人次的統計數字'
        )
      );
    }

    function form( $instance ) {

        $defaults = array(
          'sig_ga_title'    => '參觀人氣',
          'sig_ga_type'     => 0,
          'sig_ga_nums'     => 0,
        );
        $instance = wp_parse_args( (array) $instance, $defaults );

     ?>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_title'); ?>">自定標題：</label>
        <input class="widefat" type="text" id="<?php echo $this->get_field_id('sig_ga_title'); ?>" name="<?php echo $this->get_field_name('sig_ga_title'); ?>" value="<?php echo $instance['sig_ga_title']; ?>">
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_type'); ?>">顯示類型：</label>
        <select class="widefat" size="1"  id="<?php echo $this->get_field_id('sig_ga_type'); ?>" name="<?php echo $this->get_field_name('sig_ga_type'); ?>">
          <option value="0" <?php if($instance['sig_ga_type']==0) echo 'selected'?>>Visit(人次)</option>
          <option value="1" <?php if($instance['sig_ga_type']==1) echo 'selected'?>>Pageview(頁次)</option>
        </select>
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_nums'); ?>">調整計次：</label>
        <input class="widefat" placeholder="輸入起跳的數字" type="text" id="<?php echo $this->get_field_id('sig_ga_nums'); ?>" name="<?php echo $this->get_field_name('sig_ga_nums'); ?>" value="<?php echo $instance['sig_ga_nums']; ?>"  onkeyup="value=value.replace(/[^0-9]/g,'')" onbeforepaste="clipboardData.setData('text',clipboardData.getData('text').replace(/[^0-9]/g,''))">
      </p>

    <?php
    }

    function update( $new_instance, $old_instance ) {

        $instance = $old_instance;

        $instance['sig_ga_title']      = strip_tags( $new_instance['sig_ga_title'] );
        $instance['sig_ga_type']       = strip_tags( $new_instance['sig_ga_type'] );
        $instance['sig_ga_nums']       = strip_tags( $new_instance['sig_ga_nums'] );

        $instance['sig_ga_nums'] = preg_replace('/[^0-9]/','',$instance['sig_ga_nums']);
        if(empty($instance['sig_ga_nums'])) $instance['sig_ga_nums'] = 0;

        return $instance;
    }

    function widget( $args, $instance ) {

        extract( $args );

        $sig_ga_title   = $instance['sig_ga_title'];
        $sig_ga_type    = $instance['sig_ga_type'];
        $sig_ga_nums    = $instance['sig_ga_nums'];

        $config = get_ga_config();

        if( $config !== false )
        {
          $sig_ga_account   = $config['sig_ga_account'];
          $sig_ga_upload    = $config['sig_ga_upload']; //p12
          $sig_ga_id        = $config['sig_ga_id'];

          if( !empty($sig_ga_account) and !empty($sig_ga_upload) and !empty($sig_ga_id) )
          {
            //-----today------
            $data = get_option('sig_ga_today_'.$sig_ga_id);

            if(empty($data) or $data === '' )
            {
              $data = updata_today($sig_ga_id,1);
            }
            else
            {
              $cache_time = (!empty($config['sig_ga_cache']) and $config['sig_ga_cache'] > 0) ? $config['sig_ga_cache']:SIG_GA_CACHE;
              if( (time() - $data['time']) > $cache_time ){
                $data = updata_today($sig_ga_id,0);
              }
            }

            if($sig_ga_type==1){
              $today = (isset($data['pageview'])) ? $data['pageview'] : 0;
            }else{
              $today = (isset($data['visit'])) ? $data['visit'] : 0;
            }

            //------ all --------
            $data = get_option('sig_ga_tol_'.$sig_ga_id);

            if(empty($data) or $data === '' )
            {
              $data = updata_tol($sig_ga_id,1);
            }
            else
            {
              if( date('Y-m-d',strtotime("-1 days")) !== $data['end'] ) {
                $data = updata_tol($sig_ga_id,0);
              }
            }

            if($sig_ga_type==1){
              $all = (isset($data['pageview'])) ? $data['pageview'] : 0;
            }else{
              $all = (isset($data['visit'])) ? $data['visit'] : 0;
            }


            echo $before_widget;
            echo $before_title . $sig_ga_title . $after_title;
            echo '<div>本日人氣：'.number_format($today).'</div>';
            echo '<div>累積人氣：'.number_format($all+$sig_ga_nums).'</div>';
            echo $after_widget;
          }
          else
          {
            echo 'building...';
          }

        }
        else
        {
          echo 'setuping...';
        }

    }
}

add_action( 'widgets_init', 'sig_register_ga_widget' );

function sig_register_ga_widget() {
  register_widget( 'Sig_Ga_Count_Widget' );
}


/*-----------------------------------------------
*  show this month
-----------------------------------------------*/
function theme_name_scripts($hook) {

  if($hook != 'toplevel_page_view-ga')  return;

  wp_enqueue_style( 'chart', plugin_dir_url(__FILE__) . 'js/morris.css' );
  wp_enqueue_script( 'raphael', plugin_dir_url(__FILE__) . 'js/raphael-min.js',array('jquery') );
  wp_enqueue_script('chart', plugin_dir_url(__FILE__) . 'js/morris.min.js',array('jquery'));
}
add_action( 'admin_enqueue_scripts', 'theme_name_scripts' );

function add_ga_view_menu(){
  add_menu_page('查看本月份的瀏覽人次統計表','本月份GA','administrator','view-ga', 'add_ga_info_page');
}
add_action('admin_menu', 'add_ga_view_menu');


function add_ga_info_page() {

  $data = array(
    array('date'),
	  array('pageviews','visits'),
	  'date',
	  '',
	  date('Y-m-01'),
	  date('Y-m-d'),
	  1,
	  10000
  );
  $ga = get_api($data);

  if( !is_object($ga) )
  {
    echo '<div class="wrap"><h1>是否還沒設定GA服務帳號？</h1></div>';
    echo '<a href="/wp-admin/options-general.php?page=sig-ga-account" class="button button-primary widgets-chooser-add">立刻去新增</a>';
    if(!empty($ga)) echo '<p>相關訊息：<br>'.$ga.'</p>';
  }
  else
  {

?>
  <h1><?php echo '從 '.date('Y-m-01').' 到 '.date('Y-m-d')?></h1>
  <div id="mychart" style="height: 250px;"></div>
  <table class="">
  <tr>
    <th>Total Results</th>
    <td><?php echo $ga->getTotalResults() ?></td>
  </tr>
  <tr>
    <th>Total Pageviews</th>
    <td><?php echo $ga->getPageviews() ?>
  </tr>
  <tr>
    <th>Total Visits</th>
    <td><?php echo $ga->getVisits() ?></td>
  </tr>
  <tr>
    <th>Result Date Range</th>
    <td><?php echo $ga->getStartDate() ?> to <?php echo $ga->getEndDate() ?></td>
  </tr>
  </table>

  <script>
    new Morris.Line({
      element: 'mychart',
      data: [
        <?php
        foreach($ga->getResults() as $k => $result)
        {
          if($k>0) echo ',';
          echo "{ x:'".substr($result,0,4).'-'.substr($result,4,2).'-'.substr($result,6)."', a: ".$result->getPageviews().", b: ".$result->getVisits()." }";
        }
        ?>
      ],
      xkey: 'x',
      ykeys: ['a','b'],
      labels: ['Pageview','Visits'],
      fillOpacity: 1.0
    });
  </script>
<?php
  }

}

/*-------------------------------------------
* call api
/*------------------------------------------*/
function get_api($data='')
{
  $config = get_ga_config();

  if( $config !== false ){
    $account  = $config['sig_ga_account'];
    $p12      = $config['sig_ga_upload'];
    $report_id= $config['sig_ga_id'];
  }else{
    return false;
  }

  if( empty($account) or empty($p12) or empty($report_id) )
  {
    return false;
  }
  else
  {
    if(is_array($data))
    {
      //$filter = 'country == United States && browser == Firefox || browser == Chrome';
      list($dimensions, $metrics, $sort_metric, $filter,$start_date, $end_date, $start_index, $max_results) = $data;

      require_once(SIG_GA_DIR.'/lib/gapi.class.php');

      if(file_exists($p12)){
        $ga = new gapi($account, $p12);
      }else{
        return '<p><b>注意: 尚未將 p12 檔案上傳到網站內</b></p>';
      }

      try {
        $ga->requestReportData($report_id, $dimensions, $metrics, $sort_metric, $filter,$start_date, $end_date, $start_index, $max_results);
        return $ga;
      } catch (Exception $e) {
        return $e->getMessage();
      }

    }
    else
    {
      return false;
    }
  }
}

function updata_today($sig_ga_id,$new=1)
{
    // today
    $data = array(
      array('date'),
      array('pageviews','visits'),
      'date',
      '',
      date('Y-m-d'),
      date('Y-m-d'),
      1,
      10000
    );

    $ga = get_api($data);

    if( is_object($ga) )
    {
      $option = array(
        'pageview'  => $ga->getPageviews(),
        'visit'     => $ga->getVisits(),
        'time'      => time()
      );

      if($new){
        add_option( 'sig_ga_today_'.$sig_ga_id, $option, '', 'no' );
      }else{
        update_option( 'sig_ga_today_'.$sig_ga_id, $option);
      }
    }
    else
    {
      $option = array();
    }

    return $option;

}

function updata_tol($sig_ga_id,$new=1)
{
    $data = array(
      array('date'),
      array('pageviews','visits'),
      'date',
      '',
      '',
      date('Y-m-d',strtotime("-1 days")),
      1,
      10000
    );

    $ga = get_api($data);

    if( is_object($ga) )
    {
      $option = array(
        'pageview'  => $ga->getPageviews(),
        'visit'     => $ga->getVisits(),
        'start'     => $ga->getStartDate(),
        'end'       => $ga->getEndDate()
      );

      if($new){
        add_option( 'sig_ga_tol_'.$sig_ga_id, $option, '', 'no' );
      }else{
        update_option( 'sig_ga_tol_'.$sig_ga_id, $option);
      }
    }
    else
    {
      $option = array();
    }

    return $option;

}

/*-----------------------------------------------
  show GA on page
-----------------------------------------------*/
function show_post_count($content)
{
  if( !is_single() && !is_page() ) return $content;

  $config = get_ga_config();

  if( $config === false ){
    return $content;
  }else{
    if( isset($config['sig_show_post']) and $config['sig_show_post'] == 1 ){
      //
    }else{
      return $content;
    }
  }

  global $post;
  $post_id = $post->ID;

  $path = wp_make_link_relative(get_permalink($post_id));
  $path = urldecode($path);

  $data = array(
    array('pagePath'),
    array('pageViews','UniquePageviews'),
    'pageViews',
    'ga:pagePath=='.$path,
    '',
    date('Y-m-d'),
    1
  );

  $ga = get_api($data);

  if( is_object($ga) )
  {
    $view = $ga->getPageviews();
  }
  else
  {
    $view = '?';
  }

  return '<p>瀏覽次數：'.$view.'</p>'.$content;
}

add_filter('the_content','show_post_count',40,1);


/*-----------------------------------------------
*  GA config
-----------------------------------------------*/
function get_ga_config()
{
  $config = get_option(SIG_GA_CONFIG);
  if( is_array($config) ){
    return $config;
  }else{
    return false;
  }
}

add_action('admin_menu', 'sig_ga_option_menu');

function sig_ga_option_menu(){
  add_options_page('設定GA服務帳號的參數','GA服務帳號','administrator','sig-ga-account', 'sig_ga_settings_page');
  add_action( 'admin_init', 'sig_register_ga_opt_var' );
}

function sig_register_ga_opt_var() {
  register_setting( 'sig-ga-option-group',SIG_GA_CONFIG,'handle_file_upload' );
}


function handle_file_upload($option)
{
  if(!empty($_FILES["sig_ga_upload"]["tmp_name"]))
  {
    $temp = wp_handle_upload($_FILES["sig_ga_upload"], array('test_form' => FALSE));
    if ( $temp && ! isset( $temp['error'] ) ) {
      $option['sig_ga_upload'] = $temp['file'];
    }
  }
  return $option;
}

function sig_ga_settings_page() {

  $config = get_option(SIG_GA_CONFIG);
  $alert = false;

  if(empty($config))
  {
    $old = get_option('widget_'.SIG_GA_WIDGET);
    if( !empty($old) and count($old) > 1){
      $config = array_shift($old);
      if(isset($config['sig_ga_account'])) $alert = true;
    }
  }

?>
<div class="wrap">
  <h2>設定GA必要的參數</h2>
  <form method="post" action="options.php" enctype="multipart/form-data">
    <?php settings_fields('sig-ga-option-group'); ?>
    <table class="form-table">
      <tr valign="top">
        <th scope="row">GA授權服務帳號：</th>
        <td><input type="text" class="regular-text" name="<?php echo SIG_GA_CONFIG?>[sig_ga_account]" value="<?php echo esc_attr( $config['sig_ga_account'] ); ?>" />
        <p class="description">到 <a href="https://console.developers.google.com/" target="_blank">Google Developers</a> 申請，並下載p12檔案。再把這個服務帳號加入 Google Analytics 你的站台管理員，權限要可檢視和分析。 </p>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">上傳 P12 key檔：</th>
        <td><input type="file" class="regular-text" name="sig_ga_upload" />
          <p class="description"><?php
            if( isset($config['sig_ga_upload']) and $config['sig_ga_upload'] !==''){
              echo '目前檔案位置：'.$config['sig_ga_upload'];
              echo '<input type="hidden" name="'.SIG_GA_CONFIG.'[sig_ga_upload]" value="'.$config['sig_ga_upload'].'">';
            }else{
              echo '你可以先自行更改檔名再上傳。';
            }
             ?></p>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">當日人次暫存時間：</th>
        <td><input type="text" class="" name="<?php echo SIG_GA_CONFIG?>[sig_ga_cache]" value="<?php echo (empty(!$config['sig_ga_cache'])) ? esc_attr( $config['sig_ga_cache'] ) : SIG_GA_CACHE ; ?>"  onkeyup="value=value.replace(/[^\d.]/g,'')" onbeforepaste="clipboardData.setData('text',clipboardData.getData('text').replace(/[^\d.]/g,''))">秒
        <p class="description">預設時間為600秒，過短的時間有可能造成網頁開啟過於緩慢。</p>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">網站的 Profile ID：</th>
        <td><input type="text" class="" name="<?php echo SIG_GA_CONFIG?>[sig_ga_id]" value="<?php echo esc_attr( $config['sig_ga_id'] ); ?>" />
        <p class="description">到你的 Google Analytics 中，切換到你的站台，在瀏覽器的URL應該是這樣子『https://www.google.com/analytics/web/#report/visitors-overview/a1234b23478970 p1234567/』，找最後 p 之後的數字1234567</p>
        </td>
      </tr>

      <tr valign="top">
        <th scope="row">文章瀏覽次數</th>
        <td>
          <fieldset>
            <legend class="screen-reader-text"><span>文章瀏覽次數</span></legend>
            <p>
              <label><input name="<?php echo SIG_GA_CONFIG?>[sig_show_post]" value="0" type="radio" <?php if($config['sig_show_post']=='0') echo 'checked="checked"'?>> 關閉</label><br>
              <label><input name="<?php echo SIG_GA_CONFIG?>[sig_show_post]" value="1" type="radio" <?php if($config['sig_show_post']=='1') echo 'checked="checked"'?>> 開啟</label>
            </p>
          </fieldset>
          <p class="description">(開啟本功能，可能會跟某些佈景衝突)</p>
        </td>
      </tr>
      </table>

      <?php submit_button(); ?>
      <?php if($alert) echo '(第一次設定，以上資料來自小工具設定，請按下儲存按鈕做轉換儲存。)'?>
  </form>
</div>
<?php
}


add_filter('upload_mimes', 'custom_upload_mimes');
function custom_upload_mimes ( $existing_mimes=array() )
{
  $existing_mimes['p12'] = 'application/x-pkcs12';
  return $existing_mimes;
}
