<?php
/*
Plugin Name: Trinay Zencoder
Description: Plugin to Read amazon s3 files and store in DB and send those files for zencoder video  conversion 
Author: Trinay Technology Solutions
License: Public Domain
Version: 1.1
*/
global $custom_table_example_db_version;
$custom_table_example_db_version = '1.1'; // version changed from 1.0 to 1.1


/**
 * register_activation_hook implementation
 *
 * will be called when user activates plugin first time
 * must create needed database tables
 */
function custom_table_install()
{
    global $wpdb;
    global $custom_table_example_db_version;

    $table_name = $wpdb->prefix . 'videodata'; // do not forget about tables prefix
    $sql = "CREATE TABLE " . $table_name . " (
      ID int(11) NOT NULL AUTO_INCREMENT,
      Title VARCHAR(255) NOT NULL,
      Description VARCHAR(255) NOT NULL,
      InputBucketLink VARCHAR(1000) NOT NULL,
      Link VARCHAR(1000) NOT NULL,
      JobId VARCHAR(100) NOT NULL,
      Status int(11),
      YoutubeStatus int(11),
      YoutubeID varchar(100),
      Categories varchar(255) NOT NULL,
      CustomPost int(11),
      PRIMARY KEY  (ID)
    );";

    // we do not execute sql directly
    // we are calling dbDelta which cant migrate database
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // save current database version for later use (on upgrade)
    add_option('custom_table_db_version', $custom_table_db_version);

    /**
     * [OPTIONAL] Example of updating to 1.1 version
     *
     * If you develop new version of plugin
     * just increment $custom_table_example_db_version variable
     * and add following block of code
     *
     * must be repeated for each new version
     * in version 1.1 we change email field
     * to contain 200 chars rather 100 in version 1.0
     * and again we are not executing sql
     * we are using dbDelta to migrate table changes
     */
    $installed_ver = get_option('custom_table_db_version');
    if ($installed_ver != $custom_table_example_db_version) {
       $sql = "CREATE TABLE " . $table_name . " (
      ID int(11) NOT NULL AUTO_INCREMENT,
      Title VARCHAR(255) NOT NULL,
      Description VARCHAR(255) NOT NULL,
      InputBucketLink VARCHAR(1000) NOT NULL,
      Link VARCHAR(1000) NOT NULL,
      JobId VARCHAR(100) NOT NULL,
      Status int(11),
      YoutubeStatus int(11),
      YoutubeID varchar(100),
      Categories varchar(255) NOT NULL,
      CustomPost int(11),
      PRIMARY KEY  (ID)
    );";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // notice that we are updating option, rather than adding it
        update_option('custom_table_db_version', $custom_table_db_version);
    }
}

register_activation_hook(__FILE__, 'custom_table_install');

/**
 * Trick to update plugin database, see docs
 */
function custom_table_update_db_check()
{
    global $custom_table_db_version;
    if (get_site_option('custom_table_db_version') != $custom_table_db_version) {
        custom_table_install();
    }
}

add_action('plugins_loaded', 'custom_table_update_db_check');

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

if (!class_exists('S3'))require_once('S3.php');  

require_once('Services/Zencoder.php');

$adminOptionsName = "AmazonS3AdminOptions";
$awsoptions=get_option($adminOptionsName);

//AWS access info  
if (!defined('awsAccessKey')) define('awsAccessKey', $awsoptions['accesskey']);  
if (!defined('awsSecretKey')) define('awsSecretKey', $awsoptions['secretkey']);  

/**
 * Custom_Table_Example_List_Table class that will display our custom table
 * records in nice table
 */
class Custom_Table_Example_List_Table extends WP_List_Table
{
    /**
     * [REQUIRED] You must declare constructor and give some basic params
     */
    function __construct()
    {
        global $status, $page;

        parent::__construct(array(
            'singular' => 'video',
            'plural' => 'videos',
        ));
    }

    /**
     * [REQUIRED] this is a default column renderer
     *
     * @param $item - row (key, value array)
     * @param $column_name - string (key)
     * @return HTML
     */
    function column_default($item, $column_name)
    {
        return stripslashes($item[$column_name]);
    }

    /* [OPTIONAL] this is example, how to render column with actions,
     * when you hover row "Edit | Delete" links showed
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_title($item)
    {
        // links going to /admin.php?page=[your_plugin_page][&other_params]
        // notice how we used $_REQUEST['page'], so action will be done on curren page
        // also notice how we use $this->_args['singular'] so in this example it will
        // be something like &person=2
       
        $actions = array(
            'edit' => sprintf('<a href="?page=videos_form&id=%s">%s</a>', $item['id'], __('Edit', 'custom_table_example')),
            'delete' => sprintf('<a href="?page=%s&action=delete&id=%s">%s</a>', $_REQUEST['page'], $item['id'], __('Delete', 'custom_table_example')),
        );
        

        return sprintf('%s %s',
            stripslashes($item['title']),
            $this->row_actions($actions)
        );
    }

    /**
     * [REQUIRED] this is how checkbox column renders
     *
     * @param $item - row (key, value array)
     * @return HTML
     */
    function column_cb($item)
    {
        return sprintf(
            '<input type="checkbox" name="id[]" value="%s" />',
            $item['id']
        );
    }

    /**
     * [REQUIRED] This method return columns to display in table
     * you can skip columns that you do not want to show
     * like content, or description
     *
     * @return array
     */
    function get_columns()
    {
        $columns = array(
            'cb' => '<input type="checkbox" />', //Render a checkbox instead of text        	'id' => __('ID', 'custom_table_example'),        		
            'title' => __('Title', 'custom_table_example'),
            'jobid' => __('Job Id', 'custom_table_example'),
             'status' => __('Status', 'custom_table_example'),
            'inputbucket' => __('Input Bucket Url', 'custom_table_example'),
           
         );
        return $columns;
    }

    /**
     * [OPTIONAL] This method return columns that may be used to sort table
     * all strings in array - is column names
     * notice that true on name column means that its default sort
     *
     * @return array
     */
    function get_sortable_columns()
    {
        $sortable_columns = array(
            'title' => array('title', true),
            'description' => array('description', false),
            'jobid' => array('jobid', true),
            'inputbucket' => array('inputbucket', false),
          
        );
        return $sortable_columns;
    }

    /**
     * [OPTIONAL] Return array of bult actions if has any
     *
     * @return array
     */
    function get_bulk_actions()
    {
        $actions = array(
            'delete' => 'Delete'
        );
        return $actions;
    }

    /**
     * [OPTIONAL] This method processes bulk actions
     * it can be outside of class
     * it can not use wp_redirect coz there is output already
     * in this example we are processing delete action
     * message about successful deletion will be shown on page in next part
     */
    function process_bulk_action()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'videodata'; // do not forget about tables prefix

        if ('delete' === $this->current_action()) {
            $ids = isset($_REQUEST['id']) ? $_REQUEST['id'] : array();
            if (is_array($ids)) $ids = implode(',', $ids);

            if (!empty($ids)) {
                $wpdb->query("DELETE FROM $table_name WHERE id IN($ids)");
            }
        }
    }

    /**
     * [REQUIRED] This is the most important method
     *
     * It will get rows from database and prepare them to be showed in table
     */
    
           
     function prepare_items()
    {
       $adminOptionsName = "AmazonS3AdminOptions";
	$getOptions = get_option($adminOptionsName);
        $columns  = $this->get_columns();
  $hidden   = array();
  $sortable = $this->get_sortable_columns();
  $this->_column_headers = array( $columns, $hidden, $sortable );
  $this->process_bulk_action();
  
  if(isset($_REQUEST['youtubestatusfilter']))
  {
  	$_SESSION['youtubestatusfilter'] =  $_REQUEST['youtubestatusfilter'];
  }
  
  if(!empty($getOptions))
	{
		
   		usort( $this->get_aws_videos(), array( &$this, 'usort_reorder' ) );
	}
  $per_page = 10;
  $current_page = $this->get_pagenum();    	
  $total_items = count( $this->get_aws_videos() );

   $this->found_data ='';
  // only ncessary because we have sample data
   if(!empty($getOptions))
	{
	 
  		$this->found_data = array_slice( $this->get_aws_videos(),( ( $current_page-1 )* $per_page ), $per_page );	}

  $this->set_pagination_args( array(
    'total_items' => $total_items,                  //WE have to calculate the total number of items
    'per_page'    => $per_page                     //WE have to determine how many items to show on a page
  ) );
  $this->items = $this->found_data;
    }
    
    function get_aws_videos()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'videodata';
	$results = $wpdb->get_results("SELECT * FROM $table_name order by ID desc");
	
	
	$values=array();
	$i=1;
	foreach ($results as $result){
	    $values[$i]['title']=$result->Title;
	    $values[$i]['description'] = $result->Description;
		$values[$i]['id']=$result->ID;
	    $values[$i]['jobid'] = $result->JobId;
	    $values[$i]['inputbucket'] =  $result->InputBucketLink;
	    if($result->Status == 0)
	    {
	    $values[$i]['status'] =  "Pending";
	    }
	    else 
	    {
	     $values[$i]['status'] = "Converted";	
	    }
	    
	    $i++;
	 }
	
	
	return $values;
}


function usort_reorder( $a, $b ) {
  // If no sort, default to title
  $orderby = ( ! empty( $_GET['orderby'] ) ) ? $_GET['orderby'] : 'videofiles';
  // If no order, default to asc
  $order = ( ! empty($_GET['order'] ) ) ? $_GET['order'] : 'asc';
  // Determine sort order
  $result = strcmp( $a[$orderby], $b[$orderby] );
  // Send final sort direction to usort
  return ( $order === 'asc' ) ? $result : -$result;
}

}

function custom_table_example_admin_menu()
{
    add_menu_page(__('Videos', 'custom_table_example'), __('Trinay ZenCoder', 'custom_table_example'), 'activate_plugins', 'videos', 'custom_table_example_videos_page_handler');
    add_submenu_page('videos', __('Trinay ZenCoder', 'custom_table_example'), __('Trinay ZenCoder', 'custom_table_example'), 'activate_plugins', 'videos', 'custom_table_example_videos_page_handler');
      add_submenu_page('videos', __('Add new', 'custom_table_example'), __('', 'custom_table_example'), 'activate_plugins', 'videos_form', 'custom_table_example_videos_form_page_handler');
     
    add_submenu_page('videos', __('Settings', 'custom_table_example'), __('Settings', 'custom_table_example'), 'activate_plugins', 'video_settings', 'custom_table_example_videos_settings_page_handler');
    
     add_submenu_page('videos', __('Start Process', 'custom_table_example'), __('Start Process', 'custom_table_example'), 'activate_plugins', 'zencoder', 'zencoder_page_handler');

}

add_action('admin_menu', 'custom_table_example_admin_menu');

/**
 * List page handler
 *
 * This function renders our custom table
 * Notice how we display message about successfull deletion
 * Actualy this is very easy, and you can add as many features
 * as you want.
 *
 * Look into /wp-admin/includes/class-wp-*-list-table.php for examples
 */
function custom_table_example_videos_page_handler()
{
    global $wpdb;

    $table = new Custom_Table_Example_List_Table();
    $table->prepare_items();

    $message = '';
    if ('delete' === $table->current_action()) {
        $message = '<div class="updated below-h2" id="message"><p>' . sprintf(__('Items deleted: %d', 'custom_table_example'), count($_REQUEST['id'])) . '</p></div>';
    }
    ?>
<div class="wrap">

    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Videos', 'custom_table_example')?> <a class="add-new-h2" style="display:none"
                                 href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=videos_form');?>"><?php _e('Add new', 'custom_table_example')?></a>
    </h2>
    <?php echo $message; ?>

    <form id="videos-table" method="GET">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
       <!-- <p class="search-box">
        <select name="youtubestatusfilter" id="youtubestatusfilter">
         <option value="">Select Filter</option>
        <option value="0">Not Uploaded to Youtube</option>
        <option value="1">Uploaded to Youtube</option>
        </select>
        <input type="submit" value="Filter" class="button action">
        </p>-->
        <?php //$table->search_box( 'search', 'search_id' ); ?>
        <?php $table->display() ?>
    </form>

</div>
<?php
}


function custom_table_example_videos_form_page_handler()
{
	 global $wpdb;
	 $table_name = $wpdb->prefix . 'videodata';
    $message = '';
    $notice = '';

    // this is default $item which will be used for new records
    $default = array(
        'id' => 0,
        'title' => '',
        'description' => ''
        );
       
    // here we are verifying does this request is post back and have correct nonce
    if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
       
        $item = shortcode_atts($default, $_REQUEST);
       
        $item_valid = custom_table_example_validate_video($item);
      
        if ($item_valid === true) {
            if (!empty($item['id'] )) {
                                    
		       $result = $wpdb->update($table_name,array('Title' => $item['title'],'Description' =>  $item['description']), 	array( 'ID' => $item['id'] ), array('%s','%s'),array( '%d' ));
		    
		      if ($result) {
		      	$url= $_SERVER['SCRIPT_NAME'].'?page=videos';
		      	
		       echo "<meta http-equiv='refresh' content='0;url=$url'/>"; 
		   
              //$message = __('Title and Description was successfully Added', 'custom_table_example');
                } else {
                    $notice = __('There was an error while saving item', 'custom_table_example');
                }
		     }
		                   
        } else {
            // if $item_valid not true it contains error message(s)
            $notice = $item_valid;
        }
    }
    else {
        // if this is not post back we load item to edit or give new one to create
        $item = $default;
        if (isset($_REQUEST['id'])) {
        	 
           $item = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $_REQUEST['id']), ARRAY_A);
                 
            if (!$item) {
                $item = $default;
                $notice = __('Item not found', 'custom_table_example');
            }
        }
    }

    // here we adding our custom meta box
    add_meta_box('videos_form_meta_box', 'Video List', 'custom_table_example_videos_form_meta_box_handler', 'videos', 'normal', 'default');
    //add_meta_box('video_filter_list', 'Category', 'video_filter_list', 'videos', 'normal', 'default');

    ?>
<div class="wrap">
    <div class="icon32 icon32-posts-post" id="icon-edit"><br></div>
    <h2><?php _e('Videos', 'custom_table_example')?> <a class="add-new-h2"
                                href="<?php echo get_admin_url(get_current_blog_id(), 'admin.php?page=videos');?>"><?php _e('back to list', 'custom_table_example')?></a>
    </h2>

    <?php if (!empty($notice)): ?>
    <div id="notice" class="error"><p><?php echo $notice ?></p></div>
    <?php endif;?>
    <?php if (!empty($message)): ?>
    <div id="message" class="updated"><p><?php echo $message ?></p></div>
    <?php endif;?>

    <form id="form" method="POST">
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
        <?php /* NOTICE: here we storing id to determine will be item added or updated */ ?>
        <input type="hidden" name="id" value="<?php echo $item['id'] ?>"/>

        <div class="metabox-holder" id="poststuff">
            <div id="post-body">
                <div id="post-body-content">
                    <?php /* And here we call our custom meta box */ ?>
                    <?php do_meta_boxes('videos', 'normal', $item); ?>
                    <input type="submit" value="<?php _e('Save', 'custom_table_example')?>" id="submit" class="button-primary" name="submit">
                </div>
            </div>
        </div>
    </form>
</div>
<?php
}

/**
 * This function renders our custom meta box
 * $item is row
 *
 * @param $item
 */
function custom_table_example_videos_form_meta_box_handler($item)
{
	$item['Categories']=explode(',',$item['Categories']);
	?>
<script type="text/javascript" src="<?php echo plugins_url();?>/Trinay-Zencoder/jwplayer/jwplayer.js"></script><script>(function($) {    $.fn.extend( {        limiter: function(limit, elem) {            $(this).on("keyup focus", function() {                setCount(this, elem);            });            function setCount(src, elem) {                var chars = src.value.length;                if (chars > limit) {                    src.value = src.value.substr(0, limit);                    chars = limit;                }                elem.html( limit - chars );            }            setCount($(this)[0], elem);        }    });})(jQuery);</script>
<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="Title"><?php _e('Title', 'custom_table_example')?></label>
        </th>
        <td colspan="2">
            <input id="title" name="title" type="text" style="width: 95%" value="<?php echo esc_attr($item['Title']);?>"
                   size="50" class="code" placeholder="<?php _e('Enter Title', 'custom_table_example')?>" required>			<div id="chars" style="padding-left:10px">0</div>			<script>			var elem = jQuery("#chars");			jQuery("#title").limiter(100, elem);			</script>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="description"><?php _e('Description', 'custom_table_example')?></label>
        </th>
        <td colspan="2">
            <input id="description" name="description" type="text" style="width: 95%" value="<?php echo esc_attr($item['Description']);?>"
                   size="50" class="code" placeholder="<?php _e('Enter Description', 'custom_table_example')?>" required>
        </td>
        
    </tr>
     <tr class="form-field">
        <th valign="top" scope="row">
            <label for="inputbucket"><?php _e('Input File Link ', 'custom_table_example')?></label>
        </th>
        <td colspan="2">
            <input id="inputbucket" name="inputbucket" type="text" style="width: 95%" value="<?php echo esc_attr($item['InputBucketLink']);?>"
                   size="50" class="code" >
        </td>
        
    </tr>        <tr class="form-field">        <th valign="top" scope="row">            <label for="inputbucket"><?php _e('Output File Link', 'custom_table_example')?></label>        </th>        <td colspan="2">            <input id="Link" name="Link" type="text" style="width: 95%" value="<?php echo esc_attr($item['Link']);?>"                   size="50" class="code" >        </td>            </tr>
</tbody>
</table>
<input id="id" name="id" type="hidden" value="<?php echo $item['ID'];?>">
<?php
}

/**
 * Simple function that validates data and retrieve bool on success
 * and error message(s) on error
 *
 * @param $item
 * @return bool|string
 */
function custom_table_example_validate_video($item){
    $messages = array();
    if (empty($item['title'])) $messages[] = __('Title is required', 'custom_table_example');
    if (empty($item['description'])) $messages[] = __('Description is required', 'custom_table_example');
    if (empty($messages)) return true;
    return implode('<br />', $messages);
}

function custom_table_example_languages()
{	//zencoder Notification
	
	if ($_GET['zencoder_notify'] == '1') {
		global $wpdb;
	
		$adminOptionsName = "AmazonS3AdminOptions";
			
		$getOptions = get_option($adminOptionsName);
			
			
			
		$zencoder = new Services_Zencoder($getOptions['zencoderaccesskey']);
			
			
			
		$notification = $zencoder->notifications->parseIncoming();
			
			
			
		if($notification->job->state == "finished") {
	
	
	
			$table_name = $wpdb->prefix . 'videodata';
	
			$query="UPDATE ".$table_name." SET Status = 1 WHERE JobId =". $notification->job->id;
	
			$wpdb->query($query);
	
		}
	
	}
	
	//zencoder Notification
	if (!session_id()) {
		session_start();
	}
    load_plugin_textdomain('custom_table_example', false, dirname(plugin_basename(__FILE__)));
}

add_action('init', 'custom_table_example_languages');

function custom_table_example_videos_settings_page_handler()
{		
	$adminOptionsName = "AmazonS3AdminOptions";
    
	if (wp_verify_nonce($_REQUEST['nonce'], basename(__FILE__))) {
		
		$Options['accesskey']= $_POST['accesskey'];
		$Options['secretkey']= $_POST['secretkey'];
		$Options['bucketname']= $_POST['bucketname'];
		$Options['zencoderaccesskey']= $_POST['zencoderaccesskey'];				//$Options['jw_license']= $_POST['jw_license'];
		$Options['inputbucket']= $_POST['inputbucket'];
		$Options['outputbucket']= $_POST['outputbucket'];
		$Options['width']= $_POST['width'];
		$Options['height']= $_POST['height'];
		$Options['imageurl']= $_POST['imageurl'];
		$Options['videosize']= $_POST['videosize'];
		update_option($adminOptionsName, $Options);
		
		
		?>
		<div class="updated"><p><strong><?php _e("Settings Updated.", "custom_table_example");?></strong></p></div>
<?php	
	}
	
	$getOptions = get_option($adminOptionsName);
	$category=explode(",",$getOptions['category']);
?>
<form id="form" method="POST">
<h2>Amazon S3 Settings </h2>
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
	<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="accesskey"><?php _e('Access Key', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="accesskey" name="accesskey" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['accesskey'])?>"
                   size="50" class="code" placeholder="<?php _e('Access Key', 'custom_table_example')?>" required>
        </td>
    </tr>
    <tr class="form-field">
        <th valign="top" scope="row">
            <label for="Title"><?php _e('Secret Key', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="secretkey" name="secretkey" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['secretkey'])?>"
                   size="50" class="code" placeholder="<?php _e('Secret Key', 'custom_table_example')?>" required>
        </td>
    </tr>
   <tr class="form-field">
        <th valign="top" scope="row">
            <label for="inputbucket"><?php _e('Input Bucket ', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="inputbucket" name="inputbucket" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['inputbucket'])?>"
                   size="50" class="code" placeholder="<?php _e('Input Bucket', 'custom_table_example')?>" required>
        </td>
    </tr>
    <tr>
     <tr class="form-field">
        <th valign="top" scope="row">
            <label for="outputbucket"><?php _e('Output Bucket ', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="outputbucket" name="outputbucket" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['outputbucket'])?>"
                   size="50" class="code" placeholder="<?php _e('Output Bucket', 'custom_table_example')?>" required>
        </td>
    </tr>            
    <tr><td><h2>ZenCoder Settings </h2></td></tr>
     <tr class="form-field">
        <th valign="top" scope="row">
            <label for="zencoderaccesskey"><?php _e('ZenCoder Access Key', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="zencoderaccesskey" name="zencoderaccesskey" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['zencoderaccesskey'])?>"
                   size="50" class="code" placeholder="<?php _e('ZenCoder Access Key', 'custom_table_example')?>" required>
        </td>
    </tr>
      <tr class="form-field">
        <th valign="top" scope="row">
            <label for="width"><?php _e('Watermark Image Width', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="width" name="width" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['width'])?>"
                   size="50" class="code" placeholder="<?php _e('Width', 'custom_table_example')?>" required>
        </td>
    </tr>
      <tr class="form-field">
        <th valign="top" scope="row">
            <label for="height"><?php _e('Watermark Image Height', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="height" name="height" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['height'])?>"
                   size="50" class="code" placeholder="<?php _e('Height', 'custom_table_example')?>" required>
        </td>
    </tr>
      <tr class="form-field">
        <th valign="top" scope="row">
            <label for="Image Url"><?php _e('Watermark Image Url', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="imageurl" name="imageurl" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['imageurl'])?>"
                   size="50" class="code" placeholder="<?php _e('Image Url', 'custom_table_example')?>" required>
        </td>
    </tr>
     <tr class="form-field">
        <th valign="top" scope="row">
            <label for="videosize"><?php _e('Video Size', 'custom_table_example')?></label>
        </th>
        <td>
            <input id="videosize" name="videosize" type="text" style="width: 95%" value="<?php echo esc_attr($getOptions['videosize'])?>"
                   size="50" class="code" placeholder="<?php _e('Video Size', 'custom_table_example')?>" required><br/>(like 500x280)
        </td>
    </tr>
    <tr>
    <td>
     <input type="submit" value="<?php _e('Save', 'custom_table_example')?>" id="submit" class="button-primary" name="submit">
     </td>
    </tr>
    </tbody>
</table>
</form>
<?php
}
function zencoder_page_handler()
{
	global $wpdb;
	$table_name = $wpdb->prefix . 'videodata';
	$adminOptionsName = "AmazonS3AdminOptions";
	$getOptions = get_option($adminOptionsName);
	$zencoder = new Services_Zencoder($getOptions['zencoderaccesskey']);
    if(isset($_POST['submit']))
    {
    
			if(!empty($getOptions))
			{
		$s3 = new S3(awsAccessKey, awsSecretKey);  
		//$inputbucket = $getOptions['bucketname'];
		$inputbucket = $getOptions['inputbucket'];
		$outputbucket = $getOptions['outputbucket'];
		$width= $getOptions['width'];
		$height= $getOptions['height'];
		$imageurl= $getOptions['imageurl'];
		$videosize= $getOptions['videosize'];
	    $bucket_contents = $s3->getBucket($inputbucket);
		$job=array();
		$existvideos=$wpdb->get_results("select InputBucketLink from $table_name");
		$existinputbucket=array();
		foreach($existvideos as $existvideo)
		{
			$existinputbucket[]=$existvideo->InputBucketLink;
		}
		
		foreach ($bucket_contents as $file){
			$filename=$file['name'];
			
			$outputfile= preg_replace('"\.(MOV|mov)$"', '.mp4', $filename);
			
			if($outputfile==$filename)			$outputfile= preg_replace('"\.(flv|FLV)$"', '.mp4', $filename);
			
			if($outputfile==$filename)			$outputfile= preg_replace('"\.(wmv|WMV)$"', '.mp4', $filename);			
			$inputfile="https://s3.amazonaws.com/".$inputbucket."/".$filename;
			 
			
			if (!in_array($inputfile,$existinputbucket)) {
				
				$input_url=$inputfile;
				$output_url=$outputfile;
				
			      $encoding_job = $zencoder->jobs->create(
		   array(
		     "input" => $input_url,
		     "outputs" => array(
		       array(
		         "label" => "mp4 high",
				 "size"=> $videosize,
		         "url" => "s3://".$outputbucket."/".$output_url,
		         "public"=> true,
		         "notifications"=>get_site_url().'?zencoder_notify=1',
		         "watermarks"=>array(
		           "y"=> "-20",
		           "height"=> $height,
		           "x"=> "20",
		           "url"=>$imageurl,
		           "width"=> $width
		         )
		       )
		     )
		   )
		 );
	
	    $job[]=$encoding_job;
	    
	    $wpdb->insert($table_name, array(
	        'Title' => '',
	        'Description' => '',
	        'InputBucketLink'=>$input_url,
	        'Link' => $encoding_job->outputs['mp4 high']->url,
	        'JobId'=>$encoding_job->id,
	  'Status'=>'0',
	  'YoutubeStatus'=>'0'
	    ));
			}
			
			
			
		}
		
			}
    if(!empty($job))
    {
    	foreach($job as $addedfiles)
    	{
    ?>
    <div class="updated"><p><strong><?php echo "Job Added For"." ".$addedfiles->outputs['mp4 high']->url;?></strong></p></div>
    <?php
    }
    }	
    
  
    }

	
?>
<form id="form" method="POST">
<h2>Start Zencoder Process </h2>
        <input type="hidden" name="nonce" value="<?php echo wp_create_nonce(basename(__FILE__))?>"/>
	<table cellspacing="2" cellpadding="5" style="width: 100%;" class="form-table">
    <tbody>
   
     <tr>
    <td>
     <input type="submit" value="<?php _e('Start Process', 'custom_table_example')?>" id="submit" class="button-primary" name="submit">
     </td>
    </tr>
    </tbody>
</table>
</form>
<?php	
}/*---------------Short Code-------------*/
function video_func( $atts ){

	extract( shortcode_atts( array(
	'id' => '',
	'width' => '100',
	'height' => '20'
	), $atts ) );

	$adminOptionsName = "AmazonS3AdminOptions";
	$getOptions = get_option($adminOptionsName);	
	global $wpdb;
	$query = " SELECT * FROM ".$wpdb->prefix."videodata where ID=".$id;		
	
	$video = $wpdb->get_row($query);
	$output='';		if($video!=null || $video!="" || $video->Link!="")	{		/*$output.='<script type="text/javascript" src="<?php echo plugins_url();?>/Trinay-Zencoder/jwplayer/jwplayer.js"></script>';		if($getOptions['jw_license']!="")		{			$output.='<script type="text/javascript">jwplayer.key="'.$getOptions['jw_license'].'";</script>';		}						$output.='<div id="myElement">Loading the player...</div>';		$output.='<script type="text/javascript">';		$output.='jwplayer("myElement").setup({file: "'.$video->Link.'", bufferlength: 1});';		$output.='</script>';*/
		
		$output.='<link href="'.plugins_url().'/Trinay-Zencoder/video-js/video-js.css" rel="stylesheet" type="text/css">';
		$output.='<script src="'. plugins_url().'/Trinay-Zencoder/video-js/video.js"></script>';
		$output.='<script>videojs.options.flash.swf = "'.plugins_url().'/Trinay-Zencoder/video-js/video-js.swf";</script>';
		$output.='<video id="example_video_1" class="video-js vjs-default-skin" controls preload="none" width="640" height="264"
      				data-setup="{}">
    				<source src="'.$video->Link.'" type="video/mp4" />
  					</video>';
			}	else	{		$output.="There is no video to display";	}
	

	return $output;
}
add_shortcode( 'TTS_Video', 'video_func' );
?>