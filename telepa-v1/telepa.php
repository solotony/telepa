<?php
/*
 * Plugin Name: Телеграм парсер
 * Plugin URI: https://solotony.com/projects/telepa/
 * Description: Парсим канал и постим в вордпресс
 * Version: 0.0.1
 * Author: Antonio Solo
 * Author URI: https://solotony.com/
 * License: Proprietary
 */


require_once(ABSPATH . 'wp-admin/includes/upgrade.php');


function telepa_plugin_activate()
{
    add_option('Activated_Telepa_Plugin', 'Telepa-Plugin-Slug');

}

register_activation_hook(__FILE__, 'telepa_plugin_activate');

// Добавим видимость пункта меню для Редакторов
function register_my_page()
{
    add_menu_page('My Page Title', 'Телеграм', 'edit_others_posts', 'my_page_slug', 'telepa_page_function', plugins_url('telepa/images/telegram.png'), 6);
}

function load_plugin()
{

    if (is_admin() && get_option('Activated_Telepa_Plugin') == 'Telepa-Plugin-Slug') {

        delete_option('Activated_Telepa_Plugin');

        /* do stuff once right after activation */
        // example: add_action( 'init', 'my_init_function' );
        telepa_install();
    }
}

add_action('admin_init', 'load_plugin');

add_action('admin_menu', 'register_my_page');
add_filter('option_page_capability_' . 'my_page_slug', 'my_page_capability');

function telepa_page_function()
{
    global $wpdb;

    $task_table_name = $wpdb->prefix . "telepa_task";
    $post_table_name = $wpdb->prefix . "telepa_post";
    $media_table_name = $wpdb->prefix . "telepa_media";

    if (isset($_POST['submit']))
    {

        $channel = $_POST['channel'];
        $posts_to_load = $_POST['posts_to_read'];
        $res = $wpdb->insert($task_table_name, array("created_at" => current_time('mysql', 1), "channel" => $channel, "posts_to_load" => $posts_to_load));
    }

    $current_user = wp_get_current_user();
    $uid = $current_user->ID;
    $fname = $current_user->first_name . " " . $current_user->last_name;
    $email = $current_user->user_email;

    $tasks = $wpdb->get_results ( "SELECT * FROM $task_table_name ORDER BY `id` DESC LIMIT 50");

    echo <<<EOT
    <div class="wrap">
        <h1>Парсинг телеграм каналов</h1>
        <form method="post">
            Канал: <input name="channel" maxlength="100"  type="text" required><br><br>
            Количество постов: <input name="posts_to_read" type="number" min="1" max="1000" required><br><br>
            <input type="submit" name="submit" id="doaction" class="button action" value="Загрузить"><br>

        </form>
        <h2>Очередь заданий на парсинг</h2>
        <table class="wp-list-table widefat">        
            <tr>
                <th>№</th>
                <th>создано</th>
                <th>канал</th>
                <th>прочитать</th>
                <th>выполнено</th>
                <th>прочитано</th>
            </tr>
EOT;

        foreach($tasks as $task){
            echo <<<EOT
            <tr>
                <th>$task->id</th>
                <th>$task->created_at</th>
                <th>$task->channel</th>
                <th>$task->posts_to_load</th>
                <th>$task->done_at</th>
                <th>$task->posts_loaded</th>
            </tr>
EOT;
        }

    echo <<<EOT
        </table>
    </div>
EOT;
}


// Изменим права
function my_page_capability($capability)
{
    return 'edit_others_posts';
}

function telepa_install()
{
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $task_table_name = $wpdb->prefix . "telepa_task";
    $post_table_name = $wpdb->prefix . "telepa_post";
    $media_table_name = $wpdb->prefix . "telepa_media";

    $sql = <<<EOT
CREATE TABLE `$task_table_name` (
`id` bigint NOT NULL AUTO_INCREMENT,
`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`channel` varchar(100) NOT NULL,
`posts_to_load` mediumint(9) NOT NULL,
`done` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
KEY `done` (`done`),
`done_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`posts_loaded` mediumint(9) NOT NULL DEFAULT '0'
) $charset_collate;
EOT;

    //print ($sql."\n");
    $res = dbDelta($sql);
    //print ($res."\n");

    $sql = <<<EOT
CREATE TABLE `$post_table_name` (
`id` bigint NOT NULL AUTO_INCREMENT,
`created_at` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`channel` varchar(100) NOT NULL,
`post_id` bigint NOT NULL,
`grouped_id` bigint DEFAULT NULL,
PRIMARY KEY (`id`),
KEY `post` (`channel`, `post_id`),
KEY `post_id` (`post_id`),
KEY `grouped_id` (`grouped_id`)
) $charset_collate;
EOT;

    //print ($sql."\n");
    $res = dbDelta($sql);
    //print ($res."\n");

    $sql = <<<EOT
CREATE TABLE `$media_table_name` (
`id` bigint NOT NULL AUTO_INCREMENT,
`created_at` bigint NOT NULL,
`time` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
`channel` varchar(100) NOT NULL,
`post_id` bigint NOT NULL,
`media_id` bigint NOT NULL,
`uploaded` tinyint(1) NOT NULL DEFAULT '0',
PRIMARY KEY (`id`),
KEY `post_id` (`channel`, `post_id`),
KEY `media_id` (`media_id`)
) $charset_collate;
EOT;

    //print ($sql."\n");
    $res = dbDelta($sql);
    //print ($res."\n");
}