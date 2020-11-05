<?php

include 'madeline.php';
use danog\MadelineProto\Logger;

set_time_limit(10000);
error_reporting(E_ALL);
$BASE_PATH = __DIR__ . '/../../..';
require $BASE_PATH . '/wp-load.php';
$NOW = time();
$MEDIA_PATH = '/wp-content/uploads/telepa/'.$NOW.'/';

$MANELINE_SETTINGS = [
    'logger' => [
        'logger_level' => Logger::ERROR,
        //'logger_level' => Logger::VERBOSE,
    ]
];

function get_content($message) {
    global $NOW;
    global $BASE_PATH;
    global $MEDIA_PATH;
    global $MANELINE_SETTINGS;
    $content = '';

    print(time() - $NOW . ' ' . "get_content\n");

    if (isset($message['message']))
    {
        print(time() - $NOW . ' ' . "get_content - message\n");
        $content .= $message['message'];
        $content .= "<br>\n";
    }
    if (isset($message['media'])) {
        print(time() - $NOW . ' ' . "get_content - media\n");
        if (isset($message['media']['_']))
        {
            $content .= "media:".$message['media']['_'] . '<br>';

            try {
                $MadelineProto1 = new \danog\MadelineProto\API('session.madeline', $MANELINE_SETTINGS);
                $MadelineProto1->start();
                if ($message['media']['_'] === 'messageMediaDocument') {
                    $media_id = $message['media']['document']['id'];
                    if ($media_id != '5251725918937811394') {
                        $mime_type = $message['media']['document']['mime_type'];
                        print(time() - $NOW . ' ' . "VIDEO $media_id $mime_type\n");
                        $filename = $media_id . '.mp4';
                        print(time() - $NOW . ' ' . "start download\n");
                        $output_file_name = $MadelineProto1->downloadToFile($message, $BASE_PATH . $MEDIA_PATH . $filename);
                        print(time() - $NOW . ' ' . "end download\n");
                        print(time() - $NOW . ' ' . "VIDEO $output_file_name\n");
                        $content .= '<a href="' . $MEDIA_PATH . $filename . '">' . $MEDIA_PATH . $filename . '</a><br>';
                        $content .= '<video src="' . $MEDIA_PATH . $filename . '">Проигрывание формата не поддерживается</video><br>';
                        $content .= '[video mp4="' . $MEDIA_PATH . $filename . '"]Проигрывание формата не поддерживается[/video]<br>';
                        //  [video width="1280" height="720" mp4="https://test.tvs24.ru/wp/wp-content/uploads/2020/10/Donald-Trump-compare-Joe-Biden-a-un-zombie-dans-ce-clip-de-campagne.mp4"][/video]
                    }
                }
                elseif ($message['media']['_'] === 'messageMediaPhoto') {
                    $media_id = $message['media']['photo']['id'];
                    print(time() - $NOW . ' ' . "PHOTO $media_id\n");
                    $filename = $media_id . '.jpg';
                    print(time() - $NOW . ' ' . "start download\n");
                    $output_file_name = $MadelineProto1->downloadToFile($message, $BASE_PATH . $MEDIA_PATH . $filename);
                    print(time() - $NOW . ' ' . "end download\n");
                    print(time() - $NOW . ' ' . "PHOTO $output_file_name\n");
                    $content .= "media:<img src='" . $MEDIA_PATH . $filename . "' alt=''><br>";
                } else {
                    print(time() - $NOW . ' ' . "UNKNOWN " . $message['media']['_'] . "\n");
                }
            }
            catch (Exception $e) {
                print("!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!");
                print($e);
            }
            $content .= "<br>\n";
        }
    }
    return $content;
}

global $wpdb;

$task_table_name = $wpdb->prefix . "telepa_task";
$post_table_name = $wpdb->prefix . "telepa_post";
$media_table_name = $wpdb->prefix . "telepa_media";

$tasks = $wpdb->get_results ( "SELECT * FROM $task_table_name WHERE `done`=0  ORDER BY `id` LIMIT 1");

if (count($tasks)<1) {
    exit(0);
}

$task = $tasks[0];

if (!is_dir($BASE_PATH.$MEDIA_PATH)) {
    mkdir($BASE_PATH.$MEDIA_PATH, 0777, true);
}

if (!file_exists('madeline.php')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.php');
}

$MadelineProto = new \danog\MadelineProto\API('session.madeline', $MANELINE_SETTINGS);
$MadelineProto->start();
//$MadelineProto->async(false);
$res = $MadelineProto->channels->joinChannel(['channel' => $task->channel]);
//print('RES: '); var_dump($res); print("\n");
$messages = $MadelineProto->messages->getHistory(['peer' => $task->channel, 'offset_id' => 0, 'offset_date' => 0, 'add_offset' => 0, 'limit' => $task->posts_to_load, 'max_id' => 0, 'min_id' => 0  ]);
//print('MESSAGES: '.count($messages['messages'])); print("\n");
//var_dump($messages);print("\n");
$wpdb->update( $task_table_name, ['done'=>1, 'done_at'=>current_time('mysql', 1), 'posts_loaded'=>count($messages['messages'])], ['id'=>$task->id]);

$title = 'Телеграм';

if (isset($messages['chats']) && isset($messages['chats'][0]) && isset($messages['chats'][0]['title'])) {
    $title = '"'.$messages['chats'][0]['title'].'"';
}

$GROUPED = [];
$TOREAD = [];

foreach ($messages['messages'] as $message) {
    $channel = $task->channel;
    $channel_q = esc_sql($task->channel);
    $post_id = $message['id'] + 0;
    $grouped_id = isset($message['grouped_id']) ? $message['grouped_id'] + 0 : 0;

    $posts = $wpdb->get_results("SELECT * FROM $post_table_name WHERE (channel='$channel') AND (post_id='$post_id')");
    if (count($posts)) {
        continue;
    }

    if ($grouped_id > 0) {
        if (!isset($GROUPED[$grouped_id])) {
            $GROUPED[$grouped_id] = [];
            array_push($TOREAD, $message);
        }
        else {
            array_push($GROUPED[$grouped_id], $message);
        }
    } else {
        array_push($TOREAD, $message);
        $grouped_id = 0;
    }


    continue;
}

foreach ($TOREAD as $message) {
    $post_id = $message['id'] + 0;
    $grouped_id = isset($message['grouped_id']) ? $message['grouped_id'] + 0 : 0;

    $slug = 't_' . time() . '_r_' . rand();

    $post = array(
        'comment_status' => 'closed',
        'ping_status' => 'closed',
        'post_author' => 1, // author
        'post_category' => [],
        'post_content' => '',
        //'post_date' => [ Y-m-d H:i:s ] //Время создания страницы(поста).
        //'post_date_gmt' => [ Y-m-d H:i:s ] //Время создания страницы(поста), в GMT .
        //'post_excerpt' => [ 'an excerpt' ] //Для всех ваших нужных выдержек.
        'post_name' => $slug,
        'post_status' => 'publish', // 'draft' 'pending' 'future' 'private'
        'post_title' => 'Пост № '. $message['id'] . ' из ' .$title , //Заголовок поста(записи, статьи).
        'post_type' => 'post',
        //'tags_input' => [ 'tag', 'tag', '...' ] //Для тегов.
        //'to_ping' => [ ? ] //?
        //'tax_input' => [ array( 'taxonomy_name' => array( 'term', 'term2', 'term3' ) ) ] // Поддержка для созданных таксономий.
    );

    print time()-$NOW. "  Load message ".$message['id']."\n";
    $post['post_content'] .= get_content($message);
    $wpdb->insert($post_table_name, array("created_at" => current_time('mysql', 1), "channel" => $task->channel, "post_id" => $post_id, "grouped_id" => $grouped_id));

    if ($grouped_id>0 and isset($GROUPED[$grouped_id])) {

        print (" ------- HAS GROUPED  -----\n");

        foreach ($GROUPED[$grouped_id] as $submessage) {
            print time()-$NOW. "  Load submessage ".$submessage['id']."\n";
            $post['post_content'] .= get_content($submessage);
        }
        $post_id = $submessage['id'] + 0;
        $wpdb->insert($post_table_name, array("created_at" => current_time('mysql', 1), "channel" => $task->channel, "post_id" => $post_id, "grouped_id" => $grouped_id));
    }
    else {
        print (" ------- NO GROUPED  -----\n");
    }

    $wp_error = '';
    wp_insert_post( $post, $wp_error );
}


print(time()-$NOW. "  DONE\n\n");

exit(0);

