<?php
class Apnews extends Plugin {

  private $host;

  function about() {
    return [
      2.0, // version
      'Provides virtual AP News feeds', // description
      'wn', // author
      false, // is system
      'https://github.com/supahgreg/ttrss-apnews/', // more info URL
    ];
  }

  function api_version() {
    return 2;
  }

  function init($host) {
    $this->host = $host;
    $host->add_hook($host::HOOK_SUBSCRIBE_FEED, $this);
    $host->add_hook($host::HOOK_FEED_BASIC_INFO, $this);
    $host->add_hook($host::HOOK_FETCH_FEED, $this);
  }

  function hook_subscribe_feed($contents, $url, $auth_login, $auth_pass) {
    // Bypass "Feeds::subscribe_to_feed" trying to get feeds from AP News URLs, site HTML or API JSON,
    // since neither will succeed.
    if ($this->get_tags_from_url($url)) {
      return ' ';
    }
    return $contents;
  }

  function hook_feed_basic_info($basic_info, $fetch_url, $owner_uid, $feed_id, $auth_login, $auth_pass) {
    $tags = $this->get_tags_from_url($fetch_url);
    if (!$tags) {
      return $basic_info;
    }

    $info = [
      'site_url' => $this->get_site_url($tags),
      'title' => $this->get_title($tags),
    ];

    return is_array($basic_info) ? array_merge($basic_info, $info) : $info;
  }

  function hook_fetch_feed($feed_data, $fetch_url, $owner_uid, $feed, $last_article_timestamp, $auth_login, $auth_pass) {
    $tags = $this->get_tags_from_url($fetch_url);
    if (!$tags) {
      return $feed_data;
    }

    $site_url = $this->get_site_url($tags);

    $body = $this->get_json($site_url);
    if (!$body) {
      return $feed_data;
    }
    $data = $body['hub']['data'][array_key_first($body['hub']['data'])];

    $feed_title = $this->get_title($tags);

    require_once 'lib/MiniTemplator.class.php';
    $tpl = new MiniTemplator();

    $tpl->readTemplateFromFile('templates/generated_feed.txt');

    $tpl->setVariable('FEED_TITLE', htmlspecialchars($feed_title), true);
    $tpl->setVariable('VERSION', get_version(), true);
    $tpl->setVariable('FEED_URL', htmlspecialchars($site_url), true);
    $tpl->setVariable('SELF_URL', htmlspecialchars($site_url), true);

    foreach ($data['cards'] as $card) {
      foreach ($card['contents'] as $content) {
        // Attempting to filter out junk entries
        // TODO: revisit this to make sure legit stuff isn't getting excluded
        if (!$content['localLinkUrl']) {
          continue;
        }

        $tpl->setVariable('ARTICLE_ID', htmlspecialchars($content['id']), true);
        $tpl->setVariable('ARTICLE_LINK', htmlspecialchars($content['localLinkUrl']), true);

        $tpl->setVariable('ARTICLE_UPDATED_ATOM', date(DATE_ATOM, strtotime($content['updated'])), true);

        $tpl->setVariable('ARTICLE_TITLE', htmlspecialchars($content['headline']), true);
        $tpl->setVariable('ARTICLE_AUTHOR', htmlspecialchars($content['bylines']), true);

        // CDATA (don't convert characters)
        $tpl->setVariable('ARTICLE_EXCERPT', $content['flattenedFirstWords'], true);
        // $tpl->setVariable('ARTICLE_CONTENT', $content['storyHTML'], true);
        $tpl->setVariable('ARTICLE_CONTENT', $content['firstWords'], true);

        $tpl->setVariable('ARTICLE_SOURCE_LINK', htmlspecialchars($site_url), true);
        $tpl->setVariable('ARTICLE_SOURCE_TITLE', htmlspecialchars($feed_title), true);

        $tpl->addBlock('entry');
      }
    }

    $tpl->setVariable('ARTICLE_UPDATED_ATOM', date('c'), true);

    $tpl->addBlock('feed');

    $tmp_data = '';

    if ($tpl->generateOutputToString($tmp_data)) {
      $feed_data = $tmp_data;
    }

    return $feed_data;
  }

  private function get_tags_from_url($url) {
    if (preg_match('#^https://apnews\.com/hub/([\w,-]+)#', $url, $tags) ||
      preg_match('#^https://apnews\.com/(?:tag/)?([^/]+)#', $url, $tags) ||
      preg_match('#^https://afs-prod\.appspot\.com/api/v2/feed/tag\?tags=(.+)$#', $url, $tags)) {
      return $tags[1];
    }
    return false;
  }

  private function get_site_url($tags) {
    return 'https://apnews.com/hub/'.$tags;
  }

  private function get_json($url) {
    $content = UrlHelper::fetch($url);
    $doc = new DOMDocument();

    if (@$doc->loadHTML('<?xml encoding="utf-8" ?>' . $content)) {
      $scripts = $doc->getElementsByTagName('script');

      foreach ($scripts as $script) {
        $lines = explode(PHP_EOL, $script->nodeValue);
        foreach ($lines as $line) {
          // TODO: Do this better.  It's incredibly fragile.
          if (strpos($line, "window['titanium-state'] = {") !== false) {
            return json_decode(str_replace("window['titanium-state'] = ", '', $line), true);
          }
        }
      }
    }

    return false;
  }

  private function get_title($tags) {
    // Create a feed title like "AP News: Tag A, Tag B, ..."
    return 'AP News: '.str_replace(',', ', ', $tags);
  }
}
