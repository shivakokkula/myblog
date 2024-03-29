<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://wpvivid.com
 * @since      0.9.1
 *
 * @package    wpvivid
 * @subpackage wpvivid/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.9.1
 * @package    wpvivid
 * @subpackage wpvivid/includes
 * @author     wpvivid team
 */
if (!defined('WPVIVID_PLUGIN_DIR')){
    die;
}

class WPvivid {

	protected $plugin_name;

	protected $version;

    public $wpvivid_log;
    public $wpvivid_download_log;

    public $current_task;

    public $updater;

    public $remote_collection;

    public $function_realize;

    public $end_shutdown_function;

    public $restore_data;

    public $migrate;
    public $backup_uploader;

    public $admin;

	public function __construct()
    {
        $this->version = WPVIVID_PLUGIN_VERSION;

		$this->plugin_name = WPVIVID_PLUGIN_SLUG;

		$this->end_shutdown_function = false;

		$this->restore_data=false;

		//Load dependent files
        $this->load_dependencies();

        //A flag to determine whether plugin had been initialized
		$init=get_option('wpvivid_init', 'not init');
		if($init=='not init')
        {
            //Initialization settings
            WPvivid_Setting::init_option();
            WPvivid_Setting::update_option('wpvivid_init','init');
        }

        $wpvivid_remote_init=get_option('wpvivid_remote_init', 'not init');
		if($wpvivid_remote_init=='not init'){
            $this->init_remote_option();
            WPvivid_Setting::update_option('wpvivid_remote_init','init');
        }

        //Define the locale for this plugin for internationalization.
		$this->set_locale();
		//Register hook
        if(is_admin())
        {
            $this->define_admin_hook();
            //Add ajax hook
            $this->load_ajax_hook_for_admin();
        }

        //add_filter('pre_update_option',array( $this,'wpjam_pre_update_option_cache'),10,2);

        add_filter('wpvivid_add_backup_list', array( $this, 'wpvivid_add_backup_list' ), 10, 3);
        add_filter('wpvivid_add_remote_storage_list', array( $this, 'wpvivid_add_remote_storage_list' ), 10);
        add_filter('wpvivid_schedule_add_remote_pic', array( $this, 'wpvivid_schedule_add_remote_pic' ), 10);
        add_filter('wpvivid_get_remote_directory', array( $this, 'wpvivid_get_remote_directory' ), 10);
        add_filter('wpvivid_get_log_list', array( $this, 'wpvivid_get_log_list' ), 10);
        add_filter('wpvivid_get_last_backup_message', array( $this, 'wpvivid_get_last_backup_message' ), 10);
        add_filter('wpvivid_schedule_local_remote', array( $this, 'wpvivid_schedule_local_remote' ), 10);
        add_filter('wpvivid_remote_storage', array( $this, 'wpvivid_remote_storage'), 10);
        add_filter('wpvivid_add_remote_notice', array($this, 'wpvivid_add_remote_notice'), 10, 2);
        add_filter('wpvivid_set_general_setting', array($this, 'wpvivid_set_general_setting'), 10, 3);

        add_action('wpvivid_handle_backup_succeed',array($this,'wpvivid_handle_backup_succeed'),10);
        add_action('wpvivid_handle_upload_succeed',array($this,'wpvivid_handle_backup_succeed'),10);

        add_action('wpvivid_handle_upload_succeed',array($this,'wpvivid_mark_task'),20);
        add_action('wpvivid_handle_backup_succeed',array($this,'wpvivid_mark_task'),20);

        add_action('wpvivid_handle_backup_failed',array($this,'wpvivid_handle_backup_failed'),9, 2);

        add_action('wpvivid_handle_upload_succeed',array($this,'wpvivid_deal_upload_succeed'),9);

        add_action('wpvivid_handle_backup_failed',array($this,'wpvivid_mark_task'),20);
        add_action('init', array($this, 'init_pclzip_tmp_folder'));
        add_action('plugins_loaded', array($this, 'load_remote_storage'),10);

        add_action('wpvivid_before_setup_page',array($this,'clean_cache'));
        add_filter('wpvivid_check_type_database', array($this, 'wpvivid_check_type_database'), 10, 2);
        add_filter('wpvivid_set_mail_subject', array($this, 'set_mail_subject'), 10, 2);
        add_filter('wpvivid_set_mail_body', array($this, 'set_mail_body'), 10, 2);

		//Initialisation schedule hook
        $this->init_cron();
        //Initialisation log object
        $this->wpvivid_log=new WPvivid_Log();
        $this->wpvivid_download_log=new WPvivid_Log();
	}

	public function init_cron()
    {
        $schedule=new WPvivid_Schedule();
        add_action(WPVIVID_MAIN_SCHEDULE_EVENT,array( $this,'main_schedule'));
        add_action(WPVIVID_RESUME_SCHEDULE_EVENT,array( $this,'resume_schedule'));
        add_action(WPVIVID_CLEAN_BACKING_UP_DATA_EVENT,array($this,'clean_backing_up_data_event'));
        add_action(WPVIVID_CLEAN_BACKUP_RECORD_EVENT,array($this,'clean_backup_record_event'));
        //add_clean_event
        add_action(WPVIVID_TASK_MONITOR_EVENT,array( $this,'task_monitor'));
        add_filter('cron_schedules',array( $schedule,'wpvivid_cron_schedules'),99);
        add_filter('wpvivid_schedule_time', array($schedule, 'output'));
    }

	private function load_dependencies()
    {
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-schedule.php';

		require_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-i18n.php';
        require_once WPVIVID_PLUGIN_DIR . '/admin/class-wpvivid-admin.php';

		include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-setting.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-log.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-error-log.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-backuplist.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-restore-data.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-taskmanager.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-downloader.php';
		include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-backup.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-mail-report.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-restore.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-remote-collection.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-function-realize.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-upload.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-backup-uploader.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-crypt.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-migrate.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-db-method.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-public-interface.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-additional-db-method.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-restore-db-extra.php';

        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-export-import.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-exporter.php';
        include_once WPVIVID_PLUGIN_DIR . '/includes/class-wpvivid-importer.php';

        include_once WPVIVID_PLUGIN_DIR.'/includes/class-wpvivid-tab-page-container.php' ;

        $this->function_realize=new WPvivid_Function_Realize();
        $this->migrate=new WPvivid_Migrate();
        $this->backup_uploader=new Wpvivid_BackupUploader();
        $send_to_site=new WPvivid_Send_to_site();
        $export_import = new WPvivid_Export_Import();
	}

	public function init_pclzip_tmp_folder()
    {
        if (!defined('PCLZIP_TEMPORARY_DIR')) {
            $backupdir=WPvivid_Setting::get_backupdir();
            define( 'PCLZIP_TEMPORARY_DIR', WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$backupdir.DIRECTORY_SEPARATOR );
        }
    }

    public function load_remote_storage(){
        $this->remote_collection=new WPvivid_Remote_collection();
    }

	private function set_locale()
    {
		$plugin_i18n = new WPvivid_i18n();
        add_action('plugins_loaded',array( $plugin_i18n,'load_plugin_textdomain'));
	}

    private function define_admin_hook()
    {
        $this->admin = new WPvivid_Admin($this->get_plugin_name(), $this->get_version());

        add_action('admin_enqueue_scripts',array( $this->admin,'enqueue_styles'));
        add_action('admin_enqueue_scripts',array( $this->admin,'enqueue_scripts'));
        // Add menu item

        if(is_multisite())
        {
            add_action('network_admin_menu',array( $this->admin,'add_plugin_admin_menu'));
        }
        else
        {
            add_action('admin_menu',array( $this->admin,'add_plugin_admin_menu'));
        }

        add_action('admin_bar_menu',array( $this->admin,'add_toolbar_items'),100);
        //show admin bar
        add_action('admin_head',array( $this->admin,'wpvivid_get_siteurl'),100);

        // Add Settings link to the plugin
        $plugin_basename = plugin_basename( plugin_dir_path( __DIR__ ) . 'wpvivid-backuprestore.php' );
        add_filter('plugin_action_links_' . $plugin_basename, array( $this->admin,'add_action_links'));

        add_filter('wpvivid_pre_add_remote',array($this, 'pre_add_remote'),10,2);

        add_filter('wpvivid_add_tab_page', array($this->admin, 'wpvivid_add_default_tab_page'));
        //
    }

    public function pre_add_remote($remote,$id)
    {
        unset($remote['default']);
        return $remote;
    }

    public function wpjam_pre_update_option_cache($value, $option)
    {
        wp_cache_delete('notoptions', 'options');
        wp_cache_delete('alloptions', 'options');
        wp_cache_delete($option, 'options');
        return $value;
    }

    public function load_ajax_hook_for_admin()
    {
        //Add remote storage
        add_action('wp_ajax_wpvivid_add_remote',array( $this,'add_remote'));
        //Delete remote storage
        add_action('wp_ajax_wpvivid_delete_remote',array( $this,'delete_remote'));
        //Retrieve remote storage
        add_action('wp_ajax_wpvivid_retrieve_remote',array( $this,'retrieve_remote'));
        //Edit remote storage
        add_action('wp_ajax_wpvivid_edit_remote',array( $this,'edit_remote'));
        //List exist remote
        add_action('wp_ajax_wpvivid_list_remote',array( $this,'list_remote'));
        //Test remote connection
        add_action('wp_ajax_wpvivid_test_remote_connection',array( $this,'test_remote_connection'));
        //Start backup
        add_action('wp_ajax_wpvivid_prepare_backup',array( $this,'prepare_backup'));
        add_action('wp_ajax_wpvivid_delete_ready_task',array($this,'delete_ready_task'));
        add_action('wp_ajax_wpvivid_backup_now',array( $this,'backup_now'));
        //Cancel backup
        add_action('wp_ajax_wpvivid_backup_cancel',array( $this,'backup_cancel'));
        //List backup record
        add_action('wp_ajax_wpvivid_get_backup_list',array( $this,'get_backup_list'));
        //View backup record log file
        add_action('wp_ajax_wpvivid_view_backup_log',array( $this,'view_backup_log'));
        //View log file of the backup task
        add_action('wp_ajax_wpvivid_view_backup_task_log',array( $this,'view_backup_task_log'));
        //List all logs
        add_action('wp_ajax_wpvivid_get_log_list',array( $this,'get_log_list'));
        //View logs
        add_action('wp_ajax_wpvivid_view_log',array( $this,'view_log'));
        //Prepare download backup files
        add_action('wp_ajax_wpvivid_prepare_download_backup',array( $this,'prepare_download_backup'));
        //Download backup from site
        add_action('wp_ajax_wpvivid_download_backup',array( $this,'download_backup'));
        //Delete downloaded file
        add_action('wp_ajax_wpvivid_delete_download',array( $this,'delete_download'));
        //Delete backup record
        add_action('wp_ajax_wpvivid_delete_backup',array( $this,'delete_backup'));
        //Delete backup records
        add_action('wp_ajax_wpvivid_delete_backup_array',array( $this,'delete_backup_array'));
        //
        add_action('wp_ajax_wpvivid_init_download_page',array( $this,'init_download_page'));
        //Set security lock for backup record
        add_action('wp_ajax_wpvivid_set_security_lock',array( $this,'set_security_lock'));
        //Delete task
        add_action('wp_ajax_wpvivid_delete_task',array( $this,'delete_task'));
        //Get backup schedule data
        add_action('wp_ajax_wpvivid_get_schedule',array( $this,'get_schedule'));
        //Get last backup information
        add_action('wp_ajax_wpvivid_get_last_backup',array( $this,'get_last_backup'));
        //Get settings
        add_action('wp_ajax_wpvivid_get_setting',array( $this,'get_setting'));
        add_action('wp_ajax_wpvivid_get_general_setting',array( $this,'get_general_setting'));
        //Update settings
        add_action('wp_ajax_wpvivid_update_setting',array( $this,'update_setting'));
        add_action('wp_ajax_wpvivid_set_general_setting',array( $this,'set_general_setting'));
        add_action('wp_ajax_wpvivid_set_schedule',array( $this,'set_schedule' ));
        //Export settings
        add_action('wp_ajax_wpvivid_export_setting',array( $this,'export_setting'));
        //Import settings
        add_action('wp_ajax_wpvivid_import_setting',array( $this,'import_setting'));
        //Send test mail
        add_action('wp_ajax_wpvivid_test_send_mail',array( $this,'test_send_mail'));
        //Send debug mail
        add_action('wp_ajax_wpvivid_create_debug_package',array( $this,'create_debug_package'));
        //Get backup local storage path
        add_action('wp_ajax_wpvivid_get_dir',array( $this,'get_dir'));
        //Get Web-server disk space in use
        add_action('wp_ajax_wpvivid_junk_files_info',array( $this,'junk_files_info'));
        add_action('wp_ajax_wpvivid_clean_local_storage',array( $this,'clean_local_storage'));
        add_action('wp_ajax_wpvivid_get_out_of_date_info',array($this,'get_out_of_date_info'));
        add_action('wp_ajax_wpvivid_clean_out_of_date_backup',array($this,'clean_out_of_date_backup'));
        //Prepare backup files for restore
        add_action('wp_ajax_wpvivid_prepare_restore',array( $this,'prepare_restore'));
        //Download backup files for restore
        add_action('wp_ajax_wpvivid_download_restore',array( $this,'download_restore_file'));
        //
        add_action('wp_ajax_wpvivid_init_restore_page',array( $this,'init_restore_page'));
        //
        add_action('wp_ajax_wpvivid_delete_last_restore_data',array( $this,'delete_last_restore_data'));
        //
        add_action('wp_ajax_wpvivid_delete_old_files',array( $this,'delete_old_files'));
        //start restore
        add_action('wp_ajax_wpvivid_restore',array( $this,'restore'));
        //start rollback
        add_action('wp_ajax_wpvivid_rollback',array( $this,'rollback'));
        add_action('wp_ajax_wpvivid_get_restore_progress',array( $this,'get_restore_progress'));
        add_action('wp_ajax_wpvivid_get_rollback_progress',array( $this,'get_rollback_progress'));
        add_action('wp_ajax_wpvivid_get_download_restore_progress',array( $this,'download_restore_progress'));
        //When restoring the database use wp_ajax_nopriv_
        add_action('wp_ajax_nopriv_wpvivid_init_restore_page',array( $this,'init_restore_page'));
        add_action('wp_ajax_nopriv_wpvivid_delete_last_restore_data',array( $this,'delete_last_restore_data'));
        add_action('wp_ajax_nopriv_wpvivid_restore',array( $this,'restore'));
        add_action('wp_ajax_nopriv_wpvivid_rollback',array( $this,'rollback'));
        add_action('wp_ajax_nopriv_wpvivid_get_restore_progress',array( $this,'get_restore_progress'));
        add_action('wp_ajax_nopriv_wpvivid_get_rollback_progress',array( $this,'get_rollback_progress'));
        add_action('wp_ajax_wpvivid_list_tasks',array( $this,'list_tasks'));
        //View last backup record log
        add_action('wp_ajax_wpvivid_read_last_backup_log',array( $this,'read_last_backup_log'));
        //Set default remote storage when backing up
        add_action('wp_ajax_wpvivid_set_default_remote_storage',array( $this,'set_default_remote_storage'));
        //Get default remote storage when backing up
        add_action('wp_ajax_wpvivid_get_default_remote_storage',array( $this,'get_default_remote_storage'));
        add_action('wp_ajax_wpvivid_need_review',array( $this,'need_review'));
        add_action('wp_ajax_wpvivid_send_debug_info',array($this,'wpvivid_send_debug_info'));
        add_action('wp_ajax_wpvivid_get_ini_memory_limit',array($this,'get_ini_memory_limit'));
        add_action('wp_ajax_wpvivid_get_restore_file_is_migrate', array($this, 'get_restore_file_is_migrate'));

        add_action('wp_ajax_wpvivid_check_remote_alias_exist', array($this, 'check_remote_alias_exist'));
        add_action('wp_ajax_wpvivid_task_monitor', array($this, 'task_monitor_ex'));
        add_action('wp_ajax_wpvivid_amazons3_notice', array($this, 'amazons3_notice'));

        add_action('wp_ajax_wpvivid_hide_mainwp_tab_page', array($this, 'hide_mainwp_tab_page'));
        add_action('wp_ajax_wpvivid_hide_wp_cron_notice', array($this, 'hide_wp_cron_notice'));
        //wpvivid_task_monitor
    }

	public function get_plugin_name()
    {
		return $this->plugin_name;
	}

	public function get_version()
    {
        return $this->version;
    }

    /**
     * Prepare backup include what you want to backup,where you want to store.
     *
     *When prepare backup finished,you can use backup_now start a backup task.
     *
     * @since 0.9.1
     */
    public function prepare_backup()
    {
        $this->ajax_check_security();
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_prepare_shutdown_error'));
        try
        {
            if(isset($_POST['backup'])&&!empty($_POST['backup']))
            {
                $json = $_POST['backup'];
                $json = stripslashes($json);
                $backup_options = json_decode($json, true);
                if (is_null($backup_options))
                {
                    $this->end_shutdown_function=true;
                    die();
                }

                $backup_options = apply_filters('wpvivid_custom_backup_options', $backup_options);

                if(!isset($backup_options['type']))
                {
                    $backup_options['type']='Manual';
                    $backup_options['action']='backup';
                }

                $ret = $this->check_backup_option($backup_options, $backup_options['type']);
                if($ret['result']!=WPVIVID_SUCCESS)
                {
                    $this->end_shutdown_function=true;
                    echo json_encode($ret);
                    die();
                }

                $ret=$this->pre_backup($backup_options);
                if($ret['result']=='success')
                {
                    //Check the website data to be backed up
                    $ret['check']=$this->check_backup($ret['task_id'],$backup_options);
                    if(isset($ret['check']['result']) && $ret['check']['result'] == WPVIVID_FAILED)
                    {
                        $this->end_shutdown_function=true;
                        echo json_encode(array('result' => WPVIVID_FAILED,'error' => $ret['check']['error']));
                        die();
                    }

                    $html = '';
                    $html = apply_filters('wpvivid_add_backup_list', $html);
                    $ret['html'] = $html;
                }
                $this->end_shutdown_function=true;
                echo json_encode($ret);
                die();
            }
        }
        catch (Exception $error)
        {
            $this->end_shutdown_function=true;
            $ret['result']='failed';
            $message = 'An exception has occurred. class:'.get_class($error).';msg:'.$error->getMessage().';code:'.$error->getCode().';line:'.$error->getLine().';in_file:'.$error->getFile().';';
            $ret['error'] = $message;
            $id=uniqid('wpvivid-');
            $log_file_name=$id.'_backup';
            $log=new WPvivid_Log();
            $log->CreateLogFile($log_file_name,'no_folder','backup');
            $log->WriteLog($message,'notice');
            WPvivid_error_log::create_error_log($log->log_file);
            $log->CloseFile();
            error_log($message);
            echo json_encode($ret);
            die();
        }
    }

    public function deal_prepare_shutdown_error()
    {
        if($this->end_shutdown_function==false) {
            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true)) {
                $error = $last_error;
            } else {
                $error = false;
            }
            $ret['result'] = 'failed';
            if ($error === false) {
                $ret['error'] = 'unknown Error';
            } else {
                $ret['error'] = 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                error_log($ret['error']);
            }
            $id = uniqid('wpvivid-');
            $log_file_name = $id . '_backup';
            $log = new WPvivid_Log();
            $log->CreateLogFile($log_file_name, 'no_folder', 'backup');
            $log->WriteLog($ret['error'], 'notice');
            WPvivid_error_log::create_error_log($log->log_file);
            $log->CloseFile();
            echo json_encode($ret);
            die();
        }
    }

    public function check_backup_option($data, $backup_method = 'Manual')
    {
        $ret['result']=WPVIVID_SUCCESS;
        add_filter('wpvivid_check_backup_options_valid',array($this, 'check_backup_options_valid'),10,3);
        $ret=apply_filters('wpvivid_check_backup_options_valid',$ret,$data,$backup_method);
        return $ret;
    }

    public function check_backup_options_valid($ret,$data,$backup_method)
    {
        $ret['result']=WPVIVID_FAILED;
        if(!isset($data['backup_files']))
        {
            $ret['error']=__('A backup type is required.', 'wpvivid');
            return $ret;
        }

        $data['backup_files']=sanitize_text_field($data['backup_files']);

        if(empty($data['backup_files']))
        {
            $ret['error']=__('A backup type is required.', 'wpvivid');
            return $ret;
        }

        if(!isset($data['local']) && !isset($data['remote']))
        {
            $ret['error']=__('Choose at least one storage location for backups.', 'wpvivid');
            return $ret;
        }

        $data['local']=sanitize_text_field($data['local']);
        $data['remote']=sanitize_text_field($data['remote']);

        if(empty($data['local']) && empty($data['remote']))
        {
            $ret['error']=__('Choose at least one storage location for backups.', 'wpvivid');
            return $ret;
        }

        if($backup_method == 'Manual')
        {
            if ($data['remote'] === '1')
            {
                $remote_storage = WPvivid_Setting::get_remote_options();
                if ($remote_storage == false)
                {
                    $ret['error'] = __('There is no default remote storage configured. Please set it up first.', 'wpvivid');
                    return $ret;
                }
            }
        }
        $ret['result']=WPVIVID_SUCCESS;
        return $ret;
    }

    /**
     * Delete tasks had [ready] status.
     *
     *When prepare backup go wrong,may retain some task we don't need.Delete them.
     *
     * @since 0.9.3
     */
    public function delete_ready_task()
    {
        $this->ajax_check_security();
        try {
            WPvivid_taskmanager::delete_ready_task();
            $ret['result'] = 'success';
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * Start a backup task init by prepare_backup.
     *
     * @since 0.9.1
     */
    public function backup_now()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['task_id']) || empty($_POST['task_id']) || !is_string($_POST['task_id'])) {
                $ret['result'] = 'failed';
                $ret['error'] = __('Error occurred while parsing the request data. Please try to run backup again.', 'wpvivid');
                echo json_encode($ret);
                die();
            }
            $task_id = sanitize_key($_POST['task_id']);

            //Start backup site
            if (WPvivid_taskmanager::is_tasks_backup_running()) {
                $ret['result'] = 'failed';
                $ret['error'] = __('A task is already running. Please wait until the running task is complete, and try again.', 'wpvivid');
                echo json_encode($ret);
                die();
            }
            $this->backup($task_id);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        catch (Error $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * View backup record logs.
     *
     * @since 0.9.1
     */
    public function view_backup_log()
    {
        $this->ajax_check_security();
        try
        {
            if (isset($_POST['id']) && !empty($_POST['id']) && is_string($_POST['id']))
            {
                $backup_id = sanitize_key($_POST['id']);
                $backup = WPvivid_Backuplist::get_backup_by_id($backup_id);
                if (!$backup)
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('Retrieving the backup information failed while showing log. Please try again later.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                if (!file_exists($backup['log']))
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $file = fopen($backup['log'], 'r');

                if (!$file)
                {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $buffer = '';
                while (!feof($file))
                {
                    $buffer .= fread($file, 1024);
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo json_encode($json);
            } else {
                $json['result'] = 'failed';
                $json['error'] = __('Reading the log failed. Please try again.', 'wpvivid');
                echo json_encode($json);
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * View last backup record logs.
     *
     * @since 0.9.1
     */
    public function read_last_backup_log()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['log_file_name']) || empty($_POST['log_file_name']) || !is_string($_POST['log_file_name'])) {
                $json['result'] = 'failed';
                $json['error'] = __('Reading the log failed. Please try again.', 'wpvivid');
                echo json_encode($json);
                die();
            }
            $option = sanitize_text_field($_POST['log_file_name']);
            $log_file_name = $this->wpvivid_log->GetSaveLogFolder() . $option . '_log.txt';

            if (!file_exists($log_file_name)) {
                $json['result'] = 'failed';
                $json['error'] = __('The log not found.', 'wpvivid');
                echo json_encode($json);
                die();
            }

            $file = fopen($log_file_name, 'r');

            if (!$file) {
                $json['result'] = 'failed';
                $json['error'] = __('Unable to open the log file.', 'wpvivid');
                echo json_encode($json);
                die();
            }

            $buffer = '';
            while (!feof($file)) {
                $buffer .= fread($file, 1024);
            }
            fclose($file);

            $json['result'] = 'success';
            $json['data'] = $buffer;
            echo json_encode($json);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * View logs of the backup task.
     *
     * @since 0.9.1
     */
    public function view_backup_task_log()
    {
        $this->ajax_check_security();
        try {
            if (isset($_POST['id']) && !empty($_POST['id']) && is_string($_POST['id'])) {
                $backup_task_id = sanitize_key($_POST['id']);
                $option = WPvivid_taskmanager::get_task_options($backup_task_id, 'log_file_name');
                if (!$option) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Retrieving the backup information failed while showing log. Please try again later.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $log_file_name = $this->wpvivid_log->GetSaveLogFolder() . $option . '_log.txt';

                if (!file_exists($log_file_name)) {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $file = fopen($log_file_name, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $buffer = '';
                while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo json_encode($json);
            } else {
                $json['result'] = 'failed';
                $json['error'] = __('Reading the log failed. Please try again.', 'wpvivid');
                echo json_encode($json);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Cancel a backup task.
     *
     * @since 0.9.1
     */
    public function backup_cancel()
    {
        $this->ajax_check_security();
        try {
            /*if (isset($_POST['task_id']) && !empty($_POST['task_id']) && is_string($_POST['task_id'])) {
                $task_id = sanitize_key($_POST['task_id']);
                $json = $this->function_realize->_backup_cancel($task_id);
                echo json_encode($json);
            }*/
            $json = $this->function_realize->_backup_cancel();
            echo json_encode($json);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function main_schedule($schedule_id='')
    {
        //get backup options
        do_action('wpvivid_set_current_schedule_id', $schedule_id);
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_prepare_shutdown_error'));
        $schedule_options=WPvivid_Schedule::get_schedule($schedule_id);
        if(empty($schedule_options))
        {
            $this->end_shutdown_function=true;
            die();
        }
        try
        {
            $schedule_options['backup']['local'] = strval($schedule_options['backup']['local']);
            $schedule_options['backup']['remote'] = strval($schedule_options['backup']['remote']);
            $schedule_options['backup']['ismerge'] = strval($schedule_options['backup']['ismerge']);
            $schedule_options['backup']['lock'] = strval($schedule_options['backup']['lock']);
            $ret = $this->check_backup_option($schedule_options['backup'], 'Cron');
            if ($ret['result'] != WPVIVID_SUCCESS)
            {
                $this->end_shutdown_function=true;
                echo json_encode($ret);
                die();
            }

            if(!isset($schedule_options['backup']['type']))
            {
                $schedule_options['backup']['type']='Cron';
                $schedule_options['backup']['action']='backup';
            }

            $ret = $this->pre_backup($schedule_options['backup']);
            if ($ret['result'] == 'success') {
                //Check the website data to be backed up.
                $this->check_backup($ret['task_id'], $schedule_options['backup']);
                //start backup task.
                $this->backup($ret['task_id']);
            }
            $this->end_shutdown_function=true;
            die();
        }
        catch (Exception $error)
        {
            $this->end_shutdown_function=true;
            $ret['result']='failed';
            $message = 'An exception has occurred. class:'.get_class($error).';msg:'.$error->getMessage().';code:'.$error->getCode().';line:'.$error->getLine().';in_file:'.$error->getFile().';';
            $ret['error'] = $message;
            $id=uniqid('wpvivid-');
            $log_file_name=$id.'_backup';
            $log=new WPvivid_Log();
            $log->CreateLogFile($log_file_name,'no_folder','backup');
            $log->WriteLog($message,'notice');
            WPvivid_error_log::create_error_log($log->log_file);
            $log->CloseFile();
            error_log($message);
            echo json_encode($ret);
            die();
        }
    }
    /**
     * Resume backup schedule.
     *
     * Resume a backup task.
     *
     * @var string $task_id backup task id
     *
     * @since 0.9.1
     */
    public function resume_schedule($task_id='0')
    {
        if($task_id=='0')
        {
            die();
        }

        $task=WPvivid_taskmanager::get_task($task_id);

        if(!$task)
        {
            die();
        }

        if (WPvivid_taskmanager::is_tasks_backup_running())
        {
            $ret['result'] = 'failed';
            $ret['error'] = __('A task is already running. Please wait until the running task is complete, and try again.', 'wpvivid');
            echo json_encode($ret);
            die();
        }

        $doing=WPvivid_taskmanager::get_backup_main_task_progress($task_id);
        if($doing=='backup')
        {
            $this->backup($task_id);
        }
        else if($doing=='upload')
        {
            $this->upload($task_id);
        }
        //resume backup

        die();
    }

    /**
     * Clean backing up data schedule.
     *
     * @var string $task_id backup task id
     *
     * @since 0.9.1
     */
    public function clean_backing_up_data_event($task_id)
    {
        $tasks=WPvivid_Setting::get_option('clean_task');
        $task=$tasks[$task_id];
        unset($tasks[$task_id]);
        WPvivid_Setting::update_option('clean_task',$tasks);

        if(!empty($task))
        {
            $backup=new WPvivid_Backup(false,$task);
            $backup->clean_backup();

            $files=array();

            if($task['options']['remote_options']!==false)
            {
                $backup_files=$backup->task->get_need_cleanup_files(true);
                foreach ($backup_files as $file)
                {
                    $files[]=basename($file);
                }
                if(!empty($files))
                {
                    $upload=new WPvivid_Upload();
                    $upload->clean_remote_backup($task['options']['remote_options'],$files);
                }
            }
            //clean upload
        }
    }
    /**
     * Clean backup record schedule.
     *
     * @var string $task_id backup task id
     *
     * @since 0.9.1
     */
    public function clean_backup_record_event($backup_id)
    {
        $tasks=WPvivid_Setting::get_option('clean_task');
        $backup=$tasks[$backup_id];
        unset($tasks[$backup_id]);
        WPvivid_Setting::update_option('clean_task',$tasks);

        if(!empty($backup))
        {

            $backup_item=new WPvivid_Backup_Item($backup);
            $files=$backup_item->get_files();
            foreach ($files as $file)
            {
                if (file_exists($file))
                {
                    @unlink($file);
                }
            }

            if(!empty($backup['remote']))
            {
                $files=$backup_item->get_files(false);

                foreach($backup['remote'] as $remote)
                {
                    WPvivid_downloader::delete($remote,$files);
                }
            }
        }
    }
    /**
     * Clean oldest backup record.
     *
     * @var string $task_id backup task id
     *
     * @since 0.9.1
     */
    public function clean_oldest_backup()
    {
        $backup_ids=WPvivid_Backuplist::get_out_of_date_backuplist(WPvivid_Setting::get_max_backup_count());
        foreach($backup_ids as $backup_id)
        {
            $this->delete_backup_by_id($backup_id);
        }
        $count=WPvivid_Setting::get_max_backup_count();
        $ret=WPvivid_Backuplist::check_backuplist_limit($count);
        if($ret['result']=='need_delete')
        {
            $oldest_id=$ret['oldest_id'];
            if($oldest_id!='not set')
            {
                $this->add_clean_backup_record_event($oldest_id);
                WPvivid_Backuplist::delete_backup($oldest_id);
            }
        }
    }
    /**
     * Initialization backup task.
     *
     * @var array $backup_options
     * @var string $type
     * @var int $lock
     *
     * @return array
     *
     * @since 0.9.1
     */
    public function pre_backup($backup_options)
    {
        if(apply_filters('wpvivid_need_clean_oldest_backup',true,$backup_options))
        {
            $this->clean_oldest_backup();
        }

        if(WPvivid_taskmanager::is_tasks_backup_running())
        {
            $ret['result']='failed';
            $ret['error']=__('A task is already running. Please wait until the running task is complete, and try again.', 'wpvivid');
            return $ret;
        }

        $backup=new WPvivid_Backup_Task();
        $ret=$backup->new_backup_task($backup_options,$backup_options['type'],$backup_options['action']);
        return $ret;
    }

    /**
     * start or resume a backup task.
     *
     * @var string $task_id
     *
     * @since 0.9.1
     */
    public function backup($task_id)
    {
        //register shutdown function to catch php fatal error such as script time out and memory limit

        $common_setting = WPvivid_Setting::get_option('wpvivid_common_setting');
        if(isset($common_setting['memory_limit']) && !empty($common_setting['memory_limit'])){
            $memory_limit = $common_setting['memory_limit'];
        }
        else{
            $memory_limit = WPVIVID_MEMORY_LIMIT;
        }
        @ini_set('memory_limit', $memory_limit);
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_shutdown_error'),$task_id);
        @ignore_user_abort(true);
        WPvivid_taskmanager::update_backup_task_status($task_id,true,'running');
        $this->current_task=WPvivid_taskmanager::get_task($task_id);
        //start a watch task event
        $this->add_monitor_event($task_id);
        //flush buffer
        $this->flush($task_id);
        $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id,'log_file_name'));
        $this->wpvivid_log->WriteLog('Start backing up.','notice');
        $this->wpvivid_log->WriteLogHander();
        //start backup
        try
        {
            $backup=new WPvivid_Backup();

            $backup_ret=$backup->backup($task_id);
            $backup->clearcache();

            if($backup_ret['result'] != WPVIVID_SUCCESS)
            {
                $this->wpvivid_log->WriteLog('Backup ends with an error '. $backup_ret['error'], 'error');
            }
            else {
                $this->wpvivid_log->WriteLog('Backup completed.','notice');
            }

            if(!$this->finish_backup_task($task_id))
            {
                $this->end_shutdown_function=true;
                die();
            }
            if(WPvivid_taskmanager::get_task_options($task_id,'remote_options')!=false)
            {
                $this->upload($task_id,false);
            }
        }
        catch (Exception $e)
        {
            //catch error and stop task recording history
            $this->deal_task_error($task_id,'exception',$e);
            $this->wpvivid_log->CloseFile();
            $this->end_shutdown_function=true;
            die();
        }
        catch (Error $e)
        {
            //catch error and stop task recording history
            $this->deal_task_error($task_id,'error',$e);
            $this->wpvivid_log->CloseFile();
            $this->end_shutdown_function=true;
            die();
        }
        $this->end_shutdown_function=true;
        die();
    }

    /**
     * recording finished backup task.
     *
     * @var string $task_id
     *
     * @var array $backup_ret return data of backup
     *
     * @return boolean
     *
     * @since 0.9.1
     */
    private function finish_backup_task($task_id)
    {
        $status=WPvivid_taskmanager::get_backup_task_status($task_id);
        if($status['str']=='running')
        {
            $this->wpvivid_log->WriteLog('Backup succeeded.','notice');

            $remote_options=WPvivid_taskmanager::get_task_options($task_id,'remote_options');
            if($remote_options===false)
            {
                $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'completed');
                do_action('wpvivid_handle_backup_succeed',$task);
            }
            return true;
        }
        else
        {
            $task=WPvivid_taskmanager::get_task($task_id);
            do_action('wpvivid_handle_backup_failed',$task, false);
            return false;
        }
    }

    public function wpvivid_analysis_backup($task)
    {
        if($task['type'] == 'Cron')
        {
            $cron_backup_count = WPvivid_Setting::get_option('cron_backup_count');
            if(empty($cron_backup_count)){
                $cron_backup_count = 0;
            }
            $cron_backup_count++;
            WPvivid_Setting::update_option('cron_backup_count', $cron_backup_count);
            $common_setting = WPvivid_Setting::get_option('wpvivid_common_setting');
            $max_backup_count = $common_setting['max_backup_count'];
            if($cron_backup_count >= $max_backup_count){
                $need_review=WPvivid_Setting::get_option('wpvivid_need_review');
                if($need_review=='not')
                {
                    WPvivid_Setting::update_option('wpvivid_need_review','show');
                    $msg = 'Cheers! The schedule feature of WPvivid Backup plugin seems to be running well. If you found WPvivid Backup plugin helpful, a 5-star rating will motivate us to keep improving the plugin quality.';
                    WPvivid_Setting::update_option('wpvivid_review_msg',$msg);
                }
            }
        }
    }
    /**
     * start upload files to remote.
     *
     * @var string $task_id
     * @var bool $restart
     * @since 0.9.10
     */
    public function upload($task_id,$restart=true)
    {
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_shutdown_error'),$task_id);
        @ignore_user_abort(true);
        WPvivid_taskmanager::update_backup_task_status($task_id,$restart,'running',false,0);
        $this->current_task=WPvivid_taskmanager::get_task($task_id);
        //start a watch task event
        $this->add_monitor_event($task_id);
        //flush buffer
        $this->flush($task_id);
        $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id,'log_file_name'));
        $this->wpvivid_log->WriteLog('Start upload.','notice');

        $this->set_time_limit($task_id);

        $upload=new WPvivid_Upload();
        $ret=$upload->upload($task_id);
        if($ret['result'] == WPVIVID_SUCCESS)
        {
            $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'completed');
            do_action('wpvivid_handle_upload_succeed',$task);
        }
        else
        {
            $backup = WPvivid_Backuplist::get_backup_by_id($task_id);
            if($backup!==false)
            {
                $backup['save_local']=1;
                WPvivid_Backuplist::update_backup_option($task_id, $backup);
            }

            $this->wpvivid_log->WriteLog('Uploading the file ends with an error '. $ret['error'], 'error');
            $task=WPvivid_taskmanager::get_task($task_id);
            do_action('wpvivid_handle_backup_failed',$task, false);
        }
        $this->end_shutdown_function=true;
        die();
    }

    function wpvivid_deal_upload_succeed($task)
    {
        $save_local=$task['options']['save_local'];
        if($save_local==0)
        {
            $this->wpvivid_log->WriteLog('Cleaned up local files after uploading to remote storages.','notice');
            $backup=new WPvivid_Backup($task['id']);
            $backup->clean_backup();
        }
        $this->wpvivid_log->WriteLog('Upload succeeded.','notice');
        $remote_options=$task['options']['remote_options'];
        $remote_options=apply_filters('wpvivid_set_backup_remote_options',$remote_options,$task['id']);
        WPvivid_Backuplist::update_backup($task['id'],'remote',$remote_options);
    }

    function wpvivid_handle_backup_succeed($task)
    {
        if($task['action'] === 'backup')
        {
            $backup_task=new WPvivid_Backup_Task($task['id']);
            $backup_task->add_new_backup();

            $remote_options = WPvivid_taskmanager::get_task_options($task['id'], 'remote_options');
            if($remote_options != false){
                WPvivid_Backuplist::update_backup($task['id'],'remote', $remote_options);
            }

            $backup_success_count = WPvivid_Setting::get_option('wpvivid_backup_success_count');
            if (empty($backup_success_count))
            {
                $backup_success_count = 0;
            }
            $backup_success_count++;
            WPvivid_Setting::update_option('wpvivid_backup_success_count', $backup_success_count);
            $this->wpvivid_analysis_backup($task);
        }
        WPvivid_Schedule::clear_monitor_schedule($task['id']);
        WPvivid_mail_report::send_report_mail($task);
    }

    function wpvivid_mark_task($task)
    {
        WPvivid_taskmanager::mark_task($task['id']);
    }

    function wpvivid_handle_backup_failed($task, $need_set_low_resource_mode)
    {
        if($task['action'] === 'backup')
        {
            $backup_error_array = WPvivid_Setting::get_option('wpvivid_backup_error_array');
            if (!isset($backup_error_array) || empty($backup_error_array))
            {
                $backup_error_array = array();
                $backup_error_array['bu_error']['task_id'] = '';
                $backup_error_array['bu_error']['error_msg'] = '';
            }
            if (!array_key_exists($task['id'], $backup_error_array['bu_error']))
            {
                $backup_error_array['bu_error']['task_id'] = $task['id'];
                $backup_error_array['bu_error']['error_msg'] = 'Unknown error.';

                $general_setting=WPvivid_Setting::get_setting(true, "");
                $need_notice = false;
                if(!isset($general_setting['options']['wpvivid_compress_setting']['subpackage_plugin_upload'])){
                    $need_notice = true;
                }
                else{
                    if($general_setting['options']['wpvivid_compress_setting']['subpackage_plugin_upload']){
                        $need_notice = false;
                    }
                    else{
                        $need_notice = true;
                    }
                }
                if($need_notice) {
                    if($need_set_low_resource_mode) {
                        $notice_msg1 = 'Backup failed, it seems due to insufficient server resource or hitting server limit. Please navigate to Settings > Advanced > ';
                        $notice_msg2 = 'optimization mode for web hosting/shared hosting';
                        $notice_msg3 = ' to enable it and try again';
                        $backup_error_array['bu_error']['error_msg']=__('<div class="notice notice-error inline"><p>'.$notice_msg1.'<strong>'.$notice_msg2.'</strong>'.$notice_msg3.'</p></div>');
                    }
                    else{
                        $notice_msg = 'Backup error: '.$task['status']['error'].', task id: '.$task['id'];
                        $backup_error_array['bu_error']['error_msg']=__('<div class="notice notice-error inline"><p>'.$notice_msg.', Please switch to <a href="#" onclick="wpvivid_click_switch_page(\'wrap\', \'wpvivid_tab_debug\', true);">Website Info</a> page to send us the debug information. </p></div>');
                    }
                }
                else{
                    if($need_set_low_resource_mode) {
                        $notice_msg = 'Backup failed, it seems due to insufficient server resource or hitting server limit.';
                        $backup_error_array['bu_error']['error_msg'] = __('<div class="notice notice-error inline"><p>' . $notice_msg . ', Please switch to <a href="#" onclick="wpvivid_click_switch_page(\'wrap\', \'wpvivid_tab_debug\', true);">Website Info</a> page to send us the debug information. </p></div>');
                    }
                    else {
                        $notice_msg = 'Backup error: ' . $task['status']['error'] . ', task id: ' . $task['id'];
                        $backup_error_array['bu_error']['error_msg'] = __('<div class="notice notice-error inline"><p>' . $notice_msg . ', Please switch to <a href="#" onclick="wpvivid_click_switch_page(\'wrap\', \'wpvivid_tab_debug\', true);">Website Info</a> page to send us the debug information. </p></div>');
                    }
                }
            }
            WPvivid_Setting::update_option('wpvivid_backup_error_array', $backup_error_array);
        }
        $this->wpvivid_log->WriteLog($task['status']['error'],'error');
        WPvivid_error_log::create_error_log($this->wpvivid_log->log_file);
        $this->wpvivid_log->CloseFile();
        WPvivid_Schedule::clear_monitor_schedule($task['id']);
        $this->add_clean_backing_up_data_event($task['id']);
        WPvivid_mail_report::send_report_mail($task);
    }

    public function deal_shutdown_error($task_id)
    {
        if($this->end_shutdown_function===false)
        {
            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true))
            {
                $error = $last_error;
            } else {
                $error = false;
            }
            //$this->task_monitor($task_id,$error);
            if (WPvivid_taskmanager::get_task($task_id) !== false)
            {
                if ($this->wpvivid_log->log_file_handle == false)
                {
                    $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id, 'log_file_name'));
                }

                $status = WPvivid_taskmanager::get_backup_task_status($task_id);

                if ($status['str'] == 'running' || $status['str'] == 'error' || $status['str'] == 'no_responds')
                {
                    $options=WPvivid_Setting::get_option('wpvivid_common_setting');
                    if(isset($options['max_execution_time']))
                    {
                        $limit=$options['max_execution_time'];
                    }
                    else
                    {
                        $limit=WPVIVID_MAX_EXECUTION_TIME;
                    }

                    if(isset($options['max_resume_count']))
                    {
                        $max_resume_count=$options['max_resume_count'];
                    }
                    else
                    {
                        $max_resume_count=WPVIVID_RESUME_RETRY_TIMES;
                    }
                    $time_spend = time() - $status['timeout'];
                    $time_start = time() - $status['start_time'];
                    $time_min=min($limit, 120);
                    if ($time_spend >= $limit)
                    {
                        //time out
                        $status['resume_count']++;

                        if ($status['resume_count'] > $max_resume_count)
                        {
                            $message = __('Too many resumption attempts.', 'wpvivid');
                            $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                            do_action('wpvivid_handle_backup_failed', $task, true);
                        } else {
                            $this->check_cancel_backup($task_id);
                            $message = 'Task timed out.';
                            if ($this->add_resume_event($task_id))
                            {
                                WPvivid_taskmanager::update_backup_task_status($task_id, false, 'wait_resume', false, $status['resume_count']);
                            } else {
                                $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                                do_action('wpvivid_handle_backup_failed', $task, true);
                            }
                        }
                        if ($this->wpvivid_log)
                            $this->wpvivid_log->WriteLog($message, 'error');
                    }
                    else if($time_start>=$time_min)
                    {
                        $status['resume_count']++;
                        if ($status['resume_count'] > $max_resume_count)
                        {
                            $message = __('Too many resumption attempts.', 'wpvivid');
                            if ($error !== false)
                            {
                                $message.= 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                            }
                            $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                            do_action('wpvivid_handle_backup_failed', $task ,true);
                        } else {
                            $this->check_cancel_backup($task_id);
                            $message = 'Task timed out (WebHosting).';
                            if ($this->add_resume_event($task_id))
                            {
                                WPvivid_taskmanager::update_backup_task_status($task_id, false, 'wait_resume', false, $status['resume_count']);
                            } else {
                                $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                                do_action('wpvivid_handle_backup_failed', $task, true);
                            }
                        }
                        if ($this->wpvivid_log)
                            $this->wpvivid_log->WriteLog($message, 'error');
                    }
                    else
                    {
                        $status['resume_count']++;
                        if ($status['resume_count'] > $max_resume_count)
                        {
                            $message = __('Too many resumption attempts.', 'wpvivid');
                            if ($error !== false)
                            {
                                $message.= 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                            }
                            $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                            do_action('wpvivid_handle_backup_failed', $task ,true);
                        } else {
                            $this->check_cancel_backup($task_id);
                            $message = 'Task timed out (WebHosting).';
                            if ($this->add_resume_event($task_id))
                            {
                                WPvivid_taskmanager::update_backup_task_status($task_id, false, 'wait_resume', false, $status['resume_count']);
                            } else {
                                $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                                do_action('wpvivid_handle_backup_failed', $task, true);
                            }
                        }
                        if ($this->wpvivid_log)
                            $this->wpvivid_log->WriteLog($message, 'error');
                    }
                    /*
                    else
                    {
                        if ($status['str'] != 'error')
                        {
                            if ($error !== false)
                            {
                                $message = 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                            } else {
                                $message = __('Backup timed out. Please set the value of PHP script execution timeout to '.$time_start.' in plugin settings.', 'wpvivid');
                            }
                            WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                        }
                        $task = WPvivid_taskmanager::get_task($task_id);
                        do_action('wpvivid_handle_backup_failed', $task, false);
                    }
                    */
                }
            }
            die();
        }
    }
    public function deal_task_error($task_id,$error_type,$error)
    {
        $message = 'An '.$error_type.' has occurred. class:'.get_class($error).';msg:'.$error->getMessage().';code:'.$error->getCode().';line:'.$error->getLine().';in_file:'.$error->getFile().';';
        error_log($message);
        $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'error',false,false,$message);
        $this->wpvivid_log->WriteLog($message,'error');

        do_action('wpvivid_handle_backup_failed',$task, false);
    }
    /**
     * update time limit.
     *
     * @var string $task_id
     *
     * @var int $second
     *
     * @since 0.9.1
     */
    public function set_time_limit($task_id,$second=0)
    {
        if($second==0)
        {
            $options=WPvivid_Setting::get_option('wpvivid_common_setting');
            if(isset($options['max_execution_time']))
            {
                $second=$options['max_execution_time'];
            }
            else
            {
                $second=WPVIVID_MAX_EXECUTION_TIME;
            }
        }
        WPvivid_taskmanager::update_backup_task_status($task_id,false,'',true);
        @set_time_limit($second);
    }
    /**
     * Watch task status.
     *
     * @var string $task_id
     *
     * @var array|false $error
     *
     * @since 0.9.1
     */
    public function task_monitor($task_id)
    {
        if(WPvivid_taskmanager::get_task($task_id)!==false)
        {
            if($this->wpvivid_log->log_file_handle==false)
            {
                $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id,'log_file_name'));
            }

            $status=WPvivid_taskmanager::get_backup_task_status($task_id);

            if($status['str']=='running'||$status['str']=='error'||$status['str']=='no_responds')
            {
                $options=WPvivid_Setting::get_option('wpvivid_common_setting');
                if(isset($options['max_execution_time']))
                {
                    $limit=$options['max_execution_time'];
                }
                else
                {
                    $limit=WPVIVID_MAX_EXECUTION_TIME;
                }
                $time_spend=time()-$status['timeout'];

                if($time_spend>=$limit)
                {
                    //time out
                    if(isset($options['max_resume_count']))
                    {
                        $max_resume_count=$options['max_resume_count'];
                    }
                    else
                    {
                        $max_resume_count=WPVIVID_RESUME_RETRY_TIMES;
                    }
                    $status['resume_count']++;
                    if($status['resume_count']>$max_resume_count)
                    {
                        $message=__('Too many resumption attempts.', 'wpvivid');
                        $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'error',false,$status['resume_count'],$message);
                        WPvivid_error_log::create_error_log($this->wpvivid_log->log_file);
                        do_action('wpvivid_handle_backup_failed',$task, true);
                    }
                    else
                    {
                        $this->check_cancel_backup($task_id);
                        $message=__('Task timed out.', 'wpvivid');
                        if($this->add_resume_event($task_id))
                        {
                            WPvivid_taskmanager::update_backup_task_status($task_id,false,'wait_resume',false,$status['resume_count']);
                        }
                        else
                        {
                            $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'error',false,$status['resume_count'],$message);
                            do_action('wpvivid_handle_backup_failed',$task, true);
                        }
                    }
                    if($this->wpvivid_log)
                        $this->wpvivid_log->WriteLog($message,'error');
                }
                else {
                    $time_spend=time()-$status['run_time'];
                    if($time_spend>180)
                    {
                        $this->check_cancel_backup($task_id);
                        $this->wpvivid_log->WriteLog('Not responding for a long time.','notice');
                        WPvivid_taskmanager::update_backup_task_status($task_id,false,'no_responds',false,$status['resume_count']);
                        $this->add_monitor_event($task_id);
                    }
                    else{
                        $this->add_monitor_event($task_id);
                    }
                }
            }
            else if($status['str']=='wait_resume')
            {
                $timestamp = wp_next_scheduled(WPVIVID_RESUME_SCHEDULE_EVENT,array($task_id));
                if($timestamp===false)
                {
                    if($this->wpvivid_log)
                        $this->wpvivid_log->WriteLog('Missing resume task,so we create new one.','error');
                    $message = 'Task timed out (WebHosting).';
                    if ($this->add_resume_event($task_id))
                    {
                        WPvivid_taskmanager::update_backup_task_status($task_id, false, 'wait_resume', false, $status['resume_count']);
                    } else {
                        $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                        do_action('wpvivid_handle_backup_failed', $task, true);
                    }
                }
            }
        }
    }

    public function task_monitor_ex()
    {
        $tasks=WPvivid_Setting::get_tasks();
        $task_id='';
        foreach ($tasks as $task)
        {
            if($task['action']=='backup')
            {
                $status=WPvivid_taskmanager::get_backup_tasks_status($task['id']);
                if($status['str']=='completed'||$status['str']=='error')
                {
                    continue;
                }
                else
                {
                    $task_id=$task['id'];
                    break;
                }
            }
        }

        if(empty($task_id))
        {
            die();
        }

        if(WPvivid_taskmanager::get_task($task_id)!==false)
        {
            if($this->wpvivid_log->log_file_handle==false)
            {
                $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id,'log_file_name'));
            }

            $status=WPvivid_taskmanager::get_backup_task_status($task_id);

            if($status['str']=='running'||$status['str']=='error'||$status['str']=='no_responds')
            {
                $options=WPvivid_Setting::get_option('wpvivid_common_setting');
                if(isset($options['max_execution_time']))
                {
                    $limit=$options['max_execution_time'];
                }
                else
                {
                    $limit=WPVIVID_MAX_EXECUTION_TIME;
                }
                $time_spend=time()-$status['timeout'];

                if($time_spend>=$limit)
                {
                    //time out
                    if(isset($options['max_resume_count']))
                    {
                        $max_resume_count=$options['max_resume_count'];
                    }
                    else
                    {
                        $max_resume_count=WPVIVID_RESUME_RETRY_TIMES;
                    }
                    $status['resume_count']++;
                    if($status['resume_count']>$max_resume_count)
                    {
                        $message=__('Too many resumption attempts.', 'wpvivid');
                        $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'error',false,$status['resume_count'],$message);
                        WPvivid_error_log::create_error_log($this->wpvivid_log->log_file);
                        do_action('wpvivid_handle_backup_failed',$task, true);
                    }
                    else
                    {
                        $this->check_cancel_backup($task_id);
                        $message=__('Task timed out.', 'wpvivid');
                        if($this->add_resume_event($task_id))
                        {
                            WPvivid_taskmanager::update_backup_task_status($task_id,false,'wait_resume',false,$status['resume_count']);
                        }
                        else
                        {
                            $task=WPvivid_taskmanager::update_backup_task_status($task_id,false,'error',false,$status['resume_count'],$message);
                            do_action('wpvivid_handle_backup_failed',$task, true);
                        }
                    }
                    if($this->wpvivid_log)
                        $this->wpvivid_log->WriteLog($message,'error');
                }
                else {
                    $time_spend=time()-$status['run_time'];
                    if($time_spend>180)
                    {
                        $this->check_cancel_backup($task_id);
                        $this->wpvivid_log->WriteLog('Not responding for a long time.','notice');
                        WPvivid_taskmanager::update_backup_task_status($task_id,false,'no_responds',false,$status['resume_count']);
                        $this->add_monitor_event($task_id);
                    }
                    else{
                        $this->add_monitor_event($task_id);
                    }
                }
            }
            else if($status['str']=='wait_resume')
            {
                $timestamp = wp_next_scheduled(WPVIVID_RESUME_SCHEDULE_EVENT,array($task_id));
                if($timestamp===false)
                {
                    if($this->wpvivid_log)
                        $this->wpvivid_log->WriteLog('Missing resume task,so we create new one.','error');
                    $message = 'Task timed out (WebHosting).';
                    if ($this->add_resume_event($task_id))
                    {
                        WPvivid_taskmanager::update_backup_task_status($task_id, false, 'wait_resume', false, $status['resume_count']);
                    } else {
                        $task = WPvivid_taskmanager::update_backup_task_status($task_id, false, 'error', false, $status['resume_count'], $message);
                        do_action('wpvivid_handle_backup_failed', $task, true);
                    }
                }
            }
        }
    }
    /**
     * Estimate the size of files, folder, database and backup time before backing up.
     *
     * @var string $task_id
     *
     * @var string $backup_files
     *
     * @var array $backup_option
     *
     * @return array
     *
     * @since 0.9.1
     */
    public function check_backup($task_id,$backup_option)
    {
        @set_time_limit(180);
        $options=WPvivid_Setting::get_option('wpvivid_common_setting');
        if(isset($options['estimate_backup']))
        {
            if($options['estimate_backup'] == false)
            {
                $ret['alert_db']=false;
                $ret['alter_files']=false;
                $ret['alter_fcgi']=false;
                $ret['alter_big_file']=false;
                return $ret;
            }
        }

        $file_size=false;

        $check['check_file']=false;
        $check['check_db']=false;
        add_filter('wpvivid_check_backup_size',array($this, 'check_backup_size'),10,2);
        $check = apply_filters('wpvivid_check_backup_size', $check,$backup_option);
        $check_file=$check['check_file'];
        $check_db=$check['check_db'];

        $sapi_type=php_sapi_name();

        if($sapi_type=='cgi-fcgi'||$sapi_type==' fpm-fcgi')
        {
            $alter_fcgi=true;
        }
        else
        {
            $alter_fcgi=false;
        }
        if($check_db)
        {
            $db_method=new WPvivid_DB_Method();
            $ret=$db_method->check_db($alter_fcgi);
            if($ret['result']==WPVIVID_FAILED)
            {
                return $ret;
            }
        }
        else
        {
            $ret['alert_db']=false;
            $ret['db_size']=false;
        }

        $ret['alter_files']=false;
        $ret['alter_big_file']=false;
        $ret['alter_fcgi']=false;

        if($check_file)
        {
            include_once WPVIVID_PLUGIN_DIR .'/includes/class-wpvivid-backup.php';
            $task=new WPvivid_Backup_Task($task_id);

            $file_size=$task->get_file_info();
            $sum_size=$file_size['sum_size'];
            $sum_count=$file_size['sum_count'];
            if($alter_fcgi)
            {
                $alter_sum_size=1024*1024*1024;
                $alter_sum_count=20000;
            }
            else
            {
                $alter_sum_size=4*1024*1024*1024;
                $alter_sum_count=8*10000;
            }

            if($sum_size>$alter_sum_size||$sum_count>$alter_sum_count)
            {
                $ret['alter_files']=true;
                $ret['sum_size']=$this->formatBytes($sum_size);
                $ret['sum_count']=$sum_count;
                $ret['file_size']=$file_size;
                if($alter_fcgi)
                    $ret['alter_fcgi']=true;
            }
            else{
                $ret['sum_count']=$sum_count;
            }
            $file_size['sum']=$this->formatBytes($sum_size);
        }

        $ret['file_size']=$file_size;
        if($task_id!==false)
        {
            $task=new WPvivid_Backup_Task($task_id);
            $task->set_file_and_db_info($ret['db_size'],$file_size);
        }
        return $ret;
    }

    public function check_backup_size($check,$backup_option)
    {
        if(isset($backup_option['backup_files']))
        {
            if($backup_option['backup_files']=='files+db')
            {
                $check['check_file']=true;
                $check['check_db']=true;
            }
            else if($backup_option['backup_files']=='files')
            {
                $check['check_file']=true;
            }
            else if($backup_option['backup_files']=='db')
            {
                $check['check_db']=true;
            }
        }
        return $check;
    }

    /**
     * Add a backup task resume schedule.
     *
     * @var string $task_id
     *
     * @return boolean
     *
     * @since 0.9.1
     */
    private function add_resume_event($task_id)
    {
        $resume_time=time()+WPVIVID_RESUME_INTERVAL;

        $b=wp_schedule_single_event($resume_time,WPVIVID_RESUME_SCHEDULE_EVENT,array($task_id));

        if($b===false)
        {
            $timestamp = wp_next_scheduled(WPVIVID_RESUME_SCHEDULE_EVENT,array($task_id));

            if($timestamp!==false)
            {
                $resume_time=max($resume_time,$timestamp+10*60+10);

                $b=wp_schedule_single_event($resume_time,WPVIVID_RESUME_SCHEDULE_EVENT,array($task_id));

                if($b===false)
                {
                    $this->wpvivid_log->WriteLog('Add and retry resume event failed.','notice');
                    return false;
                }
                $this->wpvivid_log->WriteLog('Retry resume event succeeded.','notice');
            }
            else
            {
                $this->wpvivid_log->WriteLog('Add resume event failed.','notice');
                return false;
            }
        }
        $this->wpvivid_log->WriteLog('Add resume event succeeded.. arg1:'.$resume_time.' arg2:'.WPVIVID_RESUME_SCHEDULE_EVENT.' arg3:'.$task_id,'notice');
        return true;
    }
    /**
     * Add a scheduled task to clear backup data.
     *
     * @var string $task_id
     *
     * @return boolean
     *
     * @since 0.9.1
     */
    public function add_clean_backing_up_data_event($task_id)
    {
        $task=WPvivid_taskmanager::get_task($task_id);
        $tasks=WPvivid_Setting::get_option('clean_task');
        $tasks[$task_id]=$task;
        WPvivid_Setting::update_option('clean_task',$tasks);

        $resume_time=time()+60;

        $b=wp_schedule_single_event($resume_time,WPVIVID_CLEAN_BACKING_UP_DATA_EVENT,array($task_id));

        if($b===false)
        {
            $timestamp = wp_next_scheduled(WPVIVID_CLEAN_BACKING_UP_DATA_EVENT,array($task_id));

            if($timestamp!==false)
            {
                $resume_time=max($resume_time,$timestamp+10*60+10);

                $b=wp_schedule_single_event($resume_time,WPVIVID_CLEAN_BACKING_UP_DATA_EVENT,array($task_id));

                if($b===false)
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
        return true;
    }
    /**
     * Add a scheduled task to clear backup record.
     *
     * @var string $task_id
     *
     * @return boolean
     *
     * @since 0.9.1
     */
    private function add_clean_backup_record_event($backup_id)
    {
        $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);
        $tasks=WPvivid_Setting::get_option('clean_task');
        $tasks[$backup_id]=$backup;
        WPvivid_Setting::update_option('clean_task',$tasks);
        $resume_time=time()+60;

        $b=wp_schedule_single_event($resume_time,WPVIVID_CLEAN_BACKUP_RECORD_EVENT,array($backup_id));

        if($b===false)
        {
            $timestamp = wp_next_scheduled(WPVIVID_CLEAN_BACKUP_RECORD_EVENT,array($backup_id));

            if($timestamp!==false)
            {
                $resume_time=max($resume_time,$timestamp+10*60+10);

                $b=wp_schedule_single_event($resume_time,WPVIVID_CLEAN_BACKUP_RECORD_EVENT,array($backup_id));

                if($b===false)
                {
                    return false;
                }
            }
            else
            {
                return false;
            }
        }
        return true;
    }
    /**
     * Add a watch task scheduled event.
     *
     * @var string $task_id
     *
     * @var int $next_time
     *
     * @return boolean
     *
     * @since 0.9.1
     */
    public function add_monitor_event($task_id,$next_time=120)
    {
        $resume_time=time()+$next_time;

        $timestamp = wp_next_scheduled(WPVIVID_TASK_MONITOR_EVENT,array($task_id));

        if($timestamp===false)
        {
            $b = wp_schedule_single_event($resume_time, WPVIVID_TASK_MONITOR_EVENT, array($task_id));
            if ($b === false)
            {
                return false;
            } else {
                return true;
            }
        }
        return true;
    }

    public function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        // Uncomment one of the following alternatives
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . '' . $units[$pow];
    }
    /**
     * check backup task canceled or not
     *
     * @var string $task_id
     *
     * @since 0.9.1
     */
    public function check_cancel_backup($task_id)
    {
        if(WPvivid_taskmanager::get_task($task_id)!==false)
        {
            $task=new WPvivid_Backup_Task($task_id);

            if($task->is_cancel_file_exist())
            {
                if($this->wpvivid_log->log_file_handle==false)
                {
                    $this->wpvivid_log->OpenLogFile(WPvivid_taskmanager::get_task_options($task_id,'log_file_name'));
                }
                $this->wpvivid_log->WriteLog('Backup cancelled.','notice');

                $task->update_status('cancel');
                //WPvivid_taskmanager::update_backup_task_status($task_id,false,'cancel',false);
                $this->add_clean_backing_up_data_event($task_id);
                WPvivid_Schedule::clear_monitor_schedule($task_id);
                WPvivid_taskmanager::delete_task($task_id);
                die();
            }
        }
    }
    private function flush($task_id)
    {
        $ret['result']='success';
        $ret['task_id']=$task_id;
        $json=json_encode($ret);
        if(!headers_sent())
        {
            header('Content-Length: '.strlen($json));
            header('Connection: close');
            header('Content-Encoding: none');
        }


        if (session_id())
            session_write_close();
        echo $json;

        if(function_exists('fastcgi_finish_request'))
        {
            fastcgi_finish_request();
        }
        else
        {
            ob_flush();
            flush();
        }
    }
    /**
     * return initialization download page data
     *
     * @var string $task_id
     *
     * @since 0.9.1
     */
    public function init_download_page()
    {
        $this->ajax_check_security();
        try {
            if (isset($_POST['backup_id']) && !empty($_POST['backup_id']) && is_string($_POST['backup_id'])) {
                $backup_id = sanitize_key($_POST['backup_id']);
                $ret = $this->init_download($backup_id);
                echo json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * prepare download backup
     *
     * Retrieve files from the server
     *
     * @var string $task_id
     *
     * @since 0.9.1
     */
    public function prepare_download_backup()
    {
        $this->ajax_check_security();
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_prepare_download_shutdown_error'));
        $id=uniqid('wpvivid-');
        $log_file_name=$id.'_download';
        $this->wpvivid_download_log->OpenLogFile($log_file_name);
        $this->wpvivid_download_log->WriteLog('Prepare download backup.','notice');
        $this->wpvivid_download_log->WriteLogHander();
        try {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id']) || !isset($_POST['file_name']) || empty($_POST['file_name']) || !is_string($_POST['file_name'])) {
                $this->end_shutdown_function=true;
                die();
            }
            $download_info = array();
            $download_info['backup_id'] = sanitize_key($_POST['backup_id']);
            //$download_info['file_name']=sanitize_file_name($_POST['file_name']);
            $download_info['file_name'] = $_POST['file_name'];
            @set_time_limit(600);
            if (session_id())
                session_write_close();

            $downloader = new WPvivid_downloader();
            $downloader->ready_download($download_info);

            $ret['result'] = 'success';
            $json = json_encode($ret);
            echo $json;
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            if($this->wpvivid_download_log){
                $this->wpvivid_download_log->WriteLog($message ,'error');
                WPvivid_error_log::create_error_log($this->wpvivid_download_log->log_file);
                $this->wpvivid_download_log->CloseFile();
            }
            else {
                $id = uniqid('wpvivid-');
                $log_file_name = $id . '_download';
                $log = new WPvivid_Log();
                $log->CreateLogFile($log_file_name, 'no_folder', 'download');
                $log->WriteLog($message, 'error');
                WPvivid_error_log::create_error_log($log->log_file);
                $log->CloseFile();
            }
            error_log($message);
            $this->end_shutdown_function=true;
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        $this->wpvivid_download_log->CloseFile();
        $this->end_shutdown_function=true;
        die();
    }

    public function deal_prepare_download_shutdown_error()
    {
        if($this->end_shutdown_function==false) {
            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true)) {
                $error = $last_error;
            } else {
                $error = false;
            }
            $ret['result'] = 'failed';
            if ($error === false) {
                $ret['error'] = 'unknown Error';
            } else {
                $ret['error'] = 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                error_log($ret['error']);
            }
            if($this->wpvivid_download_log){
                $this->wpvivid_download_log->WriteLog($ret['error'] ,'error');
                WPvivid_error_log::create_error_log($this->wpvivid_download_log->log_file);
                $this->wpvivid_download_log->CloseFile();
            }
            else {
                $id = uniqid('wpvivid-');
                $log_file_name = $id . '_download';
                $log = new WPvivid_Log();
                $log->CreateLogFile($log_file_name, 'no_folder', 'download');
                $log->WriteLog($ret['error'], 'notice');
                WPvivid_error_log::create_error_log($log->log_file);
                $log->CloseFile();
            }
            echo json_encode($ret);
            die();
        }
    }

    public function init_download($backup_id)
    {
        if(empty($backup_id))
        {
            $ret['result']=WPVIVID_SUCCESS;
            $ret['data']=array();
            return $ret;
        }
        $ret['result']=WPVIVID_SUCCESS;
        $type_list=array();
        $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);

        if($backup===false)
        {
            $ret['result']=WPVIVID_FAILED;
            $ret['error']='backup id not found';
            return $ret;
        }

        $backup_item=new WPvivid_Backup_Item($backup);
        $ret=$backup_item->get_download_backup_files($backup_id);
        if($ret['result']==WPVIVID_SUCCESS){
            $ret=$backup_item->get_download_progress($backup_id, $ret['files']);
            WPvivid_taskmanager::update_download_cache($backup_id,$ret);
        }

        return $ret;
    }

    /**
     * delete download file
     *
     * @since 0.9.1
     */
    public function delete_download()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id']) || !isset($_POST['file_name']) || empty($_POST['file_name']) || !is_string($_POST['file_name'])) {
                die();
            }
            $download_info = array();
            $download_info['backup_id'] = sanitize_key($_POST['backup_id']);
            //$download_info['file_name']=sanitize_file_name($_POST['file_name']);
            $download_info['file_name'] = $_POST['file_name'];

            $files = array();
            $backup = WPvivid_Backuplist::get_backup_by_id($download_info['backup_id']);
            if (!$backup) {
                $json['result'] = 'failed';
                $json['error'] = __('Retrieving the backup(s) information failed while deleting the selected backup(s). Please try again later.', 'wpvivid');
                json_encode($json);
                die();
            }
            if ($backup['backup']['ismerge'] == 1) {
                $backup_files = $backup['backup']['data']['meta']['files'];
                foreach ($backup_files as $file) {
                    if ($file['file_name'] == $download_info['file_name']) {
                        $files[] = $file;
                        break;
                    }
                }
            } else {
                foreach ($backup['backup']['data']['type'] as $type) {
                    $backup_files = $type['files'];
                    foreach ($backup_files as $file) {
                        if ($file['file_name'] == $download_info['file_name']) {
                            $files[] = $file;
                            break;
                        }
                    }
                }
            }

            $download_dir = $backup['local']['path'];

            foreach ($files as $file) {
                $download_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $download_dir . DIRECTORY_SEPARATOR . $file['file_name'];
                if (file_exists($download_path)) {
                    unlink($download_path);
                }
            }
            $ret = $this->init_download($_POST['backup_id']);
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * download backup file
     *
     * @since 0.9.1
     */
    public function download_backup()
    {
        $this->ajax_check_security();
        try {
            if (isset($_REQUEST['backup_id']) && isset($_REQUEST['file_name'])) {
                if (!empty($_REQUEST['backup_id']) && is_string($_REQUEST['backup_id'])) {
                    $backup_id = sanitize_key($_REQUEST['backup_id']);
                } else {
                    die();
                }

                if (!empty($_REQUEST['file_name']) && is_string($_REQUEST['file_name'])) {
                    //$file_name=sanitize_file_name($_REQUEST['file_name']);
                    $file_name = $_REQUEST['file_name'];
                } else {
                    die();
                }

                $cache = WPvivid_taskmanager::get_download_cache($backup_id);
                if ($cache === false) {
                    $this->init_download($backup_id);
                    $cache = WPvivid_taskmanager::get_download_cache($backup_id);
                }
                $path = false;
                if (array_key_exists($file_name, $cache['files'])) {
                    if ($cache['files'][$file_name]['status'] == 'completed') {
                        $path = $cache['files'][$file_name]['download_path'];
                    }
                }
                if ($path !== false) {
                    if (file_exists($path)) {
                        if (session_id())
                            session_write_close();

                        $size = filesize($path);
                        if (!headers_sent()) {
                            header('Content-Description: File Transfer');
                            header('Content-Type: application/zip');
                            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                            header('Cache-Control: must-revalidate');
                            header('Content-Length: ' . $size);
                            header('Content-Transfer-Encoding: binary');
                        }

                        if ($size < 1024 * 1024 * 60) {
                            ob_end_clean();
                            readfile($path);
                            exit;
                        } else {
                            ob_end_clean();
                            $download_rate = 1024 * 10;
                            $file = fopen($path, "r");
                            while (!feof($file)) {
                                @set_time_limit(20);
                                // send the current file part to the browser
                                print fread($file, round($download_rate * 1024));
                                // flush the content to the browser
                                flush();
                                // sleep one second
                                sleep(1);
                            }
                            fclose($file);
                            exit;
                        }
                    }
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        $admin_url = admin_url();
        echo __('file not found. please <a href="'.$admin_url.'admin.php?page=WPvivid">retry</a> again.');
        die();
    }
    /**
     * List backup record
     *
     * @since 0.9.1
     */
    public function get_backup_list()
    {
        $this->ajax_check_security('manage_options');
        try
        {
            $json['result'] = 'success';
            $html = '';
            $html = apply_filters('wpvivid_add_backup_list', $html);
            $json['html'] = $html;
            echo json_encode($json);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Delete backup record
     *
     * @since 0.9.1
     */
    public function delete_backup()
    {
        $this->ajax_check_security();
        try
        {
            if (isset($_POST['backup_id']) && !empty($_POST['backup_id']) && is_string($_POST['backup_id']) && isset($_POST['force']))
            {
                if ($_POST['force'] == 0 || $_POST['force'] == 1)
                {
                    $force_del = $_POST['force'];
                } else {
                    $force_del = 0;
                }
                $backup_id = sanitize_key($_POST['backup_id']);
                $ret = $this->delete_backup_by_id($backup_id, $force_del);
                echo json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Delete backup records
     *
     * @since 0.9.1
     */
    public function delete_backup_array()
    {
        $this->ajax_check_security();
        try
        {
            if (isset($_POST['backup_id']) && !empty($_POST['backup_id']) && is_array($_POST['backup_id']))
            {
                $backup_ids = $_POST['backup_id'];
                $ret = array();
                foreach ($backup_ids as $backup_id)
                {
                    $backup_id = sanitize_key($backup_id);
                    $ret = $this->delete_backup_by_id($backup_id);
                }
                $html = '';
                $html = apply_filters('wpvivid_add_backup_list', $html);
                $ret['html'] = $html;
                echo json_encode($ret);
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function delete_backup_by_id($backup_id,$force=0)
    {
        $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);
        if(!$backup)
        {
            $ret['result']='failed';
            $ret['error']=__('Retrieving the backup(s) information failed while deleting the selected backup(s). Please try again later.', 'wpvivid');
            return $ret;
        }

        $backup_item=new WPvivid_Backup_Item($backup);

        if($backup_item->is_lock())
        {
            if($force==0)
            {
                $ret['result']='failed';
                $ret['error']=__('Unable to delete the locked backup. Please unlock it first and try again.', 'wpvivid');
                return $ret;
            }
        }

        $files=$backup_item->get_files();

        foreach ($files as $file)
        {
            if (file_exists($file))
            {
                @unlink($file);
            }
        }

        $ret = WPvivid_Backuplist::get_backuplist_by_id($backup_id);

        if(!empty($backup['remote']))
        {
            WPvivid_Backuplist::delete_backup($backup_id);
            $files=$backup_item->get_files(false);
            foreach($backup['remote'] as $remote)
            {
                WPvivid_downloader::delete($remote,$files);
            }
        }
        else
        {
            WPvivid_Backuplist::delete_backup($backup_id);
        }

        $html = '';
        $html = apply_filters('wpvivid_add_backup_list', $html);
        $ret['html'] = $html;

        $ret['result']='success';
        return $ret;
    }

    public function delete_local_backup($backup_ids)
    {
        foreach ($backup_ids as $backup_id)
        {
            $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);
            if(!$backup)
            {
               continue;
            }

            if(array_key_exists('lock',$backup))
            {
               continue;
            }

            $files=array();
            $download_dir=$backup['local']['path'];

            $backup_item = new WPvivid_Backup_Item($backup);
            $file=$backup_item->get_files(false);
            foreach ($file as $filename) {
                $files[] = $filename;
            }

            foreach ($files as $file)
            {
                $download_path = WP_CONTENT_DIR .DIRECTORY_SEPARATOR . $download_dir . DIRECTORY_SEPARATOR . $file;
                if (file_exists($download_path))
                {
                    unlink($download_path);
                }

            }
        }
    }

    public function delete_task()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['task_id']) && !empty($_POST['task_id']) && is_string($_POST['task_id'])) {
                $task_id = sanitize_key($_POST['task_id']);
                WPvivid_taskmanager::delete_task($task_id);

                echo $json['result'] = 'success';
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Add remote storage
     *
     * @since 0.9.1
     */
    public function add_remote()
    {
        try {
            if (empty($_POST) || !isset($_POST['remote']) || !is_string($_POST['remote']) || !isset($_POST['type']) || !is_string($_POST['type'])) {
                die();
            }
            $json = $_POST['remote'];
            $json = stripslashes($json);
            $remote_options = json_decode($json, true);
            if (is_null($remote_options)) {
                die();
            }

            $remote_options['type'] = $_POST['type'];
            if ($remote_options['type'] == 'amazons3')
            {
                if(isset($remote_options['s3Path']))
                    $remote_options['s3Path'] = rtrim($remote_options['s3Path'], "/");
            }
            $ret = $this->remote_collection->add_remote($remote_options);

            if ($ret['result'] == 'success') {
                $html = '';
                $html = apply_filters('wpvivid_add_remote_storage_list', $html);
                $ret['html'] = $html;
                $pic = '';
                $pic = apply_filters('wpvivid_schedule_add_remote_pic', $pic);
                $ret['pic'] = $pic;
                $dir = '';
                $dir = apply_filters('wpvivid_get_remote_directory', $dir);
                $ret['dir'] = $dir;
                $schedule_local_remote = '';
                $schedule_local_remote = apply_filters('wpvivid_schedule_local_remote', $schedule_local_remote);
                $ret['local_remote'] = $schedule_local_remote;
                $remote_storage = '';
                $remote_storage = apply_filters('wpvivid_remote_storage', $remote_storage);
                $ret['remote_storage'] = $remote_storage;
                $remote_select_part = '';
                $remote_select_part = apply_filters('wpvivid_remote_storage_select_part', $remote_select_part);
                $ret['remote_select_part'] = $remote_select_part;
                $default = array();
                $remote_array = apply_filters('wpvivid_archieve_remote_array', $default);
                $ret['remote_array'] = $remote_array;
                $success_msg = 'You have successfully added a remote storage.';
                $ret['notice'] = apply_filters('wpvivid_add_remote_notice', true, $success_msg);
            }
            else{
                $ret['notice'] = apply_filters('wpvivid_add_remote_notice', false, $ret['error']);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * Delete remote storage
     *
     * @since 0.9.1
     */
    public function delete_remote()
    {
        try {
            $this->ajax_check_security('manage_options');
            if (empty($_POST) || !isset($_POST['remote_id']) || !is_string($_POST['remote_id'])) {
                die();
            }
            $id = sanitize_key($_POST['remote_id']);
            if (WPvivid_Setting::delete_remote_option($id)) {
                $remote_selected = WPvivid_Setting::get_user_history('remote_selected');
                if (in_array($id, $remote_selected)) {
                    WPvivid_Setting::update_user_history('remote_selected', array());
                }
                $ret['result'] = 'success';
                $html = '';
                $html = apply_filters('wpvivid_add_remote_storage_list', $html);
                $ret['html'] = $html;
                $pic = '';
                $pic = apply_filters('wpvivid_schedule_add_remote_pic', $pic);
                $ret['pic'] = $pic;
                $dir = '';
                $dir = apply_filters('wpvivid_get_remote_directory', $dir);
                $ret['dir'] = $dir;
                $schedule_local_remote = '';
                $schedule_local_remote = apply_filters('wpvivid_schedule_local_remote', $schedule_local_remote);
                $ret['local_remote'] = $schedule_local_remote;
                $remote_storage = '';
                $remote_storage = apply_filters('wpvivid_remote_storage', $remote_storage);
                $ret['remote_storage'] = $remote_storage;
            } else {
                $ret['result'] = 'failed';
                $ret['error'] = __('Fail to delete the remote storage, can not retrieve the storage infomation. Please try again.', 'wpvivid');
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }

    /**
     * Retrieve remote storage
     *
     * @since 0.9.8
     */
    public function retrieve_remote()
    {
        try {
            $this->ajax_check_security();
            if (empty($_POST) || !isset($_POST['remote_id']) || !is_string($_POST['remote_id'])) {
                die();
            }
            $id = sanitize_key($_POST['remote_id']);
            $remoteslist = WPvivid_Setting::get_all_remote_options();
            $ret['result'] = WPVIVID_FAILED;
            $ret['error'] = __('Failed to get the remote storage information. Please try again later.', 'wpvivid');
            foreach ($remoteslist as $key => $value) {
                if ($key == $id) {
                    if ($key === 'remote_selected') {
                        continue;
                    }
                    $ret = $value;
                    $ret['result'] = WPVIVID_SUCCESS;
                    break;
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * Edit remote storage
     *
     * @since 0.9.8
     */
    public function edit_remote()
    {
        $this->ajax_check_security();
        try {
            if (empty($_POST) || !isset($_POST['remote']) || !is_string($_POST['remote']) || !isset($_POST['id']) || !is_string($_POST['id']) || !isset($_POST['type']) || !is_string($_POST['type'])) {
                die();
            }
            $json = $_POST['remote'];
            $json = stripslashes($json);
            $remote_options = json_decode($json, true);
            if (is_null($remote_options)) {
                die();
            }
            $remote_options['type'] = $_POST['type'];
            if ($remote_options['type'] == 'amazons3')
            {
                if(isset($remote_options['s3Path']))
                    $remote_options['s3Path'] = rtrim($remote_options['s3Path'], "/");
            }

            $old_remote=WPvivid_Setting::get_remote_option($_POST['id']);
            foreach ($old_remote as $key=>$value)
            {
                if(isset($remote_options[$key]))
                    $old_remote[$key]=$remote_options[$key];
            }

            $ret = $this->remote_collection->update_remote($_POST['id'], $old_remote);

            if ($ret['result'] == 'success') {
                $ret['result'] = WPVIVID_SUCCESS;
                $html = '';
                $html = apply_filters('wpvivid_add_remote_storage_list', $html);
                $ret['html'] = $html;
                $pic = '';
                $pic = apply_filters('wpvivid_schedule_add_remote_pic', $pic);
                $ret['pic'] = $pic;
                $dir = '';
                $dir = apply_filters('wpvivid_get_remote_directory', $dir);
                $ret['dir'] = $dir;
                $schedule_local_remote = '';
                $schedule_local_remote = apply_filters('wpvivid_schedule_local_remote', $schedule_local_remote);
                $ret['local_remote'] = $schedule_local_remote;
                $remote_storage = '';
                $remote_storage = apply_filters('wpvivid_remote_storage', $remote_storage);
                $ret['remote_storage'] = $remote_storage;
                $remote_select_part = '';
                $remote_select_part = apply_filters('wpvivid_remote_storage_select_part', $remote_select_part);
                $ret['remote_select_part'] = $remote_select_part;
                $default = array();
                $remote_array = apply_filters('wpvivid_archieve_remote_array', $default);
                $ret['remote_array'] = $remote_array;
                $success_msg = 'You have successfully updated the account information of your remote storage.';
                $ret['notice'] = apply_filters('wpvivid_add_remote_notice', true, $success_msg);
            }
            else{
                $ret['notice'] = apply_filters('wpvivid_add_remote_notice', false, $ret['error']);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * List exist remote
     *
     * @since 0.9.1
     */
    public function list_remote()
    {
        $this->ajax_check_security('manage_options');
        try {
            $ret['result'] = 'success';
            $html = '';
            $html = apply_filters('wpvivid_add_remote_storage_list', $html);
            $ret['html'] = $html;
            $pic = '';
            $pic = apply_filters('wpvivid_schedule_add_remote_pic', $pic);
            $ret['pic'] = $pic;
            $dir = '';
            $dir = apply_filters('wpvivid_get_remote_directory', $dir);
            $ret['dir'] = $dir;
            $schedule_local_remote = '';
            $schedule_local_remote = apply_filters('wpvivid_schedule_local_remote', $schedule_local_remote);
            $ret['local_remote'] = $schedule_local_remote;
            $remote_storage = '';
            $remote_storage = apply_filters('wpvivid_remote_storage', $remote_storage);
            $ret['remote_storage'] = $remote_storage;
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * Test remote connection
     *
     * @since 0.9.1
     */
    public function test_remote_connection()
    {
        $this->ajax_check_security();
        try {
            if (empty($_POST) || !isset($_POST['remote']) || !is_string($_POST['remote']) || !isset($_POST['type']) || !is_string($_POST['type'])) {
                die();
            }
            $json = $_POST['remote'];
            $json = stripslashes($json);
            $remote_options = json_decode($json, true);
            if (is_null($remote_options)) {
                die();
            }

            $remote_options['type'] = $_POST['type'];
            $remote = $this->remote_collection->get_remote($remote_options);
            $ret = $remote->test_connect();
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }
    /**
     * Get backup schedule data
     *
     * @since 0.9.1
     */
    public function get_schedule()
    {
        $this->ajax_check_security('manage_options');
        try {
            $schedule = WPvivid_Schedule::get_schedule();
            $schedule['next_start'] = date("l, F d, Y H:i", $schedule['next_start']);
            $ret['result'] = 'success';
            $ret['data'] = $schedule;
            $ret['user_history'] = WPvivid_Setting::get_user_history('remote_selected');
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function scan_last_restore()
    {
        try
        {
            if($this->restore_data->has_restore())
            {
                $ret['has_exist_restore']=1;
            }
            else
            {
                $ret['has_exist_restore']=0;
            }

            if($this->restore_data->has_old_files())
            {
                $ret['has_old_files']=1;
            }
            else
            {
                $ret['has_old_files']=0;
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';

            $ret['has_exist_restore']=1;
            $ret['restore_error']=$message;
        }


        return $ret;
    }
    public function init_restore_page()
    {
        $this->ajax_check_security();
        try
        {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id']))
            {
                die();
            }

            $this->restore_data = new WPvivid_restore_data();
            $ret_scan_last_restore = $this->scan_last_restore();

            $backup_id = sanitize_key($_POST['backup_id']);

            $backup = WPvivid_Backuplist::get_backup_by_id($backup_id);

            $backup_item = new WPvivid_Backup_Item($backup);

            $ret = $backup_item->check_backup_files();

            $ret['is_migrate'] = $backup_item->check_migrate_file();

            if ($backup_item->get_backup_type() == 'Upload' || $backup_item->get_backup_type() == 'Migration')
            {
                $is_display = $backup_item->is_display_migrate_option();
                if($is_display === true)
                {
                    $ret['is_migrate_ui'] = 1;
                }
                else {
                    $ret['is_migrate_ui'] = 0;
                }
            } else {
                $ret['is_migrate_ui'] = 0;
            }



            $ret['skip_backup_old_site'] = 1;
            $ret['skip_backup_old_database'] = 1;

            $ret = array_merge($ret, $ret_scan_last_restore);

            $restore_db_data = new WPvivid_RestoreDB();
            $ret['max_allow_packet_warning'] = $restore_db_data->check_max_allow_packet_ex();

            $common_setting = WPvivid_Setting::get_option('wpvivid_common_setting');
            if(isset($common_setting['restore_memory_limit']) && !empty($common_setting['restore_memory_limit'])){
                $memory_limit = $common_setting['restore_memory_limit'];
            }
            else{
                $memory_limit = WPVIVID_RESTORE_MEMORY_LIMIT;
            }

            @ini_set('memory_limit', $memory_limit);

            $memory_limit = ini_get('memory_limit');
            $unit = strtoupper(substr($memory_limit, -1));
            if ($unit == 'K')
            {
                $memory_limit_tmp = intval($memory_limit) * 1024;
            }
            else if ($unit == 'M')
            {
                $memory_limit_tmp = intval($memory_limit) * 1024 * 1024;
            }
            else if ($unit == 'G')
            {
                $memory_limit_tmp = intval($memory_limit) * 1024 * 1024 * 1024;
            }
            else{
                $memory_limit_tmp = intval($memory_limit);
            }
            if ($memory_limit_tmp < 256 * 1024 * 1024)
            {
                $ret['memory_limit_warning'] = 'memory_limit = ' . $memory_limit . ' is too small. The recommended value is 256M or higher. Too small value could result in a failure of website restore.';
            } else {
                $ret['memory_limit_warning'] = false;
            }

            if ($ret['result'] == WPVIVID_FAILED)
            {
                $this->wpvivid_handle_restore_error($ret['error'], 'Init restore page');
            }

            echo json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function get_restore_file_is_migrate()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id'])) {
                die();
            }

            $backup_id = sanitize_key($_POST['backup_id']);

            $backup = WPvivid_Backuplist::get_backup_by_id($backup_id);

            $backup_item = new WPvivid_Backup_Item($backup);

            $ret = $backup_item->check_backup_files();

            $ret['is_migrate'] =  $backup_item->check_migrate_file();

            if ($backup_item->get_backup_type() == 'Upload' || $backup_item->get_backup_type() == 'Migration')
            {
                $is_display = $backup_item->is_display_migrate_option();
                if($is_display === true){
                    $ret['is_migrate_ui'] = 1;
                }
                else{
                    $ret['is_migrate_ui'] = 0;
                }
                /*if( $ret['is_migrate']==0)
                    $ret['is_migrate_ui'] = 1;
                else
                    $ret['is_migrate_ui'] = 0;*/
            } else {
                $ret['is_migrate_ui'] = 0;
            }

            if ($ret['result'] == WPVIVID_FAILED) {
                $this->wpvivid_handle_restore_error($ret['error'], 'Init restore page');
            }

            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function delete_last_restore_data()
    {
        $this->ajax_check_security();
        try {
            $this->restore_data = new WPvivid_restore_data();
            $this->restore_data->clean_restore_data();
            $ret['result'] = 'success';
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function delete_old_files()
    {
        try {
            $this->restore_data = new WPvivid_restore_data();
            $this->restore_data->delete_old_files();
            $ret['result'] = 'success';
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Prepare backup files for restore
     *
     * @since 0.9.1
     */
    public function prepare_restore()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id'])) {
                die();
            }

            $backup_id = sanitize_key($_POST['backup_id']);

            $backup = WPvivid_Backuplist::get_backup_by_id($backup_id);

            $backup_item = new WPvivid_Backup_Item($backup);

            $ret = $backup_item->check_backup_files();

            if ($backup_item->get_backup_type() == 'Upload')
            {
                $ret['is_migrate'] = 1;
            } else {
                $ret['is_migrate'] = 0;
            }

            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Download backup files from remote server for restore
     *
     * @since 0.9.1
     */
    public function download_restore_file()
    {
        $this->ajax_check_security();
        try {
            if (!isset($_POST['backup_id']) || empty($_POST['backup_id']) || !is_string($_POST['backup_id'])
                || !isset($_POST['file_name']) || empty($_POST['file_name']) || !is_string($_POST['file_name'])) {
                die();
            }

            @set_time_limit(600);

            $backup_id = sanitize_key($_POST['backup_id']);
            //$file_name=sanitize_file_name($_POST['file_name']);
            $file_name = $_POST['file_name'];

            $file['file_name'] = $file_name;
            $file['size'] = $_POST['size'];
            $file['md5'] = $_POST['md5'];
            $backup = WPvivid_Backuplist::get_backup_by_id($backup_id);
            if (!$backup) {
                echo json_encode(array('result' => WPVIVID_FAILED, 'error' => 'backup not found'));
                die();
            }

            $backup_item = new WPvivid_Backup_Item($backup);

            $remote_option = $backup_item->get_remote();

            if ($remote_option === false) {
                echo json_encode(array('result' => WPVIVID_FAILED, 'error' => 'Retrieving the cloud storage information failed while downloading backups. Please try again later.'));
                die();
            }

            //$downloader = new WPvivid_downloader();
            //$ret = $downloader->download($file, $local_path, $remote_option);
            $download_info = array();
            $download_info['backup_id'] = sanitize_key($_POST['backup_id']);
            //$download_info['file_name']=sanitize_file_name($_POST['file_name']);
            $download_info['file_name'] = $_POST['file_name'];
            //set_time_limit(600);
            if (session_id())
                session_write_close();

            $downloader = new WPvivid_downloader();
            $downloader->ready_download($download_info);

            $ret['result'] = 'success';
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function download_restore_progress()
    {
        try
        {
            if (!isset($_POST['file_name'])) {
                die();
            }

            $file_name = $_POST['file_name'];
            $file_size = $_POST['size'];

            $task = WPvivid_taskmanager::get_download_task_v2($_POST['file_name']);

            if ($task === false)
            {
                $check_status = false;
                $backupdir=WPvivid_Setting::get_backupdir();
                $local_storage_dir = WP_CONTENT_DIR.DIRECTORY_SEPARATOR.$backupdir;
                $local_file=$local_storage_dir.DIRECTORY_SEPARATOR.$file_name;
                if(file_exists($local_file))
                {
                    if(filesize($local_file)==$file_size)
                    {
                        $check_status = true;
                    }
                }

                if($check_status){
                    $ret['result'] = WPVIVID_SUCCESS;
                    $ret['status'] = 'completed';
                }
                else {
                    $ret['result'] = WPVIVID_FAILED;
                    $ret['error'] = 'not found download file';
                    $this->wpvivid_handle_restore_error($ret['error'], 'Downloading backup file');
                }
                echo json_encode($ret);
            } else {
                $ret['result'] = WPVIVID_SUCCESS;
                $ret['status'] = $task['status'];
                $ret['log'] = $task['download_descript'];
                $ret['error'] = $task['error'];
                echo json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_handle_restore_error($error_message, $error_type)
    {
        $this->restore_data=new WPvivid_restore_data();
        $this->restore_data->delete_restore_log();
        $this->restore_data->write_log($error_type, 'error');
        $this->restore_data->write_log($error_message, 'error');
        $this->restore_data->save_error_log_to_debug();
    }

    public function wpvivid_handle_remote_storage_error($error_message, $error_type)
    {
        $id = uniqid('wpvivid-');
        $log_file_name = $id . '_add_remote';
        $log = new WPvivid_Log();
        $log->CreateLogFile($log_file_name, 'no_folder', 'Add Remote Test Connection');
        $log->WriteLog('Remote Type: '.$error_type, 'notice');
        if(isset($ret['error'])) {
            $log->WriteLog($error_message, 'notice');
        }
        WPvivid_error_log::create_error_log($log->log_file);
        $log->CloseFile();
    }

    /**
     * Start restore
     *
     * @since 0.9.1
     */
    public function restore()
    {
        $this->end_shutdown_function=false;
        register_shutdown_function(array($this,'deal_restore_shutdown_error'));
        if(!isset($_POST['backup_id'])||empty($_POST['backup_id'])||!is_string($_POST['backup_id']))
        {
            $this->end_shutdown_function=true;
            die();
        }

        $backup_id=sanitize_key($_POST['backup_id']);

        $this->restore_data=new WPvivid_restore_data();

        $restore_options=array();
        if(isset($_POST['restore_options']))
        {
            $json = stripslashes($_POST['restore_options']);
            $restore_options = json_decode($json, 1);
            if(is_null($restore_options))
            {
                $restore_options=array();
            }
        }
        try
        {
            if ($this->restore_data->has_restore())
            {
                $status = $this->restore_data->get_restore_status();

                if ($status === WPVIVID_RESTORE_ERROR)
                {
                    $ret['result'] =WPVIVID_FAILED;
                    $ret['error'] = $this->restore_data->get_restore_error();
                    $this->restore_data->delete_temp_files();
                    $this->_disable_maintenance_mode();
                    echo json_encode($ret);
                    $this->end_shutdown_function=true;
                    die();
                }
                else if ($status === WPVIVID_RESTORE_COMPLETED)
                {
                    $this->restore_data->write_log('disable maintenance mode', 'notice');
                    $this->restore_data->delete_temp_files();
                    $this->_disable_maintenance_mode();
                    echo json_encode(array('result' => 'finished'));
                    $this->end_shutdown_function=true;
                    die();
                }
            }
            else {
                $this->restore_data->init_restore_data($backup_id,$restore_options);
                $this->restore_data->write_log('init restore data', 'notice');
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            echo $message;
            $this->end_shutdown_function=true;
            die();
        }
        catch (Error $error)
        {
            $message = 'An error has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            echo $message;
            $this->end_shutdown_function=true;
            die();
        }

        try
        {
            $this->_enable_maintenance_mode();
            $restore=new WPvivid_Restore();
            $common_setting = WPvivid_Setting::get_option('wpvivid_common_setting');
            if(isset($common_setting['restore_memory_limit']) && !empty($common_setting['restore_memory_limit'])){
                $memory_limit = $common_setting['restore_memory_limit'];
            }
            else{
                $memory_limit = WPVIVID_RESTORE_MEMORY_LIMIT;
            }

            @ini_set('memory_limit', $memory_limit);
            $ret=$restore->restore();
            if($ret['result']==WPVIVID_FAILED&&$ret['error']=='A restore task is already running.')
            {
                echo json_encode(array('result'=> WPVIVID_SUCCESS));
                $this->end_shutdown_function=true;
                die();
            }
            $this->_disable_maintenance_mode();
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            $this->restore_data->delete_temp_files();
            $this->restore_data->update_error($message);
            $this->restore_data->write_log($message,'error');
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>WPVIVID_FAILED,'error'=>$message));
            $this->end_shutdown_function=true;
            die();
        }
        catch (Error $error)
        {
            $message = 'An error has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            $this->restore_data->delete_temp_files();
            $this->restore_data->update_error($message);
            $this->restore_data->write_log($message,'error');
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>WPVIVID_FAILED,'error'=>$message));
            $this->end_shutdown_function=true;
            die();
        }

        if($ret['result']==WPVIVID_FAILED)
        {
            $this->restore_data->delete_temp_files();
            $this->_disable_maintenance_mode();
        }

        echo json_encode($ret);
        $this->end_shutdown_function=true;
        die();
    }

    public function deal_restore_shutdown_error()
    {
        if($this->end_shutdown_function===false)
        {
            $last_error = error_get_last();
            if (!empty($last_error) && !in_array($last_error['type'], array(E_NOTICE,E_WARNING,E_USER_NOTICE,E_USER_WARNING,E_DEPRECATED), true))
            {
                $error = $last_error;
            } else {
                $error = false;
            }
            //$this->task_monitor($task_id,$error);

            if ($error !== false)
            {
                $message = 'type: '. $error['type'] . ', ' . $error['message'] . ' file:' . $error['file'] . ' line:' . $error['line'];
                $this->restore_data->delete_temp_files();
                $this->restore_data->update_error($message);
                $this->restore_data->write_log($message,'error');
                $this->_disable_maintenance_mode();
                echo json_encode(array('result'=>WPVIVID_FAILED,'error'=>$message));
            }
            else {
                $message = __('backup failed error unknown', 'wpvivid');
                $this->restore_data->delete_temp_files();
                $this->restore_data->update_error($message);
                $this->restore_data->write_log($message,'error');
                $this->_disable_maintenance_mode();
                echo json_encode(array('result'=>WPVIVID_FAILED,'error'=>$message));
            }

            die();
        }
    }
    /**
     * Start rollback
     *
     * @since 0.9.1
     */
    public function rollback()
    {
        $this->_enable_maintenance_mode();
        try
        {
            $rollback=new WPvivid_RollBack();
            $rollback->rollback();
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        catch (Error $error)
        {
            $message = 'An error has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        $this->_disable_maintenance_mode();
        echo json_encode(array('result'=>'finished'));
        die();


        $this->restore_data=new WPvivid_restore_data();
        if($this->restore_data->has_rollback())
        {
            $status=$this->restore_data->get_rollback_status();

            if($status === WPVIVID_RESTORE_ERROR)
            {
                $ret['result']='failed';
                $ret['error']=$this->restore_data->get_rollback_error();
                echo json_encode($ret);
                die();
            }
            else if($status === WPVIVID_RESTORE_COMPLETED)
            {
                $this->restore_data->write_rollback_log('disable maintenance mode','notice');
                $this->_disable_maintenance_mode();
                echo json_encode(array('result'=>'finished'));
                die();
            }
        }
        else
        {
            $this->restore_data->init_rollback_data();
            $this->restore_data->write_rollback_log('init restore data','notice');
            $this->_enable_maintenance_mode();
            $this->restore_data->write_rollback_log('enable maintenance mode','notice');
        }

        try
        {
            $rollback=new WPvivid_RollBack();
            $this->_disable_maintenance_mode();
            $ret=$rollback->rollback();
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);

            $this->restore_data->update_rollback_error($message);
            $this->restore_data->write_rollback_log($message,'error');
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        catch (Error $error)
        {
            $message = 'An error has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);

            $this->restore_data->update_rollback_error($message);
            $this->restore_data->write_rollback_log($message,'error');
            $this->_disable_maintenance_mode();
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }

        echo json_encode($ret);
        die();
    }
    /**
     * Get restore progress
     *
     * @since 0.9.1
     */
    public function get_restore_progress()
    {
        try {
            $this->restore_data = new WPvivid_restore_data();

            if ($this->restore_data->has_restore()) {
                $ret['result'] = 'success';
                $ret['status'] = $this->restore_data->get_restore_status();
                if ($ret['status'] == WPVIVID_RESTORE_ERROR) {
                    $this->restore_data->save_error_log_to_debug();
                }
                $ret['log'] = $this->restore_data->get_log_content();
                echo json_encode($ret);
                die();
            } else {
                $ret['result'] = 'failed';
                $ret['error'] = __('The restore file not found. Please verify the file exists.', 'wpvivid');
                echo json_encode($ret);
                die();
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
    }
    /**
     * Get rollback progress
     *
     * @since 0.9.1
     */
    public function get_rollback_progress()
    {
        try {
            $this->restore_data = new WPvivid_restore_data();

            if ($this->restore_data->has_rollback()) {
                $ret['result'] = 'success';
                $ret['status'] = $this->restore_data->get_rollback_status();
                $ret['log'] = $this->restore_data->get_rollback_log_content();
                echo json_encode($ret);
                die();
            } else {
                $ret['result'] = 'failed';
                $ret['error'] = 'The restore file not found. Please verify the file exists.';
                echo json_encode($ret);
                die();
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
    }

    public function init_filesystem()
    {
        $credentials = request_filesystem_credentials(wp_nonce_url(admin_url('admin.php')."?page=WPvivid", 'wpvivid-nonce'));

        if ( ! WP_Filesystem($credentials) )
        {
            return false;
        }
        return true;
    }

    public function _enable_maintenance_mode()
    {
        //enable maintenance mode by create the .maintenance file.
        //If your wordpress version is greater than 4.6, use the enable_maintenance_mode filter to make our ajax request pass
        $this->init_filesystem();
        global $wp_filesystem;
        $file = $wp_filesystem->abspath() . '.maintenance';
        $maintenance_string = '<?php $upgrading = ' . (time()+1200) . ';';
        $maintenance_string.='global $wp_version;';
        $maintenance_string.='$version_check=version_compare($wp_version,4.6,\'>\' );';
        $maintenance_string.='if($version_check)';
        $maintenance_string.='{';
        $maintenance_string.='function enable_maintenance_mode_filter($enable_checks,$upgrading)';
        $maintenance_string.='{';
        $maintenance_string.='if(is_admin()&&isset($_POST[\'wpvivid_restore\']))';
        $maintenance_string.='{';
        $maintenance_string.='return false;';
        $maintenance_string.='}';
        $maintenance_string.='return $enable_checks;';
        $maintenance_string.='}';
        $maintenance_string.='add_filter( \'enable_maintenance_mode\',\'enable_maintenance_mode_filter\',10, 2 );';
        $maintenance_string.='}';
        $maintenance_string.='else';
        $maintenance_string.='{';
        $maintenance_string.='if(is_admin()&&isset($_POST[\'wpvivid_restore\']))';
        $maintenance_string.='{';
        $maintenance_string.='global $upgrading;';
        $maintenance_string.='$upgrading=0;';
        $maintenance_string.='return 1;';
        $maintenance_string.='}';
        $maintenance_string.='}';
        if ($wp_filesystem->exists( $file ) )
        {
            $wp_filesystem->delete($file);
        }
        $wp_filesystem->put_contents($file, $maintenance_string, FS_CHMOD_FILE);
    }

    public function _disable_maintenance_mode()
    {
        $this->init_filesystem();
        global $wp_filesystem;
        $file = $wp_filesystem->abspath() . '.maintenance';
        if ($wp_filesystem->exists( $file ))
        {
            $wp_filesystem->delete($file);
        }
    }

    public function deal_restore_error($error_type,$error)
    {
        $message = 'A '.$error_type.' has occurred. class:'.get_class($error).';msg:'.$error->getMessage().';code:'.$error->getCode().';line:'.$error->getLine().';in_file:'.$error->getFile().';';
        error_log($message);
        echo $message;
    }

    public function update_last_backup_task($task)
    {
        WPvivid_Setting::update_option('wpvivid_last_msg',$task);
    }
    /**
     * Get last backup information
     *
     * @since 0.9.1
     */
    public function get_last_backup()
    {
        $this->ajax_check_security('manage_options');
        try {
            $html = '';
            $html = apply_filters('wpvivid_get_last_backup_message', $html);
            $ret['data'] = $html;
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_get_last_backup_message($html)
    {
        $html='';
        $message=WPvivid_Setting::get_last_backup_message('wpvivid_last_msg');
        if(empty($message)){
            $last_message=__('The last backup message not found.', 'wpvivid');
        }
        else{
            if($message['status']['str'] == 'completed'){
                $backup_status='Succeeded';
                $last_message=$backup_status.', '.$message['status']['start_time'].' <a onclick="wpvivid_read_log(\''.__('wpvivid_read_last_backup_log').'\', \''.$message['log_file_name'].'\');" style="cursor:pointer;">   Log</a>';
            }
            elseif($message['status']['str'] == 'error'){
                $backup_status='Failed';
                $last_message=$backup_status.', '.$message['status']['start_time'].' <a onclick="wpvivid_read_log(\''.__('wpvivid_read_last_backup_log').'\', \''.$message['log_file_name'].'\');" style="cursor:pointer;">   Log</a>';
            }
            elseif($message['status']['str'] == 'cancel'){
                $backup_status='Failed';
                $last_message=$backup_status.', '.$message['status']['start_time'].' <a onclick="wpvivid_read_log(\''.__('wpvivid_read_last_backup_log').'\', \''.$message['log_file_name'].'\');" style="cursor:pointer;">   Log</a>';
            }
            else{
                $last_message=__('The last backup message not found.', 'wpvivid');
            }
        }
        $html .= '<strong>'.__('Last Backup: ', 'wpvivid').'</strong>'.$last_message;
        return $html;
    }

    public function list_tasks()
    {
        $this->ajax_check_security('manage_options');
        try
        {
            if (isset($_POST['backup_id']))
            {
                $backup_id=sanitize_key($_POST['backup_id']);
            }
            else{
                $backup_id=false;
            }
            $ret = $this->_list_tasks($backup_id);
            $backup_success_count=WPvivid_Setting::get_option('wpvivid_backup_success_count');
            if(!empty($backup_success_count)){
                WPvivid_Setting::delete_option('wpvivid_backup_success_count');
            }

            $backup_error_array=WPvivid_Setting::get_option('wpvivid_backup_error_array');
            if(!empty($backup_error_array)){
                WPvivid_Setting::delete_option('wpvivid_backup_error_array');
            }

            echo json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function _list_tasks($backup_id){
        $tasks=WPvivid_Setting::get_tasks();
        $ret=array();
        $list_tasks=array();
        foreach ($tasks as $task)
        {
            if($task['action']=='backup')
            {
                $backup=new WPvivid_Backup_Task($task['id']);
                $list_tasks[$task['id']]=$backup->get_backup_task_info($task['id']);
                if($list_tasks[$task['id']]['task_info']['need_next_schedule']===true){
                    $timestamp = wp_next_scheduled(WPVIVID_TASK_MONITOR_EVENT,array($task['id']));

                    if($timestamp===false)
                    {
                        $this->add_monitor_event($task['id'],20);
                    }
                }
                if($list_tasks[$task['id']]['task_info']['need_update_last_task']===true){
                    $task_msg = WPvivid_taskmanager::get_task($task['id']);
                    $this->update_last_backup_task($task_msg);
                    if($task['type'] === 'Cron') {
                        //update last backup time
                        //do_action('wpvivid_update_schedule_last_time_addon');
                    }
                }
                $list_tasks[$task['id']]['progress_html'] = '<div class="action-progress-bar" id="wpvivid_action_progress_bar">
                                                <div class="action-progress-bar-percent" id="wpvivid_action_progress_bar_percent" style="height:24px;width:' . $list_tasks[$task['id']]['task_info']['backup_percent'] . '"></div>
                                             </div>
                                             <div id="wpvivid_estimate_backup_info" style="float:left; ' . $list_tasks[$task['id']]['task_info']['display_estimate_backup'] . '">
                                                <div class="backup-basic-info"><span>' . __('Database Size:', 'wpvivid') . '</span><span id="wpvivid_backup_database_size">' . $list_tasks[$task['id']]['task_info']['db_size'] . '</span></div>
                                                <div class="backup-basic-info"><span>' . __('File Size:', 'wpvivid') . '</span><span id="wpvivid_backup_file_size">' . $list_tasks[$task['id']]['task_info']['file_size'] . '</span></div>
                                             </div>
                                             <div id="wpvivid_estimate_upload_info" style="float: left;"> 
                                                <div class="backup-basic-info"><span>' . __('Total Size:', 'wpvivid') . '</span><span>' . $list_tasks[$task['id']]['task_info']['total'] . '</span></div>
                                                <div class="backup-basic-info"><span>' . __('Uploaded:', 'wpvivid') . '</span><span>' . $list_tasks[$task['id']]['task_info']['upload'] . '</span></div>
                                                <div class="backup-basic-info"><span>' . __('Speed:', 'wpvivid') . '</span><span>' . $list_tasks[$task['id']]['task_info']['speed'] . '</span></div>
                                             </div>
                                             <div style="float: left;">
                                                <div class="backup-basic-info"><span>' . __('Network Connection:', 'wpvivid') . '</span><span>' . $list_tasks[$task['id']]['task_info']['network_connection'] . '</span></div>
                                             </div>
                                             <div style="clear:both;"></div>
                                             <div style="margin-left:10px; float: left; width:100%;"><p id="wpvivid_current_doing">' . $list_tasks[$task['id']]['task_info']['descript'] . '</p></div>
                                             <div style="clear: both;"></div>
                                             <div>
                                                <div id="wpvivid_backup_cancel" class="backup-log-btn"><input class="button-primary" id="wpvivid_backup_cancel_btn" type="submit" value="' . esc_attr('Cancel', 'wpvivid') . '" style="' . $list_tasks[$task['id']]['task_info']['css_btn_cancel'] . '" /></div>
                                                <div id="wpvivid_backup_log" class="backup-log-btn"><input class="button-primary" id="wpvivid_backup_log_btn" type="submit" value="' . esc_attr('Log', 'wpvivid') . '" style="' . $list_tasks[$task['id']]['task_info']['css_btn_log'] . '" /></div>
                                             </div>
                                             <div style="clear: both;"></div>';
            }
        }
        WPvivid_taskmanager::delete_marked_task();

        $ret['backuplist_html'] = false;
        $backup_success_count=WPvivid_Setting::get_option('wpvivid_backup_success_count');
        if(!empty($backup_success_count)){
            $notice_msg = $backup_success_count.' backup tasks have been completed. Please switch to <a href="#" onclick="wpvivid_click_switch_page(\'wrap\', \'wpvivid_tab_log\', true);">Log</a> page to check the details.';
            $success_notice_html=__('<div class="notice notice-success is-dismissible inline"><p>'.$notice_msg.'</p>
                                    <button type="button" class="notice-dismiss" onclick="click_dismiss_notice(this);">
                                    <span class="screen-reader-text">Dismiss this notice.</span>
                                    </button>
                                    </div>');
            //WPvivid_Setting::delete_option('wpvivid_backup_success_count');
            $html = '';
            $html = apply_filters('wpvivid_add_backup_list', $html);
            $ret['backuplist_html'] = $html;
        }
        else {
            $success_notice_html = false;
        }
        $ret['success_notice_html'] = $success_notice_html;

        $backup_error_array=WPvivid_Setting::get_option('wpvivid_backup_error_array');
        if(!empty($backup_error_array)){
            $error_notice_html = array();
            foreach ($backup_error_array as $key => $value){
                $error_notice_html['bu_error']['task_id']=$value['task_id'];
                $error_notice_html['bu_error']['error_msg']=$value['error_msg'];
            }
            //WPvivid_Setting::delete_option('wpvivid_backup_error_array');
            $html = '';
            $html = apply_filters('wpvivid_add_backup_list', $html);
            $ret['backuplist_html'] = $html;
        }
        else{
            $error_notice_html = false;
        }
        $ret['error_notice_html'] = $error_notice_html;

        $ret['backup']['result']='success';
        $ret['backup']['data']=$list_tasks;

        $ret['download']=array();
        if($backup_id !== false && !empty($backup_id)) {
            $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);
            if($backup===false)
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['error']='backup id not found';
                return $ret;
            }
            $backup_item=new WPvivid_Backup_Item($backup);
            $ret['download']=$backup_item->update_download_page($backup_id);
        }

        $html='';
        $html=apply_filters('wpvivid_get_last_backup_message', $html);
        $ret['last_msg_html']=$html;

        $html='';
        $html=apply_filters('wpvivid_get_log_list', $html);
        $ret['log_html'] = $html['html'];
        $ret['log_count'] = $html['log_count'];

        return $ret;
    }

    public function clean_cache()
    {
        delete_option('wpvivid_download_cache');
        delete_option('wpvivid_download_task');
        WPvivid_taskmanager::delete_out_of_date_finished_task();
        WPvivid_taskmanager::delete_ready_task();
    }
    /**
     * Get backup local storage path
     *
     * @since 0.9.1
     */
    public function get_dir()
    {
        $this->ajax_check_security('manage_options');
        try {
            $dir = WPvivid_Setting::get_option('wpvivid_local_setting');

            if (!isset($dir['path'])) {
                $dir = WPvivid_Setting::set_default_local_option();
            }

            if (!is_dir(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'])) {
                @mkdir(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'], 0777, true);
                @fopen(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . '/index.html', 'x');
                $tempfile = @fopen(WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'] . '/.htaccess', 'x');
                if ($tempfile) {
                    $text = "deny from all";
                    fwrite($tempfile, $text);
                    fclose($tempfile);
                } else {
                    $ret['result'] = 'failed';
                    $ret['error'] = __('Getting backup directory failed. Please try again later.', 'wpvivid');
                }

            }

            $ret['result'] = 'success';
            $ret['path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir['path'];
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Set security lock for backup record
     *
     * @since 0.9.1
     */
    public function set_security_lock()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['backup_id']) && !empty($_POST['backup_id']) && is_string($_POST['backup_id']) && isset($_POST['lock'])) {
                $backup_id = sanitize_key($_POST['backup_id']);
                if ($_POST['lock'] == 0 || $_POST['lock'] == 1) {
                    $lock = $_POST['lock'];
                } else {
                    $lock = 0;
                }

                $ret = WPvivid_Backuplist::set_security_lock($backup_id, $lock);
                echo json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }
    /**
     * Get Web-server disk space in use
     *
     * @since 0.9.1
     */
    public function junk_files_info()
    {
        $this->ajax_check_security();
        try {
            $ret['result'] = 'success';
            $ret['data'] = $this->_junk_files_info();
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function _junk_files_info()
    {
        try {
            $ret['log_path'] = $log_dir = $this->wpvivid_log->GetSaveLogFolder();
            $log_dir_byte = $this->GetDirectorySize($ret['log_path']);
            $ret['log_dir_size'] = $this->formatBytes($log_dir_byte);

            $dir = WPvivid_Setting::get_backupdir();
            $ret['old_files_path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . WPVIVID_DEFAULT_ROLLBACK_DIR;
            $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir;
            $ret['junk_path'] = $dir;


            $backup_dir_byte = $this->GetDirectorySize($dir);
            $ret['backup_dir_size'] = $this->formatBytes($backup_dir_byte);

            $ret['sum_size'] = $this->formatBytes($backup_dir_byte + $log_dir_byte);
        }
        catch (Exception $e)
        {
            $ret['log_path'] = $log_dir = $this->wpvivid_log->GetSaveLogFolder();
            $dir = WPvivid_Setting::get_backupdir();
            $ret['old_files_path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . WPVIVID_DEFAULT_ROLLBACK_DIR;
            $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir;
            $ret['junk_path'] = $dir;
            $ret['sum_size'] = '0';
        }
        catch (Error $e)
        {
            $ret['log_path'] = $log_dir = $this->wpvivid_log->GetSaveLogFolder();
            $dir = WPvivid_Setting::get_backupdir();
            $ret['old_files_path'] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . WPVIVID_DEFAULT_ROLLBACK_DIR;
            $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir;
            $ret['junk_path'] = $dir;
            $ret['sum_size'] = '0';
        }
        return $ret;
    }

    public function get_out_of_date_info()
    {
        $this->ajax_check_security();
        try {
            $dir = WPvivid_Setting::get_backupdir();
            $dir = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $dir;
            $ret['web_server'] = $dir;
            $ret['remote_options'] = WPvivid_Setting::get_remote_options();

            $info = WPvivid_Backuplist::get_out_of_date_backuplist_info(WPvivid_Setting::get_max_backup_count());
            $ret['info'] = $info;
            $ret['info']['size'] = $this->formatBytes($info['size']);

            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function _get_out_of_date_info()
    {
        $dir=WPvivid_Setting::get_backupdir();
        $dir=WP_CONTENT_DIR.DIRECTORY_SEPARATOR. $dir;
        $ret['web_server']=$dir;
        $ret['remote_options']=WPvivid_Setting::get_remote_options();

        $info=WPvivid_Backuplist::get_out_of_date_backuplist_info(WPvivid_Setting::get_max_backup_count());
        $ret['info']=$info;
        $ret['info']['size']=$this->formatBytes($info['size']);

        return $ret;
    }

    public function clean_out_of_date_backup()
    {
        $this->ajax_check_security();
        try {
            $backup_ids = WPvivid_Backuplist::get_out_of_date_backuplist(WPvivid_Setting::get_max_backup_count());
            foreach ($backup_ids as $backup_id)
            {
                $this->delete_backup_by_id($backup_id);
            }
            $ret['result'] = 'success';
            $html = '';
            $html = apply_filters('wpvivid_add_backup_list', $html);
            $ret['html'] = $html;

            echo json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    private function GetDirectorySize($path){
        $bytes_total = 0;
        $path = realpath($path);
        if($path!==false && $path!='' && file_exists($path))
        {
            foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)) as $object){
                $bytes_total += $object->getSize();
            }
        }
        return $bytes_total;
    }

    public function clean_local_storage()
    {
        $this->ajax_check_security();

        try
        {
            if(!isset($_POST['options'])||empty($_POST['options'])||!is_string($_POST['options']))
            {
                die();
            }
            $options=$_POST['options'];
            $options =stripslashes($options);
            $options=json_decode($options,true);
            if(is_null($options))
            {
                die();
            }
            if($options['log']=='0' && $options['backup_cache']=='0' && $options['junk_files']=='0' && $options['old_files']=='0')
            {
                $ret['result']=WPVIVID_FAILED;
                $ret['msg']=__('Choose at least one type of junk files for deleting.', 'wpvivid');
                echo json_encode($ret);
                die();
            }
            $delete_files = array();
            $delete_folder=array();
            if($options['log']=='1')
            {
                $log_dir=$this->wpvivid_log->GetSaveLogFolder();
                $log_files=array();
                $temp=array();
                $this -> get_dir_files($log_files,$temp,$log_dir,array('file' => '&wpvivid-&'),array(),array(),0,false);

                foreach ($log_files as $file)
                {
                    $file_name=basename($file);
                    $id=substr ($file_name,0,21);
                    if(WPvivid_Backuplist::get_backup_by_id($id)===false)
                    {
                        $delete_files[]=$file;
                    }
                }
            }

            if($options['backup_cache']=='1')
            {
                $backup_id_list=WPvivid_Backuplist::get_has_remote_backuplist();
                $this->delete_local_backup($backup_id_list);
                WPvivid_tools::clean_junk_cache();
            }

            if($options['junk_files']=='1')
            {
                $list=WPvivid_Backuplist::get_backuplist();
                $files=array();
                foreach ($list as $backup_id => $backup_value)
                {
                    $backup=WPvivid_Backuplist::get_backup_by_id($backup_id);
                    if($backup===false)
                    {
                        continue;
                    }
                    $backup_item = new WPvivid_Backup_Item($backup);
                    $file=$backup_item->get_files(false);
                    foreach ($file as $filename){
                        $files[]=$filename;
                    }
                }

                $dir=WPvivid_Setting::get_backupdir();
                $dir=WP_CONTENT_DIR.DIRECTORY_SEPARATOR. $dir;
                $path=str_replace('/',DIRECTORY_SEPARATOR,$this->wpvivid_log->GetSaveLogFolder());
                if(substr($path, -1) == DIRECTORY_SEPARATOR) {
                    $path = substr($path, 0, -1);
                }
                $folder[]= $path;
                $except_regex['file'][]='&wpvivid-&';
                $except_regex['file'][]='&wpvivid_temp-&';
                $this -> get_dir_files($delete_files,$delete_folder,$dir,$except_regex,$files,$folder,0,false);
            }

            if($options['old_files']=='1')
            {
                $this->restore_data=new WPvivid_restore_data();
                $this->restore_data->delete_old_files();
            }

            foreach ($delete_files as $file)
            {
                if(file_exists($file))
                    @unlink($file);
            }

            foreach ($delete_folder as $folder)
            {
                if(file_exists($folder))
                    @rmdir($folder);
            }

            $ret['result']='success';
            $ret['msg']=__('The selected junk flies have been deleted.', 'wpvivid');
            $ret['data']=$this->_junk_files_info();
            $html = '';
            $html = apply_filters('wpvivid_get_log_list', $html);
            $ret['html'] = $html['html'];
            $ret['log_count'] = $html['log_count'];
            echo json_encode($ret);
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }

        die();
    }

    public function get_dir_files(&$files,&$folder,$path,$except_regex,$exclude_files=array(),$exclude_folder=array(),$exclude_file_size=0,$flag = true)
    {
        $handler=opendir($path);
        while(($filename=readdir($handler))!==false)
        {
            if($filename != "." && $filename != "..")
            {
                $dir=str_replace('/',DIRECTORY_SEPARATOR,$path.DIRECTORY_SEPARATOR.$filename);


                if(in_array($dir,$exclude_folder))
                {
                    continue;
                }
                else if(is_dir($path.DIRECTORY_SEPARATOR.$filename))
                {
                    if($except_regex!==false)
                    {
                        if($this -> regex_match($except_regex['directory'],$path.DIRECTORY_SEPARATOR.$filename,$flag)){
                            continue;
                        }
                        $folder[]=$path.DIRECTORY_SEPARATOR.$filename;
                    }else
                    {
                        $folder[]=$path.DIRECTORY_SEPARATOR.$filename;
                    }
                    $this->get_dir_files($files ,$folder, $path.DIRECTORY_SEPARATOR.$filename,$except_regex,$exclude_folder);
                }else {
                    if($except_regex===false||!$this -> regex_match($except_regex['file'] ,$path.DIRECTORY_SEPARATOR.$filename,$flag))
                    {
                        if(in_array($filename,$exclude_files))
                        {
                            continue;
                        }
                        if($exclude_file_size==0)
                        {
                            $files[] = $path.DIRECTORY_SEPARATOR.$filename;
                        }
                        else if(filesize($path.DIRECTORY_SEPARATOR.$filename)<$exclude_file_size*1024*1024)
                        {
                            $files[] = $path.DIRECTORY_SEPARATOR.$filename;
                        }
                    }
                }
            }
        }
        if($handler)
            @closedir($handler);

    }
    private function regex_match($regex_array,$filename,$flag){
        if($flag){
            if(empty($regex_array)){
                return false;
            }
            if(is_array($regex_array)){
                foreach ($regex_array as $regex)
                {
                    if(preg_match($regex,$filename))
                    {
                        return true;
                    }
                }
            }else{
                if(preg_match($regex_array,$filename))
                {
                    return true;
                }
            }
            return false;
        }else{
            if(empty($regex_array)){
                return true;
            }
            if(is_array($regex_array)){
                foreach ($regex_array as $regex)
                {
                    if(preg_match($regex,$filename))
                    {
                        return false;
                    }
                }
            }else{
                if(preg_match($regex_array,$filename))
                {
                    return false;
                }
            }
            return true;
        }
    }


    public function get_setting()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['all']) && is_bool($_POST['all'])) {
                $all = $_POST['all'];
                if (!$all) {
                    if (isset($_POST['options_name']) && is_array($_POST['options_name'])) {
                        $options_name = $_POST['options_name'];
                        $ret = WPvivid_Setting::get_setting($all, $options_name);
                        echo json_encode($ret);
                    }
                } else {
                    $options_name = array();
                    $ret = WPvivid_Setting::get_setting($all, $options_name);
                    echo json_encode($ret);
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function update_setting()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['options']) && !empty($_POST['options']) && is_string($_POST['options'])) {
                $json = $_POST['options'];
                $json = stripslashes($json);
                $options = json_decode($json, true);
                if (is_null($options)) {
                    die();
                }
                $ret = WPvivid_Setting::update_setting($options);
                echo json_encode($ret);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    function set_default_remote_storage()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (!isset($_POST['remote_storage']) || empty($_POST['remote_storage']) || !is_array($_POST['remote_storage'])) {
                $ret['result'] = WPVIVID_FAILED;
                $ret['error'] = __('Choose one storage from the list to be the default storage.', 'wpvivid');
                echo json_encode($ret);
                die();
            }
            $remote_storage = $_POST['remote_storage'];
            WPvivid_Setting::update_user_history('remote_selected', $remote_storage);
            $ret['result'] = 'success';
            $html = '';
            $html = apply_filters('wpvivid_add_remote_storage_list', $html);
            $ret['html'] = $html;
            $pic = '';
            $pic = apply_filters('wpvivid_schedule_add_remote_pic', $pic);
            $ret['pic'] = $pic;
            $dir = '';
            $dir = apply_filters('wpvivid_get_remote_directory', $dir);
            $ret['dir'] = $dir;
            $schedule_local_remote = '';
            $schedule_local_remote = apply_filters('wpvivid_schedule_local_remote', $schedule_local_remote);
            $ret['local_remote'] = $schedule_local_remote;
            $remote_storage = '';
            $remote_storage = apply_filters('wpvivid_remote_storage', $remote_storage);
            $ret['remote_storage'] = $remote_storage;
            $remote_select_part = '';
            $remote_select_part = apply_filters('wpvivid_remote_storage_select_part', $remote_select_part);
            $ret['remote_select_part'] = $remote_select_part;
            $default = array();
            $remote_array = apply_filters('wpvivid_archieve_remote_array', $default);
            $ret['remote_array'] = $remote_array;
            $success_msg = 'You have successfully changed your default remote storage.';
            $ret['notice'] = apply_filters('wpvivid_add_remote_notice', true, $success_msg);
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function check_remote_alias_exist()
    {
        $this->ajax_check_security('manage_options');
        if (!isset($_POST['remote_alias']))
        {
            $remoteslist=WPvivid_Setting::get_all_remote_options();
            foreach ($remoteslist as $key=>$value)
            {
                if(isset($value['name'])&&$value['name'] == $_POST['remote_alias'])
                {
                    $ret['result']=WPVIVID_FAILED;
                    $ret['error']="Warning: The alias already exists in storage list.";
                    echo json_encode($ret);
                    die();
                }
            }
            $ret['result']=WPVIVID_SUCCESS;
            echo json_encode($ret);
            die();
        }

        die();
    }

    function get_default_remote_storage(){
        $this->ajax_check_security('manage_options');
        try {
            $ret['result'] = 'success';
            $ret['remote_storage'] = WPvivid_Setting::get_user_history('remote_selected');
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    function get_default_remote_storage_ex(){
        $ret['result']='success';
        $ret['remote_storage']=WPvivid_Setting::get_user_history('remote_selected');
        return $ret;
    }

    public function get_general_setting()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['all']) && is_bool($_POST['all'])) {
                $all = $_POST['all'];
                if (!$all) {
                    if (isset($_POST['options_name']) && is_array($_POST['options_name'])) {
                        $options_name = $_POST['options_name'];
                        $ret['data']['setting'] = WPvivid_Setting::get_setting($all, $options_name);

                        $schedule = WPvivid_Schedule::get_schedule();
                        $schedule['next_start'] = date("l, F d, Y H:i", $schedule['next_start']);
                        $ret['result'] = 'success';
                        $ret['data']['schedule'] = $schedule;
                        $ret['user_history'] = WPvivid_Setting::get_user_history('remote_selected');
                        echo json_encode($ret);
                    }
                } else {
                    $options_name = array();
                    $ret['data']['setting'] = WPvivid_Setting::get_setting($all, $options_name);
                    $schedule = WPvivid_Schedule::get_schedule();
                    $schedule['next_start'] = date("l, F d, Y H:i", $schedule['next_start']);
                    $ret['result'] = 'success';
                    $ret['data']['schedule'] = $schedule;
                    $ret['user_history'] = WPvivid_Setting::get_user_history('remote_selected');
                    echo json_encode($ret);
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_set_general_setting($setting_data, $setting, $options)
    {
        $setting['use_temp_file'] = intval($setting['use_temp_file']);
        $setting['use_temp_size'] = intval($setting['use_temp_size']);
        $setting['exclude_file_size'] = intval($setting['exclude_file_size']);
        $setting['max_execution_time'] = intval($setting['max_execution_time']);
        $setting['max_backup_count'] = intval($setting['max_backup_count']);
        $setting['max_resume_count'] = intval($setting['max_resume_count']);

        $setting_data['wpvivid_email_setting']['send_to'][] = $setting['send_to'];
        $setting_data['wpvivid_email_setting']['always'] = $setting['always'];
        if(isset($setting['email_enable'])) {
            $setting_data['wpvivid_email_setting']['email_enable'] = $setting['email_enable'];
        }

        $setting_data['wpvivid_compress_setting']['compress_type'] = $setting['compress_type'];
        $setting_data['wpvivid_compress_setting']['max_file_size'] = $setting['max_file_size'] . 'M';
        $setting_data['wpvivid_compress_setting']['no_compress'] = $setting['no_compress'];
        $setting_data['wpvivid_compress_setting']['use_temp_file'] = $setting['use_temp_file'];
        $setting_data['wpvivid_compress_setting']['use_temp_size'] = $setting['use_temp_size'];
        $setting_data['wpvivid_compress_setting']['exclude_file_size'] = $setting['exclude_file_size'];
        $setting_data['wpvivid_compress_setting']['subpackage_plugin_upload'] = $setting['subpackage_plugin_upload'];

        $setting_data['wpvivid_local_setting']['path'] = $setting['path'];
        $setting_data['wpvivid_local_setting']['save_local'] = $options['options']['wpvivid_local_setting']['save_local'];

        $setting_data['wpvivid_common_setting']['max_execution_time'] = $setting['max_execution_time'];
        $setting_data['wpvivid_common_setting']['log_save_location'] = $setting['path'].'/wpvivid_log';
        $setting_data['wpvivid_common_setting']['max_backup_count'] = $setting['max_backup_count'];
        $setting_data['wpvivid_common_setting']['show_admin_bar'] = $setting['show_admin_bar'];
        $setting_data['wpvivid_common_setting']['show_tab_menu'] = $setting['show_tab_menu'];
        $setting_data['wpvivid_common_setting']['domain_include'] = $setting['domain_include'];
        $setting_data['wpvivid_common_setting']['estimate_backup'] = $setting['estimate_backup'];
        $setting_data['wpvivid_common_setting']['max_resume_count'] = $setting['max_resume_count'];
        $setting_data['wpvivid_common_setting']['memory_limit'] = $setting['memory_limit'].'M';
        $setting_data['wpvivid_common_setting']['restore_memory_limit'] = $setting['restore_memory_limit'].'M';
        $setting_data['wpvivid_common_setting']['migrate_size'] = $setting['migrate_size'];
        $setting_data['wpvivid_common_setting']['ismerge'] = $setting['ismerge'];
        $setting_data['wpvivid_common_setting']['db_connect_method'] = $setting['db_connect_method'];
		return $setting_data;
    }

    public function set_general_setting()
    {
        $this->ajax_check_security('manage_options');
        $ret=array();
        try
        {
            if(isset($_POST['setting'])&&!empty($_POST['setting']))
            {
                $json_setting = $_POST['setting'];
                $json_setting = stripslashes($json_setting);
                $setting = json_decode($json_setting, true);
                if (is_null($setting)){
                    die();
                }
                $ret = $this->check_setting_option($setting);
                if($ret['result']!=WPVIVID_SUCCESS)
                {
                    echo json_encode($ret);
                    die();
                }
                $options=WPvivid_Setting::get_setting(true, "");
                $setting_data = array();
                $setting_data= apply_filters('wpvivid_set_general_setting',$setting_data, $setting, $options);
                $ret['setting']=WPvivid_Setting::update_setting($setting_data);
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }

    public function set_schedule(){
        $this->ajax_check_security('manage_options');

        $ret=array();

        try{
            if(isset($_POST['schedule'])&&!empty($_POST['schedule']))
            {
                $json = $_POST['schedule'];
                $json = stripslashes($json);
                $schedule = json_decode($json, true);
                if (is_null($schedule))
                {
                    die();
                }
                $ret = $this->check_schedule_option($schedule);
                if($ret['result']!=WPVIVID_SUCCESS)
                {
                    echo json_encode($ret);
                    die();
                }
                //set_schedule_ex
                $ret=WPvivid_Schedule::set_schedule_ex($schedule);
                if($ret['result']!='success')
                {
                    echo json_encode($ret);
                    die();
                }
            }
        }
        catch (Exception $error)
        {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        echo json_encode($ret);
        die();
    }

    public function check_setting_option($data)
    {
        $ret['result']=WPVIVID_FAILED;
        if(!isset($data['max_file_size']))
        {
            $ret['error']=__('The maximum zip file size is required.', 'wpvivid');
            return $ret;
        }

        $data['max_file_size']=sanitize_text_field($data['max_file_size']);

        if(empty($data['max_file_size']) && $data['max_file_size'] != '0')
        {
            $ret['error']=__('The maximum zip file size is required.', 'wpvivid');
            return $ret;
        }

        if(!isset($data['exclude_file_size']))
        {
            $ret['error']=__('The size for excluded files is required.', 'wpvivid');
        }

        $data['exclude_file_size']=sanitize_text_field($data['exclude_file_size']);

        if(empty($data['exclude_file_size']) && $data['exclude_file_size'] != '0')
        {
            $ret['error']=__('The size for excluded files is required.', 'wpvivid');
            return $ret;
        }

        if(!isset($data['max_execution_time']))
        {
            $ret['error']=__('The maximum execution time for PHP script is required.', 'wpvivid');
        }

        $data['max_execution_time']=sanitize_text_field($data['max_execution_time']);

        if(empty($data['max_execution_time']) && $data['max_execution_time'] != '0')
        {
            $ret['error']=__('The maximum execution time for PHP script is required.', 'wpvivid');
            return $ret;
        }

        if(!isset($data['path']))
        {
            $ret['error']=__('The local storage path is required.', 'wpvivid');
        }

        $data['path']=sanitize_text_field($data['path']);

        if(empty($data['path']))
        {
            $ret['error']=__('The local storage path is required.', 'wpvivid');
            return $ret;
        }

        $data['email_enable']=sanitize_text_field($data['email_enable']);
        $data['send_to']=sanitize_text_field($data['send_to']);
        if($data['email_enable'] == '1')
        {
            if(empty($data['send_to']))
            {
                $ret['error']=__('An email address is required.', 'wpvivid');
                return $ret;
            }
        }

        if(isset($data['db_connect_method']) && $data['db_connect_method'] === 'pdo') {
            if (class_exists('PDO')) {
                $extensions = get_loaded_extensions();
                if (!array_search('pdo_mysql', $extensions)) {
                    $ret['error'] = __('The pdo_mysql extension is not detected. Please install the extension first or choose wpdb option for Database connection method.', 'wpvivid');
                    return $ret;
                }
            } else {
                $ret['error'] = __('The pdo_mysql extension is not detected. Please install the extension first or choose wpdb option for Database connection method.', 'wpvivid');
                return $ret;
            }
        }

        $ret['result']=WPVIVID_SUCCESS;
        return $ret;
    }

    public function check_schedule_option($data)
    {
        $ret['result']=WPVIVID_FAILED;

        $data['enable']=sanitize_text_field($data['enable']);
        $data['save_local_remote']=sanitize_text_field($data['save_local_remote']);

        if(!empty($data['enable'])){
            if($data['enable'] == '1'){
                if(!empty($data['save_local_remote'])){
                    if($data['save_local_remote'] == 'remote'){
                        $remote_storage=WPvivid_Setting::get_remote_options();
                        if($remote_storage == false) {
                            $ret['error']=__('There is no default remote storage configured. Please set it up first.', 'wpvivid');
                            return $ret;
                        }
                    }
                }
            }
        }

        $ret['result']=WPVIVID_SUCCESS;
        return $ret;
    }

    public function export_setting()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_REQUEST['setting']) && !empty($_REQUEST['setting']) && isset($_REQUEST['history']) && !empty($_REQUEST['history']) && isset($_REQUEST['review'])) {
                $setting = sanitize_text_field($_REQUEST['setting']);
                $history = sanitize_text_field($_REQUEST['history']);
                $review = sanitize_text_field($_REQUEST['review']);

                if ($setting == '1') {
                    $setting = true;
                } else {
                    $setting = false;
                }

                if ($history == '1') {
                    $history = true;
                } else {
                    $history = false;
                }

                if ($review == '1') {
                    $review = true;
                } else {
                    $review = false;
                }

                $backup_list = false;

                $json = WPvivid_Setting::export_setting_to_json($setting, $history, $review, $backup_list);
                if (!headers_sent()) {
                    header('Content-Disposition: attachment; filename=wpvivid_setting.json');
                    //header('Content-type: application/json');
                    header('Content-Type: application/force-download');
                    header('Content-Description: File Transfer');
                    header('Cache-Control: must-revalidate');
                    header('Content-Transfer-Encoding: binary');
                }

                echo json_encode($json);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        exit;
    }

    public function import_setting()
    {
        $this->ajax_check_security('manage_options');
        try {
            if (isset($_POST['data']) && !empty($_POST['data']) && is_string($_POST['data'])) {
                $data = $_POST['data'];
                $data = stripslashes($data);
                $json = json_decode($data, true);
                if (is_null($json)) {
                    die();
                }
                if (json_last_error() === JSON_ERROR_NONE && is_array($json) && array_key_exists('plugin', $json) && $json['plugin'] == 'WPvivid') {
                    WPvivid_Setting::import_json_to_setting($json);
                    //WPvivid_Schedule::reset_schedule();
                    do_action('wpvivid_reset_schedule');
                    $ret['result'] = 'success';
                    echo json_encode($ret);
                } else {
                    $ret['result'] = 'failed';
                    $ret['error'] = __('The selected file is not the setting file for WPvivid. Please upload the right file.', 'wpvivid');
                    echo json_encode($ret);
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function test_send_mail()
    {
        $this->ajax_check_security();
        try {
            if (isset($_POST['send_to']) && !empty($_POST['send_to']) && is_string($_POST['send_to'])) {
                $send_to = sanitize_email($_POST['send_to']);
                if (empty($send_to)) {
                    $ret['result'] = 'failed';
                    $ret['error'] = __('Invalid email address', 'wpvivid');
                    echo json_encode($ret);
                } else {
                    $subject = 'WPvivid Test Mail';
                    $body = 'This is a test mail from WPvivid backup plugin';
                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    if (wp_mail($send_to, $subject, $body, $headers) === false) {
                        $ret['result'] = 'failed';
                        $ret['error'] = __('Unable to send email. Please check the configuration of email server.', 'wpvivid');
                    } else {
                        $ret['result'] = 'success';
                    }
                    echo json_encode($ret);
                }
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function create_debug_package()
    {
        $this->ajax_check_security();
        try {
            $files = WPvivid_error_log::get_error_log();

            if (!class_exists('PclZip'))
                include_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');

            $backup_path = WPvivid_Setting::get_backupdir();
            $path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backup_path . DIRECTORY_SEPARATOR . 'wpvivid_debug.zip';

            if (file_exists($path)) {
                @unlink($path);
            }
            $archive = new PclZip($path);

            if (!empty($files)) {
                if (!$archive->add($files, PCLZIP_OPT_REMOVE_ALL_PATH)) {
                    echo __($archive->errorInfo(true) . ' <a href="' . admin_url() . 'admin.php?page=WPvivid">retry</a>.');
                    exit;
                }
            }

            $server_info = json_encode($this->get_website_info());
            $server_file_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . $backup_path . DIRECTORY_SEPARATOR . 'wpvivid_server_info.json';
            if (file_exists($server_file_path)) {
                @unlink($server_file_path);
            }
            $server_file = fopen($server_file_path, 'x');
            fclose($server_file);
            file_put_contents($server_file_path, $server_info);
            if (!$archive->add($server_file_path, PCLZIP_OPT_REMOVE_ALL_PATH)) {
                echo __($archive->errorInfo(true) . ' <a href="' . admin_url() . 'admin.php?page=WPvivid">retry</a>.');
                exit;
            }
            @unlink($server_file_path);

            if (session_id())
                session_write_close();

            $size = filesize($path);
            if (!headers_sent()) {
                header('Content-Description: File Transfer');
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="' . basename($path) . '"');
                header('Cache-Control: must-revalidate');
                header('Content-Length: ' . $size);
                header('Content-Transfer-Encoding: binary');
            }


            ob_end_clean();
            readfile($path);
            @unlink($path);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        exit;
    }

    public function get_admin_bar_setting()
    {
        return WPvivid_Setting::get_admin_bar_setting();
    }

    public function get_log_list()
    {
        $this->ajax_check_security();
        try {
            $ret['result'] = 'success';
            $html = '';
            $html = apply_filters('wpvivid_get_log_list', $html);
            $ret['html'] = $html['html'];
            $ret['log_count'] = $html['log_count'];
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_get_log_list($html)
    {
        $loglist=$this->get_log_list_ex();
        $current_num=1;
        $max_log_diaplay=20;
        $log_index=0;
        $pic_log='/admin/partials/images/Log.png';
        if(!empty($loglist['log_list']['file'])) {
            foreach ($loglist['log_list']['file'] as $value) {
                if ($current_num <= $max_log_diaplay) {
                    $log_tr_display = '';
                } else {
                    $log_tr_display = 'display: none;';
                }
                if (empty($value['time'])) {
                    $value['time'] = 'N/A';
                }
                if (empty($value['des'])) {
                    $value['des'] = 'N/A';
                }
                $value['path'] = str_replace('\\', '/', $value['path']);
                $html .= '<tr style="'.esc_attr($log_tr_display, 'wpvivid').'">
                <td class="row-title"><label for="tablecell">'.__($value['time'], 'wpvivid').'</label>
                </td>
                <td>'.__($value['des'], 'wpvivid').'</td>
                <td>'.__($value['file_name'], 'wpvivid').'</td>
                <td>
                    <a onclick="wpvivid_read_log(\''.__('wpvivid_view_log').'\', \''.__($value['path']).'\')" style="cursor:pointer;">
                    <img src="'.esc_url(WPVIVID_PLUGIN_URL.$pic_log).'" style="vertical-align:middle;">Log
                    </a>
                </td>
                </tr>';
                $log_index++;
                $current_num++;
            }
        }
        $ret['log_count']=$log_index;
        $ret['html']=$html;
        return $ret;
    }

    public function get_log_list_ex(){
        $ret['log_list']['file']=array();
        $log=new WPvivid_Log();
        $dir=$log->GetSaveLogFolder();
        $files=array();
        $handler=opendir($dir);
        $regex='#^wpvivid.*_log.txt#';
        while(($filename=readdir($handler))!==false)
        {
            if($filename != "." && $filename != "..")
            {
                if(is_dir($dir.$filename))
                {
                    continue;
                }else{
                    if(preg_match($regex,$filename))
                    {
                        $files[$filename] = $dir.$filename;
                    }
                }
            }
        }
        if($handler)
            @closedir($handler);

        foreach ($files as $file)
        {
            $handle = @fopen($file, "r");
            if ($handle)
            {
                $log_file['file_name']=basename($file);
                $log_file['path']=$file;
                $log_file['des']='';
                $log_file['time']='';
                $line = fgets($handle);
                if($line!==false)
                {
                    $pos=strpos($line,'Log created: ');
                    if($pos!==false)
                    {
                        $log_file['time']=substr ($line,$pos+strlen('Log created: '));
                    }
                }
                $line = fgets($handle);
                if($line!==false)
                {
                    $pos=strpos($line,'Type: ');
                    if($pos!==false)
                    {
                        $log_file['des']=substr ($line,$pos+strlen('Type: '));
                    }
                }

                fclose($handle);
                $ret['log_list']['file'][basename($file)]=$log_file;
            }
        }

        $ret['log_list']['file'] =$this->sort_list($ret['log_list']['file']);

        return $ret;
    }

    public function sort_list($list)
    {
        uasort ($list,function($a, $b)
        {
            if($a['time']>$b['time'])
            {
                return -1;
            }
            else if($a['time']===$b['time'])
            {
                return 0;
            }
            else
            {
                return 1;
            }
        });

        return $list;
    }

    public function view_log()
    {
        $this->ajax_check_security();
        try {
            if (isset($_POST['path']) && !empty($_POST['path']) && is_string($_POST['path'])) {
                $path = sanitize_text_field($_POST['path']);
                if (!file_exists($path)) {
                    $json['result'] = 'failed';
                    $json['error'] = __('The log not found.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $file = fopen($path, 'r');

                if (!$file) {
                    $json['result'] = 'failed';
                    $json['error'] = __('Unable to open the log file.', 'wpvivid');
                    echo json_encode($json);
                    die();
                }

                $buffer = '';
                while (!feof($file)) {
                    $buffer .= fread($file, 1024);
                }
                fclose($file);

                $json['result'] = 'success';
                $json['data'] = $buffer;
                echo json_encode($json);
            } else {
                $json['result'] = 'failed';
                $json['error'] = __('Reading the log failed. Please try again.', 'wpvivid');
                echo json_encode($json);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function get_website_info()
    {
        try {
            $version = $this->version;
            $version = apply_filters('wpvivid_display_pro_version', $version);
            $ret['result'] = 'success';
            $ret['data']['version'] = $version;
            $ret['data']['home_url'] = get_home_url();
            $ret['data']['abspath'] = ABSPATH;
            $ret['data']['wp_content_path'] = WP_CONTENT_DIR;
            $ret['data']['wp_plugin_path'] = WP_PLUGIN_DIR;
            $ret['data']['active_plugins'] = get_option('active_plugins');

            global $wp_version;
            $ret['wp_version'] = $wp_version;
            if (is_multisite()) {
                $ret['data']['multisite'] = 'enable';
            } else {
                $ret['data']['multisite'] = 'disable';
            }
            $ret['data']['web_server'] = $_SERVER["SERVER_SOFTWARE"];
            $ret['data']['php_version'] = phpversion();
            global $wpdb;
            $ret['data']['mysql_version'] = $wpdb->db_version();
            if (defined('WP_DEBUG')) {
                $ret['data']['wp_debug'] = WP_DEBUG;
            } else {
                $ret['wp_debug'] = false;
            }
            $ret['data']['language'] = get_bloginfo('language');
            $ret['data']['upload_max_filesize'] = ini_get("upload_max_filesize");

            $options = WPvivid_Setting::get_option('wpvivid_common_setting');
            if (isset($options['max_execution_time'])) {
                $limit = $options['max_execution_time'];
            } else {
                $limit = WPVIVID_MAX_EXECUTION_TIME;
            }
            ini_set('max_execution_time', $limit);


            $ret['data']['max_execution_time'] = ini_get("max_execution_time");
            $ret['data']['max_input_vars'] = ini_get("max_input_vars");
            $ret['data']['max_input_vars'] = ini_get("max_input_vars");
            $ret['data']['timezone'] = date_default_timezone_get();
            $ret['data']['OS'] = php_uname();
            $ret['data']['memory_current'] = $this->formatBytes(memory_get_usage());
            $ret['data']['memory_peak'] = $this->formatBytes(memory_get_peak_usage());
            $ret['data']['memory_limit'] = ini_get('memory_limit');
            $ret['data']['post_max_size'] = ini_get('post_max_size');
            $ret['data']['allow_url_fopen'] = ini_get('allow_url_fopen');
            $ret['data']['safe_mode'] = ini_get('safe_mode');
            $ret['data']['pcre.backtrack_limit'] = ini_get('pcre.backtrack_limit');
            $extensions = get_loaded_extensions();
            if (array_search('exif', $extensions)) {
                $ret['data']['exif'] = 'support';
            } else {
                $ret['data']['exif'] = 'not support';
            }

            if (array_search('xml', $extensions)) {
                $ret['data']['xml'] = 'support';
            } else {
                $ret['data']['xml'] = 'not support';
            }

            if (array_search('suhosin', $extensions)) {
                $ret['data']['suhosin'] = 'support';
            } else {
                $ret['data']['suhosin'] = 'not support';
            }

            if (array_search('gd', $extensions)) {
                $ret['data']['IPTC'] = 'support';
            } else {
                $ret['data']['IPTC'] = 'not support';
            }

            $ret['data']['extensions'] = $extensions;

            if (function_exists('apache_get_modules')) {
                $ret['data']['apache_modules'] = apache_get_modules();
            } else {
                $ret['data']['apache_modules'] = array();
            }

            if (array_search('pdo_mysql', $extensions)) {
                $ret['data']['pdo_mysql'] = 'support';
            } else {
                $ret['data']['pdo_mysql'] = 'not support';
            }

            if ($ret['data']['pdo_mysql'] == 'support') {
                $db_method = new WPvivid_DB_Method();
                $ret_sql_mode = $db_method->get_sql_mode();
                if ($ret_sql_mode['result'] == WPVIVID_FAILED) {
                    $ret['data']['mysql_mode'] = '';
                } else {
                    $ret['data']['mysql_mode'] = $ret_sql_mode['mysql_mode'];
                    $ret['mysql_mode'] = $ret_sql_mode['mysql_mode'];
                }
            } else {
                $ret['data']['mysql_mode'] = '';
            }
            if (!class_exists('PclZip')) include_once(ABSPATH . '/wp-admin/includes/class-pclzip.php');
            if (!class_exists('PclZip')) {
                $ret['data']['PclZip'] = 'not support';
            } else {
                $ret['data']['PclZip'] = 'support';
            }

            if (is_multisite() && !defined('MULTISITE')) {
                $prefix = $wpdb->base_prefix;
            } else {
                $prefix = $wpdb->get_blog_prefix(0);
            }

            $ret['data']['wp_prefix'] = $prefix;

            $sapi_type = php_sapi_name();

            if ($sapi_type == 'cgi-fcgi' || $sapi_type == ' fpm-fcgi') {
                $ret['data']['fast_cgi'] = 'On';
            } else {
                $ret['data']['fast_cgi'] = 'Off';
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            return array('result'=>'failed','error'=>$message);
        }
        return $ret;
    }

    public function ajax_check_security($role='administrator')
    {
        $check=is_admin()&&current_user_can($role);
        $check=apply_filters('wpvivid_ajax_check_security',$check);

        if(!$check)
        {
            die();
        }
    }

    public function wpvivid_add_backup_list($html, $list_name = 'wpvivid_backup_list', $tour = false)
    {
        $html = '';
        $backuplist=WPvivid_Backuplist::get_backuplist($list_name);
        $remote=array();
        $remote=apply_filters('wpvivid_remote_pic', $remote);

        foreach ($backuplist as $key=>$value) {
            if($value['type'] !== 'Rollback') {
                $row_style = '';
                if ($value['type'] == 'Migration' || $value['type'] == 'Upload') {
                    if ($value['type'] == 'Migration') {
                        $upload_title = 'Received Backup: ';
                    } else if ($value['type'] == 'Upload') {
                        $upload_title = 'Uploaded Backup: ';
                    }
                    $row_style = 'border: 2px solid #006799; box-sizing:border-box; -moz-box-sizing:border-box; -webkit-box-sizing:border-box;';
                } else if ($value['type'] == 'Manual' || $value['type'] == 'Cron') {
                    $row_style = '';
                    $upload_title = '';
                } else {
                    $upload_title = '';
                }

                if (empty($value['lock'])) {
                    $backup_lock = '/admin/partials/images/unlocked.png';
                    $lock_status = 'unlock';
                } else {
                    if ($value['lock'] == 0) {
                        $backup_lock = '/admin/partials/images/unlocked.png';
                        $lock_status = 'unlock';
                    } else {
                        $backup_lock = '/admin/partials/images/locked.png';
                        $lock_status = 'lock';
                    }
                }

                $remote_pic_html = '';
                $save_local_pic_y = '/admin/partials/images/storage-local.png';
                $save_local_pic_n = '/admin/partials/images/storage-local(gray).png';
                $local_title = 'Localhost';
                if ($value['save_local'] == 1 || $value['type'] == 'Migration') {
                    $remote_pic_html .= '<img  src="' . esc_url(WPVIVID_PLUGIN_URL . $save_local_pic_y) . '" style="vertical-align:middle; " title="' . $local_title . '"/>';
                } else {
                    $remote_pic_html .= '<img  src="' . esc_url(WPVIVID_PLUGIN_URL . $save_local_pic_n) . '" style="vertical-align:middle; " title="' . $local_title . '"/>';
                }
                $b_has_remote = false;
                if (is_array($remote)) {
                    foreach ($remote as $key1 => $value1) {
                        foreach ($value['remote'] as $storage_type) {
                            $b_has_remote = true;
                            if ($key1 === $storage_type['type']) {
                                $pic = $value1['selected_pic'];
                            } else {
                                $pic = $value1['default_pic'];
                            }
                        }
                        if (!$b_has_remote) {
                            $pic = $value1['default_pic'];
                        }
                        $title = $value1['title'];
                        $remote_pic_html .= '<img  src="' . esc_url(WPVIVID_PLUGIN_URL . $pic) . '" style="vertical-align:middle; " title="' . $title . '"/>';
                    }
                }
                if ($tour) {
                    $tour = false;
                    $tour_message = '<div class="wpvivid-popuptext" id="wpvivid_popup_tour">Click the button to complete website restore or migration</div>';
                    $tour_class = 'wpvivid-popup';
                } else {
                    $tour_message = '';
                    $tour_class = '';
                }

                $hide = 'hide';
                $html .= '<tr style="' . $row_style . '">
                <th class="check-column"><input name="check_backup" type="checkbox" id="' . esc_attr($key, 'wpvivid') . '" value="' . esc_attr($key, 'wpvivid') . '" onclick="wpvivid_click_check_backup(\'' . $key . '\', \'' . $list_name . '\');" /></th>
                <td class="tablelistcolumn">
                    <div style="float:left;padding:0 10px 10px 0;">
                        <div class="backuptime"><strong>' . $upload_title . '</strong>' . __(date('M d, Y H:i', $value['create_time']), 'wpvivid') . '</div>
                        <div class="common-table">
                            <span title="To lock the backup, the backup can only be deleted manually" id="wpvivid_lock_' . $key . '">
                            <img src="' . esc_url(WPVIVID_PLUGIN_URL . $backup_lock) . '" name="' . esc_attr($lock_status, 'wpvivid') . '" onclick="wpvivid_set_backup_lock(\'' . $key . '\', \'' . $lock_status . '\');" style="vertical-align:middle; cursor:pointer;"/>
                            </span>
                            <span style="margin:0;">|</span> <span>' . __('Type:', 'wpvivid') . '</span><span>' . __($value['type'], 'wpvivid') . '</span>
                            <span style="margin:0;">|</span> <span title="Backup log"><a href="#" onclick="wpvivid_read_log(\'' . __('wpvivid_view_backup_log') . '\', \'' . __($key) . '\');"><img src="' . esc_url(WPVIVID_PLUGIN_URL . '/admin/partials/images/Log.png') . '" style="vertical-align:middle;cursor:pointer;"/><span style="margin:0;">' . __('Log', 'wpvivid') . '</span></a></span>
                        </div>
                    </div>
                </td>
                <td class="tablelistcolumn">
                    <div style="float:left;padding:10px 10px 10px 0;">' . $remote_pic_html . '</div>
                </td>
                <td class="tablelistcolumn" style="min-width:100px;">
                    <div id="wpvivid_file_part_' . __($key, 'wpvivid') . '" style="float:left;padding:10px 10px 10px 0;">
                        <div style="cursor:pointer;" onclick="wpvivid_initialize_download(\'' . $key . '\', \'' . $list_name . '\');" title="Prepare to download the backup">
                            <img id="wpvivid_download_btn_' . __($key, 'wpvivid') . '" src="' . esc_url(WPVIVID_PLUGIN_URL . '/admin/partials/images/download.png') . '" style="vertical-align:middle;" /><span>' . __('Download', 'wpvivid') . '</span>
                            <div class="spinner" id="wpvivid_download_loading_' . __($key, 'wpvivid') . '" style="float:right;width:auto;height:auto;padding:10px 180px 10px 0;background-position:0 0;"></div>
                        </div>
                    </div>
                </td>
                <td class="tablelistcolumn" style="min-width:100px;">
                    <div class="' . $tour_class . '" onclick="wpvivid_popup_tour(\'' . $hide . '\');">
                        ' . $tour_message . '<div style="cursor:pointer;padding:10px 0 10px 0;" onclick="wpvivid_initialize_restore(\'' . __($key, 'wpvivid') . '\',\'' . __(date('M d, Y H:i', $value['create_time']), 'wpvivid') . '\',\'' . __($value['type'], 'wpvivid') . '\');" style="float:left;padding:10px 10px 10px 0;">
                            <img src="' . esc_url(WPVIVID_PLUGIN_URL . '/admin/partials/images/Restore.png') . '" style="vertical-align:middle;" /><span>' . __('Restore', 'wpvivid') . '</span>
                        </div>
                    </div>
                </td>
                <td class="tablelistcolumn">
                    <div class="backuplist-delete-backup" style="padding:10px 0 10px 0;">
                        <img src="' . esc_url(WPVIVID_PLUGIN_URL . '/admin/partials/images/Delete.png') . '" style="vertical-align:middle; cursor:pointer;" title="Delete the backup" onclick="wpvivid_delete_selected_backup(\'' . $key . '\', \'' . $list_name . '\');"/>
                    </div>
                </td>
            </tr>';
            }
        }
        return $html;
    }

    public function wpvivid_add_remote_storage_list($html)
    {
        $html = '';
        $remoteslist=WPvivid_Setting::get_all_remote_options();
        $default_remote_storage='';
        foreach ($remoteslist['remote_selected'] as $value) {
            $default_remote_storage=$value;
        }
        $i=1;
        foreach ($remoteslist as $key=>$value)
        {
            if($key === 'remote_selected')
            {
                continue;
            }
            if ($key === $default_remote_storage)
            {
                $check_status = 'checked';
            }
            else
            {
                $check_status='';
            }
            $storage_type = $value['type'];
            $storage_type=apply_filters('wpvivid_storage_provider_tran', $storage_type);
            $html .= '<tr>
                <td>'.__($i++, 'wpvivid').'</td>
                <td><input type="checkbox" name="remote_storage" value="'.esc_attr($key, 'wpvivid').'" '.esc_attr($check_status, 'wpvivid').' /></td>
                <td>'.__($storage_type, 'wpvivid').'</td>
                <td class="row-title"><label for="tablecell">'.__($value['name'], 'wpvivid').'</label></td>
                <td>
                    <div style="float: left;"><img src="'.esc_url(WPVIVID_PLUGIN_URL.'/admin/partials/images/Edit.png').'" onclick="click_retrieve_remote_storage(\''.__($key, 'wpvivid').'\',\''.__($value['type'], 'wpvivid').'\',\''.__($value['name'], 'wpvivid').'\'
                    );" style="vertical-align:middle; cursor:pointer;" title="Edit the remote storage"/></div>
                    <div><img src="'.esc_url(WPVIVID_PLUGIN_URL.'/admin/partials/images/Delete.png').'" onclick="wpvivid_delete_remote_storage(\''.__($key, 'wpvivid').'\'
                    );" style="vertical-align:middle; cursor:pointer;" title="Remove the remote storage"/></div>
                </td>
                </tr>';
        }
        return $html;
    }

    public function wpvivid_remote_storage($remote_storage){
        $remote_id_array = WPvivid_Setting::get_user_history('remote_selected');
        $remote_storage = false;
        foreach ($remote_id_array as $value){
            $remote_storage = true;
        }
        return $remote_storage;
    }

    public function wpvivid_add_remote_notice($notice_type, $message){
        $html = '';
        if($notice_type) {
            $html .= __('<div class="notice notice-success is-dismissible inline"><p>'.$message.'</p>
                                    <button type="button" class="notice-dismiss" onclick="click_dismiss_notice(this);">
                                    <span class="screen-reader-text">Dismiss this notice.</span>
                                    </button>
                                    </div>');

        }
        else{
            $html .= __('<div class="notice notice-error"><p>' . $message . '</p></div>');
        }
        return $html;
    }

    public function wpvivid_schedule_add_remote_pic($html){
        $html = '';
        $remoteslist=WPvivid_Setting::get_all_remote_options();
        $default_remote_storage=array();
        foreach ($remoteslist['remote_selected'] as $value) {
            $default_remote_storage[]=$value;
        }
        $remote_storage_type=array();
        foreach ($remoteslist as $key=>$value)
        {
            if(in_array($key, $default_remote_storage))
            {
                $remote_storage_type[]=$value['type'];
            }
        }

        $remote=array();
        $remote=apply_filters('wpvivid_remote_pic', $remote);
        if(is_array($remote)) {
            foreach ($remote as $key => $value) {
                $title = $value['title'];
                if (in_array($key, $remote_storage_type)) {
                    $pic = $value['selected_pic'];
                } else {
                    $pic = $value['default_pic'];
                }
                $html .= '<img  src="' . esc_url(WPVIVID_PLUGIN_URL . $pic) . '" style="vertical-align:middle; " title="' . $title . '"/>';
            }
            $html.='<img onclick="wpvivid_click_switch_page(\'wrap\', \'wpvivid_tab_remote_storage\', true);" src="'.esc_url(WPVIVID_PLUGIN_URL.'/admin/partials/images/add-storages.png').'" style="vertical-align:middle;" title="Add a storage"/>';
        }
        return $html;
    }

    public function wpvivid_schedule_local_remote($html){
        $html = '';
        $schedule=WPvivid_Schedule::get_schedule();
        $backup_local = 'checked';
        $backup_remote = '';
        if($schedule['enable'] == true)
        {
            if($schedule['backup']['remote'] === 1)
            {
                $backup_local = '';
                $backup_remote = 'checked';
            }
            else{
                $backup_local = 'checked';
                $backup_remote = '';
            }
        }
        $html .= '<fieldset>
                   <label title="">
                        <input type="radio" option="schedule" name="save_local_remote" value="local" '.$backup_local.' />
                        <span>'.__( 'Save backups on localhost (web server)', 'wpvivid' ).'</span>
                   </label><br>
                   <label title="">
                        <input type="radio" option="schedule" name="save_local_remote" value="remote" '.$backup_remote.' />
                        <span>'.__( 'Send backups to remote storage (choose this option, the local backup will be deleted after uploading to remote storage completely)', 'wpvivid' ).'</span>
                   </label>
                   <label style="display: none;">
                        <input type="checkbox" option="schedule" name="lock" value="0" />
                   </label>
                   </fieldset>';
        return $html;
    }

    public function wpvivid_get_remote_directory($out_of_date_remote){
        $out_of_date=$this->_get_out_of_date_info();
        $out_of_date_remote='There is no path for remote storage, please set it up first.';

        if($out_of_date['remote_options'] !== false)
        {
            $out_of_date_remote_temp = array();
            foreach ($out_of_date['remote_options'] as $value)
            {
                $out_of_date_remote=apply_filters('wpvivid_get_out_of_date_remote',$out_of_date_remote, $value);
                $value['type']=apply_filters('wpvivid_storage_provider_tran', $value['type']);
                $out_of_date_remote_temp[] = $value['type'].': '.$out_of_date_remote;
            }
            $out_of_date_remote = implode(',', $out_of_date_remote_temp);
        }
        return $out_of_date_remote;
    }

    public function init_remote_option()
    {
        $remoteslist=WPvivid_Setting::get_all_remote_options();
        foreach ($remoteslist as $key=>$value)
        {
            if(!array_key_exists('options',$value))
            {
                continue;
            }
            $remote = array();
            if($value['type'] === 'ftp')
            {
                $remote['host']=$value['options']['host'];
                $remote['username']=$value['options']['username'];
                $remote['password']=$value['options']['password'];
                $remote['path']=$value['options']['path'];
                $remote['name']=$value['options']['name'];
                $remote['passive']=$value['options']['passive'];
                $value['type'] = strtolower($value['type']);
                $remote['type']=$value['type'];
                $remoteslist[$key]=$remote;
            }
            elseif ($value['type'] === 'sftp')
            {
                $remote['host']=$value['options']['host'];
                $remote['username']=$value['options']['username'];
                $remote['password']=$value['options']['password'];
                $remote['path']=$value['options']['path'];
                $remote['name']=$value['options']['name'];
                $remote['port']=$value['options']['port'];
                $value['type'] = strtolower($value['type']);
                $remote['type']=$value['type'];
                $remoteslist[$key]=$remote;
            }
            elseif ($value['type'] === 'amazonS3')
            {
                $remote['classMode']='0';
                $remote['sse']='0';
                $remote['name']=$value['options']['name'];
                $remote['access']=$value['options']['access'];
                $remote['secret']=$value['options']['secret'];
                $remote['s3Path']=$value['options']['s3Path'];
                $value['type'] = strtolower($value['type']);
                $remote['type']=$value['type'];
                $remoteslist[$key]=$remote;
            }
        }
        WPvivid_Setting::update_option('wpvivid_upload_setting',$remoteslist);

        $backuplist=WPvivid_Backuplist::get_backuplist();
        foreach ($backuplist as $key=>$value)
        {
            if(is_array($value['remote']))
            {
                foreach ($value['remote'] as $remote_key=>$storage_type)
                {
                    if(!array_key_exists('options',$storage_type))
                    {
                        continue;
                    }
                    $remote = array();
                    if($storage_type['type'] === 'ftp')
                    {
                        $remote['host']=$storage_type['options']['host'];
                        $remote['username']=$storage_type['options']['username'];
                        $remote['password']=$storage_type['options']['password'];
                        $remote['path']=$storage_type['options']['path'];
                        $remote['name']=$storage_type['options']['name'];
                        $remote['passive']=$storage_type['options']['passive'];
                        $storage_type['type'] = strtolower($storage_type['type']);
                        $remote['type']=$storage_type['type'];
                    }
                    elseif ($storage_type['type'] === 'sftp')
                    {
                        $remote['host']=$storage_type['options']['host'];
                        $remote['username']=$storage_type['options']['username'];
                        $remote['password']=$storage_type['options']['password'];
                        $remote['path']=$storage_type['options']['path'];
                        $remote['name']=$storage_type['options']['name'];
                        $remote['port']=$storage_type['options']['port'];
                        $storage_type['type'] = strtolower($storage_type['type']);
                        $remote['type']=$storage_type['type'];
                    }
                    elseif ($storage_type['type'] === 'amazonS3')
                    {
                        $remote['classMode']='0';
                        $remote['sse']='0';
                        $remote['name']=$storage_type['options']['name'];
                        $remote['access']=$storage_type['options']['access'];
                        $remote['secret']=$storage_type['options']['secret'];
                        $remote['s3Path']=$storage_type['options']['s3Path'];
                        $storage_type['type'] = strtolower($storage_type['type']);
                        $remote['type']=$storage_type['type'];
                    }
                    $backuplist[$key]['remote'][$remote_key]=$remote;
                }
            }
        }
        WPvivid_Setting::update_option('wpvivid_backup_list',$backuplist);
    }

    public function need_review()
    {
        $this->ajax_check_security();
        try {
            if (isset($_POST['review']) && !empty($_POST['review']) && is_string($_POST['review'])) {
                $review = $_POST['review'];
                if ($review == 'rate-now') {
                    $review_option = 'do_not_ask';
                    echo 'https://wordpress.org/support/plugin/wpvivid-backuprestore/reviews/?filter=5';
                } elseif ($review == 'never-ask') {
                    $review_option = 'do_not_ask';
                    echo '';
                } elseif ($review == 'ask-later') {
                    $review_option = 'not';
                    WPvivid_Setting::update_option('cron_backup_count', 0);
                    echo '';
                } else {
                    $review_option = 'not';
                    echo '';
                }
                WPvivid_Setting::update_option('wpvivid_need_review', $review_option);
            }
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_send_debug_info(){
        $this->ajax_check_security();
        try {
            if (!isset($_POST['user_mail']) || empty($_POST['user_mail'])) {
                $ret['result'] = 'failed';
                $ret['error'] = __('User\'s email address is required.', 'wpvivid');
            } else {
                $pattern = '/^[a-z0-9]+([._-][a-z0-9]+)*@([0-9a-z]+\.[a-z]{2,14}(\.[a-z]{2})?)$/i';
                if (!preg_match($pattern, $_POST['user_mail'])) {
                    $ret['result'] = 'failed';
                    $ret['error'] = __('Please enter a valid email address.', 'wpvivid');
                } else {
                    $this->ajax_check_security();
                    $ret = WPvivid_mail_report::wpvivid_send_debug_info($_POST['user_mail']);
                }
            }
            echo json_encode($ret);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function get_ini_memory_limit(){
        $this->ajax_check_security();
        try {
            $memory_limit = @ini_get('memory_limit');
            echo $memory_limit;
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_switch_domain_to_folder($domain){
        $parse = parse_url($domain);
        $path = '';
        if(isset($parse['path'])) {
            $parse['path'] = str_replace('/', '_', $parse['path']);
            $parse['path'] = str_replace('.', '_', $parse['path']);
            $path = $parse['path'];
        }
        $parse['host'] = str_replace('/', '_', $parse['host']);
        $parse['host'] = str_replace('.', '_', $parse['host']);
        return $parse['host'].$path;
    }

    public function wpvivid_check_zip_valid()
    {
        return true;
    }

    public function amazons3_notice()
    {
        $this->ajax_check_security();
        try {
            $notice_message = 'init';
            WPvivid_Setting::update_option('wpvivid_amazons3_notice', $notice_message);
        }
        catch (Exception $error) {
            $message = 'An exception has occurred. class: '.get_class($error).';msg: '.$error->getMessage().';code: '.$error->getCode().';line: '.$error->getLine().';in_file: '.$error->getFile().';';
            error_log($message);
            echo json_encode(array('result'=>'failed','error'=>$message));
            die();
        }
        die();
    }

    public function wpvivid_check_type_database($is_type_db, $data){
        if(isset($data['dump_db'])){
            $is_type_db = true;
        }
        return $is_type_db;
    }

    public function hide_mainwp_tab_page(){
        WPvivid_Setting::update_option('wpvivid_hide_mwp_tab_page', true);
        $ret['result']=WPVIVID_SUCCESS;
        echo json_encode($ret);
        die();
    }

    public function hide_wp_cron_notice(){
        WPvivid_Setting::update_option('wpvivid_hide_wp_cron_notice', true);
        $ret['result']=WPVIVID_SUCCESS;
        echo json_encode($ret);
        die();
    }

    public function set_mail_subject($subject, $task){
        $subject=WPvivid_mail_report::create_subject($task);
        return $subject;
    }

    public function set_mail_body($body, $task){
        $body=WPvivid_mail_report::create_body($task);
        return $body;
    }
}
