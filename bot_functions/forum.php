<?php
global $bot;
$bot->add_category('forums', array(), PUBLICY);

// crons

// callbacks
$bot->add_privmsg_hook("BackupForums",                 // command key
                       "LouBot_backup_forums",        // callback function
                       true,                          // is a command PRE needet?
                       '',                            // optional regex for key
function ($bot, $data) {
  global $redis;
  if (!$redis->status()) return;
  if($bot->is_op_user($data['user'])) {
    // bot forums
    $_forums = array(
      BOT_STATISTICS_FORUM,
      BOT_SETTLERS_FORUM,
      BOT_REPORTS_FORUM,
      BOT_BLACK_FORUM,
      BOT_SURVEY_FORUM
    );
    $forums = $bot->forum->get_all_forums();

    if (is_array($forums)) foreach($forums as $forum_id => $forum) {
      if (in_array($forum['name'], $_forums)) continue;
      $file_forum = BACK_DATA . str_sanitize($forum['name']) . '.xml';
      
      $forumXML = new SimpleXMLExtended("<forum></forum>");
      $forumXML->addChild('id', $forum['id']);
      $forumXML->addChild('name')->addCData($forum['name']);
      $forumXML->addChild('lang', BOT_LANG);
      $forumThreadsXML = $forumXML->addChild('threads');
      $forum_threads = $bot->forum->get_all_forum_threads($forum_id);
      if (is_array($forum_threads)) foreach($forum_threads as $thread_id => $forum_thread) {
        $forumThreadXML = $forumThreadsXML->addChild('thread');
        $forumThreadXML->addChild('id', $forum_thread['id']);
        $forumThreadXML->addChild('title')->addCData($forum_thread['title']);
        $forumThreadXML->addChild('author_id', $forum_thread['author_id']);
        $forumThreadXML->addChild('author_name', $forum_thread['author_name']);
        $forum_posts = $bot->forum->get_all_forum_posts($forum_id, $thread_id);
        if (is_array($forum_posts)) foreach($forum_posts as $post_id => $forum_post) {
          $forumPostXML = $forumThreadXML->addChild('post');
          $forumPostXML->addCData($forum_post['message']);
          $forumPostXML->addAttribute('id', $forum_post['post_id']);
          $forumPostXML->addAttribute('author_id', $forum_post['author_id']);
          $forumPostXML->addAttribute('author_name', $forum_post['author_name']);
          $forumPostXML->addAttribute('last_change', $forum_post['last_change']);
        }
      }
      $fh = fopen($file_forum, 'w');
      fwrite($fh, $forumXML->asXML());
      fclose($fh);
    }
    $bot->add_privmsg("Forum backup done!", $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'operator');

// special functions
if (!function_exists('str_sanitize')) {
  function str_sanitize($string, $force_lowercase = true) {
      $strip = array("~", "`", "!", "@", "#", "$", "%", "^", "&", "*", "(", ")", "_", "=", "+", "[", "{", "]",
                     "}", "\\", "|", ";", ":", "\"", "'", "‘", "’", "“", "”", "–", "—",
                     "â€”", "â€“", ",", "<", ".", ">", "/", "?");
      $clean = trim(str_replace($strip, "", strip_tags($string)));
      $clean = preg_replace('/\s+/', "-", $clean);
      $clean = preg_replace("/[^\w\s\d\-_~,;:\[\]\(\]]|[\.]{2,}/", '', $clean);
      return ($force_lowercase) ?
          (function_exists('mb_strtolower')) ?
              mb_strtolower($clean, 'UTF-8') :
              strtolower($clean) :
          $clean;
  }
}

if (!class_exists('SimpleXMLExtended')) {
  class SimpleXMLExtended extends SimpleXMLElement // http://coffeerings.posterous.com/php-simplexml-and-cdata
  {
    public function addCData($cdata_text)
    {
      $node= dom_import_simplexml($this); 
      $no = $node->ownerDocument; 
      return $node->appendChild($no->createCDATASection($cdata_text)); 
    } 
  }
}
?>