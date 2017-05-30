<?php
/*
Plugin Name: Show Google Analytics widget
Plugin URI: http://webdesign.sig.tw
Description: 像痞客邦的顯示今日參觀人數和總參觀人數的小工具
Version: 1.1.0
Author: Simon Chuang
Author URI: http://webdesign.sig.tw

*/

define( 'SIG_GA_DIR', dirname(__FILE__) );
define( 'SIG_GA_WIDGET', 'sig-show-pageview');   // widget dom id
define( 'SIG_GA_KEY_PATH', SIG_GA_DIR.'/p12/');  //p12檔存放位置
define( 'SIG_GA_CACHE', 600); //今日人氣暫存時間(秒數)

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
          'sig_ga_account'  => '',
          'sig_ga_p12'      => '',
          'sig_ga_id'       => '',
          'sig_ga_nums'     => 0,
        );
        $instance = wp_parse_args( (array) $instance, $defaults );

     ?>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_title'); ?>">自定標題：</label>
        <input class="widefat" type="text" id="<?php echo $this->get_field_id('sig_ga_title'); ?>" name="<?php echo $this->get_field_name('sig_ga_title'); ?>" value="<?php echo $instance['sig_ga_title']; ?>">
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_account'); ?>">GA授權服務帳號：</label>
        <input class="widefat" type="text" id="<?php echo $this->get_field_id('sig_ga_account'); ?>" name="<?php echo $this->get_field_name('sig_ga_account'); ?>" value="<?php echo $instance['sig_ga_account']; ?>">
        <small>到 <a href="https://console.developers.google.com/" target="_blank">Google Developers</a> 申請，並下載p12檔案。再把這個服務帳號加入 Google Analytics 你的站台管理員，權限要可檢視和分析。 </small>
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_p12'); ?>">P12 key檔名：</label>
        <input class="widefat" type="text" id="<?php echo $this->get_field_id('sig_ga_p12'); ?>" name="<?php echo $this->get_field_name('sig_ga_p12'); ?>" value="<?php echo $instance['sig_ga_p12']; ?>">
        <small>請將檔案放在外掛的 p12 資料夾下。</small>
      </p>
      <p>
        <label for="<?php echo $this->get_field_id('sig_ga_id'); ?>">網站的 Profile ID：</label>
        <input class="widefat" type="text" id="<?php echo $this->get_field_id('sig_ga_id'); ?>" name="<?php echo $this->get_field_name('sig_ga_id'); ?>" value="<?php echo $instance['sig_ga_id']; ?>">
        <small>到你的 Google Analytics 中，切換到你的站台，在瀏覽器的URL應該是這樣子『https://www.google.com/analytics/web/#report/visitors-overview/a1234b23478970 p1234567/』，找最後 p 之後的數字1234567</small>
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
        $instance['sig_ga_account']    = strip_tags( $new_instance['sig_ga_account'] );
        $instance['sig_ga_p12']        = strip_tags( $new_instance['sig_ga_p12'] );
        $instance['sig_ga_id']         = strip_tags( $new_instance['sig_ga_id'] );
        $instance['sig_ga_nums']       = strip_tags( $new_instance['sig_ga_nums'] );

        $instance['sig_ga_nums'] = preg_replace('/[^0-9]/','',$instance['sig_ga_nums']);
        if(empty($instance['sig_ga_nums'])) $instance['sig_ga_nums'] = 0;

        // clear option table
        global $wpdb;
        $sql = "DELETE FROM `".$wpdb->prefix."options` WHERE `option_name` like 'sig_ga_%_$instance[sig_ga_id]'";
        $wpdb->query( $sql );

        return $instance;
    }

    function widget( $args, $instance ) {

        extract( $args );

        $sig_ga_title   = $instance['sig_ga_title'];
        $sig_ga_type    = $instance['sig_ga_type'];
        $sig_ga_account = $instance['sig_ga_account'];
        $sig_ga_p12     = $instance['sig_ga_p12'];
        $sig_ga_id      = $instance['sig_ga_id'];
        $sig_ga_nums    = $instance['sig_ga_nums'];

        if( !empty($sig_ga_account) and !empty($sig_ga_p12) and !empty($sig_ga_id) )
        {
          //-----today------
          $data = get_option('sig_ga_today_'.$sig_ga_id);

          if(empty($data) or $data === '' )
          {
            $data = updata_today($instance,1);
          }
          else
          {
            if( (time() - $data['time']) > SIG_GA_CACHE ){
              $data = updata_today($instance,0);
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
            $data = updata_tol($instance,1);
          }
          else
          {
            if( date('Y-m-d',strtotime("-1 days")) !== $data['end'] ) {
              $data = updata_tol($instance,0);
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
    }
}

add_action( 'widgets_init', 'sig_register_ga_widget' );

function sig_register_ga_widget() {
  register_widget( 'Sig_Ga_Count_Widget' );
}


/*-----------------------------------------------
*  show Ga page
-----------------------------------------------*/
add_action( 'admin_enqueue_scripts', 'theme_name_scripts' );
function theme_name_scripts() {
  wp_enqueue_style( 'chart', plugin_dir_url(__FILE__) . 'js/morris.css' );
  wp_enqueue_script( 'raphael', plugin_dir_url(__FILE__) . 'js/raphael-min.js',array('jquery') );
  wp_enqueue_script('chart', plugin_dir_url(__FILE__) . 'js/morris.min.js',array('jquery'));
}


add_action('admin_menu', 'add_ga_view_menu');
function add_ga_view_menu(){
  add_menu_page('查看本月份的瀏覽人次統計表','本月份GA','administrator','view-ga', 'add_ga_info_page');
}

function add_ga_info_page() {

  $data = array(
    array('date'),
	  array('pageviews','visits'),
	  'date',
	  '',
	  date('Y-m-01'),
	  date('Y-m-d'),
	  1,
	  500
  );
  $ga = get_api('',$data);

  if( !is_object($ga) )
  {
    echo '<h1>是否還沒新增小工具？</h1>';
    echo '<p>'.$ga.'</p>';
  }
  else
  {

?>
  <h1><?php echo '從 '.date('Y-m-01').' 到 '.date('Y-m-d')?></h1>
  <div id="mychart" style="height: 250px;"></div>
  <table>
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

function get_api($config=array(),$data)
{

  if( empty($config) )
  {
    $w = new Sig_Ga_Count_Widget();
    $settings = $w->get_settings();
    $settings = reset($settings);

    $account  = $settings['sig_ga_account'];
    $p12      = $settings['sig_ga_p12'];
    $report_id= $settings['sig_ga_id'];
  }
  else
  {
    $account  = $config['sig_ga_account'];
    $p12      = $config['sig_ga_p12'];
    $report_id= $config['sig_ga_id'];
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
      $ga = new gapi($account, SIG_GA_KEY_PATH.$p12);

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

function updata_today($instance,$new=1)
{
    // today
    $data = array(
      array('date'),
      array('pageviews','visits'),
      'date',
      '',
      date('Y-m-d'),
      date('Y-m-d'),
      1
    );

    $ga = get_api($instance,$data);

    if( is_object($ga) )
    {
      $option = array(
        'pageview'  => $ga->getPageviews(),
        'visit'     => $ga->getVisits(),
        'time'      => time()
      );

      if($new){
        add_option( 'sig_ga_today_'.$instance['sig_ga_id'], $option, '', 'no' );
      }else{
        update_option( 'sig_ga_today_'.$instance['sig_ga_id'], $option);
      }
    }
    else
    {
      $option = array();
    }

    return $option;

}

function updata_tol($instance,$new=1)
{
    $data = array(
      array('date'),
      array('pageviews','visits'),
      'date',
      '',
      '',
      date('Y-m-d',strtotime("-1 days")),
      1
    );

    $ga = get_api($instance,$data);

    if( is_object($ga) )
    {
      $option = array(
        'pageview'  => $ga->getPageviews(),
        'visit'     => $ga->getVisits(),
        'start'     => $ga->getStartDate(),
        'end'       => $ga->getEndDate()
      );

      if($new){
        add_option( 'sig_ga_tol_'.$instance['sig_ga_id'], $option, '', 'no' );
      }else{
        update_option( 'sig_ga_tol_'.$instance['sig_ga_id'], $option);
      }
    }
    else
    {
      $option = array();
    }

    return $option;

}
